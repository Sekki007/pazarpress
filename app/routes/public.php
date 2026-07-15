<?php

declare(strict_types=1);

$uri = rtrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/', '/') ?: '/';

if ($uri === '/sitemap.xml') {
    header('Content-Type: application/xml; charset=utf-8');
    send_dynamic_cache_headers();
    $xml = cache_remember('sitemap:xml', 600, static function (): string {
        $pdo = Database::connection();
        $articles = $pdo->query("SELECT slug, updatedAt FROM articles WHERE status = 'PUBLISHED' ORDER BY publishedAt DESC")->fetchAll();
        $categories = $pdo->query('SELECT slug, updatedAt FROM categories ORDER BY name')->fetchAll();
        $tags = ArticleRepository::getTagsForSitemap();
        $base = config('site_url');
        $out = '<?xml version="1.0" encoding="UTF-8"?>';
        $out .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        $out .= "<url><loc>{$base}/</loc><changefreq>hourly</changefreq><priority>1.0</priority></url>";
        $out .= "<url><loc>{$base}/video</loc><changefreq>daily</changefreq><priority>0.7</priority></url>";
        $out .= "<url><loc>{$base}/pretraga</loc><changefreq>weekly</changefreq><priority>0.5</priority></url>";
        foreach ($categories as $cat) {
            $loc = $base . '/rubrika/' . rawurlencode($cat['slug']);
            $lastmod = date('Y-m-d', strtotime($cat['updatedAt']));
            $out .= "<url><loc>{$loc}</loc><lastmod>{$lastmod}</lastmod><changefreq>daily</changefreq><priority>0.75</priority></url>";
        }
        foreach ($tags as $tag) {
            $loc = $base . '/tag/' . rawurlencode($tag['slug']);
            $lastmod = date('Y-m-d', strtotime($tag['updatedAt']));
            $out .= "<url><loc>{$loc}</loc><lastmod>{$lastmod}</lastmod><changefreq>weekly</changefreq><priority>0.6</priority></url>";
        }
        foreach ($articles as $a) {
            $loc = $base . '/vijest/' . rawurlencode($a['slug']);
            $lastmod = date('Y-m-d', strtotime($a['updatedAt']));
            $out .= "<url><loc>{$loc}</loc><lastmod>{$lastmod}</lastmod><changefreq>weekly</changefreq><priority>0.8</priority></url>";
        }
        $out .= '</urlset>';
        return $out;
    });
    echo $xml;
    exit;
}

if ($uri === '/feed.xml' || $uri === '/rss.xml') {
    header('Content-Type: application/rss+xml; charset=utf-8');
    send_dynamic_cache_headers();
    $rss = cache_remember('feed:rss', 300, static function (): string {
        $pdo = Database::connection();
        $articles = $pdo->query(
            "SELECT a.slug, a.title, a.`lead`, a.publishedAt, a.coverImage, au.name AS authorName
             FROM articles a JOIN authors au ON au.id = a.authorId
             WHERE a.status = 'PUBLISHED' ORDER BY a.publishedAt DESC LIMIT 40"
        )->fetchAll();
        $base = config('site_url');
        $site = e(config('site_name'));
        $out = '<?xml version="1.0" encoding="UTF-8"?>';
        $out .= '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:media="http://search.yahoo.com/mrss/">';
        $out .= '<channel>';
        $out .= "<title>{$site}</title>";
        $out .= '<description>Vesti iz Novog Pazara</description>';
        $out .= "<link>{$base}/</link>";
        $out .= '<language>bs</language>';
        $out .= "<atom:link href=\"{$base}/feed.xml\" rel=\"self\" type=\"application/rss+xml\"/>";
        foreach ($articles as $a) {
            $link = $base . '/vijest/' . rawurlencode($a['slug']);
            $pub = date(DATE_RSS, strtotime($a['publishedAt']));
            $out .= '<item>';
            $out .= '<title>' . e($a['title']) . '</title>';
            $out .= '<link>' . e($link) . '</link>';
            $out .= '<guid isPermaLink="true">' . e($link) . '</guid>';
            $out .= '<pubDate>' . $pub . '</pubDate>';
            $out .= '<description>' . e($a['lead']) . '</description>';
            $out .= '<author>' . e($a['authorName']) . '</author>';
            if (!empty($a['coverImage'])) {
                $img = absolute_url($a['coverImage']);
                $out .= '<media:content url="' . e($img) . '" medium="image"/>';
            }
            $out .= '</item>';
        }
        $out .= '</channel></rss>';
        return $out;
    });
    echo $rss;
    exit;
}

if ($uri === '/robots.txt') {
    header('Content-Type: text/plain; charset=utf-8');
    $base = config('site_url');
    echo "User-agent: *\nAllow: /\nAllow: /llms.txt\nDisallow: /admin/\nDisallow: /pretraga\nSitemap: {$base}/sitemap.xml\n";
    exit;
}

if ($uri === '/llms.txt') {
    header('Content-Type: text/plain; charset=utf-8');
    send_dynamic_cache_headers();
    echo cache_remember('llms:txt', 600, static fn (): string => generate_llms_txt());
    exit;
}

if ($uri === '/newsletter/potvrdi') {
    $token = trim((string) ($_GET['token'] ?? ''));
    $pdo = Database::connection();
    $stmt = $pdo->prepare('SELECT * FROM newsletter_subscribers WHERE token = ? LIMIT 1');
    $stmt->execute([$token]);
    $sub = $stmt->fetch();
    if ($sub) {
        $pdo->prepare('UPDATE newsletter_subscribers SET confirmed = 1 WHERE id = ?')->execute([$sub['id']]);
        flash('newsletter', 'Email adresa je potvrđena. Hvala!');
    } else {
        flash('newsletter', 'Link za potvrdu nije valjan ili je istekao.');
    }
    redirect('/');
}

if ($uri === '/pretraga') {
    $q = trim((string) ($_GET['q'] ?? ''));
    $results = $q !== '' ? ArticleRepository::search($q) : [];
    $infoStrip = InfoStrip::get();
    view('search', [
        'title' => $q ? 'Pretraga: ' . $q . ' — Pazar Press' : 'Pretraga — Pazar Press',
        'description' => 'Rezultati pretrage vijesti na Pazar Press',
        'noindex' => true,
        'q' => $q,
        'results' => $results,
        'infoStrip' => $infoStrip,
    ]);
    exit;
}

if ($uri === '/sacuvano') {
    $infoStrip = InfoStrip::get();
    view('sacuvano', [
        'title' => 'Sačuvano — Pazar Press',
        'description' => 'Sačuvane vijesti i istorija čitanja na Pazar Press.',
        'noindex' => true,
        'canonical' => config('site_url') . '/sacuvano',
        'infoStrip' => $infoStrip,
        'navActive' => 'saved',
    ]);
    exit;
}

if ($uri === '/video') {
    $page = max(1, (int) ($_GET['str'] ?? 1));
    $data = ArticleRepository::getVideosPage($page);
    if ($page > 1 && $page > $data['pages']) {
        not_found('Stranica nije pronađena');
    }
    $infoStrip = InfoStrip::get();
    $basePath = '/video';
    $canonical = config('site_url') . '/video' . ($page > 1 ? '?str=' . $page : '');
    view('video', [
        'title' => 'Video — Pazar Press',
        'description' => 'Video prilozi i reportaže sa Pazar Press portala.',
        'canonical' => $canonical,
        'preconnectYoutube' => true,
        'videos' => $data['items'],
        'pagination' => $data,
        'infoStrip' => $infoStrip,
        'breadcrumbs' => [
            ['label' => 'Početna', 'url' => '/'],
            ['label' => 'Video'],
        ],
        'jsonLd' => build_json_ld_graph([
            [
                '@type' => 'CollectionPage',
                'name' => 'Video — Pazar Press',
                'url' => $canonical,
                'description' => 'Video prilozi i reportaže sa Pazar Press portala.',
            ],
            breadcrumb_json_ld([
                ['label' => 'Početna', 'url' => '/'],
                ['label' => 'Video'],
            ]),
        ]),
        'paginationRel' => pagination_rel_links($basePath, [], $data),
    ]);
    exit;
}

if (preg_match('#^/rubrika/([^/]+)$#', $uri, $m)) {
    $slug = urldecode($m[1]);
    $category = ArticleRepository::getCategoryBySlug($slug);
    if (!$category) {
        not_found('Rubrika nije pronađena');
    }
    $citySlug = isset($_GET['grad']) ? trim((string) $_GET['grad']) : null;
    if ($citySlug === '') {
        $citySlug = null;
    }
    $city = $citySlug ? slug_to_city($citySlug) : null;
    if ($citySlug && !$city) {
        not_found('Grad nije pronađen');
    }
    $page = max(1, (int) ($_GET['str'] ?? 1));
    $data = ArticleRepository::getCategoryArticles($slug, $city, $page);
    if ($page > 1 && $page > $data['pages']) {
        not_found('Stranica nije pronađena');
    }
    $infoStrip = InfoStrip::get();
    $canonical = config('site_url') . '/rubrika/' . rawurlencode($slug);
    if ($citySlug) {
        $canonical .= '?grad=' . rawurlencode($citySlug);
    }
    if ($page > 1) {
        $canonical .= ($citySlug ? '&' : '?') . 'str=' . $page;
    }
    $breadcrumbs = [
        ['label' => 'Početna', 'url' => '/'],
        ['label' => $category['name']],
    ];
    $basePath = '/rubrika/' . $category['slug'];
    $queryParams = $citySlug ? ['grad' => $citySlug] : [];
    view('category', [
        'title' => $category['name'] . ' — Pazar Press',
        'description' => 'Najnovije vijesti iz rubrike ' . $category['name'] . ' na Pazar Press',
        'canonical' => $canonical,
        'category' => $category,
        'articles' => $data['items'],
        'pagination' => $data,
        'citySlug' => $citySlug,
        'city' => $city,
        'infoStrip' => $infoStrip,
        'breadcrumbs' => $breadcrumbs,
        'jsonLd' => build_json_ld_graph([
            [
                '@type' => 'CollectionPage',
                'name' => $category['name'] . ' — Pazar Press',
                'url' => $canonical,
                'description' => 'Najnovije vijesti iz rubrike ' . $category['name'],
            ],
            breadcrumb_json_ld($breadcrumbs),
        ]),
        'paginationRel' => pagination_rel_links($basePath, $queryParams, $data),
    ]);
    exit;
}

if (preg_match('#^/tag/([^/]+)$#', $uri, $m)) {
    $slug = urldecode($m[1]);
    $tag = ArticleRepository::getTagBySlug($slug);
    if (!$tag) {
        not_found('Tag nije pronađen');
    }
    $page = max(1, (int) ($_GET['str'] ?? 1));
    $data = ArticleRepository::getTagArticles($slug, $page);
    if ($page > 1 && $page > $data['pages']) {
        not_found('Stranica nije pronađena');
    }
    $infoStrip = InfoStrip::get();
    $canonical = config('site_url') . '/tag/' . rawurlencode($slug) . ($page > 1 ? '?str=' . $page : '');
    $breadcrumbs = [
        ['label' => 'Početna', 'url' => '/'],
        ['label' => '#' . $tag['name']],
    ];
    $basePath = '/tag/' . $tag['slug'];
    view('tag', [
        'title' => '#' . $tag['name'] . ' — Pazar Press',
        'description' => 'Vijesti označene tagom #' . $tag['name'] . ' na Pazar Press',
        'canonical' => $canonical,
        'tag' => $tag,
        'articles' => $data['items'],
        'pagination' => $data,
        'infoStrip' => $infoStrip,
        'breadcrumbs' => $breadcrumbs,
        'jsonLd' => build_json_ld_graph([
            [
                '@type' => 'CollectionPage',
                'name' => '#' . $tag['name'],
                'url' => $canonical,
                'description' => 'Arhiva vijesti za tag #' . $tag['name'],
            ],
            breadcrumb_json_ld($breadcrumbs),
        ]),
        'paginationRel' => pagination_rel_links($basePath, [], $data),
    ]);
    exit;
}

if (preg_match('#^/vijest/([^/]+)$#', $uri, $m)) {
    $article = ArticleRepository::getBySlug(urldecode($m[1]));
    if (!$article) {
        not_found('Članak nije pronađen');
    }
    ArticleRepository::incrementViews($article['id']);
    $related = ArticleRepository::getRelated($article['categorySlug'], $article['id']);
    $nextArticle = ArticleRepository::getNextArticle(
        (string) ($article['publishedAt'] ?? $article['createdAt'] ?? ''),
        (string) $article['id']
    );
    $comments = ArticleRepository::getApprovedComments($article['id']);
    $canonical = config('site_url') . '/vijest/' . $article['slug'];
    $metaDesc = !empty($article['seoDescription']) ? $article['seoDescription'] : $article['lead'];
    $breadcrumbs = [
        ['label' => 'Početna', 'url' => '/'],
        ['label' => $article['category']['name'], 'url' => '/rubrika/' . $article['category']['slug']],
        ['label' => $article['title']],
    ];
    $base = config('site_url');
    $newsNode = [
        '@type' => 'NewsArticle',
        '@id' => $canonical . '#article',
        'headline' => $article['title'],
        'description' => $metaDesc,
        'datePublished' => $article['publishedAt'],
        'dateModified' => $article['updatedAt'] ?? $article['publishedAt'],
        'author' => ['@type' => 'Person', 'name' => $article['author']['name'] ?? $article['authorName']],
        'image' => $article['coverImage'] ? [absolute_url($article['coverImage'])] : [og_image_url(null)],
        'articleSection' => $article['category']['name'],
        'inLanguage' => 'bs',
        'publisher' => ['@id' => $base . '/#organization'],
        'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $canonical],
        'isAccessibleForFree' => true,
    ];
    if (!empty($article['isBreaking'])) {
        $newsNode['breakingNews'] = true;
    }
    if (!empty($article['importSchema'])) {
        $imported = json_decode((string) $article['importSchema'], true);
        if (is_array($imported)) {
            unset($imported['@context']);
            $newsNode = array_merge($newsNode, $imported);
            $newsNode['@type'] = 'NewsArticle';
            $newsNode['@id'] = $canonical . '#article';
        }
    }
    $pageTitle = !empty($article['seoTitle'])
        ? $article['seoTitle'] . ' — Pazar Press'
        : $article['title'] . ' — Pazar Press';

    view('article', [
        'title' => $pageTitle,
        'description' => $metaDesc,
        'article' => $article,
        'related' => $related,
        'nextArticle' => $nextArticle,
        'comments' => $comments,
        'canonical' => $canonical,
        'ogImage' => og_image_url($article['coverImage'] ?? null),
        'preloadImage' => lcp_image_url($article['coverImage'] ?? null),
        'needsSerifFont' => true,
        'ogType' => 'article',
        'breadcrumbs' => $breadcrumbs,
        'jsonLd' => build_json_ld_graph([
            $newsNode,
            breadcrumb_json_ld($breadcrumbs),
        ]),
    ]);
    exit;
}

if (preg_match('#^/r/([a-z0-9]+)$#', $uri, $m) || $uri === '/restorani' || preg_match('#^/restorani/#', $uri)) {
    if (!restaurants_enabled()) {
        not_found();
    }
}

if (preg_match('#^/r/([a-z0-9]+)$#', $uri, $m)) {
    $restaurant = RestaurantRepository::getByQrCode($m[1]);
    if (!$restaurant) {
        not_found('Meni nije pronađen');
    }
    redirect('/restorani/' . $restaurant['slug']);
}

if ($uri === '/restorani') {
    $citySlug = isset($_GET['grad']) ? trim((string) $_GET['grad']) : null;
    $city = $citySlug ? slug_to_city($citySlug) : null;
    if ($citySlug && !$city) {
        not_found('Grad nije pronađen');
    }
    $restaurants = cache_remember('restaurants:list:' . ($city ?? 'all'), 120, static function () use ($city): array {
        return RestaurantRepository::listPublished($city, 100);
    });
    $title = $city ? 'Restorani — ' . city_label($city) : 'Restorani u Sandžaku';
    view('restaurants/index', [
        'title' => $title . ' — Pazar Press',
        'description' => 'Besplatni digitalni meniji restorana u Sandžaku. Pregledajte cjenovnike, ocjene i kontakt.',
        'canonical' => config('site_url') . '/restorani' . ($citySlug ? '?grad=' . rawurlencode($citySlug) : ''),
        'citySlug' => $citySlug,
        'city' => $city,
        'restaurants' => $restaurants,
        'navActive' => 'restorani',
    ], 'layout-meni');
    exit;
}

if (preg_match('#^/restorani/([^/]+)/qr\.png$#', $uri, $m)) {
    $restaurant = RestaurantRepository::getBySlug(urldecode($m[1]));
    if (!$restaurant) {
        not_found();
    }
    RestaurantService::outputQrPng($restaurant);
}

if (preg_match('#^/restorani/([^/]+)/recenzija$#', $uri, $m) && $method === 'POST') {
    verify_csrf();
    $restaurant = RestaurantRepository::getBySlug(urldecode($m[1]));
    if (!$restaurant || !$restaurant['reviewsEnabled']) {
        json_response(['error' => 'Recenzije nisu dostupne.'], 400);
    }
    if (!RateLimiter::hit(RateLimiter::clientKey('rst-review:' . $restaurant['id']), 3, 86400)) {
        flash('error', 'Već ste ostavili recenziju danas.');
        redirect('/restorani/' . $restaurant['slug'] . '#recenzije');
    }
    $name = trim((string) ($_POST['name'] ?? ''));
    $rating = (int) ($_POST['rating'] ?? 0);
    $body = trim((string) ($_POST['body'] ?? ''));
    if ($name === '' || $rating < 1 || $rating > 5) {
        flash('error', 'Ime i ocjena (1–5) su obavezni.');
        redirect('/restorani/' . $restaurant['slug'] . '#recenzije');
    }
    RestaurantService::addReview($restaurant['id'], $name, $rating, $body, RateLimiter::clientKey('ip'));
    flash('success', 'Hvala! Recenzija čeka odobrenje urednika.');
    redirect('/restorani/' . $restaurant['slug'] . '#recenzije');
}

if (preg_match('#^/restorani/([^/]+)$#', $uri, $m)) {
    $slug = urldecode($m[1]);
    $restaurant = RestaurantRepository::getBySlug($slug);
    if (!$restaurant) {
        not_found('Restoran nije pronađen');
    }
    RestaurantRepository::incrementViews($restaurant['id']);
    $menuLangs = MenuI18n::enabledLangs($restaurant);
    $menuLang = MenuI18n::resolveLang($restaurant, isset($_GET['lang']) ? (string) $_GET['lang'] : null);
    if (isset($_GET['lang']) && in_array($menuLang, $menuLangs, true)) {
        setcookie('menu_lang', $menuLang, [
            'expires' => time() + 86400 * 365,
            'path' => '/',
            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
    $menu = MenuI18n::localizeMenu(RestaurantRepository::getFullMenu($restaurant['id']), $menuLang);
    $reviews = RestaurantRepository::getReviews($restaurant['id']);
    $canonical = config('site_url') . '/restorani/' . $restaurant['slug'];
    $L = MenuI18n::uiLabels($menuLang);
    view('restaurants/show', [
        'title' => $restaurant['name'] . ' — Digitalni meni — Pazar Press',
        'description' => mb_substr(strip_tags((string) ($restaurant['description'] ?? '')), 0, 160)
            ?: 'Digitalni meni restorana ' . $restaurant['name'] . ' u ' . city_label($restaurant['city']) . '.',
        'canonical' => $canonical,
        'ogImage' => og_image_url(restaurant_cover_url($restaurant['coverImage'] ?? null, $restaurant)),
        'restaurant' => $restaurant,
        'menu' => $menu,
        'reviews' => $reviews,
        'menuLang' => $menuLang,
        'menuLangs' => $menuLangs,
        'L' => $L,
        'navActive' => 'restorani',
    ], 'layout-meni');
    exit;
}

if ($uri !== '/') {
    not_found();
}

// Homepage
$citySlug = isset($_GET['grad']) ? trim((string) $_GET['grad']) : null;
if ($citySlug === '') {
    $citySlug = null;
}
$city = $citySlug ? slug_to_city($citySlug) : null;
if ($citySlug && !$city) {
    not_found('Grad nije pronađen');
}
if (HomeFeaturedService::isEnabled()) {
    HomeFeaturedService::ensureTodayFeatured();
}
$featured = cache_remember('home:featured', 120, static fn () => ArticleRepository::getFeatured());
$breaking = cache_remember('home:breaking', 60, static fn () => ArticleRepository::getBreaking());
$latest = cache_remember('home:latest:' . ($city ?? 'all'), 90, static fn () => ArticleRepository::getLatest($city, null, 10));
$latestSidebar = cache_remember('home:latest-sidebar', 90, static function () use ($featured): array {
    $items = ArticleRepository::getLatest(null, null, 7)['items'];
    if ($featured) {
        $items = array_values(array_filter($items, static fn (array $a): bool => ($a['slug'] ?? '') !== ($featured['slug'] ?? '')));
    }
    return array_slice($items, 0, 5);
});
$sport = cache_remember('home:sport', 120, static fn () => ArticleRepository::getByCategory('sport', 4));
$diaspora = cache_remember('home:diaspora', 120, static fn () => ArticleRepository::getByCategory('dijaspora', 4));
$videos = cache_remember('home:videos', 300, static fn () => ArticleRepository::getLatestVideos(3));
$poll = cache_remember('home:poll', 60, static fn () => ArticleRepository::getActivePoll());

$feed = array_values(array_filter($latest['items'], static fn ($a) => !$featured || $a['slug'] !== $featured['slug']));

$homeCanonical = config('site_url') . '/';
if ($citySlug) {
    $homeCanonical .= '?grad=' . rawurlencode($citySlug);
}

view('home', [
    'title' => 'Pazar Press — Vesti iz Novog Pazara',
    'description' => site_meta_description(),
    'canonical' => $homeCanonical,
    'ogImage' => og_image_url(null),
    'preloadImage' => $featured ? lcp_image_url($featured['coverImage'] ?? null) : null,
    'preconnectYoutube' => $videos !== [],
    'jsonLd' => build_json_ld_graph(site_seo_schemas()),
    'citySlug' => $citySlug,
    'city' => $city,
    'featured' => $featured,
    'breaking' => $breaking,
    'feed' => $feed,
    'feedCursor' => $latest['nextCursor'],
    'latestSidebar' => $latestSidebar,
    'sport' => $sport,
    'diaspora' => $diaspora,
    'videos' => $videos,
    'poll' => $poll,
]);
