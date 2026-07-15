<footer class="site-footer">
  <div class="container site-footer__grid">
    <div class="site-footer__col site-footer__col--brand">
      <p class="site-footer__brand">Pazar Press</p>
      <p class="site-footer__text"><?= e(Settings::get('site_tagline')) ?></p>
      <p class="site-footer__rss">
        <a href="/feed.xml">RSS</a>
        <span class="site-footer__rss-sep">·</span>
        <a href="/video">Video</a>
        <span class="site-footer__rss-sep">·</span>
        <a href="/pretraga">Pretraga</a>
      </p>
    </div>
    <nav class="site-footer__nav" aria-label="Rubrike">
      <h2 class="site-footer__heading">Rubrike</h2>
      <ul class="site-footer__links site-footer__links--grid">
        <?php foreach (CATEGORIES as $cat): ?>
        <li>
          <a href="<?= e($cat['slug'] === 'video' ? '/video' : '/rubrika/' . $cat['slug']) ?>">
            <?= e($cat['name']) ?>
          </a>
        </li>
        <?php endforeach; ?>
      </ul>
    </nav>
    <nav class="site-footer__nav site-footer__nav--utility" aria-label="Korisno">
      <h2 class="site-footer__heading">Korisno</h2>
      <ul class="site-footer__links">
        <li><a href="/">Početna</a></li>
        <li><a href="/pretraga">Pretraga</a></li>
        <li><a href="/video">Video</a></li>
        <li><a href="/feed.xml">RSS</a></li>
      </ul>
    </nav>
  </div>
  <div class="container">
    <p class="site-footer__copy">&copy; <?= date('Y') ?> Pazar Press</p>
  </div>
</footer>
<?php include __DIR__ . '/bottom-nav.php'; ?>
