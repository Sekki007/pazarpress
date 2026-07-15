<?php
/** @var array $article */
/** @var int $variant */
$variant = $variant ?? 0;
$gradients = ['thumb-a', 'thumb-b', 'thumb-c', 'thumb-d'];
$thumb = $gradients[$variant % 4];
$cover = $article['coverImage'] ?? null;
?>
<article class="news-card">
  <a href="/vijest/<?= e($article['slug']) ?>" class="news-card__thumb-link">
    <?php if ($cover): ?>
    <?= responsive_image($cover, $article['title'] ?? '', [
        'class' => 'news-card__thumb news-card__thumb--img',
        'loading' => 'lazy',
        'sizes' => '(max-width:640px) 72px, 110px',
        'src_variant' => 'sm',
    ]) ?>
    <?php else: ?>
    <span class="news-card__thumb <?= $thumb ?>"></span>
    <?php endif; ?>
  </a>
  <div class="news-card__body">
    <a href="/vijest/<?= e($article['slug']) ?>">
      <h3 class="news-card__title"><?= e($article['title']) ?></h3>
    </a>
    <div class="news-card__meta">
      <span class="chip"><?= e($article['category']['name'] ?? $article['categoryName'] ?? '') ?></span>
      <span class="news-card__meta-sep" aria-hidden="true">·</span>
      <time class="news-card__meta-time" datetime="<?= e($article['publishedAt'] ?? '') ?>"><?= e(format_relative($article['publishedAt'] ?? null)) ?></time>
    </div>
  </div>
</article>
