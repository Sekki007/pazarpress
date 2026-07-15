<?php /** @var array<int, array<string, mixed>> $latestSidebar */ ?>
<ul class="mix-list">
  <?php foreach ($latestSidebar as $article):
    $catName = (string) ($article['category']['name'] ?? $article['categoryName'] ?? '');
    $pub = !empty($article['publishedAt']) ? format_relative((string) $article['publishedAt']) : '';
  ?>
  <li class="mix-list__item">
    <a href="/vijest/<?= e($article['slug']) ?>" class="mix-list__link">
      <?php if ($catName !== ''): ?>
      <span class="mix-list__cat"><?= e($catName) ?></span>
      <?php endif; ?>
      <span class="mix-list__title"><?= e($article['title']) ?></span>
      <?php if ($pub !== ''): ?>
      <time class="mix-list__time" datetime="<?= e($article['publishedAt'] ?? '') ?>"><?= e($pub) ?></time>
      <?php endif; ?>
    </a>
  </li>
  <?php endforeach; ?>
</ul>
