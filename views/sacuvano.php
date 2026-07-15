<?php include __DIR__ . '/partials/header.php'; ?>

<main class="container saved-page">
  <header class="saved-page__head">
    <h1 class="saved-page__title">Za čitanje</h1>
    <p class="saved-page__lead">Sačuvane vijesti i istorija — samo na ovom uređaju.</p>
  </header>

  <div class="saved-tabs" role="tablist">
    <button type="button" class="saved-tabs__btn is-active" data-tab="saved" role="tab" aria-selected="true">Sačuvano</button>
    <button type="button" class="saved-tabs__btn" data-tab="history" role="tab" aria-selected="false">Istorija</button>
  </div>

  <section id="saved-panel" class="saved-panel" data-panel="saved" role="tabpanel">
    <div id="saved-list" class="news-list"></div>
    <p id="saved-empty" class="saved-page__empty" hidden>Još nema sačuvanih vijesti. Na članku dodirnite „Za kasnije”.</p>
  </section>

  <section id="history-panel" class="saved-panel" data-panel="history" role="tabpanel" hidden>
    <div id="history-list" class="news-list"></div>
    <p id="history-empty" class="saved-page__empty" hidden>Istorija čitanja je prazna.</p>
  </section>
</main>

<?php $navActive = 'saved'; include __DIR__ . '/partials/footer.php'; ?>

<?php $extraScripts = ['/assets/js/saved-page.js']; ?>
