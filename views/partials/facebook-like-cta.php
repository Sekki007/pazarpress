<?php
$fbPage = facebook_page_url();
if (!$fbPage) {
    return;
}
?>
<a href="<?= e($fbPage) ?>" class="fb-like-cta" target="_blank" rel="noopener noreferrer">
  <span class="fb-like-cta__text">
    <strong>Pratite nas na Facebooku</strong>
    <span class="fb-like-cta__sub">Pazar Press — lokalne vesti</span>
  </span>
  <span class="fb-like-cta__go">Otvori →</span>
</a>
