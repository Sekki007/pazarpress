<?php include __DIR__ . '/partials/header.php'; ?>

<?php if ($breaking): ?>
<div class="ticker">
  <span class="ticker__badge">
    <span class="ticker__dot" aria-hidden="true"></span>
    Uživo
  </span>
  <div class="ticker__viewport">
    <div class="ticker__track">
      <?php foreach (array_merge($breaking, $breaking) as $item): ?>
      <a href="/vijest/<?= e($item['slug']) ?>" class="ticker__item"><?= e($item['title']) ?></a>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php endif; ?>

<div class="ptr-indicator" id="ptr-indicator" aria-live="polite" hidden>
  <span class="ptr-indicator__spinner" aria-hidden="true"></span>
  <span class="ptr-indicator__text">Osveženo upravo</span>
</div>

<main class="container main-grid">
  <div class="main-col">
    <?php if ($featured): ?>
    <a href="/vijest/<?= e($featured['slug']) ?>" class="hero">
      <div class="hero__media">
        <?php if (!empty($featured['coverImage'])): ?>
        <?= responsive_image($featured['coverImage'], $featured['title'], [
            'class' => 'hero__img',
            'loading' => 'eager',
            'fetchpriority' => 'high',
            'width' => 720,
            'height' => 405,
            'sizes' => '(max-width:640px) 100vw, (max-width:1024px) 70vw, 720px',
            'src_variant' => 'md',
        ]) ?>
        <?php endif; ?>
      </div>
      <div class="hero__overlay">
        <span class="hero__badge">Izdvojeno</span>
        <h1 class="hero__title"><?= e($featured['title']) ?></h1>
        <p class="hero__lead"><?= e($featured['lead']) ?></p>
        <div class="hero__meta">
          <strong><?= e($featured['authorName']) ?></strong>
          <span><?= e(format_relative($featured['publishedAt'])) ?></span>
          <span>· <?= (int) $featured['readingTimeMin'] ?> min čitanja</span>
        </div>
      </div>
    </a>
    <?php endif; ?>

    <section id="najnovije">
      <div class="section-head">
        <h2>Najnovije</h2>
        <a href="/rubrika/vijesti">Sve →</a>
      </div>
      <div class="news-feed-panel">
      <div id="news-feed" class="news-list news-list--feed" data-cursor="<?= e($feedCursor ?? '') ?>">
        <?php foreach ($feed as $i => $article): ?>
        <?php $variant = $i; include __DIR__ . '/partials/news-card.php'; ?>
        <?php endforeach; ?>
      </div>
      </div>
      <button type="button" class="btn-load-more" id="btn-load-more">Učitaj još</button>
    </section>

    <?php if ($poll): ?>
    <div class="home-poll-mobile hide-desktop">
      <?php include __DIR__ . '/partials/poll-widget.php'; ?>
    </div>
    <?php endif; ?>

    <?php if ($videos): ?>
    <div class="section-head"><h2>Video</h2><a href="/video">Svi →</a></div>
    <div class="video-strip">
      <?php foreach ($videos as $v):
        $vThumb = youtube_thumb($v['youtubeId'] ?? null);
      ?>
      <a href="<?= $v['youtubeId'] ? 'https://www.youtube.com/watch?v=' . e($v['youtubeId']) : '#' ?>" class="video-card" target="_blank" rel="noopener">
        <div class="video-card__thumb"><?php if ($vThumb): ?><img src="<?= e($vThumb) ?>" alt="" width="320" height="180" loading="lazy" decoding="async"><?php endif; ?></div>
        <span class="video-card__title"><?= e($v['title']) ?></span>
        <?php if (!empty($v['duration'])): ?><span class="video-card__dur"><?= e($v['duration']) ?></span><?php endif; ?>
      </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if ($sport): ?>
    <div class="section-head"><h2>Sport</h2><a href="/rubrika/sport">Sve →</a></div>
    <div class="news-feed-panel">
    <div class="grid-2 news-list--feed">
      <?php foreach ($sport as $i => $article): ?>
      <?php $variant = $i + 2; include __DIR__ . '/partials/news-card.php'; ?>
      <?php endforeach; ?>
    </div>
    </div>
    <?php endif; ?>

    <?php if ($diaspora): ?>
    <div class="section-head section-head--diaspora"><h2>Dijaspora</h2><a href="/rubrika/dijaspora">Sve →</a></div>
    <div class="news-feed-panel">
    <div class="grid-2 news-list--feed">
      <?php foreach ($diaspora as $i => $article): ?>
      <?php $variant = $i + 1; include __DIR__ . '/partials/news-card.php'; ?>
      <?php endforeach; ?>
    </div>
    </div>
    <?php endif; ?>
  </div>

  <aside class="sidebar hide-mobile">
    <?php if (!empty($latestSidebar)): include __DIR__ . '/partials/latest-news-sidebar.php'; endif; ?>
    <?php if ($poll): include __DIR__ . '/partials/poll-widget.php'; endif; ?>
    <div class="widget widget--fb">
      <?php include __DIR__ . '/partials/facebook-like-cta.php'; ?>
    </div>
    <div class="widget widget--newsletter">
      <h3 class="widget__title">Newsletter</h3>
      <p style="margin:0 0 12px;font-size:.82rem;color:#f0c8cf">Najvažnije vesti jednom dnevno.</p>
      <form class="newsletter-form" id="newsletter-form">
        <input type="email" name="email" placeholder="Vaš email" required class="input">
        <button type="submit" class="btn-primary">Prijavi se</button>
      </form>
    </div>
  </aside>
</main>

<?php $navActive = 'home'; include __DIR__ . '/partials/footer.php'; ?>

<?php $extraScripts = ['/assets/js/news-feed.js']; ?>
