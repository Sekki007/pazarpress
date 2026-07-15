<?php if (empty($preview)): include __DIR__ . '/partials/header.php'; endif; ?>

<article class="article-page" data-article-slug="<?= e($article['slug']) ?>" data-article-title="<?= e($article['title']) ?>" data-article-cover="<?= e($article['coverImage'] ?? '') ?>">
  <div class="article-progress" id="read-progress"></div>
  <header class="article-header">
    <div class="container article-header__inner">
      <?php if (empty($preview)): ?>
      <a href="javascript:history.back()" class="icon-btn" aria-label="Nazad">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
      </a>
      <?php else: ?>
      <a href="/admin/clanci" class="icon-btn">← Admin</a>
      <?php endif; ?>
      <a href="/rubrika/<?= e($article['category']['slug']) ?>" class="chip"><?= e($article['category']['name']) ?></a>
      <button type="button" class="icon-btn" id="btn-theme-article" aria-label="Tema">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 14.5A8.5 8.5 0 1 1 9.5 3 7 7 0 0 0 21 14.5z"/></svg>
      </button>
    </div>
  </header>

  <div class="container article-wrap article-wrap--padded">
    <?php if (empty($preview)): include __DIR__ . '/partials/breadcrumbs.php'; endif; ?>
    <h1 class="article-title"><?= e($article['title']) ?></h1>
    <p class="article-lead"><?= e($article['lead']) ?></p>
    <div class="article-meta">
      <span class="article-meta__pill article-meta__pill--author"><?= e($article['author']['name'] ?? $article['authorName']) ?></span>
      <span class="article-meta__pill article-meta__pill--date"><?= e(format_datetime($article['publishedAt'] ?? $article['createdAt'])) ?></span>
      <span class="article-meta__pill article-meta__pill--city"><?= e(city_label($article['city'])) ?></span>
      <span class="article-meta__pill article-meta__pill--time"><?= (int) $article['readingTimeMin'] ?> min čitanja</span>
      <?php if (!empty($article['viewCount'])): ?>
      <span class="article-meta__pill article-meta__pill--views"><?= e(format_view_count((int) $article['viewCount'])) ?> pregleda</span>
      <?php endif; ?>
    </div>

    <?php
    $bodyHasSameCover = !empty($article['coverImage']) && str_contains((string) ($article['body'] ?? ''), (string) $article['coverImage']);
    if (!empty($article['coverImage']) && !$bodyHasSameCover):
    ?>
    <figure class="article-cover">
      <?= responsive_image($article['coverImage'], $article['title'], ['class' => '', 'loading' => 'eager', 'width' => 760, 'height' => 428]) ?>
      <?php if ($article['coverCaption']): ?><figcaption><?= e($article['coverCaption']) ?></figcaption><?php endif; ?>
    </figure>
    <?php endif; ?>

    <?php if (empty($preview)): $slot = 'ad_article_html'; include __DIR__ . '/partials/ad-slot.php'; endif; ?>

    <div class="article-body serif<?= !empty($article['sourceUrl']) ? ' avc-content' : '' ?>">
      <?= render_article_body($article['body']) ?>
    </div>

    <?php if (empty($preview)):
      $shareUrl = $canonical ?? absolute_url('/vijest/' . $article['slug']);
      $shareTitle = $article['title'];
      include __DIR__ . '/partials/share-bar.php';
      include __DIR__ . '/partials/facebook-like-cta.php';
    endif; ?>

    <?php if (!empty($article['tags'])): ?>
    <div class="article-tags">
      <?php foreach ($article['tags'] as $tag): ?>
      <a href="/tag/<?= e($tag['slug']) ?>" class="chip">#<?= e($tag['name']) ?></a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($nextArticle) && is_array($nextArticle)): ?>
    <a href="/vijest/<?= e($nextArticle['slug']) ?>" class="next-article">
      <span class="next-article__label">Sledeća vest</span>
      <span class="next-article__title"><?= e($nextArticle['title']) ?></span>
      <span class="next-article__meta"><?= e($nextArticle['category']['name'] ?? '') ?> · <?= e(format_relative($nextArticle['publishedAt'] ?? null)) ?></span>
    </a>
    <?php endif; ?>

    <?php if (empty($preview)): include __DIR__ . '/partials/comments.php'; endif; ?>
  </div>

  <?php if (empty($preview)): ?>
  <div class="article-tools" role="group" aria-label="Veličina teksta">
    <div class="article-tools__inner">
      <button type="button" class="article-tools__btn" id="btn-font-down" aria-label="Manji tekst">A−</button>
      <button type="button" class="article-tools__btn" id="btn-font-up" aria-label="Veći tekst">A+</button>
    </div>
  </div>
  <?php endif; ?>

  <?php if ($related): ?>
  <section class="container related">
    <h2>Povezane vijesti</h2>
    <div class="grid-2">
      <?php foreach ($related as $i => $rel): ?>
      <?php $article = $rel; $variant = $i; include __DIR__ . '/partials/news-card.php'; ?>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>
</article>

<?php if (empty($preview)): include __DIR__ . '/partials/footer.php'; endif; ?>

<?php $extraScripts = ['/assets/js/article-lightbox.js']; ?>
