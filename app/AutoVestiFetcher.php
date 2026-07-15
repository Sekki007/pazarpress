<?php

declare(strict_types=1);

final class AutoVestiFetcher
{
    private const UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/122.0.0.0 Safari/537.36';

    /** @return array<int, array<string, mixed>>|string error message */
    public static function fetch(string $type, string $url, int $limit = 10): array|string
    {
        return match ($type) {
            'scraper' => self::scrapeCategory($url),
            'wp_rest' => self::fetchWpRest($url, $limit),
            default => self::fetchRss($url),
        };
    }

    /** @return array<int, array<string, mixed>>|string */
    public static function fetchRss(string $url): array|string
    {
        $body = HttpClient::get($url, 30);
        if (!$body) {
            return 'Prazan ili nedostupan RSS feed.';
        }
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($body);
        if (!$xml) {
            return 'Ne mogu parsirati RSS.';
        }

        $ns = $xml->getNamespaces(true);
        $channel = $xml->channel ?? $xml;
        $items = [];

        foreach ($channel->item as $it) {
            $guid = (string) ($it->guid ?? $it->link ?? '');
            $title = (string) ($it->title ?? '');
            $desc = (string) ($it->description ?? '');
            $link = (string) ($it->link ?? '');
            $pub = (string) ($it->pubDate ?? '');
            if ($guid === '' || $title === '') {
                continue;
            }

            $img = '';
            $yt = '';
            $enc = '';

            if (isset($ns['media'])) {
                $m = $it->children($ns['media']);
                if (!empty($m->content)) {
                    $a = $m->content->attributes();
                    $u = (string) ($a['url'] ?? '');
                    $me = (string) ($a['medium'] ?? '');
                    if ($u && ($me === 'image' || preg_match('/\.(jpe?g|png|webp|gif)/i', $u))) {
                        $img = $u;
                    }
                }
                if ($img === '' && !empty($m->thumbnail)) {
                    $a = $m->thumbnail->attributes();
                    $img = (string) ($a['url'] ?? '');
                }
            }

            if ($img === '' && isset($it->enclosure)) {
                $a = $it->enclosure->attributes();
                $et = (string) ($a['type'] ?? '');
                $eu = (string) ($a['url'] ?? '');
                if ($eu && str_contains($et, 'image')) {
                    $img = $eu;
                }
            }

            if (isset($ns['content'])) {
                $cn = $it->children($ns['content']);
                $enc = (string) ($cn->encoded ?? '');
                if ($enc && $img === '' && preg_match('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $enc, $mm)) {
                    $img = $mm[1];
                }
            }

            if ($img === '' && $desc && preg_match('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $desc, $mm)) {
                $img = $mm[1];
            }
            if ($img === '' && $desc && preg_match('/https?:\/\/[^\s"\'<>]+\.(?:jpe?g|png|webp|gif)(?:\?[^\s"\'<>]*)?/i', $desc, $mm)) {
                $img = $mm[0];
            }

            $video = [];
            if (isset($ns['media'])) {
                $media = $it->children($ns['media']);
                foreach ($media->content ?? [] as $mc) {
                    $a = $mc->attributes();
                    $medium = strtolower((string) ($a['medium'] ?? ''));
                    $mediaUrl = (string) ($a['url'] ?? '');
                    if ($mediaUrl === '') {
                        continue;
                    }
                    if ($medium === 'video' || str_contains($mediaUrl, 'youtube') || str_contains($mediaUrl, 'youtu.be')) {
                        $mediaVideo = AutoVestiVideo::extract($mediaUrl);
                        if (!empty($mediaVideo['type'])) {
                            $video = $mediaVideo;
                            break;
                        }
                    }
                }
            }
            if (empty($video['type']) && $desc) {
                $video = AutoVestiVideo::extract($desc);
            }
            if (empty($video['type']) && $enc) {
                $video = AutoVestiVideo::extract($enc);
            }
            if (!empty($video['type']) && $video['type'] === 'youtube') {
                $yt = $video['url'];
            }

            $row = [
                'guid' => md5($guid),
                'title' => self::cleanText(html_entity_decode($title, ENT_QUOTES, 'UTF-8')),
                'description' => self::cleanText(html_entity_decode(mb_substr(strip_tags($desc), 0, 1000), ENT_QUOTES, 'UTF-8')),
                'link' => $link,
                'pub_date' => $pub,
                'image_url' => $img,
                'youtube_url' => $yt,
            ];
            if ($enc !== '') {
                $row['_content_html'] = $enc;
            }
            AutoVestiVideo::apply($row, $video);
            $items[] = $row;
        }

        return $items;
    }

    /** @return array<int, array<string, mixed>>|string */
    public static function fetchWpRest(string $url, int $limit): array|string
    {
        $base = rtrim($url, '/');
        if (!str_contains($base, '/wp-json/')) {
            $base = rtrim($base, '/') . '/wp-json/wp/v2/posts';
        }
        $api = $base . (str_contains($base, '?') ? '&' : '?') . 'per_page=' . max(1, min(20, $limit)) . '&_embed';
        $json = HttpClient::get($api, 30);
        if (!$json) {
            return 'WP REST API nije dostupan.';
        }
        $posts = json_decode($json, true);
        if (!is_array($posts)) {
            return 'Nevalidan WP REST odgovor.';
        }
        $items = [];
        foreach ($posts as $post) {
            $link = $post['link'] ?? '';
            $title = self::cleanText(html_entity_decode(strip_tags($post['title']['rendered'] ?? ''), ENT_QUOTES, 'UTF-8'));
            if ($title === '' || $link === '') {
                continue;
            }
            $content = $post['content']['rendered'] ?? '';
            $excerpt = $post['excerpt']['rendered'] ?? '';
            $img = '';
            if (!empty($post['_embedded']['wp:featuredmedia'][0]['source_url'])) {
                $img = $post['_embedded']['wp:featuredmedia'][0]['source_url'];
            }
            $row = [
                'guid' => md5((string) ($post['guid']['rendered'] ?? $link)),
                'title' => $title,
                'description' => self::cleanText(mb_substr(strip_tags($excerpt ?: $content), 0, 1000)),
                'link' => $link,
                'pub_date' => $post['date'] ?? '',
                'image_url' => $img,
                'youtube_url' => '',
                '_content_html' => $content,
            ];
            AutoVestiVideo::apply($row, AutoVestiVideo::extract($content));
            $items[] = $row;
        }
        return $items;
    }

    /** @return array<int, array<string, mixed>>|string */
    public static function scrapeCategory(string $url): array|string
    {
        $html = HttpClient::get($url, 30, self::UA);
        if (!$html) {
            return 'Prazna kategorija stranica.';
        }
        $scheme = parse_url($url, PHP_URL_SCHEME);
        $host = parse_url($url, PHP_URL_HOST);
        $base = $scheme . '://' . $host;
        $current = parse_url($url, PHP_URL_PATH);
        preg_match_all('/href=["\']([^"\'#\s]+)["\']/', $html, $raw);
        $skip = ['category', 'kategorija', 'tag', '/page/', '/feed', 'rss', 'wp-content', 'wp-admin', 'wp-json', 'wp-login', 'author', '/search', 'login', 'register', 'facebook.com', 'twitter.com', 'instagram.com', 'youtube.com', 'x.com', '.jpg', '.png', '.pdf', 'mailto:', 'javascript:'];
        $links = [];
        foreach ($raw[1] as $href) {
            if (!str_starts_with($href, 'http')) {
                if (str_starts_with($href, '//')) {
                    $href = $scheme . ':' . $href;
                } elseif (str_starts_with($href, '/')) {
                    $href = $base . $href;
                } else {
                    continue;
                }
            }
            if (parse_url($href, PHP_URL_HOST) !== $host) {
                continue;
            }
            $path = parse_url($href, PHP_URL_PATH) ?: '';
            if (strlen($path) < 8 || $path === $current) {
                continue;
            }
            $skipIt = false;
            foreach ($skip as $kw) {
                if (stripos($href, $kw) !== false) {
                    $skipIt = true;
                    break;
                }
            }
            if (!$skipIt) {
                $links[$href] = $href;
            }
        }
        AutoVestiConfig::log('Scraper pronadjeno ' . count($links) . ' linkova na: ' . $url);
        if (!$links) {
            return 'Nema linkova za scrape.';
        }
        $items = [];
        foreach (array_slice(array_values($links), 0, 15) as $articleUrl) {
            $item = self::scrapeArticle($articleUrl);
            if (is_array($item)) {
                $items[] = $item;
            }
            usleep(300000);
        }
        return $items ?: 'Nijedan članak nije scrapeovan.';
    }

    /** @return array<string, mixed>|null */
    public static function scrapeArticle(string $url): ?array
    {
        $html = HttpClient::get($url, 20, self::UA);
        if (!$html) {
            return null;
        }
        $title = '';
        if (preg_match('/<meta[^>]+property=["\']og:title["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $m)) {
            $title = self::cleanText(html_entity_decode($m[1], ENT_QUOTES, 'UTF-8'));
        }
        if ($title === '' && preg_match('/<h1[^>]*>(.*?)<\/h1>/is', $html, $m)) {
            $title = self::cleanText(trim(strip_tags($m[1])));
        }
        if ($title === '') {
            return null;
        }
        $img = self::scrapeOgImageFromHtml($html, $url);
        $pub = '';
        if (preg_match('/<meta[^>]+property=["\']article:published_time["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $m)) {
            $pub = $m[1];
        }
        preg_match_all('/<p[^>]*>(.*?)<\/p>/is', $html, $pm);
        $paras = array_filter(array_map(static fn ($p) => trim(strip_tags($p)), $pm[1]), static fn ($p) => strlen($p) > 50);
        $content = self::cleanText(mb_substr(implode(' ', array_slice(array_values($paras), 0, 8)), 0, 1000));
        $row = [
            'guid' => md5($url),
            'title' => $title,
            'description' => $content,
            'link' => $url,
            'pub_date' => $pub,
            'image_url' => $img,
            'youtube_url' => '',
            '_content_html' => $html,
        ];
        AutoVestiVideo::apply($row, AutoVestiVideo::extract($html));
        return $row;
    }

    /** @return array<string,mixed>|string */
    public static function fetchArticleFromUrl(string $url): array|string
    {
        $url = trim($url);
        if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
            return 'Neispravan link.';
        }
        $host = (string) parse_url($url, PHP_URL_HOST);
        foreach (['instagram.com', 'facebook.com', 'fb.com', 'twitter.com', 'x.com', 'tiktok.com', 't.me'] as $blocked) {
            if ($host !== '' && stripos($host, $blocked) !== false) {
                return 'Link sa društvenih mreža nije podržan.';
            }
        }

        $item = self::scrapeArticle($url);
        if (!is_array($item)) {
            return 'Ne mogu preuzeti vest sa linka.';
        }

        if (!empty(AutoVestiConfig::get('use_full_article', true)) && strlen((string) $item['description']) < 800) {
            $full = self::fetchFullArticle($url);
            if ($full && strlen($full) > strlen((string) $item['description'])) {
                $item['description'] = self::cleanText($full);
            }
        }
        if (!empty(AutoVestiConfig::get('use_youtube', true))) {
            AutoVestiVideo::enrich($item);
        }

        $desc = trim(strip_tags((string) ($item['description'] ?? '')));
        if (mb_strlen($desc, 'UTF-8') < 80) {
            return 'Tekst vesti je previše kratak ili stranica blokira preuzimanje.';
        }

        $item['guid'] = md5('tglink_' . $url . '_' . time());
        $item['title'] = self::cleanText((string) ($item['title'] ?? ''));
        $item['description'] = self::cleanText((string) ($item['description'] ?? ''));
        return $item;
    }

    public static function fetchFullArticle(string $url): string
    {
        $html = HttpClient::get($url, 20, self::UA);
        if (!$html) {
            return '';
        }
        $html = preg_replace('@<script[^>]*?>.*?</script>@si', '', $html) ?? $html;
        $html = preg_replace('@<style[^>]*?>.*?</style>@si', '', $html) ?? $html;
        $classes = ['entry-content', 'post-content', 'article-content', 'article-body', 'td-post-content', 'tdb-block-inner', 'post-body', 'news-content', 'single-content', 'content-inner', 'post-entry', 'article-text'];
        $best = '';
        foreach ($classes as $cls) {
            if (preg_match('@<div[^>]+class=[^>]*' . preg_quote($cls, '@') . '[^>]*>(.*?)</div>@si', $html, $m)) {
                $text = trim(strip_tags($m[1]));
                if (strlen($text) > strlen($best)) {
                    $best = $text;
                }
            }
        }
        if (strlen($best) < 200) {
            preg_match_all('@<p[^>]*>(.*?)</p>@si', $html, $pm);
            $paras = [];
            foreach ($pm[1] as $p) {
                $p = trim(strip_tags($p));
                if (strlen($p) > 40) {
                    $paras[] = $p;
                }
            }
            $fallback = implode(' ', $paras);
            if (strlen($fallback) > strlen($best)) {
                $best = $fallback;
            }
        }
        return self::cleanText(mb_substr(trim(preg_replace('/\s+/', ' ', $best) ?? ''), 0, 3000));
    }

    public static function scrapeOgImage(string $url): string
    {
        $html = HttpClient::get($url, 20, self::UA);
        return $html ? self::scrapeOgImageFromHtml($html, $url) : '';
    }

    private static function scrapeOgImageFromHtml(string $html, string $url): string
    {
        if (preg_match('/<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $m)) {
            $img = html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
            if (!preg_match('/-\d{1,3}x\d{1,3}\./i', $img)) {
                return $img;
            }
        }
        if (preg_match('/<meta[^>]+name=["\']twitter:image["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $m)) {
            return html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
        }
        $base = parse_url($url, PHP_URL_SCHEME) . '://' . parse_url($url, PHP_URL_HOST);
        $slug = basename(rtrim(parse_url($url, PHP_URL_PATH) ?: '', '/'));
        if ($slug) {
            $api = $base . '/wp-json/wp/v2/posts?slug=' . rawurlencode($slug) . '&_embed';
            $json = HttpClient::get($api, 10);
            if ($json) {
                $posts = json_decode($json, true);
                if (!empty($posts[0]['_embedded']['wp:featuredmedia'][0]['source_url'])) {
                    return $posts[0]['_embedded']['wp:featuredmedia'][0]['source_url'];
                }
            }
        }
        if (preg_match_all('@(https?://[^"\']+/wp-content/uploads/[^"\']+\.(?:jpe?g|png|webp|gif))@i', $html, $all)) {
            foreach ($all[1] as $img) {
                if (!preg_match('/(logo|icon|avatar|placeholder)/i', $img)) {
                    return $img;
                }
            }
        }
        return '';
    }

    public static function cleanText(string $text): string
    {
        $decoded = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $decoded = str_replace(["\xc2\xa0", '&nbsp;'], ' ', $decoded);
        $decoded = preg_replace('/\.[a-z0-9_-]+\{[^}]{0,800}\}/iu', ' ', $decoded) ?? $decoded;
        $decoded = preg_replace('/@[^{]+\{[^}]{0,1200}\}/iu', ' ', $decoded) ?? $decoded;
        $decoded = preg_replace('/\b(?:font-family|line-height|padding|margin|display|width|height|position)\s*:\s*[^;]+;?/iu', ' ', $decoded) ?? $decoded;
        $decoded = str_replace(['|', '↗', '🌐', '📅', '📥'], "\n", $decoded);
        $chunks = preg_split('/[\r\n]+/u', $decoded) ?: [];
        $clean = [];
        foreach ($chunks as $chunk) {
            $line = trim($chunk);
            if ($line === '' || self::isNoiseLine($line)) {
                continue;
            }
            $clean[] = $line;
        }
        return trim(preg_replace('/\s+/u', ' ', implode("\n", $clean)) ?? '');
    }

    private static function isNoiseLine(string $line): bool
    {
        $lower = mb_strtolower($line, 'UTF-8');
        if (str_contains($lower, '.tdb_') || str_contains($lower, '{') || str_contains($lower, '}')) {
            return true;
        }
        if (preg_match('/\b(?:td-module|tdb_module|wp-content|wp-json|viewbox|svg|stylesheet|font-family|line-height|display:flex|position:|width:|height:)\b/iu', $lower)) {
            return true;
        }
        if (preg_match('/\b(?:retyped pass|invalid pass pattern|red hat display|zilla slab)\b/iu', $lower)) {
            return true;
        }
        preg_match_all('/\b(?:naslovna|vesti|drustvo|društvo|novi pazar|politika|sport|hronika|region|crna gora|bosna i hercegovina|hrvatska|planeta)\b/iu', $lower, $m);
        if (count($m[0] ?? []) >= 4) {
            return true;
        }
        preg_match_all('/\b\d+(?:[.,:x]\d+)?\b/u', $line, $nums);
        $numCount = count($nums[0] ?? []);
        if ($numCount >= 4 && preg_match('/\b(?:width|height|padding|margin|module|container|display|position|flex)\b/iu', $lower)) {
            return true;
        }
        return false;
    }

}
