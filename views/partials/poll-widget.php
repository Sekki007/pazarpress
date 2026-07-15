<div class="widget poll-widget" data-poll-id="<?= e($poll['id']) ?>">
  <h3 class="widget__title">Anketa</h3>
  <p class="poll-q"><?= e($poll['question']) ?></p>
  <form class="poll-form" method="post" action="/api/poll/vote">
    <?php foreach ($poll['options'] as $opt): ?>
    <label class="poll-opt">
      <input type="radio" name="optionId" value="<?= e($opt['id']) ?>" required>
      <span><?= e($opt['text']) ?></span>
      <small><?= (int) ($opt['votes'] ?? 0) ?> glasova</small>
    </label>
    <?php endforeach; ?>
    <button type="submit" class="btn-primary btn-sm">Glasaj</button>
  </form>
</div>
