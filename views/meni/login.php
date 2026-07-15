<!DOCTYPE html>
<html lang="bs">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Prijava vlasnika — Sandžak.net</title>
  <link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body class="login-page">
  <form method="post" class="login-card">
    <h1>Digitalni meni</h1>
    <p class="admin-muted">Prijava za vlasnike restorana</p>
    <?php if ($msg = flash('error')): ?><p class="flash flash--err"><?= e($msg) ?></p><?php endif; ?>
    <?= csrf_field() ?>
    <label>Email<input type="email" name="email" required class="admin-input" autocomplete="username"></label>
    <label>Lozinka<input type="password" name="password" required class="admin-input" autocomplete="current-password"></label>
    <button type="submit" class="admin-btn admin-btn--primary admin-btn--block">Prijava</button>
    <p style="margin-top:1rem;font-size:.9rem">Nemate nalog? <a href="/moj-meni/registracija">Registrujte restoran besplatno</a></p>
    <a href="/" class="login-back">← Nazad na Sandžak.net</a>
  </form>
</body>
</html>
