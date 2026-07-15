<nav class="bottom-nav hide-desktop" aria-label="Glavna navigacija">
  <a href="/" class="bottom-nav__item<?= ($navActive ?? '') === 'home' ? ' bottom-nav__item--active' : '' ?>">
    <span class="bottom-nav__icon" aria-hidden="true">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 10.5L12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1v-9.5z"/></svg>
    </span>
    <span class="bottom-nav__label">Početna</span>
  </a>
  <a href="/#najnovije" class="bottom-nav__item">
    <span class="bottom-nav__icon" aria-hidden="true">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 5h16v14H4z"/><path d="M8 9h8M8 13h5"/></svg>
    </span>
    <span class="bottom-nav__label">Vijesti</span>
  </a>
  <a href="/sacuvano" class="bottom-nav__item<?= ($navActive ?? '') === 'saved' ? ' bottom-nav__item--active' : '' ?>">
    <span class="bottom-nav__icon" aria-hidden="true">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21l-7-4-7 4V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>
    </span>
    <span class="bottom-nav__label">Sačuvano</span>
  </a>
  <button type="button" class="bottom-nav__item" id="btn-search-m">
    <span class="bottom-nav__icon" aria-hidden="true">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
    </span>
    <span class="bottom-nav__label">Pretraga</span>
  </button>
  <button type="button" class="bottom-nav__item" id="btn-menu-bottom">
    <span class="bottom-nav__icon" aria-hidden="true">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
    </span>
    <span class="bottom-nav__label">Meni</span>
  </button>
</nav>
