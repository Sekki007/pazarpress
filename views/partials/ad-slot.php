<?php
$slot = $slot ?? '';
$html = Settings::get($slot, '');
if (!$html) {
    return;
}
?>
<aside class="ad-slot ad-slot--<?= e(str_replace('ad_', '', $slot)) ?>" aria-label="Oglas">
  <?= $html ?>
</aside>
