<?php
/** @var array|null $item */
$trans = is_array($item['translations'] ?? null) ? $item['translations'] : [];
?>
<fieldset class="admin-fieldset">
  <legend>Prijevodi (opciono)</legend>
  <p class="admin-hint">Bosanski naziv i opis su u poljima iznad. Dodajte prijevode za strane goste.</p>
  <?php foreach (MenuI18n::LANGS as $code => $label): if ($code === 'bs') continue; ?>
  <div class="admin-i18n-block">
    <strong><?= e($label) ?></strong>
    <label>Naziv
      <input class="admin-input" name="name_<?= e($code) ?>" value="<?= e($trans[$code]['name'] ?? '') ?>">
    </label>
    <label>Opis
      <textarea class="admin-input" name="description_<?= e($code) ?>" rows="2"><?= e($trans[$code]['description'] ?? '') ?></textarea>
    </label>
  </div>
  <?php endforeach; ?>
</fieldset>
