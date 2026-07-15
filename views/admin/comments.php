<h1>Komentari</h1>
<div class="filter-tabs">
  <a href="/admin/komentari?status=PENDING" class="<?= $status === 'PENDING' ? 'active' : '' ?>">Na čekanju</a>
  <a href="/admin/komentari?status=APPROVED" class="<?= $status === 'APPROVED' ? 'active' : '' ?>">Odobreno</a>
</div>
<?php foreach ($comments as $c): ?>
<div class="admin-card comment-card">
  <p><strong><?= e($c['name']) ?></strong> na <a href="/vijest/<?= e($c['slug']) ?>"><?= e($c['articleTitle']) ?></a></p>
  <p><?= e($c['body']) ?></p>
  <form method="post" class="inline-form">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="id" value="<?= e($c['id']) ?>">
    <?php if ($c['status'] === 'PENDING'): ?>
    <button name="action" value="approve" class="admin-btn admin-btn--primary btn-sm">Odobri</button>
    <?php endif; ?>
    <button name="action" value="delete" class="admin-btn admin-btn--danger btn-sm">Obriši</button>
  </form>
</div>
<?php endforeach; ?>
