<?php
$shareUrl = $shareUrl ?? ($canonical ?? config('site_url'));
$shareTitle = $shareTitle ?? ($title ?? config('site_name'));
$encodedUrl = rawurlencode($shareUrl);
$encodedTitle = rawurlencode($shareTitle);
?>
<div class="share-bar" data-share-url="<?= e($shareUrl) ?>" data-share-title="<?= e($shareTitle) ?>">
  <div class="share-bar__top">
    <span class="share-bar__label">Podijeli</span>
    <div class="share-bar__actions">
      <a class="share-bar__btn" href="https://wa.me/?text=<?= $encodedTitle ?>%20<?= $encodedUrl ?>" target="_blank" rel="noopener noreferrer" aria-label="WhatsApp" title="WhatsApp">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.435 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
      </a>
      <a class="share-bar__btn" href="viber://forward?text=<?= $encodedTitle ?>%20<?= $encodedUrl ?>" aria-label="Viber" title="Viber">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M11.4 0C9.4 0 .8.4.1 9.7c-.5 7 5.4 12.4 9.5 13.2V24l3.4-3.7c4.5-.4 8.4-3.5 8.9-8.3C22.5 2.3 13.4 0 11.4 0zm.5 17.9c-1.2 0-2.1-.2-3-.5l-.8-.4-1.4.8.3-1.5-.5-.8c-.8-1.2-1.2-2.6-1.2-4.1 0-4.1 3.4-7.4 7.5-7.4s7.5 3.3 7.5 7.4-3.4 7.5-7.4 7.5zm4.1-5.5c-.1-.2-.6-.3-1.2-.6s-.7-.3-1-.2-.5.5-.9.9c-.3.3-.5.4-.9.2-.4-.2-1.5-.6-2.8-1.8-1-1-1.7-2.2-1.9-2.6s0-.6.2-.7c.2-.2.4-.4.6-.6.2-.2.3-.3.4-.5.1-.2 0-.4 0-.5s-.9-2.2-1.3-3c-.3-.8-.7-.7-.9-.7h-.8c-.3 0-.7.1-1.1.5s-1.4 1.4-1.4 3.3 1.4 3.9 1.6 4.1c.2.3 2.8 4.3 6.8 6 1 .4 1.7.7 2.3.9.9.3 1.8.3 2.5.2.8-.1 2.3-.9 2.6-1.8.3-.9.3-1.6.2-1.8z"/></svg>
      </a>
      <a class="share-bar__btn" href="https://www.facebook.com/sharer/sharer.php?u=<?= $encodedUrl ?>" target="_blank" rel="noopener noreferrer" aria-label="Facebook" title="Facebook">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
      </a>
      <a class="share-bar__btn" href="https://twitter.com/intent/tweet?url=<?= $encodedUrl ?>&text=<?= $encodedTitle ?>" target="_blank" rel="noopener noreferrer" aria-label="X" title="X">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-4.714-6.231-5.401 6.231H2.744l7.727-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
      </a>
      <span class="share-bar__sep" aria-hidden="true"></span>
      <button type="button" class="share-bar__btn" id="btn-copy-link" aria-label="Kopiraj link" title="Kopiraj link">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
      </button>
      <button type="button" class="share-bar__btn share-bar__btn--later" id="btn-read-later" aria-label="Sačuvaj za kasnije" title="Za kasnije">
        <svg class="share-bar__star" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M19 21l-7-4-7 4V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>
        <span class="share-bar__later-text visually-hidden">Za kasnije</span>
      </button>
      <span class="share-bar__sep" aria-hidden="true"></span>
      <button type="button" class="share-bar__btn share-bar__font" id="btn-font-down" aria-label="Manji tekst">A−</button>
      <button type="button" class="share-bar__btn share-bar__font" id="btn-font-up" aria-label="Veći tekst">A+</button>
    </div>
  </div>
</div>
