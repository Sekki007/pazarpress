<?php /** @var array $restaurants @var int $pendingCount @var string|null $status */ ?>
<div class="admin-page-head" style="display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;flex-wrap:wrap;margin-bottom:1rem">
  <h1 style="margin:0">Restorani — digitalni meniji</h1>
  <a href="/admin/restorani/novi" class="admin-btn admin-btn--primary">+ Dodaj restoran</a>
</div>
<?php if ($pendingCount): ?><p class="flash flash--ok"><?= (int) $pendingCount ?> restorana čeka odobrenje</p><?php endif; ?>

<p>
  <a href="/admin/restorani" class="admin-btn<?= !$status ? ' admin-btn--primary' : '' ?>">Svi</a>
  <a href="/admin/restorani?status=PENDING" class="admin-btn<?= $status === 'PENDING' ? ' admin-btn--primary' : '' ?>">Na čekanju</a>
  <a href="/admin/restorani?status=PUBLISHED" class="admin-btn<?= $status === 'PUBLISHED' ? ' admin-btn--primary' : '' ?>">Objavljeni</a>
</p>

<table class="admin-table" style="width:100%;margin-top:1rem">
  <thead>
    <tr><th>Restoran</th><th>Grad</th><th>Vlasnik</th><th>Status</th><th>Akcije</th></tr>
  </thead>
  <tbody>
  <?php foreach ($restaurants as $r): ?>
  <tr>
    <td>
      <strong><?= e($r['name']) ?></strong><br>
      <a href="/restorani/<?= e($r['slug']) ?>" target="_blank" style="font-size:.8rem">/restorani/<?= e($r['slug']) ?></a>
    </td>
    <td><?= e(city_label($r['city'])) ?></td>
    <td><?= e($r['ownerEmail']) ?></td>
    <td><?= e($r['status']) ?></td>
    <td>
      <a href="/admin/restorani/<?= e($r['id']) ?>" class="admin-btn">Uredi</a>
      <a href="/admin/restorani/<?= e($r['id']) ?>/meni" class="admin-btn">Meni</a>
      <a href="/admin/restorani/<?= e($r['id']) ?>/qr" class="admin-btn" title="QR kod">📱 QR</a>
      <?php if ($r['status'] === 'PENDING'): ?>
      <form method="post" style="display:inline">
        <?= csrf_field() ?>
        <input type="hidden" name="restaurant_id" value="<?= e($r['id']) ?>">
        <button type="submit" name="action" value="approve" class="admin-btn admin-btn--primary">Odobri</button>
        <button type="submit" name="action" value="reject" class="admin-btn">Odbij</button>
      </form>
      <?php elseif ($r['status'] === 'PUBLISHED'): ?>
      <form method="post" style="display:inline">
        <?= csrf_field() ?>
        <input type="hidden" name="restaurant_id" value="<?= e($r['id']) ?>">
        <button type="submit" name="action" value="suspend" class="admin-btn">Suspenduj</button>
      </form>
      <?php endif; ?>
    </td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>
