<?php if (empty($breadcrumbs)): return; endif; ?>
<nav class="breadcrumbs" aria-label="Navigacija">
  <ol class="breadcrumbs__list">
    <?php foreach ($breadcrumbs as $i => $crumb): ?>
    <li class="breadcrumbs__item">
      <?php if (!empty($crumb['url']) && $i < count($breadcrumbs) - 1): ?>
      <a href="<?= e($crumb['url']) ?>"><?= e($crumb['label']) ?></a>
      <?php else: ?>
      <span aria-current="page"><?= e($crumb['label']) ?></span>
      <?php endif; ?>
    </li>
    <?php endforeach; ?>
  </ol>
</nav>
