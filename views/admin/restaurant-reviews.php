<?php /** @var array $reviews */ ?>
<h1>Recenzije restorana</h1>
<?php if (!$reviews): ?>
<p class="admin-muted">Nema recenzija na čekanju.</p>
<?php else: ?>
<table class="admin-table" style="width:100%">
  <thead><tr><th>Restoran</th><th>Autor</th><th>Ocjena</th><th>Tekst</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($reviews as $rv): ?>
  <tr>
    <td><a href="/restorani/<?= e($rv['restaurantSlug']) ?>" target="_blank"><?= e($rv['restaurantName']) ?></a></td>
    <td><?= e($rv['name']) ?></td>
    <td><?= (int) $rv['rating'] ?>/5</td>
    <td><?= e($rv['body'] ?? '—') ?></td>
    <td>
      <form method="post" style="display:inline">
        <?= csrf_field() ?>
        <input type="hidden" name="review_id" value="<?= e($rv['id']) ?>">
        <button type="submit" name="action" value="approve" class="admin-btn admin-btn--primary">Odobri</button>
        <button type="submit" name="action" value="reject" class="admin-btn">Odbij</button>
      </form>
    </td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>
