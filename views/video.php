<?php include __DIR__ . '/partials/info-strip.php'; ?>
<?php include __DIR__ . '/partials/header.php'; ?>

<main class="container video-page">
  <?php include __DIR__ . '/partials/breadcrumbs.php'; ?>
  <header class="category-page__head">
    <h1 class="category-page__title">Video</h1>
    <p class="category-page__count">Reportaže i prilozi sa Pazar Press</p>
  </header>

  <?php if (!$videos): ?>
  <p class="category-page__empty">Trenutno nema video priloga.</p>
  <?php else: ?>
  <div class="video-grid">
    <?php foreach ($videos as $v):
      $thumb = youtube_thumb($v['youtubeId'] ?? null);
      $href = !empty($v['youtubeId']) ? 'https://www.youtube.com/watch?v=' . rawurlencode($v['youtubeId']) : '#';
    ?>
    <a href="<?= e($href) ?>" class="video-grid__card" target="_blank" rel="noopener noreferrer">
      <div class="video-grid__thumb">
        <?php if ($thumb): ?><img src="<?= e($thumb) ?>" alt="" width="320" height="180" loading="lazy" decoding="async"><?php endif; ?>
        <span class="video-grid__play" aria-hidden="true">▶</span>
      </div>
      <h2 class="video-grid__title"><?= e($v['title']) ?></h2>
      <?php if (!empty($v['duration'])): ?><span class="video-grid__dur"><?= e($v['duration']) ?></span><?php endif; ?>
    </a>
    <?php endforeach; ?>
  </div>
  <?php
  $basePath = '/video';
  $queryParams = [];
  include __DIR__ . '/partials/pagination.php';
  ?>
  <?php endif; ?>
</main>

<?php include __DIR__ . '/partials/footer.php'; ?>
