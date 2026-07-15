<?php /** @var array $restaurant */ ?>
<p class="admin-muted">
  <a href="/admin/restorani">← Restorani</a> ·
  <a href="/admin/restorani/<?= e($restaurant['id']) ?>">Profil</a> ·
  <a href="/admin/restorani/<?= e($restaurant['id']) ?>/meni">Cjenovnik</a>
</p>
<h1>QR meni — <?= e($restaurant['name']) ?></h1>
<?php include __DIR__ . '/../partials/restaurant-qr-card.php'; ?>
<div class="admin-card" style="max-width:560px;margin-top:1rem">
  <h3 style="margin-top:0">Kako koristiti</h3>
  <ol style="margin:0;padding-left:1.2rem;line-height:1.7">
    <li>Preuzmite PNG i odštampajte (A6 ili A5 za stolove)</li>
    <li>Kratki link <code>/r/<?= e($restaurant['qrCode']) ?></code> radi isto kao puni URL</li>
    <li>Kada promijenite cijene u cjenovniku, QR ostaje isti — ažurira se sadržaj</li>
  </ol>
</div>
