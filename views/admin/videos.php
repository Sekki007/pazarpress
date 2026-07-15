<h1>Video</h1>
<form method="post" class="admin-form">
  <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
  <div class="form-grid">
    <label>Naslov<input class="admin-input" name="title" required></label>
    <label>YouTube ID<input class="admin-input" name="youtubeId"></label>
    <label>Trajanje<input class="admin-input" name="duration" placeholder="12:30"></label>
  </div>
  <button class="admin-btn admin-btn--primary">Dodaj video</button>
</form>
<table class="admin-table">
  <thead><tr><th>Naslov</th><th>Trajanje</th><th></th></tr></thead>
  <tbody>
    <?php foreach ($videos as $v): ?>
    <tr>
      <td><?= e($v['title']) ?></td>
      <td><?= e($v['duration'] ?? '') ?></td>
      <td>
        <form method="post" style="display:inline"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <button name="delete" value="<?= e($v['id']) ?>" class="admin-btn admin-btn--danger btn-sm">Obriši</button></form>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
