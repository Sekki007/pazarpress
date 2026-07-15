<h1>Pregled</h1>
<?php if (!empty($weakPassword)): ?>
<div class="flash flash--err">Default lozinka je još uvijek aktivna. <a href="/admin/profil">Promijenite je odmah</a> prije produkcije.</div>
<?php endif; ?>
<?php if (!empty($gdMissing)): ?>
<div class="flash flash--err">PHP GD ekstenzija nije uključena — WebP varijante slika neće raditi. Uključite <code>extension=gd</code> u php.ini.</div>
<?php endif; ?>
<form method="post" action="/admin/cache/flush" style="margin:.75rem 0 1.25rem">
  <?= csrf_field() ?>
  <button type="submit" class="admin-btn">Obriši keš stranice</button>
  <span class="admin-hint" style="margin-left:.5rem">Ako početna ne prikazuje nove vijesti</span>
</form>
<div class="stat-grid">
  <div class="stat-card"><span>Članci</span><strong><?= (int) $stats['articles'] ?></strong></div>
  <div class="stat-card"><span>Objavljeno</span><strong><?= (int) $stats['published'] ?></strong></div>
  <div class="stat-card"><span>Nacrti</span><strong><?= (int) $stats['drafts'] ?></strong></div>
  <div class="stat-card"><span>Komentari</span><strong><?= (int) $stats['comments'] ?></strong></div>
  <div class="stat-card"><span>Newsletter</span><strong><?= (int) $stats['subscribers'] ?></strong></div>
  <div class="stat-card"><span>Nove prijave</span><strong><?= (int) $stats['submissions'] ?></strong></div>
</div>
<h2>Nedavno uređivano</h2>
<table class="admin-table">
  <thead><tr><th>Naslov</th><th>Status</th><th>Ažurirano</th></tr></thead>
  <tbody>
    <?php foreach ($recent as $r): ?>
    <tr>
      <td><a href="/admin/clanci/<?= e($r['id']) ?>"><?= e($r['title']) ?></a></td>
      <td><?= e($r['status']) ?></td>
      <td><?= e(format_datetime((string) $r['updatedAt'])) ?></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
