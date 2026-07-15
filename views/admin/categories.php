<h1>Rubrike</h1>
<form method="post" class="admin-form inline-form">
  <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
  <input class="admin-input" name="name" placeholder="Naziv" required>
  <input class="admin-input" name="slug" placeholder="slug (opcionalno)">
  <button class="admin-btn admin-btn--primary">Dodaj</button>
</form>
<table class="admin-table">
  <thead><tr><th>Naziv</th><th>Slug</th><th>Članaka</th></tr></thead>
  <tbody>
    <?php foreach ($categories as $c): ?>
    <tr><td><?= e($c['name']) ?></td><td><?= e($c['slug']) ?></td><td><?= (int) $c['articleCount'] ?></td></tr>
    <?php endforeach; ?>
  </tbody>
</table>
