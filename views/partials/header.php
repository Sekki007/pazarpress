<?php
$path = current_path();
$activeCategory = null;
if (preg_match('#^/rubrika/([^/]+)$#', $path, $navMatch)) {
    $activeCategory = $navMatch[1];
}
?>
<header class="site-header" id="hdr">
  <div class="container site-header__inner">
    <a href="/" class="logo" aria-label="Pazar Press">
      <img class="logo__img" src="/assets/img/pazar-press-logo.png" alt="Pazar Press" width="200" height="58">
    </a>
    <nav class="site-nav hide-mobile">
      <a href="/" class="site-nav__link<?= $path === '/' ? ' site-nav__link--active' : '' ?>">Početna</a>
      <?php if (restaurants_enabled()): ?>
      <a href="/restorani" class="site-nav__link<?= str_starts_with($path, '/restorani') ? ' site-nav__link--active' : '' ?>">Restorani</a>
      <?php endif; ?>
      <?php foreach (CATEGORIES as $cat): ?>
      <a href="/rubrika/<?= e($cat['slug']) ?>" class="site-nav__link<?= $activeCategory === $cat['slug'] ? ' site-nav__link--active' : '' ?>"><?= e($cat['name']) ?></a>
      <?php endforeach; ?>
    </nav>
    <div class="site-header__actions">
      <button type="button" class="icon-btn" id="btn-search" aria-label="Pretraga">🔍</button>
      <button type="button" class="icon-btn hide-desktop" id="btn-menu" aria-label="Meni">☰</button>
      <button type="button" class="icon-btn" id="btn-theme" aria-label="Tema">🌙</button>
    </div>
  </div>
</header>
