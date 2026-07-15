<h1><?= $id ? 'Uredi članak' : 'Novi članak' ?></h1>

<form method="post" class="admin-form cms-form" id="article-form">
  <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">

  <div class="cms-layout">
    <div class="cms-main">
      <label class="cms-field cms-field--title">
        <span>Naslov članka</span>
        <input class="admin-input cms-title-input" name="title" value="<?= e($article['title'] ?? '') ?>" required placeholder="Unesite naslov…">
      </label>

      <label class="cms-field">
        <span>Uvod (lead) — prikazuje se ispod naslova</span>
        <textarea class="admin-input" name="lead" rows="3" required placeholder="Kratak uvod u 1–2 rečenice…"><?= e($article['lead'] ?? '') ?></textarea>
      </label>

      <div class="cms-field">
        <span>Sadržaj članka</span>
        <textarea id="article-body" name="body" class="cms-editor-source"><?= e($article['body'] ?? '') ?></textarea>
        <p class="form-hint">Toolbar iznad polja: formatiranje, slike, linkovi, tabele. Dugme <strong>Source code</strong> za HTML.</p>
      </div>
    </div>

    <aside class="cms-sidebar">
      <div class="cms-panel">
        <h3>Objava</h3>
        <label>Status
          <select name="status" class="admin-input">
            <option value="DRAFT" <?= ($article['status'] ?? 'DRAFT') === 'DRAFT' ? 'selected' : '' ?>>Nacrt</option>
            <option value="PUBLISHED" <?= ($article['status'] ?? '') === 'PUBLISHED' ? 'selected' : '' ?>>Objavljeno</option>
          </select>
        </label>
        <label>Datum objave
          <input type="datetime-local" name="publishedAt" class="admin-input" value="<?= !empty($article['publishedAt']) ? e(date('Y-m-d\TH:i', strtotime($article['publishedAt']))) : '' ?>">
        </label>
        <label class="check"><input type="checkbox" name="isBreaking" <?= !empty($article['isBreaking']) ? 'checked' : '' ?>> Urgentno (breaking)</label>
        <?php if (Settings::get('auto_feature_today', true)): ?>
        <p class="admin-hint">Hero na početnoj se automatski rotira među današnjim vestima (Postavke → Početna stranica).</p>
        <?php else: ?>
        <label class="check"><input type="checkbox" name="isFeatured" <?= !empty($article['isFeatured']) ? 'checked' : '' ?>> Izdvojeno na početnoj</label>
        <?php endif; ?>
      </div>

      <div class="cms-panel">
        <h3>Kategorizacija</h3>
        <label>URL slug
          <input class="admin-input" name="slug" value="<?= e($article['slug'] ?? '') ?>" placeholder="auto iz naslova">
        </label>
        <label>Rubrika
          <select name="categoryId" class="admin-input" required>
            <?php foreach ($categories as $c): ?>
            <option value="<?= e($c['id']) ?>" <?= ($article['categoryId'] ?? '') === $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>Autor
          <select name="authorId" class="admin-input" required>
            <?php foreach ($authors as $a): ?>
            <option value="<?= e($a['id']) ?>" <?= ($article['authorId'] ?? '') === $a['id'] ? 'selected' : '' ?>><?= e($a['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>Tagovi
          <input class="admin-input" name="tags" value="<?= e($articleTags) ?>" placeholder="tag1, tag2">
        </label>
      </div>

      <div class="cms-panel">
        <h3>Naslovna slika</h3>
        <div id="coverPreview" class="cover-preview<?= empty($article['coverImage']) ? ' cover-preview--empty' : '' ?>">
          <?php if (!empty($article['coverImage'])): ?>
          <img src="<?= e($article['coverImage']) ?>" alt="Cover">
          <?php endif; ?>
        </div>
        <label class="cover-upload-btn">
          Odaberi sliku
          <input type="file" id="coverUpload" accept="image/jpeg,image/png,image/webp,image/gif" hidden>
        </label>
        <p id="coverUploadStatus" class="cover-upload-status" role="status"></p>
        <input type="hidden" name="coverImage" id="coverImage" value="<?= e($article['coverImage'] ?? '') ?>">
        <label>Opis slike
          <input class="admin-input" name="coverCaption" value="<?= e($article['coverCaption'] ?? '') ?>">
        </label>
      </div>

      <div class="cms-panel cms-panel--actions">
        <button type="submit" class="admin-btn admin-btn--primary admin-btn--block">Sačuvaj članak</button>
        <?php if ($id && $article): ?>
        <a href="/admin/preview/<?= e($article['slug']) ?>" target="_blank" class="admin-btn admin-btn--block">Pregled</a>
        <?php endif; ?>
        <a href="/admin/clanci" class="admin-btn admin-btn--block">Nazad na listu</a>
      </div>
    </aside>
  </div>
</form>

<?php if ($id): ?>
<form method="post" action="/admin/clanci/<?= e($id) ?>/delete" class="delete-form" onsubmit="return confirm('Obrisati članak?')">
  <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
  <button type="submit" class="admin-btn admin-btn--danger">Obriši članak</button>
</form>
<?php endif; ?>

<script src="/assets/js/admin-upload.js" defer></script>
<script src="https://cdn.jsdelivr.net/npm/tinymce@7.6.1/tinymce.min.js"></script>
<script src="/assets/js/admin-editor.js"></script>
