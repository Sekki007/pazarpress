<?php

declare(strict_types=1);

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function config(string $key, mixed $default = null): mixed
{
    static $cfg;
    $cfg ??= require __DIR__ . '/config.php';
    return $cfg[$key] ?? $default;
}

function is_mysql(): bool
{
    static $is = null;
    if ($is === null) {
        $is = (config('db')['driver'] ?? 'sqlite') === 'mysql';
    }
    return $is;
}

function sql_limit(int $limit, int $max = 200): int
{
    return max(1, min($max, $limit));
}

function sql_insert_ignore(): string
{
    return is_mysql() ? 'INSERT IGNORE' : 'INSERT OR IGNORE';
}

function view(string $template, array $data = [], ?string $layout = 'layout'): void
{
    send_dynamic_cache_headers();
    extract($data, EXTR_SKIP);
    ob_start();
    require __DIR__ . '/../views/' . $template . '.php';
    $content = ob_get_clean();
    // foreach u šablonima ostavlja $article — ne sme da “zarazi” OG meta u layoutu
    if (!array_key_exists('article', $data)) {
        unset($article);
    }
    if ($layout) {
        require __DIR__ . '/../views/' . $layout . '.php';
    } else {
        echo $content;
    }
}

function admin_view(string $template, array $data = []): void
{
    view('admin/' . $template, $data, 'admin/layout');
}

function meni_view(string $template, array $data = []): void
{
    view('meni/' . $template, $data, 'meni/layout');
}

/** @param array<string, mixed>|null $restaurant */
function restaurant_cover_url(?string $url, ?array $restaurant = null): string
{
    if ($url) {
        return $url;
    }
    if ($restaurant) {
        $generated = RestaurantCoverGenerator::ensureStored($restaurant);
        if ($generated) {
            return $generated;
        }
    }

    return '/assets/img/placeholders/restaurant-cover.svg';
}

function restaurant_logo_url(?string $url): string
{
    return $url ?: '/assets/img/placeholders/restaurant-logo.svg';
}

function menu_item_image_url(?string $url, int $variant = 0): string
{
    if ($url) {
        return $url;
    }
    $n = ($variant % 4) + 1;

    return '/assets/img/placeholders/menu-item-' . $n . '.svg';
}

function restaurant_stars_html(?float $rating): string
{
    if ($rating === null) {
        return '<span class="rst-stars rst-stars--empty">Nema ocjena</span>';
    }
    $full = (int) round($rating);
    $out = '<span class="rst-stars" aria-label="Ocjena ' . number_format($rating, 1) . ' od 5">';
    for ($i = 1; $i <= 5; $i++) {
        $out .= '<span class="rst-stars__s' . ($i <= $full ? ' rst-stars__s--on' : '') . '">★</span>';
    }
    $out .= ' <span class="rst-stars__num">' . number_format($rating, 1) . '</span></span>';

    return $out;
}

/** @param array<string, mixed> $restaurant */
function restaurant_location_query(array $restaurant): string
{
    $parts = array_filter([
        trim((string) ($restaurant['address'] ?? '')),
        city_label((string) ($restaurant['city'] ?? 'OTHER')),
        'Srbija',
    ]);

    return implode(', ', $parts);
}

/**
 * @param array<string, mixed> $restaurant
 * @return array{query: string, google: string, google_dir: string, apple: string, embed: string}|null
 */
function restaurant_maps_urls(array $restaurant): ?array
{
    $query = restaurant_location_query($restaurant);
    if (trim(str_replace(',', '', $query)) === '' || trim((string) ($restaurant['address'] ?? '')) === '') {
        return null;
    }
    $enc = rawurlencode($query);

    return [
        'query' => $query,
        'google' => 'https://www.google.com/maps/search/?api=1&query=' . $enc,
        'google_dir' => 'https://www.google.com/maps/dir/?api=1&destination=' . $enc,
        'apple' => 'https://maps.apple.com/?q=' . $enc,
        'embed' => 'https://www.google.com/maps?q=' . $enc . '&z=16&output=embed',
    ];
}

/** @param array<string, string> $hours */
function restaurant_today_hours_label(array $hours): ?string
{
    $keys = ['ned', 'pon', 'uto', 'sri', 'cet', 'pet', 'sub'];
    $key = $keys[(int) date('w')] ?? 'pon';
    $val = trim($hours[$key] ?? '');

    return $val !== '' ? $val : null;
}

function restaurants_enabled(): bool
{
    return (bool) Settings::get('restaurants_enabled', false);
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function not_found(string $title = 'Stranica nije pronađena'): never
{
    http_response_code(404);
    $latest = ArticleRepository::getLatest(null, null, 6)['items'];
    view('404', [
        'title' => $title . ' — Pazar Press',
        'description' => 'Stranica nije pronađena.',
        'latest' => $latest,
        'noindex' => true,
    ]);
    exit;
}

function current_path(): string
{
    return rtrim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/', '/') ?: '/';
}

function json_response(mixed $data, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function slugify(string $text): string
{
    $text = mb_strtolower($text, 'UTF-8');
    $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: $text;
    $text = str_replace(['đ', 'ž', 'č', 'ć', 'š'], ['dj', 'z', 'c', 'c', 's'], $text);
    $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
    return trim($text, '-');
}

function new_id(): string
{
    return bin2hex(random_bytes(12));
}

function city_label(string $city): string
{
    return CITY_LABELS[$city] ?? $city;
}

function city_slug(string $city): string
{
    return CITY_SLUGS[$city] ?? $city;
}

function format_relative(?string $datetime): string
{
    if (!$datetime) {
        return '';
    }
    $ts = strtotime($datetime);
    $diff = time() - $ts;
    if ($diff < 60) {
        return 'upravo sada';
    }
    if ($diff < 3600) {
        $m = (int) floor($diff / 60);
        return "prije {$m} min";
    }
    if ($diff < 86400) {
        $h = (int) floor($diff / 3600);
        return "prije {$h} h";
    }
    if ($diff < 604800) {
        $d = (int) floor($diff / 86400);
        return "prije {$d} d";
    }
    return date('j. n. Y.', $ts);
}

function format_datetime(string $datetime): string
{
    $months = [
        1 => 'januara', 2 => 'februara', 3 => 'marta', 4 => 'aprila',
        5 => 'maja', 6 => 'juna', 7 => 'jula', 8 => 'avgusta',
        9 => 'septembra', 10 => 'oktobra', 11 => 'novembra', 12 => 'decembra',
    ];
    $ts = strtotime($datetime);
    $d = (int) date('j', $ts);
    $m = $months[(int) date('n', $ts)];
    $y = date('Y', $ts);
    $t = date('H:i', $ts);
    return "{$d}. {$m} {$y}. u {$t}";
}

function compute_reading_time(string $text): int
{
    $plain = trim(html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    $words = preg_split('/\s+/', $plain) ?: [];
    return max(1, (int) ceil(count($words) / 200));
}

function sanitize_article_html(string $html): string
{
    $allowed = '<p><br><strong><b><em><i><u><s><h2><h3><h4><ul><ol><li><blockquote><a><img><figure><figcaption><hr><table><thead><tbody><tr><th><td><span><div><pre><code><iframe><video><source>';
    $clean = strip_tags($html, $allowed);
    $clean = preg_replace('/\s(on\w+|style)=("[^"]*"|\'[^\']*\'|[^\s>]+)/iu', '', $clean) ?? $clean;
    $clean = preg_replace_callback(
        '/\sclass=("|\')([^"\']*)(\1)/iu',
        static function (array $m): string {
            $keep = array_values(array_filter(
                preg_split('/\s+/', $m[2]) ?: [],
                static fn (string $c): bool => str_starts_with($c, 'avc-') || str_starts_with($c, 'article-cover')
            ));
            return $keep ? ' class="' . implode(' ', $keep) . '"' : '';
        },
        $clean
    ) ?? $clean;
    $clean = preg_replace_callback(
        '/<iframe\b[^>]*>/iu',
        static function (array $m): string {
            if (!preg_match('/\ssrc=("|\')(https?:\/\/[^"\']+)\1/i', $m[0], $src)) {
                return '';
            }
            $url = $src[2];
            $ok = preg_match('#^https://(?:www\.)?(?:youtube\.com/embed/|player\.vimeo\.com/video/|www\.dailymotion\.com/embed/|www\.facebook\.com/plugins/video\.php|instagram\.com/(?:p|reel|tv)/[a-zA-Z0-9_-]+/embed)#i', $url);
            return $ok ? '<iframe src="' . $url . '" allowfullscreen loading="lazy"></iframe>' : '';
        },
        $clean
    ) ?? $clean;
    $clean = preg_replace_callback(
        '/<video\b[^>]*>.*?<\/video>/is',
        static function (array $m): string {
            if (preg_match('/<source[^>]+src=("|\')(https?:\/\/[^"\']+\.mp4[^"\']*)\1/i', $m[0], $src)) {
                return '<div class="avc-mp4-wrap"><video controls playsinline preload="metadata"><source src="' . $src[2] . '" type="video/mp4"></video></div>';
            }
            return '';
        },
        $clean
    ) ?? $clean;
    $clean = preg_replace('/href\s*=\s*"\s*javascript:[^"]*"/iu', 'href="#"', $clean) ?? $clean;
    $clean = preg_replace('/src\s*=\s*"\s*javascript:[^"]*"/iu', '', $clean) ?? $clean;
    return $clean;
}

function render_article_body(string $body): string
{
    $trim = trim($body);
    if ($trim !== '' && preg_match('/<\s*(p|h[1-6]|ul|ol|blockquote|img|figure|div|table|br)\b/i', $trim)) {
        return sanitize_article_html($body);
    }
    return parse_article_body($body);
}

function get_uploaded_image_file(): ?array
{
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        return $_FILES['file'];
    }
    if (isset($_FILES['files']) && is_array($_FILES['files']['error'] ?? null)) {
        if (($_FILES['files']['error'][0] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            return [
                'name' => $_FILES['files']['name'][0],
                'type' => $_FILES['files']['type'][0],
                'tmp_name' => $_FILES['files']['tmp_name'][0],
                'error' => $_FILES['files']['error'][0],
                'size' => $_FILES['files']['size'][0],
            ];
        }
    }
    return null;
}

function format_view_count(int $count): string
{
    if ($count >= 1000) {
        return number_format($count / 1000, 1, ',', '') . 'k';
    }
    return number_format($count, 0, ',', '.');
}

function parse_article_body(string $body): string
{
    $lines = preg_split('/\r\n|\r|\n/', $body) ?: [];
    $html = '';
    $inQuote = false;
    foreach ($lines as $line) {
        $trim = trim($line);
        if ($trim === '') {
            if ($inQuote) {
                $html .= '</blockquote>';
                $inQuote = false;
            }
            continue;
        }
        if (str_starts_with($trim, '> ')) {
            if (!$inQuote) {
                $html .= '<blockquote>';
                $inQuote = true;
            }
            $html .= '<p>' . e(substr($trim, 2)) . '</p>';
            continue;
        }
        if ($inQuote) {
            $html .= '</blockquote>';
            $inQuote = false;
        }
        if (str_starts_with($trim, '## ')) {
            $html .= '<h2>' . e(substr($trim, 3)) . '</h2>';
        } else {
            $html .= '<p>' . e($trim) . '</p>';
        }
    }
    if ($inQuote) {
        $html .= '</blockquote>';
    }
    return $html;
}

function cache_remember(string $key, int $ttl, callable $callback): mixed
{
    $file = config('cache_dir') . '/' . md5($key) . '.cache';
    if (is_file($file) && filemtime($file) + $ttl > time()) {
        return unserialize((string) file_get_contents($file));
    }
    $value = $callback();
    file_put_contents($file, serialize($value));
    return $value;
}

function cache_forget(string $key): void
{
    $file = config('cache_dir') . '/' . md5($key) . '.cache';
    if (is_file($file)) {
        unlink($file);
    }
}

function cache_flush_all(): void
{
    $dir = config('cache_dir');
    if (!is_dir($dir)) {
        return;
    }
    foreach (glob($dir . '/*.cache') ?: [] as $file) {
        if (is_file($file)) {
            @unlink($file);
        }
    }
}

/** Briše sav serverski keš za vijesti, početnu, RSS i sitemap. */
function cache_flush_content(): void
{
    cache_flush_all();
}

function cache_flush_prefix(string $prefix): void
{
    if ($prefix === 'home' || $prefix === 'content') {
        cache_flush_content();
        return;
    }

    static $prefixMap = [
        'restaurants' => ['restaurants:list'],
    ];
    $keys = $prefixMap[$prefix] ?? [];
    foreach ($keys as $k) {
        cache_forget($k);
    }
}

function send_dynamic_cache_headers(): void
{
    if (headers_sent()) {
        return;
    }
    header('Cache-Control: private, no-cache, must-revalidate');
    header('CDN-Cache-Control: no-store');
    header('Cloudflare-CDN-Cache-Control: no-store');
}

function asset_url(string $path): string
{
    $rel = ltrim($path, '/');
    $file = __DIR__ . '/../public/' . $rel;
    $v = is_file($file) ? (string) filemtime($file) : '1';
    return '/' . $rel . '?v=' . $v;
}

function absolute_url(string $path): string
{
    if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
        return $path;
    }
    return config('site_url') . '/' . ltrim($path, '/');
}

function og_image_url(?string $path): string
{
    if ($path) {
        return absolute_url($path);
    }
    $default = trim((string) Settings::get('og_default_image', ''));
    if ($default !== '') {
        return absolute_url($default);
    }
    $png = __DIR__ . '/../public/assets/img/og-default.png';
    if (is_file($png)) {
        return absolute_url('/assets/img/og-default.png');
    }
    return absolute_url('/assets/img/og-default.svg');
}

function og_image_mime(string $url): string
{
    $path = strtolower(parse_url($url, PHP_URL_PATH) ?: $url);
    return match (true) {
        str_ends_with($path, '.png') => 'image/png',
        str_ends_with($path, '.webp') => 'image/webp',
        str_ends_with($path, '.gif') => 'image/gif',
        str_ends_with($path, '.svg') => 'image/svg+xml',
        default => 'image/jpeg',
    };
}

/** ISO-8601 za Open Graph / JSON-LD. */
function og_datetime(?string $value): string
{
    if ($value === null || trim($value) === '') {
        return '';
    }
    $ts = strtotime($value);
    if ($ts === false) {
        return $value;
    }
    return gmdate('c', $ts);
}

function breadcrumb_json_ld(array $breadcrumbs): array
{
    $elements = [];
    foreach ($breadcrumbs as $i => $crumb) {
        $item = [
            '@type' => 'ListItem',
            'position' => $i + 1,
            'name' => $crumb['label'],
        ];
        if (!empty($crumb['url'])) {
            $item['item'] = absolute_url($crumb['url']);
        }
        $elements[] = $item;
    }
    return [
        '@type' => 'BreadcrumbList',
        'itemListElement' => $elements,
    ];
}

function pagination_rel_links(string $basePath, array $queryParams, array $pagination): array
{
    $page = (int) ($pagination['page'] ?? 1);
    $pages = (int) ($pagination['pages'] ?? 1);
    $links = [];
    if ($page > 1) {
        $q = array_merge($queryParams, ['str' => $page - 1]);
        $links['prev'] = absolute_url($basePath . '?' . http_build_query($q));
    }
    if ($page < $pages) {
        $q = array_merge($queryParams, ['str' => $page + 1]);
        $links['next'] = absolute_url($basePath . '?' . http_build_query($q));
    }
    return $links;
}

function site_navigation_links(): array
{
    $links = [['name' => 'Početna', 'url' => '/']];
    foreach (CATEGORIES as $cat) {
        $links[] = [
            'name' => $cat['name'],
            'url' => $cat['slug'] === 'video' ? '/video' : '/rubrika/' . $cat['slug'],
        ];
    }
    $links[] = ['name' => 'Pretraga', 'url' => '/pretraga'];
    return $links;
}

/** @return list<array{name:string,url:string}> */
function site_city_nav_links(): array
{
    $links = [];
    foreach (CITIES_ORDER as $code) {
        if ($code === 'OTHER') {
            continue;
        }
        $slug = CITY_SLUGS[$code] ?? null;
        if ($slug) {
            $links[] = [
                'name' => CITY_LABELS[$code],
                'url' => '/?grad=' . $slug,
            ];
        }
    }
    return $links;
}

function site_meta_description(): string
{
    $tagline = trim((string) Settings::get('site_tagline'));
    if (mb_strlen($tagline) >= 130) {
        return $tagline;
    }
    $rubrike = implode(', ', array_map(static fn (array $c): string => $c['name'], array_slice(CATEGORIES, 0, 6)));
    $extra = " Vijesti iz Novog Pazara — {$rubrike}.";
    return mb_substr($tagline . $extra, 0, 158);
}

/** Markdown mapa sajta za AI asistente (llmstxt.org). */
function generate_llms_txt(): string
{
    $base = rtrim(config('site_url'), '/');
    $tagline = trim((string) Settings::get('site_tagline'));

    $lines = [
        '# ' . config('site_name'),
        '',
        '> ' . $tagline,
        '',
        'Lokalni news portal — Grad, našim očima. Jezik: srpski.',
        '',
        '## Glavno',
        '- [Početna](' . $base . '/): Naslovna stranica sa najnovijim vestima',
        '- [RSS feed](' . $base . '/feed.xml): Najnovije objavljene vijesti (XML)',
        '- [Sitemap](' . $base . '/sitemap.xml): Kompletna lista stranica za indeksiranje',
        '',
        '## Rubrike',
    ];

    foreach (CATEGORIES as $cat) {
        $url = $cat['slug'] === 'video' ? $base . '/video' : $base . '/rubrika/' . $cat['slug'];
        $lines[] = '- [' . $cat['name'] . '](' . $url . '): Arhiva rubrike ' . $cat['name'];
    }

    $lines[] = '';
    $lines[] = '## Najnovije vijesti';

    $stmt = Database::connection()->query(
        "SELECT slug, title FROM articles WHERE status = 'PUBLISHED' ORDER BY publishedAt DESC LIMIT 15"
    );
    while ($row = $stmt->fetch()) {
        $title = str_replace(['[', ']', "\n"], ['(', ')', ' '], (string) $row['title']);
        $lines[] = '- [' . $title . '](' . $base . '/vijest/' . rawurlencode((string) $row['slug']) . ')';
    }

    $lines[] = '';
    $lines[] = '## Optional';
    $lines[] = '- [Pretraga](' . $base . '/pretraga): Pretraga arhive članaka';

    if (restaurants_enabled()) {
        $lines[] = '- [Restorani](' . $base . '/restorani): Restorani i digitalni meniji u Novom Pazaru';
    }

    return implode("\n", $lines) . "\n";
}

function site_seo_schemas(): array
{
    $base = config('site_url');
    $logo = Settings::get('og_default_image') ?: '/assets/img/icon.svg';
    $navLinks = site_navigation_links();
    $navParts = [];
    foreach ($navLinks as $link) {
        $navParts[] = [
            '@type' => 'SiteNavigationElement',
            'name' => $link['name'],
            'url' => absolute_url($link['url']),
        ];
    }
    return [
        [
            '@type' => 'NewsMediaOrganization',
            '@id' => $base . '/#organization',
            'name' => config('site_name'),
            'url' => $base,
            'logo' => [
                '@type' => 'ImageObject',
                'url' => absolute_url(is_string($logo) ? $logo : '/assets/img/icon.svg'),
            ],
        ],
        [
            '@type' => 'WebSite',
            '@id' => $base . '/#website',
            'name' => config('site_name'),
            'url' => $base,
            'description' => site_meta_description(),
            'inLanguage' => 'bs',
            'publisher' => ['@id' => $base . '/#organization'],
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => $base . '/pretraga?q={search_term_string}',
                'query-input' => 'required name=search_term_string',
            ],
        ],
        [
            '@type' => 'SiteNavigationElement',
            '@id' => $base . '/#site-navigation',
            'name' => 'Navigacija Pazar Press',
            'hasPart' => $navParts,
        ],
        [
            '@type' => 'ItemList',
            '@id' => $base . '/#main-navigation',
            'name' => 'Glavna navigacija',
            'itemListElement' => array_map(
                static fn (array $link, int $i): array => [
                    '@type' => 'ListItem',
                    'position' => $i + 1,
                    'name' => $link['name'],
                    'url' => absolute_url($link['url']),
                ],
                $navLinks,
                array_keys($navLinks)
            ),
        ],
    ];
}

function build_json_ld_graph(array $nodes): ?array
{
    $nodes = array_values(array_filter($nodes));
    if ($nodes === []) {
        return null;
    }
    if (count($nodes) === 1) {
        return array_merge(['@context' => 'https://schema.org'], $nodes[0]);
    }
    return [
        '@context' => 'https://schema.org',
        '@graph' => $nodes,
    ];
}

function facebook_page_url(): ?string
{
    $raw = trim((string) Settings::get('facebook_page_url', ''));
    if ($raw === '') {
        return null;
    }
    if (str_starts_with($raw, 'http://') || str_starts_with($raw, 'https://')) {
        return $raw;
    }
    return 'https://www.facebook.com/' . ltrim($raw, '@/');
}

function youtube_thumb(?string $youtubeId): ?string
{
    if (!$youtubeId) {
        return null;
    }
    $safeId = preg_replace('/[^a-zA-Z0-9_-]/', '', $youtubeId);
    if ($safeId === '') {
        return null;
    }

    $uploadDir = realpath(config('upload_dir')) ?: config('upload_dir');
    $subdir = $uploadDir . DIRECTORY_SEPARATOR . 'yt';
    $file = $subdir . DIRECTORY_SEPARATOR . $safeId . '.jpg';
    if (is_file($file)) {
        return '/uploads/yt/' . $safeId . '.jpg';
    }

    if (!is_dir($subdir)) {
        @mkdir($subdir, 0755, true);
    }

    $remote = 'https://img.youtube.com/vi/' . $safeId . '/mqdefault.jpg';
    $ctx = stream_context_create([
        'http' => ['timeout' => 3, 'user_agent' => 'PazarPress/1.0'],
    ]);
    $data = @file_get_contents($remote, false, $ctx);
    if ($data !== false && strlen($data) > 1000) {
        @file_put_contents($file, $data);

        return '/uploads/yt/' . $safeId . '.jpg';
    }

    return $remote;
}

function responsive_image(string $url, string $alt = '', array $attrs = []): string
{
    $uploadDir = realpath(config('upload_dir')) ?: config('upload_dir');
    $path = parse_url($url, PHP_URL_PATH) ?: $url;
    $local = $uploadDir . str_replace('/uploads', '', $path);
    $base = preg_replace('/\.[^.]+$/', '', $local) ?: $local;
    $srcset = [];
    $variants = [];
    foreach (['sm' => 160, 'thumb' => 400, 'md' => 720, 'lg' => 1080] as $suffix => $fallbackW) {
        $variant = $base . '-' . $suffix . '.webp';
        if (is_file($variant)) {
            $rel = '/uploads' . str_replace('\\', '/', substr($variant, strlen($uploadDir)));
            $info = @getimagesize($variant);
            $w = (is_array($info) && !empty($info[0])) ? (int) $info[0] : $fallbackW;
            $srcset[] = $rel . ' ' . $w . 'w';
            $variants[$suffix] = $rel;
        }
    }
    $srcVariant = (string) ($attrs['src_variant'] ?? 'md');
    $src = $variants[$srcVariant] ?? $variants['md'] ?? $variants['thumb'] ?? $variants['sm'] ?? $url;
    if ($src === $url && $variants) {
        $src = reset($variants);
    }
    $class = e($attrs['class'] ?? '');
    $loading = e($attrs['loading'] ?? 'lazy');
    $width = isset($attrs['width']) ? ' width="' . (int) $attrs['width'] . '"' : '';
    $height = isset($attrs['height']) ? ' height="' . (int) $attrs['height'] . '"' : '';
    $fetchpriority = !empty($attrs['fetchpriority']) ? ' fetchpriority="' . e((string) $attrs['fetchpriority']) . '"' : '';
    $decoding = ($loading === 'eager' || !empty($attrs['fetchpriority'])) ? ' decoding="sync"' : ' decoding="async"';
    $sizes = (string) ($attrs['sizes'] ?? '(max-width:640px) 100vw, 760px');
    $srcsetAttr = $srcset ? ' srcset="' . e(implode(', ', $srcset)) . '" sizes="' . e($sizes) . '"' : '';
    return '<img src="' . e($src) . '"' . $srcsetAttr . ' alt="' . e($alt) . '" class="' . $class . '" loading="' . $loading . '"' . $fetchpriority . $decoding . $width . $height . '>';
}

/** Najbolji URL za LCP preload (WebP md/lg ako postoji). */
function lcp_image_url(?string $url): ?string
{
    if (!$url) {
        return null;
    }
    $uploadDir = realpath(config('upload_dir')) ?: config('upload_dir');
    $path = parse_url($url, PHP_URL_PATH) ?: $url;
    $local = $uploadDir . str_replace('/uploads', '', $path);
    $base = preg_replace('/\.[^.]+$/', '', $local) ?: $local;
    foreach (['md', 'lg', 'thumb'] as $suffix) {
        $variant = $base . '-' . $suffix . '.webp';
        if (is_file($variant)) {
            return '/uploads' . str_replace('\\', '/', substr($variant, strlen($uploadDir)));
        }
    }
    return $url;
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function send_mail(string $to, string $subject, string $body): bool
{
    $from = config('mail_from');
    $headers = "From: {$from}\r\nContent-Type: text/plain; charset=UTF-8\r\n";
    return @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, $headers);
}

function flash(string $key, ?string $value = null): ?string
{
    if ($value !== null) {
        $_SESSION['_flash'][$key] = $value;
        return null;
    }
    $msg = $_SESSION['_flash'][$key] ?? null;
    unset($_SESSION['_flash'][$key]);
    return $msg;
}

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function verify_csrf(): void
{
    $token = $_POST['_csrf'] ?? '';
    if (!hash_equals(csrf_token(), (string) $token)) {
        http_response_code(403);
        exit('Nevaljan CSRF token.');
    }
}

function is_valid_upload_image(array $file): bool
{
    $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
    $allowedExt = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    if (!in_array($ext, $allowedExt, true)) {
        return false;
    }
    $allowedMime = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        if (in_array($mime, $allowedMime, true)) {
            return true;
        }
    }
    // Windows često šalje application/octet-stream — prihvati ako je ekstenzija OK
    return in_array($file['type'] ?? '', $allowedMime, true)
        || ($file['type'] ?? '') === 'application/octet-stream';
}
