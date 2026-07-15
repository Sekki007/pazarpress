<div class="search-overlay" id="search-overlay" aria-hidden="true" inert>
  <div class="search-overlay__panel" role="dialog" aria-modal="true" aria-label="Pretraga">
    <div class="search-overlay__bar">
      <input
        type="search"
        id="search-input"
        class="search-overlay__input"
        placeholder="Pretraži vijesti i teme…"
        autocomplete="off"
      >
      <button type="button" class="icon-btn" id="search-close" aria-label="Zatvori pretragu">✕</button>
    </div>
    <p class="search-overlay__label">Popularne pretrage</p>
    <div class="search-overlay__hints">
      <?php foreach (['FK Novi Pazar', 'Pešter', 'budžet', 'Limska regata', 'univerzitet', 'putevi'] as $hint): ?>
      <button type="button" class="search-hint" data-search-hint><?= e($hint) ?></button>
      <?php endforeach; ?>
    </div>
  </div>
</div>
