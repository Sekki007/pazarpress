<?php /** @var array $restaurant @var array $categories */ ?>
<p class="admin-muted">
  <a href="/admin/restorani">← Restorani</a> ·
  <a href="/admin/restorani/<?= e($restaurant['id']) ?>">Profil</a> ·
  <a href="/admin/restorani/<?= e($restaurant['id']) ?>/qr" class="admin-btn admin-btn--primary" style="display:inline;padding:.35rem .75rem">📱 QR meni</a> ·
  <a href="/restorani/<?= e($restaurant['slug']) ?>" target="_blank">Javni meni ↗</a>
</p>
<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;flex-wrap:wrap">
  <div>
    <h1 style="margin:0"><?= e($restaurant['name']) ?> — cjenovnik</h1>
    <p class="admin-muted">Status: <?= e($restaurant['status']) ?></p>
  </div>
  <a href="/admin/restorani/<?= e($restaurant['id']) ?>/stavka" class="admin-btn admin-btn--primary">+ Nova stavka</a>
</div>

<form method="post" action="/admin/restorani/<?= e($restaurant['id']) ?>/meni/skeniraj" enctype="multipart/form-data" class="admin-card" style="margin:1rem 0;border:2px dashed #0e5a48;background:#f0faf7">
  <?= csrf_field() ?>
  <h3 style="margin-top:0">📷 Skeniraj meni sa slike</h3>
  <p class="admin-muted" style="margin-bottom:.75rem">Fotografišite ili uploadujte papirni cjenovnik — AI (OpenAI/Claude iz <a href="/admin/auto-vesti">Auto Vesti</a>) automatski kreira kategorije i stavke. Provjerite cijene prije objave.</p>
  <label style="display:block;margin-bottom:.75rem">
    Slika menija
    <input type="file" name="file" accept="image/jpeg,image/png,image/webp" capture="environment" required class="admin-input">
  </label>
  <label class="admin-check" style="margin-bottom:.75rem">
    <input type="checkbox" name="replace_menu" value="1">
    Zamijeni postojeći cjenovnik (inače se dodaje uz postojeći)
  </label>
  <button type="submit" class="admin-btn admin-btn--primary">Generiši meni iz slike</button>
</form>

<form method="post" class="admin-card" style="margin:1rem 0">
  <?= csrf_field() ?>
  <h3 style="margin-top:0">Nova kategorija</h3>
  <div style="display:flex;gap:.5rem;flex-wrap:wrap">
    <input class="admin-input" name="category_name" placeholder="npr. Glavna jela" style="flex:1;min-width:200px" required>
    <button type="submit" name="add_category" value="1" class="admin-btn">Dodaj</button>
  </div>
</form>

<?php if (!$categories): ?>
<p class="admin-muted">Dodajte kategoriju (Predjela, Piće…), zatim stavke.</p>
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
  <p class="admin-muted"><a href="/admin/restorani/<?= e($restaurant['id']) ?>/stavka?cat=<?= e($cat['id']) ?>">Dodaj prvu stavku</a></p>
  <?php else: ?>
  <table class="admin-table" style="width:100%">
    <thead><tr><th>Stavka</th><th>Cijena</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($cat['items'] as $item): ?>
    <tr>
      <td><strong><?= e($item['name']) ?></strong><?php if (!$item['isAvailable']): ?> <span class="admin-muted">(nedostupno)</span><?php endif; ?></td>
      <td><?= e(RestaurantService::formatPrice($item['price'], $item['priceLabel'], $item['currency'])) ?></td>
      <td><a href="/admin/restorani/<?= e($restaurant['id']) ?>/stavka/<?= e($item['id']) ?>">Uredi</a></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>
<?php endforeach; ?>
