<div class="page-head">
  <h1>Članci</h1>
  <a href="/admin/clanci/novi" class="admin-btn admin-btn--primary">+ Novi</a>
</div>
<div class="filter-tabs">
  <a href="/admin/clanci" class="<?= !$status ? 'active' : '' ?>">Svi</a>
  <a href="/admin/clanci?status=PUBLISHED" class="<?= $status === 'PUBLISHED' ? 'active' : '' ?>">Objavljeno</a>
  <a href="/admin/clanci?status=DRAFT" class="<?= $status === 'DRAFT' ? 'active' : '' ?>">Nacrti</a>
</div>
<table class="admin-table">
  <thead><tr><th>Naslov</th><th>Rubrika</th><th>Status</th><th>Grad</th><th></th></tr></thead>
  <tbody>
    <?php foreach ($articles as $a): ?>
    <tr>
      <td><?= e($a['title']) ?></td>
      <td><?= e($a['categoryName']) ?></td>
      <td><?= e($a['status']) ?></td>
      <td><?= e(city_label($a['city'])) ?></td>
      <td>
        <a href="/admin/clanci/<?= e($a['id']) ?>">Uredi</a>
        <?php if ($a['status'] === 'PUBLISHED'): ?>
        · <a href="/vijest/<?= e($a['slug']) ?>" target="_blank">Vidi</a>
        <?php else: ?>
        · <a href="/admin/preview/<?= e($a['slug']) ?>" target="_blank">Pregled</a>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
