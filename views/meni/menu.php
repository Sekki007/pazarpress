<?php /** @var array $restaurant @var array $categories */ ?>
<div class="admin-page-head" style="display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;flex-wrap:wrap">
  <div>
    <h1 style="margin:0">Cjenovnik</h1>
    <p class="admin-muted">Kategorije i stavke menija · <a href="/moj-meni/qr">QR meni za štampu →</a></p>
  </div>
  <a href="/moj-meni/stavka" class="admin-btn admin-btn--primary">+ Nova stavka</a>
</div>

<form method="post" action="/moj-meni/meni/skeniraj" enctype="multipart/form-data" class="admin-card" style="margin-bottom:1rem;border:2px dashed #0e5a48;background:#f0faf7">
  <?= csrf_field() ?>
  <h3 style="margin-top:0">📷 Skeniraj meni sa slike</h3>
  <p class="admin-muted">Slikajte papirni cjenovnik — AI automatski popunjava stavke (potreban API ključ u Auto Vesti postavkama portala).</p>
  <input type="file" name="file" accept="image/*" capture="environment" required class="admin-input" style="margin-bottom:.5rem">
  <label class="admin-check" style="display:block;margin-bottom:.75rem"><input type="checkbox" name="replace_menu" value="1"> Zamijeni postojeći meni</label>
  <button type="submit" class="admin-btn admin-btn--primary">Generiši meni</button>
</form>

<form method="post" class="admin-card" style="margin-bottom:1rem">
  <?= csrf_field() ?>
  <h3 style="margin-top:0">Nova kategorija</h3>
  <div style="display:flex;gap:.5rem;flex-wrap:wrap">
    <input class="admin-input" name="category_name" placeholder="npr. Glavna jela" style="flex:1;min-width:200px" required>
    <button type="submit" name="add_category" value="1" class="admin-btn">Dodaj</button>
  </div>
</form>

<?php if (!$categories): ?>
<p class="admin-muted">Još nema kategorija. Dodajte prvu iznad, zatim stavke.</p>
<?php endif; ?>

<?php foreach ($categories as $cat): ?>
<div class="admin-card">
  <div style="display:flex;justify-content:space-between;align-items:center;gap:.5rem;margin-bottom:.75rem">
    <h2 style="margin:0;font-size:1.1rem"><?= e($cat['name']) ?></h2>
    <form method="post" onsubmit="return confirm('Obrisati kategoriju i sve stavke?')">
      <?= csrf_field() ?>
      <input type="hidden" name="category_id" value="<?= e($cat['id']) ?>">
      <button type="submit" name="delete_category" value="1" class="admin-btn" style="color:#b91c1c">Obriši</button>
    </form>
  </div>
  <?php if (empty($cat['items'])): ?>
  <p class="admin-muted">Nema stavki. <a href="/moj-meni/stavka?cat=<?= e($cat['id']) ?>">Dodaj prvu</a></p>
  <?php else: ?>
  <table class="admin-table" style="width:100%;font-size:.9rem">
    <thead><tr><th>Stavka</th><th>Cijena</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($cat['items'] as $item): ?>
    <tr>
      <td>
        <strong><?= e($item['name']) ?></strong>
        <?php if (!$item['isAvailable']): ?><span class="admin-muted"> (nedostupno)</span><?php endif; ?>
      </td>
      <td><?= e(RestaurantService::formatPrice($item['price'], $item['priceLabel'], $item['currency'])) ?></td>
      <td><a href="/moj-meni/stavka/<?= e($item['id']) ?>">Uredi</a></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>
<?php endforeach; ?>
