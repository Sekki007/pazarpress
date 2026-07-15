<?php /** @var array $topRead */ ?>
<section class="top-read-mobile hide-desktop" aria-label="Najčitanije danas">
  <div class="top-read-mobile__head">
    <h2 class="top-read-mobile__title">Najčitanije 24h</h2>
    <a href="/rubrika/vijesti" class="top-read-mobile__more">Sve →</a>
  </div>
  <ol class="top-read-mobile__list">
    <?php foreach (array_slice($topRead, 0, 4) as $i => $item): ?>
    <li>
      <a href="/vijest/<?= e($item['slug']) ?>" class="top-read-mobile__row">
        <span class="top-read-mobile__n"><?= $i + 1 ?></span>
        <span class="top-read-mobile__text"><?= e($item['title']) ?></span>
      </a>
    </li>
    <?php endforeach; ?>
  </ol>
</section>
