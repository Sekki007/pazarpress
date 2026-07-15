<h1>Newsletter <a href="/admin/newsletter?export=csv" class="admin-btn btn-sm">Export CSV</a></h1>
<table class="admin-table">
  <thead><tr><th>Email</th><th>Potvrđen</th><th>Datum</th><th></th></tr></thead>
  <tbody>
    <?php foreach ($subscribers as $s): ?>
    <tr>
      <td><?= e($s['email']) ?></td>
      <td><?= $s['confirmed'] ? 'Da' : 'Ne' ?></td>
      <td><?= e(format_datetime($s['createdAt'])) ?></td>
      <td>
        <form method="post" style="display:inline"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <button name="delete" value="<?= e($s['id']) ?>" class="admin-btn admin-btn--danger btn-sm">Obriši</button></form>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
