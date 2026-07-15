<!DOCTYPE html>
<html lang="bs">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title ?? 'Moj meni') ?> — Sandžak.net</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body class="admin-body">
<div class="admin-shell">
  <aside class="admin-sidebar">
    <a href="/" class="admin-logo">Sandžak<span>.net</span></a>
    <p class="admin-sidebar__label">Digitalni meni</p>
    <nav class="admin-nav">
      <?php
      $links = [
          'dashboard' => ['/moj-meni', 'Pregled'],
          'profil' => ['/moj-meni/profil', 'Profil restorana'],
          'meni' => ['/moj-meni/meni', 'Cjenovnik'],
          'qr' => ['/moj-meni/qr', 'QR kod'],
      ];
      foreach ($links as $key => [$href, $label]):
      ?>
      <a href="<?= e($href) ?>" class="admin-nav__link<?= ($active ?? '') === $key ? ' admin-nav__link--active' : '' ?>"><?= e($label) ?></a>
      <?php endforeach; ?>
    </nav>
    <div class="admin-user">
      <p><?= e($user['email'] ?? '') ?></p>
      <a href="/moj-meni/odjava">Odjava</a>
    </div>
  </aside>
  <div class="admin-main">
    <header class="admin-topbar">
      <?php if (!empty($restaurant) && ($restaurant['status'] ?? '') === 'PUBLISHED'): ?>
      <a href="/restorani/<?= e($restaurant['slug']) ?>" target="_blank" class="admin-btn">↗ Javni meni</a>
      <?php endif; ?>
      <a href="/moj-meni/registracija" class="admin-btn" style="display:none"></a>
    </header>
    <main class="admin-content">
      <?php if ($msg = flash('success')): ?><div class="flash flash--ok"><?= e($msg) ?></div><?php endif; ?>
      <?php if ($msg = flash('error')): ?><div class="flash flash--err"><?= e($msg) ?></div><?php endif; ?>
      <?= $content ?? '' ?>
    </main>
  </div>
</div>
<script src="/assets/js/meni-owner.js" defer></script>
</body>
</html>
