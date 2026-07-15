<?php
/** @var array|null $restaurant @var string $ownerEmail */
$hours = $restaurant['hours'] ?? [];
$days = ['pon' => 'Ponedjeljak', 'uto' => 'Utorak', 'sri' => 'Srijeda', 'cet' => 'Četvrtak', 'pet' => 'Petak', 'sub' => 'Subota', 'ned' => 'Nedjelja'];
$isNew = !$restaurant;
?>
<p class="admin-muted"><a href="/admin/restorani">← Svi restorani</a><?php if (!$isNew): ?> · <a href="/admin/restorani/<?= e($restaurant['id']) ?>/meni">Cjenovnik</a><?php endif; ?></p>
<h1><?= $isNew ? 'Novi restoran' : 'Uredi: ' . e($restaurant['name']) ?></h1>

<form method="post" class="admin-form" style="max-width:640px">
  <?= csrf_field() ?>
  <label>Status
    <select name="status" class="admin-input">
      <?php foreach (['PUBLISHED' => 'Objavljen (vidljiv na sajtu)', 'PENDING' => 'Na čekanju', 'SUSPENDED' => 'Suspendovan', 'REJECTED' => 'Odbijen'] as $val => $lab): ?>
      <option value="<?= e($val) ?>" <?= ($restaurant['status'] ?? 'PUBLISHED') === $val ? 'selected' : '' ?>><?= e($lab) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>Naziv restorana *
    <input class="admin-input" name="name" required value="<?= e($restaurant['name'] ?? '') ?>">
  </label>
  <label>URL slug
    <input class="admin-input" name="slug" value="<?= e($restaurant['slug'] ?? '') ?>" placeholder="npr. cevabdzija-bosna">
    <span class="admin-hint">sandzak.net/restorani/<strong>ovaj-slug</strong></span>
  </label>
  <label>Grad
    <select name="city" class="admin-input">
      <?php foreach (CITIES_ORDER as $c): ?>
      <option value="<?= e($c) ?>" <?= ($restaurant['city'] ?? 'NOVI_PAZAR') === $c ? 'selected' : '' ?>><?= e(city_label($c)) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>Adresa (ulica i broj — za mapu i navigaciju)
    <input class="admin-input" name="address" value="<?= e($restaurant['address'] ?? '') ?>" placeholder="npr. Novi Pazar, Ulica bb">
  </label>
  <p class="admin-hint">Tačna adresa omogućava ugrađenu mapu i dugme „Navigacija” na digitalnom meniju.</p>
  <label>Telefon<input class="admin-input" name="phone" value="<?= e($restaurant['phone'] ?? '') ?>"></label>
  <label>WhatsApp<input class="admin-input" name="whatsapp" value="<?= e($restaurant['whatsapp'] ?? '') ?>"></label>
  <label>Opis<textarea class="admin-input" name="description" rows="4"><?= e($restaurant['description'] ?? '') ?></textarea></label>
  <fieldset>
    <legend>Radno vrijeme</legend>
    <?php foreach ($days as $key => $label): ?>
    <label><?= e($label) ?>
      <input class="admin-input" name="hours_<?= e($key) ?>" value="<?= e($hours[$key] ?? '') ?>" placeholder="09:00–22:00">
    </label>
    <?php endforeach; ?>
  </fieldset>
  <label>Logo
    <input class="admin-input admin-image-input" name="logoImage" value="<?= e($restaurant['logoImage'] ?? '') ?>" data-preview="adm-logo-preview">
    <input type="file" accept="image/*" class="admin-image-upload" data-target="logoImage">
  </label>
  <div id="adm-logo-preview" class="cover-preview"><img src="<?= e(restaurant_logo_url($restaurant['logoImage'] ?? null)) ?>" alt=""></div>
  <label>Cover
    <input class="admin-input admin-image-input" name="coverImage" value="<?= e($restaurant['coverImage'] ?? '') ?>" data-preview="adm-cover-preview">
    <input type="file" accept="image/*" class="admin-image-upload" data-target="coverImage">
  </label>
  <div id="adm-cover-preview" class="cover-preview"><img src="<?= e($restaurant ? restaurant_cover_url($restaurant['coverImage'] ?? null, $restaurant) : restaurant_cover_url(null)) ?>" alt=""></div>
  <?php if ($restaurant): ?>
  <p class="admin-hint">Bez uploada — cover se automatski generiše sa nazivom i gradom.</p>
  <?php endif; ?>
  <?php include __DIR__ . '/../partials/menu-langs-fieldset.php'; ?>
  <label class="admin-check">
    <input type="checkbox" name="reviewsEnabled" <?= ($restaurant['reviewsEnabled'] ?? true) ? 'checked' : '' ?>>
    Dozvoli recenzije
  </label>
  <fieldset>
    <legend>Vlasnik (opciono)</legend>
    <p class="admin-hint">Ostavite prazno ako admin upravlja menijem. Unesite email da vlasnik kasnije preuzme nalog.</p>
    <label>Email vlasnika<input class="admin-input" name="ownerEmail" type="email" value="<?= e($ownerEmail) ?>" placeholder="vlasnik@example.com"></label>
    <label>Ime vlasnika<input class="admin-input" name="ownerName" placeholder="Ime i prezime"></label>
  </fieldset>
  <div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-top:1rem">
    <button type="submit" class="admin-btn admin-btn--primary"><?= $isNew ? 'Kreiraj i idi na cjenovnik' : 'Sačuvaj' ?></button>
    <?php if (!$isNew): ?>
    <a href="/restorani/<?= e($restaurant['slug']) ?>" target="_blank" class="admin-btn">Pregled na sajtu</a>
    <a href="/admin/restorani/<?= e($restaurant['id']) ?>/meni" class="admin-btn">Cjenovnik</a>
    <a href="/admin/restorani/<?= e($restaurant['id']) ?>/qr" class="admin-btn admin-btn--primary">📱 QR meni</a>
    <?php endif; ?>
  </div>
</form>
<script src="/assets/js/admin-restaurant.js" defer></script>
