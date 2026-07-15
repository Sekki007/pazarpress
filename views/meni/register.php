<!DOCTYPE html>
<html lang="bs">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Registracija — Besplatni digitalni meni</title>
  <link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body class="login-page">
  <form method="post" class="login-card" style="max-width:440px">
    <h1>Besplatni digitalni meni</h1>
    <p class="admin-muted">Kreirajte online cjenovnik za vaš restoran na Sandžak.net</p>
    <?php if ($msg = flash('error')): ?><p class="flash flash--err"><?= e($msg) ?></p><?php endif; ?>
    <?= csrf_field() ?>
    <label>Vaše ime<input type="text" name="name" required class="admin-input" autocomplete="name"></label>
    <label>Email<input type="email" name="email" required class="admin-input" autocomplete="username"></label>
    <label>Lozinka (min. 8)<input type="password" name="password" required minlength="8" class="admin-input" autocomplete="new-password"></label>
    <button type="submit" class="admin-btn admin-btn--primary admin-btn--block">Kreiraj nalog</button>
    <p style="margin-top:1rem;font-size:.9rem">Već imate nalog? <a href="/moj-meni/prijava">Prijava</a></p>
    <a href="/restorani" class="login-back">← Pregled restorana</a>
  </form>
</body>
</html>
