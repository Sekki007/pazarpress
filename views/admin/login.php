<!DOCTYPE html>
<html lang="bs">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin prijava — Pazar Press</title>
  <link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body class="login-page">
  <form method="post" class="login-card">
    <h1>Pazar Press Admin</h1>
    <?php if ($msg = flash('error')): ?><p class="flash flash--err"><?= e($msg) ?></p><?php endif; ?>
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
    <label>Email<input type="email" name="email" required class="admin-input" autocomplete="username"></label>
    <label>Lozinka<input type="password" name="password" required class="admin-input" autocomplete="current-password"></label>
    <button type="submit" class="admin-btn admin-btn--primary admin-btn--block">Prijava</button>
    <a href="/" class="login-back">← Nazad na sajt</a>
  </form>
</body>
</html>
