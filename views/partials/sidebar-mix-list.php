<?php /** @var array<int, array<string, mixed>> $latestSidebar */ ?>
<ul class="mix-list">
  <?php foreach ($latestSidebar as $i => $article):
    $catName = (string) ($article['category']['name'] ?? $article['categoryName'] ?? '');
    $pub = !empty($article['publishedAt']) ? format_relative((string) $article['publishedAt']) : '';
  ?>
  <li class="mix-list__item">
    <a href="/vijest/<?= e($article['slug']) ?>" class="mix-list__link">
      <span class="mix-list__num" aria-hidden="true"><?= $i + 1 ?></span>
      <span class="mix-list__body">
        <span class="mix-list__title"><?= e($article['title']) ?></span>
        <?php if ($catName !== '' || $pub !== ''): ?>
        <span class="mix-list__meta">
          <?php if ($catName !== ''): ?><?= e($catName) ?><?php endif; ?>
          <?php if ($catName !== '' && $pub !== ''): ?> · <?php endif; ?>
          <?php if ($pub !== ''): ?><?= e($pub) ?><?php endif; ?>
        </span>
        <?php endif; ?>
      </span>
    </a>
  </li>
  <?php endforeach; ?>
</ul>
