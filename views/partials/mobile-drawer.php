<div class="drawer-backdrop" id="drawer-backdrop" hidden></div>
<aside class="mobile-drawer" id="mobile-drawer" aria-hidden="true" aria-label="Meni" inert>
  <div class="mobile-drawer__head">
    <a href="/" class="logo" aria-label="Pazar Press">
      <picture>
        <source type="image/webp" srcset="<?= e(asset_url('assets/img/pazar-press-logo.webp')) ?>">
        <img class="logo__img" src="<?= e(asset_url('assets/img/pazar-press-logo.png')) ?>" alt="Pazar Press" width="96" height="64" decoding="async">
      </picture>
    </a>
    <button type="button" class="icon-btn" id="drawer-close" aria-label="Zatvori meni">✕</button>
  </div>
  <nav class="mobile-drawer__nav">
    <a href="/" class="mobile-drawer__link">Početna</a>
    <?php if (restaurants_enabled()): ?>
    <a href="/restorani" class="mobile-drawer__link">Restorani / meniji</a>
    <?php endif; ?>
    <?php foreach (CATEGORIES as $cat): ?>
    <a href="/rubrika/<?= e($cat['slug']) ?>" class="mobile-drawer__link"><?= e($cat['name']) ?></a>
    <?php endforeach; ?>
    <a href="/sacuvano" class="mobile-drawer__link">Sačuvano</a>
    <a href="/pretraga" class="mobile-drawer__link">Pretraga</a>
    <a href="/video" class="mobile-drawer__link">Video</a>
  </nav>
</aside>
