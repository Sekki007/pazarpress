<h1>Ankete</h1>
<form method="post" class="admin-form">
  <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
  <label>Pitanje<input class="admin-input" name="question" required></label>
  <label>Opcije (jedna po liniji)<textarea class="admin-input" name="options" rows="4" required></textarea></label>
  <label class="check"><input type="checkbox" name="active" checked> Aktivna anketa</label>
  <button class="admin-btn admin-btn--primary">Kreiraj</button>
</form>
<?php foreach ($polls as $poll): ?>
<div class="admin-card" style="margin-top:1rem">
  <h3><?= e($poll['question']) ?> <?= $poll['active'] ? '<span class="badge">Aktivna</span>' : '' ?></h3>
  <ul><?php foreach ($poll['options'] as $o): ?><li><?= e($o['text']) ?> — <?= (int) $o['votes'] ?> glasova</li><?php endforeach; ?></ul>
  <form method="post" class="inline-form">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
    <?php if (!$poll['active']): ?><button name="activate" value="<?= e($poll['id']) ?>" class="admin-btn btn-sm">Aktiviraj</button><?php endif; ?>
    <?php if ($poll['active']): ?><button name="deactivate" value="<?= e($poll['id']) ?>" class="admin-btn btn-sm">Deaktiviraj</button><?php endif; ?>
    <button name="delete_poll" value="<?= e($poll['id']) ?>" class="admin-btn admin-btn--danger btn-sm">Obriši</button>
  </form>
</div>
<?php endforeach; ?>
