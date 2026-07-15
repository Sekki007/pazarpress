<?php
/** @var array $restaurants @var string|null $citySlug @var string|null $city */
?>
<header class="rst-topbar">
  <a href="/" class="rst-topbar__btn" aria-label="Početna">←</a>
  <h1 class="rst-topbar__title">Restorani</h1>
  <span class="rst-topbar__btn" aria-hidden="true"></span>
</header>

<div class="rst-dir-intro">
  <p class="rst-dir-intro__sub">Digitalni meniji u Sandžaku</p>
  <a href="/moj-meni/registracija" class="rst-btn rst-btn--primary rst-btn--block">+ Dodaj restoran besplatno</a>
</div>

<nav class="rst-city-scroll" aria-label="Gradovi">
  <a href="/restorani" class="rst-city-chip<?= !$citySlug ? ' rst-city-chip--on' : '' ?>">Svi</a>
  <?php foreach (CITIES_ORDER as $c): ?>
  <a href="/restorani?grad=<?= e(city_slug($c)) ?>" class="rst-city-chip<?= $citySlug === city_slug($c) ? ' rst-city-chip--on' : '' ?>"><?= e(city_label($c)) ?></a>
  <?php endforeach; ?>
</nav>

<main class="rst-dir-list">
  <?php if (!$restaurants): ?>
  <p class="rst-dir-empty">Još nema restorana<?= $city ? ' u ' . e(city_label($city)) : '' ?>. <a href="/moj-meni/registracija">Registrujte se!</a></p>
  <?php else: ?>
  <?php foreach ($restaurants as $r): ?>
  <a href="/restorani/<?= e($r['slug']) ?>" class="rst-dir-card">
    <div class="rst-dir-card__cover" style="background-image:url('<?= e(restaurant_cover_url($r['coverImage'] ?? null, $r)) ?>')"></div>
    <div class="rst-dir-card__body">
      <img class="rst-dir-card__logo" src="<?= e(restaurant_logo_url($r['logoImage'] ?? null)) ?>" alt="" width="48" height="48">
      <div class="rst-dir-card__info">
        <h2 class="rst-dir-card__name"><?= e($r['name']) ?></h2>
        <p class="rst-dir-card__meta"><?= e(city_label($r['city'])) ?></p>
        <?= restaurant_stars_html($r['avgRating']) ?>
      </div>
      <span class="rst-dir-card__arrow" aria-hidden="true">›</span>
    </div>
  </a>
  <?php endforeach; ?>
  <?php endif; ?>
</main>

<footer class="rst-footer">
  <a href="/">Sandžak.net</a>
</footer>
