<?php include __DIR__ . '/partials/info-strip.php'; ?>
<?php include __DIR__ . '/partials/header.php'; ?>

<main class="container category-page">
  <?php include __DIR__ . '/partials/breadcrumbs.php'; ?>
  <header class="category-page__head">
    <h1 class="category-page__title">#<?= e($tag['name']) ?></h1>
    <p class="category-page__count"><?= (int) $pagination['total'] ?> <?= $pagination['total'] === 1 ? 'članak' : 'članaka' ?></p>
  </header>

  <?php if (!$articles): ?>
  <p class="category-page__empty">Nema objavljenih vijesti sa ovim tagom.</p>
  <?php else: ?>
  <div class="news-list">
    <?php foreach ($articles as $i => $article): ?>
    <?php $variant = $i; include __DIR__ . '/partials/news-card.php'; ?>
    <?php endforeach; ?>
  </div>
  <?php
  $basePath = '/tag/' . $tag['slug'];
  $queryParams = [];
  include __DIR__ . '/partials/pagination.php';
  ?>
  <?php endif; ?>
</main>

<?php include __DIR__ . '/partials/footer.php'; ?>
