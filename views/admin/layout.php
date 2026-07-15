<!DOCTYPE html>
<html lang="sr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title ?? 'Admin') ?> — Pazar Press</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body class="admin-body">
<div class="admin-shell">
  <aside class="admin-sidebar">
    <a href="/" class="admin-logo">Pazar <span>Press</span></a>
    <p class="admin-sidebar__label">Administracija</p>
    <nav class="admin-nav">
      <?php
      $links = [
          'dashboard' => ['/admin', 'Pregled'],
          'clanci' => ['/admin/clanci', 'Članci'],
          'auto-vesti' => ['/admin/auto-vesti', 'Auto Vesti'],
          'rubrike' => ['/admin/rubrike', 'Rubrike'],
          'komentari' => ['/admin/komentari', 'Komentari'],
          'ankete' => ['/admin/ankete', 'Ankete'],
          'video' => ['/admin/video', 'Video'],
          'newsletter' => ['/admin/newsletter', 'Newsletter'],
          'prijave' => ['/admin/prijave', 'Prijave'],
          'postavke' => ['/admin/postavke', 'Postavke'],
          'profil' => ['/admin/profil', 'Profil'],
      ];
      if (restaurants_enabled()) {
          $links['restorani'] = ['/admin/restorani', 'Restorani'];
          $links['restorani-recenzije'] = ['/admin/restorani-recenzije', 'Recenzije menija'];
      }
      foreach ($links as $key => [$href, $label]):
      ?>
      <a href="<?= e($href) ?>" class="admin-nav__link<?= ($active ?? '') === $key ? ' admin-nav__link--active' : '' ?>"><?= e($label) ?></a>
      <?php endforeach; ?>
    </nav>
    <div class="admin-user">
      <p><?= e($user['email'] ?? '') ?></p>
      <a href="/admin/logout">Odjava</a>
    </div>
  </aside>
  <div class="admin-main">
    <header class="admin-topbar">
      <a href="/admin/clanci/novi" class="admin-btn admin-btn--primary">+ Novi članak</a>
      <?php if (restaurants_enabled()): ?><a href="/admin/restorani/novi" class="admin-btn">+ Restoran</a><?php endif; ?>
      <a href="/" target="_blank" class="admin-btn">↗ Sajt</a>
    </header>
    <main class="admin-content<?= ($active ?? '') === 'clanci' ? ' admin-content--wide' : '' ?>">
      <?php if ($msg = flash('success')): ?><div class="flash flash--ok"><?= e($msg) ?></div><?php endif; ?>
      <?php if ($msg = flash('error')): ?><div class="flash flash--err"><?= e($msg) ?></div><?php endif; ?>
      <?php if ($msg = flash('warning')): ?><div class="flash flash--warn"><?= e($msg) ?></div><?php endif; ?>
      <?php if ($msg = flash('info')): ?><div class="flash flash--info"><?= e($msg) ?></div><?php endif; ?>
      <?= $content ?? '' ?>
    </main>
  </div>
</div>
</body>
</html>
