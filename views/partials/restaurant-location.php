<?php
/** @var array $restaurant @var array|null $L */
$maps = restaurant_maps_urls($restaurant);
if (!$maps) {
    return;
}
$L = $L ?? [];
$t = static fn (string $k, string $fallback): string => $L[$k] ?? $fallback;
$phone = trim((string) ($restaurant['phone'] ?? ''));
$wa = preg_replace('/\D+/', '', (string) ($restaurant['whatsapp'] ?? ''));
?>
<section class="container rst-location" id="lokacija">
  <h2 class="rst-section-title"><?= e($t('location', 'Lokacija')) ?></h2>
  <div class="rst-location__grid">
    <div class="rst-location__map-wrap">
      <iframe
        class="rst-location__map"
        title="Mapa — <?= e($restaurant['name']) ?>"
        loading="lazy"
        referrerpolicy="no-referrer-when-downgrade"
        src="<?= e($maps['embed']) ?>"
        allowfullscreen
      ></iframe>
    </div>
    <div class="rst-location__info">
      <p class="rst-location__address">
        <span class="rst-location__pin" aria-hidden="true">📍</span>
        <?= e($restaurant['address']) ?><br>
        <span class="rst-location__city"><?= e(city_label($restaurant['city'])) ?></span>
      </p>
      <?php if ($today = restaurant_today_hours_label($restaurant['hours'] ?? [])): ?>
      <p class="rst-location__today"><strong><?= e($t('today', 'Danas')) ?>:</strong> <?= e($today) ?></p>
      <?php endif; ?>
      <div class="rst-location__nav-btns">
        <a href="<?= e($maps['google_dir']) ?>" class="rst-nav-btn rst-nav-btn--primary" target="_blank" rel="noopener noreferrer"><?= e($t('navigation', 'Navigacija')) ?></a>
        <a href="<?= e($maps['apple']) ?>" class="rst-nav-btn" target="_blank" rel="noopener noreferrer"><?= e($t('apple_maps', 'Apple Maps')) ?></a>
        <a href="<?= e($maps['google']) ?>" class="rst-nav-btn" target="_blank" rel="noopener noreferrer"><?= e($t('open_map', 'Mapa')) ?></a>
      </div>
      <?php if ($phone || $wa): ?>
      <div class="rst-location__contact">
        <?php if ($phone): ?>
        <a href="tel:<?= e(preg_replace('/\s+/', '', $phone)) ?>" class="rst-nav-btn">📞 <?= e($phone) ?></a>
        <?php endif; ?>
        <?php if ($wa): ?>
        <a href="https://wa.me/<?= e($wa) ?>" class="rst-nav-btn rst-nav-btn--wa" target="_blank" rel="noopener">WhatsApp</a>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
</section>
