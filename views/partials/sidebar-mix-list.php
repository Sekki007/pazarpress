<?php /** @var array<int, array<string, mixed>> $latestSidebar */ ?>
<ul class="latest-list latest-list--text">
  <?php foreach ($latestSidebar as $article):
    $catName = (string) ($article['category']['name'] ?? $article['categoryName'] ?? '');
    $catUpper = mb_strtoupper($catName, 'UTF-8');
    $pub = !empty($article['publishedAt']) ? date('d.m.Y.', strtotime((string) $article['publishedAt'])) : '';
  ?>
  <li class="latest-list__item">
    <a href="/vijest/<?= e($article['slug']) ?>" class="latest-list__link">
      <span class="latest-list__title"><?= e($article['title']) ?></span>
      <?php if ($catUpper !== '' || $pub !== ''): ?>
      <span class="latest-list__meta">
        <?php if ($catUpper !== ''): ?><?= e($catUpper) ?><?php endif; ?>
        <?php if ($catUpper !== '' && $pub !== ''): ?> · <?php endif; ?>
        <?php if ($pub !== ''): ?><?= e($pub) ?><?php endif; ?>
      </span>
      <?php endif; ?>
    </a>
  </li>
  <?php endforeach; ?>
</ul>
