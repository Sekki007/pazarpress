<?php
$shareUrl = $shareUrl ?? ($canonical ?? config('site_url'));
$shareTitle = $shareTitle ?? ($title ?? config('site_name'));
$encodedUrl = rawurlencode($shareUrl);
$encodedTitle = rawurlencode($shareTitle);
?>
<div class="share-bar" data-share-url="<?= e($shareUrl) ?>" data-share-title="<?= e($shareTitle) ?>">
  <div class="share-bar__block">
    <span class="share-bar__label">Podijeli</span>
    <div class="share-bar__row">
      <a class="share-bar__btn share-bar__btn--wa" href="https://wa.me/?text=<?= $encodedTitle ?>%20<?= $encodedUrl ?>" target="_blank" rel="noopener noreferrer">
        <span class="share-bar__icon" aria-hidden="true">💬</span>
        <span>WhatsApp</span>
      </a>
      <a class="share-bar__btn share-bar__btn--viber" href="viber://forward?text=<?= $encodedTitle ?>%20<?= $encodedUrl ?>">
        <span class="share-bar__icon" aria-hidden="true">📞</span>
        <span>Viber</span>
      </a>
      <a class="share-bar__btn share-bar__btn--fb" href="https://www.facebook.com/sharer/sharer.php?u=<?= $encodedUrl ?>" target="_blank" rel="noopener noreferrer">
        <span class="share-bar__icon" aria-hidden="true">f</span>
        <span>Facebook</span>
      </a>
      <a class="share-bar__btn share-bar__btn--x" href="https://twitter.com/intent/tweet?url=<?= $encodedUrl ?>&text=<?= $encodedTitle ?>" target="_blank" rel="noopener noreferrer">
        <span class="share-bar__icon" aria-hidden="true">𝕏</span>
        <span>Objavi</span>
      </a>
    </div>
  </div>
  <div class="share-bar__divider" aria-hidden="true"></div>
  <div class="share-bar__block share-bar__block--tools">
    <span class="share-bar__label">Sačuvaj</span>
    <div class="share-bar__row">
      <button type="button" class="share-bar__btn share-bar__btn--copy" id="btn-copy-link">
        <span class="share-bar__icon" aria-hidden="true">⧉</span>
        <span>Kopiraj link</span>
      </button>
      <button type="button" class="share-bar__btn share-bar__btn--later" id="btn-read-later">
        <span class="share-bar__icon share-bar__icon--star" aria-hidden="true">☆</span>
        <span class="share-bar__later-text">Za kasnije</span>
      </button>
    </div>
  </div>
</div>
