<!DOCTYPE html>
<html lang="sr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <?php if (!empty($preloadImage)): ?>
  <link rel="preload" as="image" href="<?= e($preloadImage) ?>" fetchpriority="high">
  <?php endif; ?>
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="default">
  <title><?= e($title ?? 'Pazar Press') ?></title>
  <meta name="description" content="<?= e($description ?? site_meta_description()) ?>">
  <?php if (!empty($noindex)): ?>
  <meta name="robots" content="noindex, nofollow">
  <?php endif; ?>
  <?php if (!empty($canonical)): ?>
  <link rel="canonical" href="<?= e($canonical) ?>">
  <?php endif; ?>
  <?php if (!empty($paginationRel['prev'])): ?>
  <link rel="prev" href="<?= e($paginationRel['prev']) ?>">
  <?php endif; ?>
  <?php if (!empty($paginationRel['next'])): ?>
  <link rel="next" href="<?= e($paginationRel['next']) ?>">
  <?php endif; ?>
  <link rel="alternate" type="application/rss+xml" title="Pazar Press RSS" href="<?= e(absolute_url('/feed.xml')) ?>">
  <link rel="manifest" href="/manifest.json">
  <?php
    $pageTitle = $title ?? 'Pazar Press';
    $pageDesc = $description ?? site_meta_description();
    $pageOgType = $ogType ?? 'website';
    $ogImg = $ogImage ?? og_image_url(null);
    $ogImgAlt = $ogImageAlt ?? ($pageTitle . ' — ' . config('site_name'));
  ?>
  <meta property="og:locale" content="sr_RS">
  <meta property="og:site_name" content="<?= e(config('site_name')) ?>">
  <meta property="og:title" content="<?= e($pageTitle) ?>">
  <meta property="og:description" content="<?= e($pageDesc) ?>">
  <meta property="og:type" content="<?= e($pageOgType) ?>">
  <?php if (!empty($canonical)): ?>
  <meta property="og:url" content="<?= e($canonical) ?>">
  <?php endif; ?>
  <meta property="og:image" content="<?= e($ogImg) ?>">
  <meta property="og:image:secure_url" content="<?= e($ogImg) ?>">
  <meta property="og:image:type" content="<?= e(og_image_mime($ogImg)) ?>">
  <meta property="og:image:width" content="1200">
  <meta property="og:image:height" content="630">
  <meta property="og:image:alt" content="<?= e($ogImgAlt) ?>">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="<?= e($pageTitle) ?>">
  <meta name="twitter:description" content="<?= e($pageDesc) ?>">
  <meta name="twitter:image" content="<?= e($ogImg) ?>">
  <meta name="twitter:image:alt" content="<?= e($ogImgAlt) ?>">
  <?php if ($pageOgType === 'article' && !empty($article)): ?>
  <meta property="article:published_time" content="<?= e(og_datetime($article['publishedAt'] ?? null)) ?>">
  <?php if (!empty($article['updatedAt'])): ?>
  <meta property="article:modified_time" content="<?= e(og_datetime($article['updatedAt'])) ?>">
  <?php endif; ?>
  <meta property="article:author" content="<?= e($article['author']['name'] ?? $article['authorName'] ?? '') ?>">
  <meta property="article:section" content="<?= e($article['category']['name'] ?? '') ?>">
  <?php endif; ?>
  <meta name="theme-color" content="#C8102E">
  <link rel="icon" href="/assets/img/icon.svg" type="image/svg+xml">
  <?php if (is_file(__DIR__ . '/../public/assets/img/icon-48.png')): ?>
  <link rel="icon" href="<?= e(asset_url('assets/img/icon-48.png')) ?>" sizes="48x48" type="image/png">
  <?php endif; ?>
  <?php if (is_file(__DIR__ . '/../public/assets/img/icon-192.png')): ?>
  <link rel="apple-touch-icon" href="<?= e(asset_url('assets/img/icon-192.png')) ?>">
  <?php else: ?>
  <link rel="apple-touch-icon" href="/assets/img/icon.svg">
  <?php endif; ?>
  <?php if (!empty($preconnectYoutube)): ?>
  <link rel="preconnect" href="https://img.youtube.com" crossorigin>
  <?php endif; ?>
  <style><?php readfile(__DIR__ . '/../public/assets/css/critical.css'); ?></style>
  <link rel="preload" href="<?= e(asset_url('assets/css/site.css')) ?>" as="style" onload="this.onload=null;this.rel='stylesheet'">
  <noscript><link rel="stylesheet" href="<?= e(asset_url('assets/css/site.css')) ?>"></noscript>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,600;12..96,700;12..96,800&family=Inter:wght@400;500;600;700&display=swap" media="print" onload="this.media='all'">
  <noscript><link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,600;12..96,700;12..96,800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"></noscript>
  <?php if (!empty($needsSerifFont)): ?>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Source+Serif+4:opsz,wght@8..60,400;8..60,600;8..60,700&display=swap" media="print" onload="this.media='all'">
  <noscript><link href="https://fonts.googleapis.com/css2?family=Source+Serif+4:opsz,wght@8..60,400;8..60,600;8..60,700&display=swap" rel="stylesheet"></noscript>
  <?php endif; ?>
  <?php if (!empty($loadRestaurantCss)): ?>
  <link rel="stylesheet" href="/assets/css/restaurant.css">
  <?php endif; ?>
  <script>
    (function(){try{var t=localStorage.getItem("pazarpress-theme");var d=t?t==="dark":window.matchMedia("(prefers-color-scheme: dark)").matches;if(d)document.documentElement.classList.add("dark")}catch(e){}})();
  </script>
  <?php
  $analyticsProvider = Settings::get('analytics_provider');
  $analyticsId = Settings::get('analytics_id');
  if ($analyticsProvider === 'plausible' && $analyticsId): ?>
  <script defer data-domain="<?= e($analyticsId) ?>" src="https://plausible.io/js/script.js"></script>
  <?php elseif ($analyticsProvider === 'matomo' && $analyticsId):
    $parts = explode('|', $analyticsId);
    $matomoUrl = rtrim($parts[0] ?? '', '/');
    $siteId = $parts[1] ?? '1';
  ?>
  <script>
    var _paq=window._paq=window._paq||[];_paq.push(["trackPageView"]);_paq.push(["enableLinkTracking"]);
    (function(){var u="<?= e($matomoUrl) ?>/";_paq.push(["setTrackerUrl",u+"matomo.php"]);_paq.push(["setSiteId","<?= e($siteId) ?>"]);
    var d=document,g=d.createElement("script");g.async=true;g.src=u+"matomo.js";d.head.appendChild(g);})();
  </script>
  <?php endif; ?>
</head>
<body>
<?php if ($msg = flash('newsletter')): ?>
<div class="flash-banner" role="status"><?= e($msg) ?></div>
<?php endif; ?>
<?= $content ?? '' ?>
<?php include __DIR__ . '/partials/search-overlay.php'; ?>
<?php include __DIR__ . '/partials/mobile-drawer.php'; ?>
<?php include __DIR__ . '/partials/push-banner.php'; ?>
<script src="<?= e(asset_url('assets/js/theme.js')) ?>" defer></script>
<script src="<?= e(asset_url('assets/js/site.js')) ?>" defer></script>
<script src="<?= e(asset_url('assets/js/reader-tools.js')) ?>" defer></script>
<script src="<?= e(asset_url('assets/js/push-banner.js')) ?>" defer></script>
<?php if (!empty($extraScripts)): foreach ($extraScripts as $s): ?>
<script src="<?= e(str_starts_with($s, '/assets/') ? asset_url(ltrim($s, '/')) : $s) ?>" defer></script>
<?php endforeach; endif; ?>
<?php if (!empty($jsonLd)): ?>
<script type="application/ld+json"><?= json_encode($jsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
<?php endif; ?>
<script>
  if ("serviceWorker" in navigator) {
    window.addEventListener("load", function () {
      navigator.serviceWorker.register("<?= e(asset_url('sw.js')) ?>").catch(function () {});
    });
  }
</script>
</body>
</html>
