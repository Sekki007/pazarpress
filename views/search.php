<?php include __DIR__ . '/partials/header.php'; ?>

<main class="container search-page">
  <h1 class="search-page__title">Pretraga</h1>

  <form class="search-page__form" action="/pretraga" method="get" role="search">
    <input
      type="search"
      name="q"
      value="<?= e($q) ?>"
      class="search-page__input"
      placeholder="Pretraži vijesti…"
      autofocus
      required
    >
    <button type="submit" class="btn-primary">Traži</button>
  </form>

  <?php if ($q === ''): ?>
  <p class="search-page__empty">Unesite pojam za pretragu ili koristite lupu u meniju.</p>
  <?php elseif (!$results): ?>
  <p class="search-page__empty">Nema rezultata za „<?= e($q) ?>”. Pokušajte drugi pojam.</p>
  <?php else: ?>
  <p class="search-page__count"><?= count($results) ?> <?= count($results) === 1 ? 'rezultat' : 'rezultata' ?> za „<?= e($q) ?>”</p>
  <div class="news-feed-panel">
  <div class="news-list news-list--feed">
    <?php foreach ($results as $i => $article): ?>
    <?php $variant = $i; include __DIR__ . '/partials/news-card.php'; ?>
    <?php endforeach; ?>
  </div>
  </div>
  <?php endif; ?>
</main>

<?php include __DIR__ . '/partials/footer.php'; ?>
