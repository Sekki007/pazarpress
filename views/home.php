<?php include __DIR__ . '/partials/info-strip.php'; ?>
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

<div class="city-filter">
  <div class="container city-filter__inner">
    <a href="/" class="city-chip<?= !$citySlug ? ' city-chip--active' : '' ?>">Svi gradovi</a>
    <?php foreach (CITIES_ORDER as $c): ?>
    <a href="/?grad=<?= e(city_slug($c)) ?>" class="city-chip<?= $citySlug === city_slug($c) ? ' city-chip--active' : '' ?>"><?= e(city_label($c)) ?></a>
    <?php endforeach; ?>
  </div>
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
            'width' => 800,
            'height' => 450,
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

    <?php if (!empty($latestSidebar)): include __DIR__ . '/partials/latest-news-mobile.php'; endif; ?>

    <?php $slot = 'ad_home_html'; include __DIR__ . '/partials/ad-slot.php'; ?>

    <section id="najnovije">
      <div class="section-head">
        <h2>Najnovije</h2>
        <a href="/rubrika/vijesti">Sve vijesti →</a>
      </div>
      <div class="news-feed-panel">
      <div id="news-feed" class="news-list news-list--feed" data-cursor="<?= e($feedCursor ?? '') ?>" data-grad="<?= e($citySlug ?? '') ?>">
        <?php foreach ($feed as $i => $article): ?>
        <?php $variant = $i; include __DIR__ . '/partials/news-card.php'; ?>
        <?php endforeach; ?>
      </div>
      </div>
      <button type="button" class="btn-load-more" id="btn-load-more">Učitaj još</button>
    </section>

    <div class="section-head"><h2>Video</h2><a href="/video">Svi prilozi →</a></div>
    <div class="video-strip">
      <?php foreach ($videos as $v):
        $vThumb = youtube_thumb($v['youtubeId'] ?? null);
      ?>
      <a href="<?= $v['youtubeId'] ? 'https://www.youtube.com/watch?v=' . e($v['youtubeId']) : '#' ?>" class="video-card" target="_blank" rel="noopener">
        <div class="video-card__thumb"><?php if ($vThumb): ?><img src="<?= e($vThumb) ?>" alt="" width="320" height="180" loading="lazy" decoding="async"><?php endif; ?></div>
        <span class="video-card__title"><?= e($v['title']) ?></span>
        <?php if ($v['duration']): ?><span class="video-card__dur"><?= e($v['duration']) ?></span><?php endif; ?>
      </a>
      <?php endforeach; ?>
    </div>

    <div class="section-head"><h2>Sport</h2><a href="/rubrika/sport">Sve →</a></div>
    <div class="news-feed-panel">
    <div class="grid-2 news-list--feed">
      <?php foreach ($sport as $i => $article): ?>
      <?php $variant = $i + 2; include __DIR__ . '/partials/news-card.php'; ?>
      <?php endforeach; ?>
    </div>
    </div>

    <?php if (!empty($homeRestaurants)): include __DIR__ . '/partials/restaurants-widget.php'; endif; ?>

    <div class="section-head section-head--diaspora"><h2>Dijaspora</h2><a href="/rubrika/dijaspora">Sve →</a></div>
    <div class="news-feed-panel">
    <div class="grid-2 news-list--feed">
      <?php foreach ($diaspora as $i => $article): ?>
      <?php $variant = $i + 1; include __DIR__ . '/partials/news-card.php'; ?>
      <?php endforeach; ?>
    </div>
    </div>
  </div>

  <aside class="sidebar">
    <?php if (!empty($latestSidebar)): include __DIR__ . '/partials/latest-news-sidebar.php'; endif; ?>
    <?php if ($poll): include __DIR__ . '/partials/poll-widget.php'; endif; ?>
    <div class="widget widget--fb">
      <?php include __DIR__ . '/partials/facebook-like-cta.php'; ?>
    </div>
    <?php $slot = 'ad_sidebar_html'; include __DIR__ . '/partials/ad-slot.php'; ?>
    <div class="widget">
      <h3 class="widget__title">Newsletter</h3>
      <form class="newsletter-form" id="newsletter-form">
        <input type="email" name="email" placeholder="Vaš email" required class="input">
        <button type="submit" class="btn-primary">Prijavi se</button>
      </form>
    </div>
  </aside>
</main>

<?php $navActive = 'home'; include __DIR__ . '/partials/footer.php'; ?>

<?php $extraScripts = ['/assets/js/news-feed.js']; ?>
