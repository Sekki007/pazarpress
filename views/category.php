<?php include __DIR__ . '/partials/info-strip.php'; ?>
<?php include __DIR__ . '/partials/header.php'; ?>

<div class="city-filter">
  <div class="container city-filter__inner">
    <?php
    $basePath = '/rubrika/' . $category['slug'];
    $queryParams = [];
    ?>
    <a href="<?= e($basePath) ?>" class="city-chip<?= !$citySlug ? ' city-chip--active' : '' ?>">Svi gradovi</a>
    <?php foreach (CITIES_ORDER as $c):
      $slug = city_slug($c);
      $q = ['grad' => $slug];
    ?>
    <a href="<?= e($basePath . '?grad=' . $slug) ?>" class="city-chip<?= $citySlug === $slug ? ' city-chip--active' : '' ?>"><?= e(city_label($c)) ?></a>
    <?php endforeach; ?>
  </div>
</div>

<main class="container category-page">
  <?php include __DIR__ . '/partials/breadcrumbs.php'; ?>
  <header class="category-page__head">
    <h1 class="category-page__title"><?= e($category['name']) ?></h1>
    <p class="category-page__count"><?= (int) $pagination['total'] ?> <?= $pagination['total'] === 1 ? 'članak' : 'članaka' ?></p>
  </header>

  <?php if (!$articles): ?>
  <p class="category-page__empty">Nema objavljenih vijesti u ovoj rubrici<?= $city ? ' za ' . city_label($city) : '' ?>.</p>
  <?php else: ?>
  <div class="news-feed-panel">
  <div class="news-list news-list--feed">
    <?php foreach ($articles as $i => $article): ?>
    <?php $variant = $i; include __DIR__ . '/partials/news-card.php'; ?>
    <?php endforeach; ?>
  </div>
  </div>
  <?php
  $basePath = '/rubrika/' . $category['slug'];
  $queryParams = $citySlug ? ['grad' => $citySlug] : [];
  include __DIR__ . '/partials/pagination.php';
  ?>
  <?php endif; ?>
</main>

<?php include __DIR__ . '/partials/footer.php'; ?>
