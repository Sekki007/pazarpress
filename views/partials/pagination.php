<?php
/** @var array $pagination */
/** @var string $basePath e.g. /rubrika/vijesti */
if (($pagination['pages'] ?? 1) <= 1) {
    return;
}
$page = (int) $pagination['page'];
$pages = (int) $pagination['pages'];
$query = $queryParams ?? [];
?>
<nav class="pagination" aria-label="Stranice">
  <?php if ($page > 1):
    $q = array_merge($query, ['str' => $page - 1]);
    $prev = $basePath . '?' . http_build_query($q);
  ?>
  <a href="<?= e($prev) ?>" class="pagination__btn">← Prethodna</a>
  <?php endif; ?>
  <span class="pagination__info">Stranica <?= $page ?> / <?= $pages ?></span>
  <?php if ($page < $pages):
    $q = array_merge($query, ['str' => $page + 1]);
    $next = $basePath . '?' . http_build_query($q);
  ?>
  <a href="<?= e($next) ?>" class="pagination__btn">Sljedeća →</a>
  <?php endif; ?>
</nav>
