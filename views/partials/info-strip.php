<?php
$infoStrip = $infoStrip ?? InfoStrip::get();
if (!is_array($infoStrip)) {
    return;
}
$weather = $infoStrip['weather'] ?? [];
$vaktija = $infoStrip['vaktija'] ?? null;
$currency = $infoStrip['currency'] ?? [];
if ($weather === [] && !$vaktija && $currency === []) {
    return;
}
?>
<div class="info-strip">
  <div class="container info-strip__inner scrollbar-none">
    <?php foreach ($weather as $w): ?>
    <span class="info-strip__item"><?= e($w['icon'] ?? '') ?> <?= e($w['city'] ?? '') ?> <b><?= (int) ($w['temp'] ?? 0) ?>°</b></span>
    <?php endforeach; ?>
    <?php if (is_array($vaktija) && !empty($vaktija['nextName'])): ?>
    <span class="info-strip__item">🕌 <?= e($vaktija['nextName']) ?> <b><?= e($vaktija['nextTime'] ?? '') ?></b></span>
    <?php endif; ?>
    <?php foreach ($currency as $c): ?>
    <span class="info-strip__item"><?= e($c['code'] ?? '') ?> <b><?= e($c['rate'] ?? '') ?></b></span>
    <?php endforeach; ?>
  </div>
</div>
