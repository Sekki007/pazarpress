<?php
/** @var array $restaurant @var array $categories @var array|null $item */
$preCat = $_GET['cat'] ?? ($item['categoryId'] ?? '');
?>
<h1><?= $item ? 'Uredi stavku' : 'Nova stavka' ?></h1>
<form method="post" class="admin-form" style="max-width:560px">
  <?= csrf_field() ?>
  <label>Kategorija *
    <select name="categoryId" class="admin-input" required>
      <option value="">— Odaberite —</option>
      <?php foreach ($categories as $c): ?>
      <option value="<?= e($c['id']) ?>" <?= $preCat === $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>Naziv jela *
    <input class="admin-input" name="name" required value="<?= e($item['name'] ?? '') ?>">
  </label>
  <label>Opis (bosanski)
    <textarea class="admin-input" name="description" rows="3"><?= e($item['description'] ?? '') ?></textarea>
  </label>
  <?php include __DIR__ . '/../partials/menu-item-i18n-fields.php'; ?>
  <label>Cijena (broj)
    <input class="admin-input" name="price" type="number" step="0.01" min="0" value="<?= $item['price'] ?? '' ?>" placeholder="450">
  </label>
  <label>Ili tekst umjesto cijene
    <input class="admin-input" name="priceLabel" value="<?= e($item['priceLabel'] ?? '') ?>" placeholder="Po dogovoru">
  </label>
  <label>Valuta
    <select name="currency" class="admin-input">
      <?php foreach (['RSD', 'EUR', 'BAM'] as $cur): ?>
      <option value="<?= $cur ?>" <?= ($item['currency'] ?? 'RSD') === $cur ? 'selected' : '' ?>><?= $cur ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>Slika jela
    <input class="admin-input meni-image-input" name="image" value="<?= e($item['image'] ?? '') ?>" data-preview="item-preview">
    <input type="file" accept="image/*" class="meni-upload" data-target="image">
    <p class="admin-muted" style="font-size:12px">Bez slike — prikazuje se informativna ilustracija</p>
  </label>
  <div id="item-preview" class="cover-preview">
    <img src="<?= e(menu_item_image_url($item['image'] ?? null, 0)) ?>" alt="">
  </div>
  <label>Oznake (zarezom)
    <input class="admin-input" name="tags" value="<?= e(implode(', ', $item['tags'] ?? [])) ?>" placeholder="vegetarijansko, halal, ljuto">
  </label>
  <label class="admin-check">
    <input type="checkbox" name="isAvailable" <?= ($item['isAvailable'] ?? true) ? 'checked' : '' ?>>
    Dostupno na meniju
  </label>
  <div style="display:flex;gap:.5rem;margin-top:1rem;flex-wrap:wrap">
    <button type="submit" class="admin-btn admin-btn--primary">Sačuvaj</button>
    <a href="/moj-meni/meni" class="admin-btn">Nazad</a>
    <?php if ($item): ?>
    <button type="submit" name="delete" value="1" class="admin-btn" style="margin-left:auto;color:#b91c1c" onclick="return confirm('Obrisati stavku?')">Obriši</button>
    <?php endif; ?>
  </div>
</form>
