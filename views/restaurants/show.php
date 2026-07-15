<?php
/** @var array $restaurant @var array $menu @var array $reviews @var string $canonical @var string $menuLang @var array $menuLangs @var array $L */
$r = $restaurant;
$days = ['pon' => 'Pon', 'uto' => 'Uto', 'sri' => 'Sri', 'cet' => 'Čet', 'pet' => 'Pet', 'sub' => 'Sub', 'ned' => 'Ned'];
$wa = preg_replace('/\D+/', '', (string) ($r['whatsapp'] ?? ''));
$phone = trim((string) ($r['phone'] ?? ''));
$maps = restaurant_maps_urls($r);
$menuItemCount = 0;
foreach ($menu as $cat) {
    $menuItemCount += count($cat['items'] ?? []);
}
$langLabels = ['bs' => 'BS', 'en' => 'EN', 'tr' => 'TR', 'de' => 'DE'];
$showLangBar = count($menuLangs) > 1;
$todayHours = restaurant_today_hours_label($r['hours'] ?? []);
$dayKeys = ['ned', 'pon', 'uto', 'sri', 'cet', 'pet', 'sub'];
$todayKey = $dayKeys[(int) date('w')];
?>
<header class="rst-topbar">
  <a href="/restorani" class="rst-topbar__btn" aria-label="Nazad">←</a>
  <h1 class="rst-topbar__title" id="rst-topbar-title" data-restaurant-name="<?= e($r['name']) ?>"><?= e($r['name']) ?></h1>
  <button type="button" class="rst-topbar__btn" id="btn-theme-rst" aria-label="Tema">🌙</button>
</header>

<div class="rst-screens" id="rst-screens" data-active="menu">

  <!-- Ekran 1: samo meni (default — kao QR meni servisi) -->
  <div class="rst-screen rst-screen--active" id="rst-screen-menu" data-screen="menu">
    <?php if ($menu || $showLangBar): ?>
    <div class="rst-sticky-top" id="rst-sticky-top">
      <?php if ($showLangBar): ?>
      <div class="rst-lang-bar" role="navigation" aria-label="<?= e($L['lang_label']) ?>">
        <div class="rst-lang-bar__pills">
          <?php foreach ($menuLangs as $code): ?>
          <a href="?lang=<?= e($code) ?>"
             class="rst-lang-bar__pill<?= $menuLang === $code ? ' rst-lang-bar__pill--active' : '' ?>"
             data-lang="<?= e($code) ?>"
             lang="<?= e($code) ?>"><?= e($langLabels[$code] ?? strtoupper($code)) ?></a>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>
      <?php if ($menu): ?>
      <nav class="rst-cat-nav" id="rst-cat-nav" aria-label="<?= e($L['menu']) ?>">
        <div class="rst-cat-nav__inner">
          <a href="#cat-top" class="rst-cat-nav__link rst-cat-nav__link--all" data-cat-jump="cat-top"><?= e($L['all']) ?></a>
          <?php foreach ($menu as $cat): if (empty($cat['items'])) continue; ?>
          <a href="#cat-<?= e($cat['id']) ?>" class="rst-cat-nav__link" data-cat-jump="cat-<?= e($cat['id']) ?>"><?= e($cat['name']) ?></a>
          <?php endforeach; ?>
        </div>
      </nav>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <main class="rst-menu" id="meni">
      <div id="cat-top"></div>
      <?php if (!$menu): ?>
      <p class="rst-menu__empty"><?= e($L['menu_empty']) ?></p>
      <?php endif; ?>
      <?php foreach ($menu as $cat): if (empty($cat['items'])) continue; ?>
      <section class="rst-menu__section" id="cat-<?= e($cat['id']) ?>">
        <h2 class="rst-menu__cat"><?= e($cat['name']) ?></h2>
        <div class="rst-menu__list">
          <?php foreach ($cat['items'] as $item): ?>
          <?php $hasImg = !empty($item['image']); ?>
          <article class="rst-dish<?= !$item['isAvailable'] ? ' rst-dish--off' : '' ?><?= $hasImg ? ' rst-dish--img' : '' ?>">
            <?php if ($hasImg): ?>
            <img class="rst-dish__thumb" src="<?= e($item['image']) ?>" alt="" loading="lazy" width="64" height="64">
            <?php endif; ?>
            <div class="rst-dish__content">
              <div class="rst-dish__row">
                <h3 class="rst-dish__name"><?= e($item['name']) ?></h3>
                <span class="rst-dish__price"><?= e(RestaurantService::formatPrice($item['price'], $item['priceLabel'], $item['currency'])) ?></span>
              </div>
              <?php if ($item['description']): ?><p class="rst-dish__desc"><?= e($item['description']) ?></p><?php endif; ?>
              <?php if (!empty($item['tags'])): ?>
              <div class="rst-dish__tags">
                <?php foreach ($item['tags'] as $tag): ?><span class="rst-chip"><?= e($tag) ?></span><?php endforeach; ?>
              </div>
              <?php endif; ?>
              <?php if (!$item['isAvailable']): ?><span class="rst-dish__badge"><?= e($L['unavailable']) ?></span><?php endif; ?>
            </div>
          </article>
          <?php endforeach; ?>
        </div>
      </section>
      <?php endforeach; ?>
    </main>
  </div>

  <!-- Ekran 2: o restoranu (hero, sati, recenzije) -->
  <div class="rst-screen" id="rst-screen-info" data-screen="info">
    <section class="rst-hero-mini">
      <div class="rst-hero-mini__cover" style="background-image:url('<?= e(restaurant_cover_url($r['coverImage'] ?? null, $r)) ?>')"></div>
      <div class="rst-hero-mini__body">
        <img class="rst-hero-mini__logo" src="<?= e(restaurant_logo_url($r['logoImage'] ?? null)) ?>" alt="" width="56" height="56">
        <div class="rst-hero-mini__meta">
          <p class="rst-hero-mini__city"><?= e(city_label($r['city'])) ?></p>
          <?= restaurant_stars_html($r['avgRating']) ?>
          <?php if ($menuItemCount): ?><p class="rst-hero-mini__stat"><?= (int) $menuItemCount ?> <?= e($L['menu_items']) ?></p><?php endif; ?>
          <?php if ($todayHours): ?><p class="rst-hero-mini__open"><?= e($L['today']) ?>: <strong><?= e($todayHours) ?></strong></p><?php endif; ?>
        </div>
      </div>
      <?php if ($r['description']): ?><p class="rst-hero-mini__desc"><?= e($r['description']) ?></p><?php endif; ?>
    </section>

    <?php if (!empty($r['hours'])): ?>
    <section class="rst-info-block">
      <h2 class="rst-info-block__title"><?= e($L['hours']) ?></h2>
      <?php if ($todayHours): ?><p class="rst-info-block__today"><?= e($L['today']) ?>: <strong><?= e($todayHours) ?></strong></p><?php endif; ?>
      <dl class="rst-hours rst-hours--card">
        <?php foreach ($days as $key => $label): if (empty($r['hours'][$key])) continue; ?>
        <div class="rst-hours__row<?= $todayKey === $key ? ' rst-hours__row--today' : '' ?>">
          <dt><?= e($label) ?></dt>
          <dd><?= e($r['hours'][$key]) ?></dd>
        </div>
        <?php endforeach; ?>
      </dl>
    </section>
    <?php endif; ?>

    <section class="rst-info-block" id="recenzije">
      <h2 class="rst-info-block__title"><?= e($L['reviews']) ?><?php if ($r['reviewCount']): ?> <span class="rst-info-block__muted">(<?= (int) $r['reviewCount'] ?>)</span><?php endif; ?></h2>
      <?php if ($reviews): ?>
      <ul class="rst-reviews">
        <?php foreach ($reviews as $rv): ?>
        <li class="rst-review">
          <div class="rst-review__head">
            <strong><?= e($rv['name']) ?></strong>
            <span class="rst-stars"><?php for ($s = 1; $s <= 5; $s++): ?><span class="rst-stars__s<?= $s <= (int) $rv['rating'] ? ' rst-stars__s--on' : '' ?>">★</span><?php endfor; ?></span>
          </div>
          <?php if ($rv['body']): ?><p class="rst-review__body"><?= e($rv['body']) ?></p><?php endif; ?>
        </li>
        <?php endforeach; ?>
      </ul>
      <?php else: ?>
      <p class="rst-reviews__empty"><?= e($L['no_reviews']) ?></p>
      <?php endif; ?>

      <?php if ($r['reviewsEnabled']): ?>
      <form method="post" action="/restorani/<?= e($r['slug']) ?>/recenzija" class="rst-form">
        <?= csrf_field() ?>
        <h3 class="rst-form__title"><?= e($L['leave_review']) ?></h3>
        <label class="rst-field"><?= e($L['your_name']) ?><input class="rst-input" name="name" required maxlength="80" autocomplete="name"></label>
        <label class="rst-field"><?= e($L['rating']) ?>
          <select name="rating" class="rst-input" required>
            <option value="">—</option>
            <?php for ($i = 5; $i >= 1; $i--): ?><option value="<?= $i ?>"><?= $i ?> ★</option><?php endfor; ?>
          </select>
        </label>
        <label class="rst-field"><?= e($L['comment']) ?><textarea class="rst-input" name="body" rows="3" maxlength="1000"></textarea></label>
        <button type="submit" class="rst-btn rst-btn--primary rst-btn--block"><?= e($L['send']) ?></button>
        <p class="rst-form__hint"><?= e($L['review_moderated']) ?></p>
      </form>
      <?php endif; ?>
    </section>

    <div class="rst-share-row">
      <button type="button" class="rst-btn rst-btn--block" id="rst-share-btn"
        data-url="<?= e($canonical ?? RestaurantService::publicUrl($r)) ?>"
        data-title="<?= e($r['name']) ?>"
        data-label="<?= e($L['share']) ?>">
        ↗ <?= e($L['share']) ?>
      </button>
    </div>
  </div>

  <?php if ($maps): ?>
  <!-- Ekran 3: lokacija -->
  <div class="rst-screen" id="rst-screen-location" data-screen="location">
    <section class="rst-loc-screen">
      <div class="rst-loc-screen__map">
        <iframe title="Mapa" loading="lazy" referrerpolicy="no-referrer-when-downgrade" src="<?= e($maps['embed']) ?>" allowfullscreen></iframe>
      </div>
      <p class="rst-loc-screen__addr">📍 <?= e($r['address']) ?></p>
      <p class="rst-loc-screen__city"><?= e(city_label($r['city'])) ?></p>
      <div class="rst-loc-screen__actions">
        <a href="<?= e($maps['google_dir']) ?>" class="rst-btn rst-btn--primary rst-btn--block" target="_blank" rel="noopener"><?= e($L['navigation']) ?> (Google)</a>
        <a href="<?= e($maps['apple']) ?>" class="rst-btn rst-btn--block" target="_blank" rel="noopener"><?= e($L['apple_maps']) ?></a>
      </div>
      <?php if ($phone || $wa): ?>
      <div class="rst-loc-screen__contact">
        <?php if ($phone): ?>
        <a href="tel:<?= e(preg_replace('/\s+/', '', $phone)) ?>" class="rst-btn rst-btn--block">📞 <?= e($phone) ?></a>
        <?php endif; ?>
        <?php if ($wa): ?>
        <a href="https://wa.me/<?= e($wa) ?>" class="rst-btn rst-btn--block" target="_blank" rel="noopener">WhatsApp</a>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </section>
  </div>
  <?php endif; ?>

</div>

<footer class="rst-footer">
  <a href="/restorani">← Svi restorani</a>
</footer>

<nav class="rst-tabbar" id="rst-tabbar" aria-label="Navigacija"
  data-label-menu="<?= e($L['menu']) ?>"
  data-label-about="<?= e($L['about']) ?>"
  data-label-location="<?= e($L['location']) ?>">
  <button type="button" class="rst-tabbar__item rst-tabbar__item--active" data-screen="menu" aria-current="page">
    <span class="rst-tabbar__icon" aria-hidden="true">☰</span>
    <span><?= e($L['menu']) ?></span>
  </button>
  <?php if ($maps): ?>
  <button type="button" class="rst-tabbar__item" data-screen="location">
    <span class="rst-tabbar__icon" aria-hidden="true">📍</span>
    <span><?= e($L['location']) ?></span>
  </button>
  <?php endif; ?>
  <button type="button" class="rst-tabbar__item" data-screen="info">
    <span class="rst-tabbar__icon" aria-hidden="true">ℹ️</span>
    <span><?= e($L['about']) ?></span>
  </button>
  <?php if ($phone): ?>
  <a href="tel:<?= e(preg_replace('/\s+/', '', $phone)) ?>" class="rst-tabbar__item rst-tabbar__item--action">
    <span class="rst-tabbar__icon" aria-hidden="true">📞</span>
    <span><?= e($L['call']) ?></span>
  </a>
  <?php elseif ($wa): ?>
  <a href="https://wa.me/<?= e($wa) ?>" class="rst-tabbar__item rst-tabbar__item--action" target="_blank" rel="noopener">
    <span class="rst-tabbar__icon" aria-hidden="true">💬</span>
    <span>WhatsApp</span>
  </a>
  <?php else: ?>
  <button type="button" class="rst-tabbar__item rst-tabbar__item--action" id="rst-share-tab" data-url="<?= e($canonical ?? RestaurantService::publicUrl($r)) ?>">
    <span class="rst-tabbar__icon" aria-hidden="true">↗</span>
    <span><?= e($L['share']) ?></span>
  </button>
  <?php endif; ?>
</nav>
