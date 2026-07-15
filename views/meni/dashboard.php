<?php /** @var array|null $restaurant @var array $user */ ?>
<h1>Moj digitalni meni</h1>
<p class="admin-muted">Dobrodošli, <?= e($user['name'] ?? '') ?>!</p>

<?php if (!$restaurant): ?>
<div class="admin-card">
  <h2>Krenite za 5 minuta</h2>
  <ol>
    <li>Popunite <a href="/moj-meni/profil">profil restorana</a></li>
    <li>Dodajte kategorije i jela u <a href="/moj-meni/meni">cjenovnik</a></li>
    <li>Pošaljite na odobrenje — objavljujemo besplatno</li>
    <li>Preuzmite <a href="/moj-meni/qr">QR kod</a> za stolove</li>
  </ol>
  <a href="/moj-meni/profil" class="admin-btn admin-btn--primary">Kreiraj profil restorana</a>
</div>
<?php else:
  $statusLabels = [
      'PENDING' => ['Na čekanju', '#b45309'],
      'PUBLISHED' => ['Objavljeno', '#15803d'],
      'REJECTED' => ['Odbijeno', '#b91c1c'],
      'SUSPENDED' => ['Suspendovano', '#6b7280'],
  ];
  $st = $statusLabels[$restaurant['status']] ?? ['Nepoznato', '#666'];
  $menu = RestaurantRepository::getFullMenu($restaurant['id']);
  $itemCount = array_sum(array_map(static fn ($c) => count($c['items'] ?? []), $menu));
?>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px;margin:1.25rem 0">
  <div class="admin-card" style="margin:0;padding:14px"><strong style="font-size:1.4rem;color:<?= $st[1] ?>"><?= e($st[0]) ?></strong><br><span class="admin-muted">Status</span></div>
  <div class="admin-card" style="margin:0;padding:14px"><strong style="font-size:1.4rem"><?= count($menu) ?></strong><br><span class="admin-muted">Kategorija</span></div>
  <div class="admin-card" style="margin:0;padding:14px"><strong style="font-size:1.4rem"><?= (int) $itemCount ?></strong><br><span class="admin-muted">Stavki menija</span></div>
  <div class="admin-card" style="margin:0;padding:14px"><strong style="font-size:1.4rem"><?= (int) $restaurant['viewCount'] ?></strong><br><span class="admin-muted">Pregleda</span></div>
</div>
<?php if ($restaurant['status'] === 'PENDING'): ?>
<p class="flash flash--ok">Vaš meni čeka odobrenje administratora. Možete nastaviti uređivanje.</p>
<?php endif; ?>
<div class="admin-card">
  <h2><?= e($restaurant['name']) ?></h2>
  <p class="admin-muted"><?= e(city_label($restaurant['city'])) ?><?= $restaurant['address'] ? ' · ' . e($restaurant['address']) : '' ?></p>
  <p style="margin-top:1rem">
    <a href="/moj-meni/profil" class="admin-btn">Uredi profil</a>
    <a href="/moj-meni/meni" class="admin-btn">Cjenovnik</a>
    <a href="/moj-meni/qr" class="admin-btn admin-btn--primary">QR kod</a>
  </p>
</div>
<?php endif; ?>
