<nav class="bottom-nav hide-desktop" aria-label="Glavna navigacija">
  <a href="/" class="bottom-nav__item<?= ($navActive ?? '') === 'home' ? ' bottom-nav__item--active' : '' ?>">
    <span class="bottom-nav__icon" aria-hidden="true">⌂</span>
    <span class="bottom-nav__label">Početna</span>
  </a>
  <a href="/#najnovije" class="bottom-nav__item">
    <span class="bottom-nav__icon" aria-hidden="true">📰</span>
    <span class="bottom-nav__label">Vijesti</span>
  </a>
  <button type="button" class="bottom-nav__item" id="btn-search-m">
    <span class="bottom-nav__icon" aria-hidden="true">🔍</span>
    <span class="bottom-nav__label">Pretraga</span>
  </button>
  <button type="button" class="bottom-nav__item" id="btn-menu-bottom">
    <span class="bottom-nav__icon" aria-hidden="true">☰</span>
    <span class="bottom-nav__label">Meni</span>
  </button>
</nav>
