<?php
/** @var array|null $restaurant */
$hours = $restaurant['hours'] ?? [];
$days = ['pon' => 'Ponedjeljak', 'uto' => 'Utorak', 'sri' => 'Srijeda', 'cet' => 'Četvrtak', 'pet' => 'Petak', 'sub' => 'Subota', 'ned' => 'Nedjelja'];
?>
<h1>Profil restorana</h1>
<form method="post" class="admin-form admin-form--narrow" style="max-width:640px">
  <?= csrf_field() ?>
  <label>Naziv restorana *
    <input class="admin-input" name="name" required value="<?= e($restaurant['name'] ?? '') ?>">
  </label>
  <label>URL slug (sandzak.net/restorani/…)
    <input class="admin-input" name="slug" value="<?= e($restaurant['slug'] ?? '') ?>" placeholder="auto iz naziva">
  </label>
  <label>Grad
    <select name="city" class="admin-input">
      <?php foreach (CITIES_ORDER as $c): ?>
      <option value="<?= e($c) ?>" <?= ($restaurant['city'] ?? 'NOVI_PAZAR') === $c ? 'selected' : '' ?>><?= e(city_label($c)) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>Adresa (za mapu i navigaciju)
    <input class="admin-input" name="address" value="<?= e($restaurant['address'] ?? '') ?>" placeholder="Ulica i broj, grad">
  </label>
  <label>Telefon<input class="admin-input" name="phone" value="<?= e($restaurant['phone'] ?? '') ?>"></label>
  <label>WhatsApp<input class="admin-input" name="whatsapp" value="<?= e($restaurant['whatsapp'] ?? '') ?>" placeholder="+381..."></label>
  <label>Opis
    <textarea class="admin-input" name="description" rows="4"><?= e($restaurant['description'] ?? '') ?></textarea>
  </label>
  <fieldset>
    <legend>Radno vrijeme (npr. 08:00–23:00 ili Zatvoreno)</legend>
    <?php foreach ($days as $key => $label): ?>
    <label><?= e($label) ?>
      <input class="admin-input" name="hours_<?= e($key) ?>" value="<?= e($hours[$key] ?? '') ?>" placeholder="09:00–22:00">
    </label>
    <?php endforeach; ?>
  </fieldset>
  <label>Logo URL
    <input class="admin-input meni-image-input" name="logoImage" value="<?= e($restaurant['logoImage'] ?? '') ?>" data-preview="logo-preview">
    <input type="file" accept="image/*" class="meni-upload" data-target="logoImage">
  </label>
  <div id="logo-preview" class="cover-preview"><?php if (!empty($restaurant['logoImage'])): ?><img src="<?= e($restaurant['logoImage']) ?>" alt=""><?php else: ?><img src="<?= e(restaurant_logo_url(null)) ?>" alt=""><?php endif; ?></div>
  <label>Cover slika
    <input class="admin-input meni-image-input" name="coverImage" value="<?= e($restaurant['coverImage'] ?? '') ?>" data-preview="cover-preview">
    <input type="file" accept="image/*" class="meni-upload" data-target="coverImage">
  </label>
  <div id="cover-preview" class="cover-preview"><img src="<?= e($restaurant ? restaurant_cover_url($restaurant['coverImage'] ?? null, $restaurant) : restaurant_cover_url(null)) ?>" alt=""></div>
  <p class="admin-hint">Ako ne uploadujete sliku, cover se automatski pravi sa nazivom restorana.</p>
  <?php include __DIR__ . '/../partials/menu-langs-fieldset.php'; ?>
  <label class="admin-check">
    <input type="checkbox" name="reviewsEnabled" <?= ($restaurant['reviewsEnabled'] ?? true) ? 'checked' : '' ?>>
    Dozvoli recenzije gostiju (moderirane)
  </label>
  <div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-top:1rem">
    <button type="submit" class="admin-btn admin-btn--primary">Sačuvaj</button>
    <button type="submit" name="submit_review" value="1" class="admin-btn">Sačuvaj i pošalji na odobrenje</button>
  </div>
</form>
