<h1>Profil i sigurnost</h1>
<p class="admin-hint">Prijavljeni kao <strong><?= e($user['email']) ?></strong></p>
<form method="post" class="admin-form admin-form--narrow">
  <?= csrf_field() ?>
  <label>Trenutna lozinka
    <input type="password" name="current_password" class="admin-input" required autocomplete="current-password">
  </label>
  <label>Nova lozinka (min. 8 znakova)
    <input type="password" name="new_password" class="admin-input" required autocomplete="new-password" minlength="8">
  </label>
  <label>Ponovi novu lozinku
    <input type="password" name="new_password_confirm" class="admin-input" required autocomplete="new-password" minlength="8">
  </label>
  <button type="submit" class="admin-btn admin-btn--primary">Promijeni lozinku</button>
</form>
