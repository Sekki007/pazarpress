<?php

declare(strict_types=1);

final class AutoVestiContent
{
    public static function isBreaking(string $text): bool
    {
        $t = mb_strtolower($text, 'UTF-8');
        foreach (AutoVestiConfig::BREAKING_KW as $kw) {
            if (mb_strpos($t, $kw) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Predloži rubriku iz naslova/uvoda/teksta (+ opcioni AI predlog).
     * Vraća slug ili null ako nema dovoljno jak signal.
     */
    public static function detectCategorySlug(
        string $title,
        string $lead = '',
        string $body = '',
        ?string $aiSuggestion = null
    ): ?string {
        $titleL = mb_strtolower($title, 'UTF-8');
        $leadL = mb_strtolower($lead, 'UTF-8');
        $bodyL = mb_strtolower(mb_substr(strip_tags($body), 0, 4000, 'UTF-8'), 'UTF-8');

        $scores = [];
        foreach (AutoVestiConfig::CATEGORY_KEYWORDS as $slug => $keywords) {
            $score = 0;
            foreach ($keywords as $kw) {
                $kw = mb_strtolower($kw, 'UTF-8');
                if ($kw === '') {
                    continue;
                }
                if (mb_strpos($titleL, $kw) !== false) {
                    $score += 4;
                }
                if ($leadL !== '' && mb_strpos($leadL, $kw) !== false) {
                    $score += 2;
                }
                if ($bodyL !== '' && mb_strpos($bodyL, $kw) !== false) {
                    $score += 1;
                }
            }
            if ($score > 0) {
                $scores[$slug] = $score;
            }
        }

        arsort($scores);
        $bestSlug = $scores ? (string) array_key_first($scores) : null;
        $bestScore = $bestSlug !== null ? (int) $scores[$bestSlug] : 0;

        // Dovoljno jak signal: bar jedan pogodak u naslovu ili više slabijih
        if ($bestSlug !== null && $bestScore >= 4) {
            return $bestSlug;
        }

        $fromAi = self::normalizeCategorySuggestion($aiSuggestion);
        if ($fromAi !== null) {
            return $fromAi;
        }

        if ($bestSlug !== null && $bestScore >= 3) {
            return $bestSlug;
        }

        return null;
    }

    public static function normalizeCategorySuggestion(?string $suggestion): ?string
    {
        if ($suggestion === null) {
            return null;
        }
        $s = mb_strtolower(trim($suggestion), 'UTF-8');
        if ($s === '') {
            return null;
        }
        $map = [
            'vijesti' => 'vijesti', 'vesti' => 'vijesti', 'news' => 'vijesti',
            'hronika' => 'hronika', 'hronike' => 'hronika', 'crna hronika' => 'hronika', 'crime' => 'hronika',
            'politika' => 'politika', 'politics' => 'politika',
            'društvo' => 'drustvo', 'drustvo' => 'drustvo', 'society' => 'drustvo',
            'ekonomija' => 'ekonomija', 'economy' => 'ekonomija', 'biznis' => 'ekonomija',
            'sport' => 'sport', 'sportovi' => 'sport',
            'kultura' => 'kultura', 'culture' => 'kultura',
            'dijaspora' => 'dijaspora', 'diaspora' => 'dijaspora',
            'video' => 'video',
        ];
        if (isset($map[$s])) {
            return $map[$s];
        }
        foreach ($map as $label => $slug) {
            if (mb_strpos($s, $label) !== false) {
                return $slug;
            }
        }
        foreach (CATEGORIES as $cat) {
            if ($cat['slug'] === $s || mb_strtolower($cat['name'], 'UTF-8') === $s) {
                return $cat['slug'];
            }
        }
        return null;
    }

    /** @param array<int, array{q?:string,a?:string}> $faqItems */
    public static function buildFaqBlock(array $faqItems): string
    {
        if (!$faqItems) {
            return '';
        }
        $html = "\n\n<div class=\"avc-faq\" itemscope itemtype=\"https://schema.org/FAQPage\">\n<h2>Najčešća pitanja</h2>\n";
        foreach ($faqItems as $item) {
            $q = isset($item['q']) ? e($item['q']) : '';
            $a = isset($item['a']) ? e($item['a']) : '';
            if ($q === '' || $a === '') {
                continue;
            }
            $html .= '<div class="avc-faq-item" itemscope itemprop="mainEntity" itemtype="https://schema.org/Question">'
                . '<div class="avc-faq-q" itemprop="name">' . $q . '</div>'
                . '<div class="avc-faq-a" itemscope itemprop="acceptedAnswer" itemtype="https://schema.org/Answer"><p itemprop="text">' . $a . '</p></div>'
                . '</div>' . "\n";
        }
        $html .= "</div>\n";
        return $html;
    }

    /** @param array<string, mixed> $data @param array<string, mixed> $original */
    public static function buildSchema(array $data, array $original, string $articleId, string $slug, bool $isBreaking): string
    {
        $url = absolute_url('/vijest/' . $slug);
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'NewsArticle',
            'headline' => $data['title'] ?? '',
            'description' => $data['meta_description'] ?? ($data['excerpt'] ?? ''),
            'keywords' => isset($data['schema_keywords']) && is_array($data['schema_keywords'])
                ? implode(', ', $data['schema_keywords']) : '',
            'url' => $url,
            'datePublished' => date('c'),
            'dateModified' => date('c'),
            'inLanguage' => 'sr',
            'isAccessibleForFree' => true,
            'publisher' => [
                '@type' => 'Organization',
                'name' => config('site_name') ?: 'Sandžak.net',
                'url' => config('site_url'),
            ],
        ];
        if ($isBreaking) {
            $schema['breakingNews'] = true;
        }
        $video = AutoVestiVideo::get($original);
        $videoSchema = AutoVestiVideo::schemaObject($video, (string) ($data['title'] ?? ''), $url);
        if ($videoSchema) {
            $schema['video'] = $videoSchema;
        }
        return json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '';
    }

    public static function cleanupVideoEmbeds(): int
    {
        $pdo = Database::connection();
        $stmt = $pdo->query("SELECT id, body FROM articles WHERE sourceUrl IS NOT NULL AND sourceUrl != ''");
        $fixed = 0;
        while ($row = $stmt->fetch()) {
            $new = preg_replace('@<div[^>]+class=[^>]*avc-video-wrap[^>]*>.*?</div>@is', '', (string) $row['body']) ?? '';
            $new = preg_replace('@<div[^>]+class=[^>]*avc-mp4-wrap[^>]*>.*?</div>@is', '', $new) ?? '';
            if ($new !== $row['body']) {
                $pdo->prepare('UPDATE articles SET body = ?, updatedAt = CURRENT_TIMESTAMP WHERE id = ?')
                    ->execute([$new, $row['id']]);
                $fixed++;
            }
        }
        AutoVestiConfig::log('Cleanup video: ' . $fixed . ' postova.');
        cache_flush_prefix('content');
        return $fixed;
    }

    public static function normalizeImageUrl(string $url, string $pageUrl = ''): string
    {
        $url = trim(html_entity_decode($url, ENT_QUOTES, 'UTF-8'));
        if ($url === '') {
            return '';
        }
        if (str_starts_with($url, '//')) {
            $scheme = parse_url($pageUrl, PHP_URL_SCHEME) ?: 'https';
            $url = $scheme . ':' . $url;
        } elseif (str_starts_with($url, '/')) {
            $parts = parse_url($pageUrl);
            if (!empty($parts['host'])) {
                $scheme = $parts['scheme'] ?? 'https';
                $url = $scheme . '://' . $parts['host'] . $url;
            }
        }
        return filter_var($url, FILTER_VALIDATE_URL) ? $url : '';
    }

    /** @return array{path:?string,error:string} */
    public static function downloadCover(string $imageUrl, string $title, string $pageUrl = ''): array
    {
        $imageUrl = self::normalizeImageUrl($imageUrl, $pageUrl);
        if ($imageUrl === '') {
            return ['path' => null, 'error' => 'nevaljan URL slike'];
        }

        $uploadDir = rtrim(config('upload_dir'), '/\\');
        if (!is_dir($uploadDir) && !@mkdir($uploadDir, 0775, true)) {
            return ['path' => null, 'error' => 'upload folder nije dostupan'];
        }

        $referer = $pageUrl !== '' ? $pageUrl : (parse_url($imageUrl, PHP_URL_SCHEME) . '://' . parse_url($imageUrl, PHP_URL_HOST));
        $result = HttpClient::download($imageUrl, 30, $referer);
        if (!$result || $result['code'] >= 400 || $result['body'] === '') {
            $code = $result['code'] ?? 0;
            return ['path' => null, 'error' => 'HTTP ' . $code . ' za ' . $imageUrl];
        }

        $bin = $result['body'];
        if (strlen($bin) < 200) {
            return ['path' => null, 'error' => 'previše mala datoteka (' . strlen($bin) . ' b)'];
        }

        $ext = self::detectImageExtension($bin, $imageUrl, $result['type'] ?? '');
        $name = 'import-' . time() . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
        $dest = $uploadDir . DIRECTORY_SEPARATOR . $name;

        if (@file_put_contents($dest, $bin) === false) {
            return ['path' => null, 'error' => 'snimanje na disk nije uspjelo'];
        }

        ImageWatermark::apply($dest);
        ImageProcessor::process($dest);

        return ['path' => '/uploads/' . $name, 'error' => ''];
    }

    public static function extractImageFromHtml(string $html, string $pageUrl = ''): string
    {
        if ($html === '') {
            return '';
        }
        if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $html, $m)) {
            return self::normalizeImageUrl($m[1], $pageUrl);
        }
        if (preg_match('/(https?:\/\/[^\s"\'<>]+\.(?:jpe?g|png|webp|gif)(?:\?[^\s"\'<>]*)?)/i', $html, $m)) {
            return self::normalizeImageUrl($m[1], $pageUrl);
        }
        return '';
    }

    public static function injectCoverIntoBody(string $content, string $coverPath, string $alt): string
    {
        if ($coverPath === '' || str_contains($content, $coverPath)) {
            return $content;
        }
        if (preg_match('/<img\b/i', $content)) {
            return $content;
        }
        $figure = '<figure class="avc-inline-cover"><img src="' . e($coverPath) . '" alt="' . e($alt) . '" loading="eager"></figure>';
        return $figure . "\n" . $content;
    }

    private static function detectImageExtension(string $bin, string $url, string $contentType): string
    {
        if (str_starts_with($bin, "\x89PNG\r\n\x1a\n")) {
            return 'png';
        }
        if (str_starts_with($bin, "\xFF\xD8\xFF")) {
            return 'jpg';
        }
        if (str_starts_with($bin, 'GIF87a') || str_starts_with($bin, 'GIF89a')) {
            return 'gif';
        }
        if (str_starts_with($bin, 'RIFF') && substr($bin, 8, 4) === 'WEBP') {
            return 'webp';
        }
        $path = parse_url($url, PHP_URL_PATH) ?: '';
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $ext = preg_replace('/\?.*/', '', $ext) ?: '';
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
            return $ext === 'jpeg' ? 'jpg' : $ext;
        }
        if (str_contains($contentType, 'png')) {
            return 'png';
        }
        if (str_contains($contentType, 'webp')) {
            return 'webp';
        }
        if (str_contains($contentType, 'gif')) {
            return 'gif';
        }
        return 'jpg';
    }

    /** @param array<string, mixed> $original */
    public static function appendSourceFooter(string $content, array $original): string
    {
        $link = trim((string) ($original['link'] ?? ''));
        if ($link === '' || !filter_var($link, FILTER_VALIDATE_URL)) {
            return $content;
        }
        $host = parse_url($link, PHP_URL_HOST);
        if (!$host) {
            return $content;
        }
        return $content . '<p class="avc-source"><em>Prema: <a href="' . e($link) . '" rel="nofollow noopener" target="_blank">' . e($host) . '</a></em></p>';
    }

    /** @return array<int, array{title:string,url:string}> */
    public static function recentPostsForLinking(int $count = 40): array
    {
        $pdo = Database::connection();
        $lim = sql_limit($count);
        $stmt = $pdo->prepare(
            "SELECT slug, title FROM articles WHERE status='PUBLISHED' ORDER BY publishedAt DESC LIMIT {$lim}"
        );
        $stmt->execute();
        $rows = $stmt->fetchAll();
        $out = [];
        foreach ($rows as $row) {
            $out[] = ['title' => $row['title'], 'url' => absolute_url('/vijest/' . $row['slug'])];
        }
        return $out;
    }
}
