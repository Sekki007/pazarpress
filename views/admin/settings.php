<h1>Postavke sajta</h1>
<form method="post" class="admin-form">
  <?= csrf_field() ?>
  <fieldset>
    <legend>Opšte</legend>
    <label>Tagline (meta opis početne)
      <input type="text" name="site_tagline" class="admin-input" value="<?= e($settings['site_tagline']) ?>">
    </label>
    <label class="admin-check">
      <input type="checkbox" name="newsletter_confirm" <?= $settings['newsletter_confirm'] ? 'checked' : '' ?>>
      Newsletter zahtijeva potvrdu emailom (double opt-in)
    </label>
    <label>Default OG slika (JPG/PNG 1200×630, putanja ili URL)
      <input type="text" name="og_default_image" class="admin-input" value="<?= e($settings['og_default_image']) ?>" placeholder="/uploads/og-slika.jpg">
    </label>
    <p class="admin-hint">Koristi se pri dijeljenju početne i stranica bez cover slike. Uploadujte sliku u članak pa kopirajte putanju, npr. <code>/uploads/...</code></p>
    <label>Facebook stranica (za „Lajkujte nas” dugme)
      <input type="text" name="facebook_page_url" class="admin-input" value="<?= e($settings['facebook_page_url']) ?>" placeholder="sandzak.net ili https://facebook.com/sandzak.net">
    </label>
    <p class="admin-hint">Korisnik se preusmjerava na vašu FB stranicu da je lajkuje. Ostavite prazno da sakrijete dugme.</p>
  </fieldset>
  <fieldset>
    <legend>Automatski Facebook share</legend>
    <label class="admin-check">
      <input type="checkbox" name="facebook_auto_share" <?= !empty($settings['facebook_auto_share']) ? 'checked' : '' ?>>
      Automatski objavi novu vest na Facebook stranici pri objavi (ručno ili Auto Vesti)
    </label>
    <label>Facebook Page ID
      <input type="text" name="facebook_page_id" class="admin-input" value="<?= e($settings['facebook_page_id'] ?? '') ?>" placeholder="123456789012345">
    </label>
    <label>Page Access Token
      <input type="password" name="facebook_page_access_token" class="admin-input" value="" placeholder="<?= !empty($settings['facebook_page_access_token']) ? '•••••••• (sačuvan)' : 'EAA...' ?>" autocomplete="new-password">
    </label>
    <p class="admin-hint">Token ostaje sačuvan ako polje ostavite prazno. Možete ga staviti i u <code>.env</code> kao <code>FB_PAGE_ID</code> i <code>FB_PAGE_ACCESS_TOKEN</code>.</p>
    <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:10px">
      <button type="submit" name="fb_verify" value="1" class="admin-btn">Provjeri konekciju</button>
      <button type="submit" name="fb_share_latest" value="1" class="admin-btn admin-btn--primary">Pošalji zadnju vest (test)</button>
    </div>
  </fieldset>
  <fieldset>
    <legend>Analitika</legend>
    <label>Provajder
      <select name="analytics_provider" class="admin-input">
        <option value="" <?= $settings['analytics_provider'] === '' ? 'selected' : '' ?>>Isključeno</option>
        <option value="plausible" <?= $settings['analytics_provider'] === 'plausible' ? 'selected' : '' ?>>Plausible</option>
        <option value="matomo" <?= $settings['analytics_provider'] === 'matomo' ? 'selected' : '' ?>>Matomo</option>
      </select>
    </label>
    <label>ID / domena
      <input type="text" name="analytics_id" class="admin-input" value="<?= e($settings['analytics_id']) ?>" placeholder="sandzak.net ili https://matomo.example.com|1">
    </label>
    <p class="admin-hint">Plausible: unesite domenu. Matomo: URL|siteId (npr. https://analytics.example.com|1).</p>
  </fieldset>
  <fieldset>
    <legend>Digitalni meniji (restorani)</legend>
    <label class="admin-check">
      <input type="checkbox" name="restaurants_enabled" <?= !empty($settings['restaurants_enabled']) ? 'checked' : '' ?>>
      Uključi modul restorana na sajtu (/restorani, QR meniji, vlasnički panel)
    </label>
    <p class="admin-hint">Isključeno = portal radi normalno, bez linkova i widgeta za restorane. Kod ostaje spreman — uključite kad budete spremni za lansiranje.</p>
  </fieldset>
  <fieldset>
    <legend>Početna stranica</legend>
    <label class="admin-check">
      <input type="checkbox" name="auto_feature_today" <?= !empty($settings['auto_feature_today']) ? 'checked' : '' ?>>
      Automatski istakni današnje vesti u hero bloku (nasumična rotacija)
    </label>
    <label>Rotacija heroja (sati; 0 = samo pri novoj objavi)
      <input type="number" name="feature_rotate_hours" class="admin-input" style="width:80px" min="0" max="24"
             value="<?= (int) ($settings['feature_rotate_hours'] ?? 3) ?>">
    </label>
    <p class="admin-hint">Kada je uključeno, „Izdvojeno na početnoj” se automatski bira među vestima objavljenim danas. Ručni checkbox u članku se ignoriše dok je ovo aktivno.</p>
  </fieldset>
  <fieldset>
    <legend>Oglasi (HTML)</legend>
    <label>Sidebar (početna)
      <textarea name="ad_sidebar_html" class="admin-input" rows="4"><?= e($settings['ad_sidebar_html']) ?></textarea>
    </label>
    <label>Članak (ispod naslova)
      <textarea name="ad_article_html" class="admin-input" rows="4"><?= e($settings['ad_article_html']) ?></textarea>
    </label>
    <label>Početna (ispod heroja)
      <textarea name="ad_home_html" class="admin-input" rows="4"><?= e($settings['ad_home_html']) ?></textarea>
    </label>
    <p class="admin-hint">Zalijepite AdSense ili drugi embed kod. Ostavite prazno da sakrijete slot.</p>
  </fieldset>
  <button type="submit" class="admin-btn admin-btn--primary">Sačuvaj postavke</button>
</form>
