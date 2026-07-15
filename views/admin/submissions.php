<h1>Prijave čitalaca</h1>
<?php foreach ($submissions as $s): ?>
<div class="admin-card comment-card<?= $s['read'] ? '' : ' unread' ?>">
  <p><strong><?= e($s['name']) ?></strong> — <?= e($s['contact']) ?> · <?= e(format_relative($s['createdAt'])) ?></p>
  <p><?= nl2br(e($s['message'])) ?></p>
  <form method="post" class="inline-form">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
    <?php if (!$s['read']): ?><button name="mark_read" value="<?= e($s['id']) ?>" class="admin-btn btn-sm">Označi pročitano</button><?php endif; ?>
    <button name="delete" value="<?= e($s['id']) ?>" class="admin-btn admin-btn--danger btn-sm">Obriši</button>
  </form>
</div>
<?php endforeach; ?>
