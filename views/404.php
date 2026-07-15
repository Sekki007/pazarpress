<?php include __DIR__ . '/partials/header.php'; ?>
<main class="container not-found">
  <h1 class="not-found__code">404</h1>
  <p class="not-found__text">Stranica nije pronađena ili je uklonjena.</p>
  <a href="/" class="btn-primary">Početna stranica</a>

  <?php if (!empty($latest)): ?>
  <section class="not-found__latest">
    <h2>Najnovije vijesti</h2>
    <div class="news-list">
      <?php foreach ($latest as $i => $article): ?>
      <?php $variant = $i; include __DIR__ . '/partials/news-card.php'; ?>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>
</main>
<?php include __DIR__ . '/partials/footer.php'; ?>
