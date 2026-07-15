<?php
/** @var array $homeRestaurants */
$widgetCity = $city ?? 'NOVI_PAZAR';
$widgetLabel = $city ? city_label($city) : 'Novi Pazaru';
?>
<div class="section-head section-head--restaurants">
  <h2>Restorani u <?= e($widgetLabel) ?></h2>
  <a href="/restorani<?= $citySlug ? '?grad=' . e($citySlug) : '?grad=novi-pazar' ?>">Svi meniji →</a>
</div>
<div class="rst-widget-scroll scrollbar-none">
  <?php foreach ($homeRestaurants as $r): ?>
  <a href="/restorani/<?= e($r['slug']) ?>" class="rst-widget-card">
    <div class="rst-widget-card__img" style="background-image:url('<?= e(restaurant_cover_url($r['coverImage'] ?? null, $r)) ?>')"></div>
    <span class="rst-widget-card__name"><?= e($r['name']) ?></span>
    <?= restaurant_stars_html($r['avgRating']) ?>
  </a>
  <?php endforeach; ?>
</div>
<p class="rst-widget-cta"><a href="/moj-meni/registracija">Besplatno dodajte digitalni meni vašeg restorana →</a></p>
