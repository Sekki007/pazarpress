<div class="info-strip">
  <div class="container info-strip__inner scrollbar-none">
    <?php foreach ($infoStrip['weather'] as $w): ?>
    <span class="info-strip__item"><?= e($w['icon']) ?> <?= e($w['city']) ?> <b><?= (int) $w['temp'] ?>°</b></span>
    <?php endforeach; ?>
    <span class="info-strip__item">🕌 <?= e($infoStrip['vaktija']['nextName']) ?> <b><?= e($infoStrip['vaktija']['nextTime']) ?></b></span>
    <?php foreach ($infoStrip['currency'] as $c): ?>
    <span class="info-strip__item"><?= e($c['code']) ?> <b><?= e($c['rate']) ?></b></span>
    <?php endforeach; ?>
  </div>
</div>
