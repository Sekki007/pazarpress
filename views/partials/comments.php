<?php $comments = $comments ?? []; ?>
<section class="comments" id="komentari">
  <h2 class="comments__title">Komentari<?= $comments ? ' (' . count($comments) . ')' : '' ?></h2>

  <?php if ($comments): ?>
  <ul class="comments__list">
    <?php foreach ($comments as $c): ?>
    <li class="comments__item">
      <strong class="comments__author"><?= e($c['name']) ?></strong>
      <time class="comments__time" datetime="<?= e($c['createdAt']) ?>"><?= e(format_relative($c['createdAt'])) ?></time>
      <p class="comments__body"><?= nl2br(e($c['body'])) ?></p>
    </li>
    <?php endforeach; ?>
  </ul>
  <?php else: ?>
  <p class="comments__empty">Budite prvi koji će komentarisati.</p>
  <?php endif; ?>

  <form class="comments__form" id="comment-form" data-article-id="<?= e($article['id']) ?>">
    <h3>Ostavite komentar</h3>
    <input type="text" name="name" class="input" placeholder="Ime" required maxlength="80">
    <textarea name="body" class="input comments__textarea" placeholder="Vaš komentar…" required maxlength="2000" rows="4"></textarea>
    <button type="submit" class="btn-primary">Pošalji na moderaciju</button>
    <p class="comments__hint">Komentari se objavljuju nakon odobrenja uredništva.</p>
  </form>
</section>
