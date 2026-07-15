<?php
/** @var array $restaurant @var string $publicUrl @var string $qrUrl @var string $shortUrl @var string $downloadUrl */
?>
<div class="admin-card rst-qr-card" style="text-align:center;max-width:440px">
  <h3 style="margin-top:0">QR meni za štampu</h3>
  <p class="admin-muted">Gosti skeniraju i otvaraju digitalni meni na mobitelu.</p>
  <img src="<?= e($qrUrl) ?>" alt="QR kod menija" width="260" height="260" style="border-radius:12px;border:1px solid var(--line,#e5e7eb)">
  <p style="margin:1rem 0 .35rem;word-break:break-all;font-size:.85rem">
    <a href="<?= e($publicUrl) ?>" target="_blank" rel="noopener"><?= e($publicUrl) ?></a>
  </p>
  <p class="admin-muted" style="font-size:.8rem;margin:0 0 1rem">Kratki link: <a href="<?= e($shortUrl) ?>" target="_blank"><?= e($shortUrl) ?></a></p>
  <p style="display:flex;gap:.5rem;justify-content:center;flex-wrap:wrap">
    <a href="<?= e($downloadUrl) ?>" class="admin-btn admin-btn--primary">⬇ Preuzmi QR (PNG)</a>
    <a href="<?= e($publicUrl) ?>" target="_blank" class="admin-btn">Pregled menija</a>
  </p>
</div>
