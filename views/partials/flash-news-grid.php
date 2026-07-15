<?php
/** @var array $flashNews */
if (empty($flashNews)) {
    return;
}
?>
<section class="flash-grid" aria-label="Bitne vijesti">
  <div class="section-head flash-grid__head">
    <h2 class="flash-grid__title">
      <span class="flash-grid__pulse" aria-hidden="true"></span>
      Bitne vijesti
    </h2>
  </div>
  <div class="flash-grid__tiles">
    <?php foreach ($flashNews as $item): ?>
    <a href="/vijest/<?= e($item['slug']) ?>" class="flash-tile">
      <div class="flash-tile__media"<?php if (!empty($item['coverImage'])): ?> style="background-image:url('<?= e($item['coverImage']) ?>')"<?php endif; ?>>
        <?php if (!empty($item['coverImage'])): ?>
        <img class="flash-tile__img" src="<?= e($item['coverImage']) ?>" alt="" loading="lazy" width="200" height="200">
        <?php endif; ?>
        <div class="flash-tile__shade"></div>
        <?php if (!empty($item['isBreaking'])): ?>
        <span class="flash-tile__badge">Uživo</span>
        <?php endif; ?>
        <span class="flash-tile__label"><?= e($item['title']) ?></span>
      </div>
    </a>
    <?php endforeach; ?>
  </div>
</section>
