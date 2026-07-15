<!DOCTYPE html>
<html lang="<?= e($menuLang ?? 'bs') ?>" class="rst-html">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, viewport-fit=cover, user-scalable=no">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <title><?= e($title ?? 'Digitalni meni') ?></title>
  <meta name="description" content="<?= e($description ?? '') ?>">
  <?php if (!empty($canonical)): ?>
  <link rel="canonical" href="<?= e($canonical) ?>">
  <?php endif; ?>
  <meta name="theme-color" content="#0E5A48">
  <link rel="icon" href="/assets/img/icon.svg" type="image/svg+xml">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:wght@600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/restaurant.css">
  <script>
    try { localStorage.removeItem('sandzak-theme'); document.documentElement.classList.remove('dark'); } catch (e) {}
  </script>
</head>
<body class="rst-body">
<div class="rst-app" id="rst-app">
<?php if ($msg = flash('success')): ?><div class="rst-flash rst-flash--ok" role="status"><?= e($msg) ?></div><?php endif; ?>
<?php if ($msg = flash('error')): ?><div class="rst-flash rst-flash--err" role="alert"><?= e($msg) ?></div><?php endif; ?>
<?= $content ?? '' ?>
</div>
<script src="/assets/js/restaurant-menu.js" defer></script>
</body>
</html>
