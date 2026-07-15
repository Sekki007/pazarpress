<?php
$path = current_path();
$activeCategory = null;
if (preg_match('#^/rubrika/([^/]+)$#', $path, $navMatch)) {
    $activeCategory = $navMatch[1];
}
?>
<header class="site-header" id="hdr">
  <div class="container site-header__inner">
    <button type="button" class="icon-btn site-header__menu hide-desktop" id="btn-menu" aria-label="Meni">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
    </button>
    <a href="/" class="logo" aria-label="Pazar Press">
      <picture>
        <source type="image/webp" srcset="<?= e(asset_url('assets/img/pazar-press-logo.webp')) ?>">
        <img class="logo__img" src="<?= e(asset_url('assets/img/pazar-press-logo.png')) ?>" alt="Pazar Press" width="96" height="64" decoding="async" fetchpriority="high">
      </picture>
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
      <button type="button" class="icon-btn" id="btn-search" aria-label="Pretraga">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
      </button>
      <button type="button" class="icon-btn" id="btn-theme" aria-label="Tema">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 14.5A8.5 8.5 0 1 1 9.5 3 7 7 0 0 0 21 14.5z"/></svg>
      </button>
    </div>
  </div>
</header>
