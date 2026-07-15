<?php
/** @var array|null $restaurant */
$enabled = $restaurant['menuLangs'] ?? ['bs', 'en', 'tr'];
?>
<fieldset class="admin-fieldset">
  <legend>Jezici menija</legend>
  <p class="admin-hint">Bosanski je uvijek uključen. Ostale jezike uključite ako imate prijevode stavki.</p>
  <?php foreach (MenuI18n::LANGS as $code => $label): if ($code === 'bs') continue; ?>
  <label class="admin-check">
    <input type="checkbox" name="menu_lang_<?= e($code) ?>" <?= in_array($code, $enabled, true) ? 'checked' : '' ?>>
    <?= e($label) ?>
  </label>
  <?php endforeach; ?>
</fieldset>
