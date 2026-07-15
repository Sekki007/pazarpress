<?php
/** @var array $user @var array $cfg @var array $queue @var array $categories @var array $authors @var int $importedCount @var int $breakingCount @var string $tab @var string $tgConnectUrl @var string $tgWebhookUrl @var list<string> $tgChats @var array|string|null $tgWebhookInfo */
$feeds = $cfg['feeds_map'] ?? [];
if (!$feeds) {
    $feeds = [['url' => '', 'type' => 'rss', 'cat' => '', 'breaking_publish' => '0']];
}
$seenCnt = count($cfg['seen_guids'] ?? []);
$log = $cfg['log'] ?? [];
$factTests = is_array($cfg['fact_protection_tests'] ?? null) ? $cfg['fact_protection_tests'] : null;
$interval = (int) ($cfg['interval_minutes'] ?? 180);
$maxFetch = (int) ($cfg['max_fetch_per_run'] ?? 20);
$hasApiKey = AutoVestiConfig::hasConfiguredApiKey();
$base = '/admin/auto-vesti';
?>
<div class="admin-page-head">
  <h1>Auto Vesti Manual</h1>
  <p class="admin-muted">Povuci vesti u red → pregledaj → AI + objavi ili odbij. Port za Pazar Press CMS.</p>
</div>

<div class="avc-admin-stats" style="display:grid;grid-template-columns:repeat(5,1fr);gap:12px;margin-bottom:20px">
  <div class="admin-card" style="margin:0;padding:14px"><strong style="font-size:1.5rem;color:#1d4ed8"><?= count($queue) ?></strong><br><span class="admin-muted">U redu</span></div>
  <div class="admin-card" style="margin:0;padding:14px"><strong style="font-size:1.5rem;color:#059669"><?= (int) $importedCount ?></strong><br><span class="admin-muted">Importovanih</span></div>
  <div class="admin-card" style="margin:0;padding:14px"><strong style="font-size:1.5rem;color:#dc2626"><?= (int) $breakingCount ?></strong><br><span class="admin-muted">Hitnih</span></div>
  <div class="admin-card" style="margin:0;padding:14px"><strong style="font-size:1.5rem;color:#7c3aed"><?= $seenCnt ?></strong><br><span class="admin-muted">GUID arhiva</span></div>
  <div class="admin-card" style="margin:0;padding:14px"><strong style="font-size:1.2rem"><?= e($cfg['last_fetch_at'] ?? $cfg['last_run_at'] ?? '—') ?></strong><br><span class="admin-muted">Zadnje povlačenje</span></div>
</div>

<nav class="admin-tabs" style="display:flex;gap:8px;margin-bottom:20px;border-bottom:1px solid #e5e7eb;padding-bottom:0">
  <a href="<?= e($base) ?>?tab=queue" class="admin-btn btn-sm<?= $tab === 'queue' ? ' admin-btn--primary' : '' ?>" style="border-radius:8px 8px 0 0;margin-bottom:-1px">
    Red čekanja<?php if ($queue): ?> <span style="background:#e63946;color:#fff;font-size:11px;padding:1px 7px;border-radius:10px;margin-left:4px"><?= count($queue) ?></span><?php endif; ?>
  </a>
  <a href="<?= e($base) ?>?tab=settings" class="admin-btn btn-sm<?= $tab === 'settings' ? ' admin-btn--primary' : '' ?>" style="border-radius:8px 8px 0 0;margin-bottom:-1px">Podešavanja</a>
  <a href="<?= e($base) ?>?tab=telegram" class="admin-btn btn-sm<?= $tab === 'telegram' ? ' admin-btn--primary' : '' ?>" style="border-radius:8px 8px 0 0;margin-bottom:-1px">Telegram</a>
  <a href="<?= e($base) ?>?tab=log" class="admin-btn btn-sm<?= $tab === 'log' ? ' admin-btn--primary' : '' ?>" style="border-radius:8px 8px 0 0;margin-bottom:-1px">Log</a>
</nav>

<?php if ($tab === 'queue'): ?>
<div class="admin-card">
  <div style="display:flex;align-items:center;gap:16px;margin-bottom:20px;flex-wrap:wrap">
    <form method="post"><?= csrf_field() ?><button type="submit" name="avc_fetch_now" class="admin-btn admin-btn--primary">Povuci vesti sada</button></form>
    <span class="admin-muted" style="font-size:13px">Cron samo povlači vesti u red (svakih <?= $interval ?> min) — AI se ne poziva automatski.</span>
  </div>

  <?php if (!$queue): ?>
    <div style="background:#f9fafb;border:1px dashed #d1d5db;border-radius:10px;padding:40px;text-align:center;color:#6b7280">
      <p>Red čekanja je prazan.</p>
      <p>Klikni <strong>Povuci vesti sada</strong> da učitaš najnovije vesti sa feedova.</p>
    </div>
  <?php else: ?>
    <form method="post" id="avc-queue-form">
      <?= csrf_field() ?>
      <div style="display:flex;align-items:center;gap:16px;margin-bottom:16px;padding:12px 16px;background:#f9fafb;border-radius:8px;flex-wrap:wrap">
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
          <input type="checkbox" id="avc-check-all" checked>
          <strong>Izaberi sve</strong>
        </label>
        <span class="admin-muted" style="font-size:13px"><span id="avc-count"><?= count($queue) ?></span> izabrano</span>
        <div style="margin-left:auto;display:flex;gap:8px;flex-wrap:wrap">
          <button type="submit" name="avc_process_selected" class="admin-btn admin-btn--primary" onclick="return avcConfirmProcess()">AI + objavi izabrane</button>
          <button type="submit" name="avc_process_selected_force" class="admin-btn" onclick="return confirm('Potvrđuješ ručnu objavu i kada fact lock prijavi grešku?')">Potvrdi i objavi (ručno)</button>
          <button type="submit" name="avc_reject_selected" class="admin-btn" onclick="return confirm('Odbiti izabrane vesti? Neće se ponovo pojaviti.')">Odbij izabrane</button>
        </div>
      </div>

      <div style="display:flex;flex-direction:column;gap:12px">
        <?php foreach ($queue as $row):
          $guid = (string) ($row['guid'] ?? '');
          $title = (string) ($row['title'] ?? '');
          $preview = (string) ($row['preview'] ?? '');
          if (class_exists('AutoVestiFetcher', false)) {
              $preview = AutoVestiFetcher::cleanText($preview);
          }
          $img = (string) ($row['image_url'] ?? '');
          $link = (string) ($row['link'] ?? '');
          $source = (string) ($row['source_host'] ?? '');
          $pub = AutoVestiQueue::formatPubDate((string) ($row['pub_date'] ?? ''));
          $fetched = (string) ($row['fetched_at'] ?? '');
          $factReport = is_array($row['fact_report'] ?? null) ? $row['fact_report'] : null;
          $factStatus = (string) ($row['fact_status'] ?? '');
          $entityAnalysis = is_array($row['entity_analysis'] ?? null) ? $row['entity_analysis'] : null;
          $factLock = is_array($row['fact_lock'] ?? null) ? $row['fact_lock'] : null;
          $mustInclude = is_array($factReport['must_include'] ?? null)
              ? $factReport['must_include']
              : (is_array($factLock['must_include'] ?? null) ? $factLock['must_include'] : null);
          $exactPersons = array_values(array_filter(array_map(
              'strval',
              (array) ($factLock['protected']['persons_exact'] ?? $factLock['protected']['persons'] ?? [])
          )));
          $styleReport = is_array($row['style_report'] ?? null) ? $row['style_report'] : null;
          $grammarReport = is_array($row['grammar_report'] ?? null) ? $row['grammar_report'] : null;
          $seoReport = is_array($row['seo_report'] ?? null) ? $row['seo_report'] : null;
        ?>
        <article class="avc-card" style="display:grid;grid-template-columns:40px 140px 1fr;gap:16px;background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:14px;align-items:start">
          <label style="padding-top:4px"><input type="checkbox" name="queue_ids[]" value="<?= e($guid) ?>" class="avc-queue-cb" checked style="width:18px;height:18px"></label>
          <div style="width:140px;height:90px;border-radius:8px;overflow:hidden;background:#f3f4f6">
            <?php if ($img): ?>
              <img src="<?= e($img) ?>" alt="" loading="lazy" style="width:100%;height:100%;object-fit:cover;display:block">
            <?php else: ?>
              <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;font-size:11px;color:#9ca3af">Nema slike</div>
            <?php endif; ?>
          </div>
          <div>
            <h3 style="margin:0 0 6px;font-size:15px;line-height:1.35"><?= e($title) ?></h3>
            <?php if ($preview): ?><p style="margin:0 0 10px;font-size:13px;color:#4b5563;line-height:1.55"><?= e($preview) ?></p><?php endif; ?>
            <div style="display:flex;flex-wrap:wrap;gap:10px 16px;font-size:12px;color:#6b7280;align-items:center">
              <?php if ($source): ?><span style="font-weight:600;color:#374151">&#127760; <?= e($source) ?></span><?php endif; ?>
              <span>&#128197; <?= e($pub) ?></span>
              <?php if ($fetched): ?><span>&#128229; <?= e($fetched) ?></span><?php endif; ?>
              <?php if ($link): ?><a href="<?= e($link) ?>" target="_blank" rel="noopener" style="color:#1d4ed8;font-weight:500;text-decoration:none">Original &#8599;</a><?php endif; ?>
            </div>
            <?php if ($factReport): ?>
              <div style="margin-top:8px;padding:8px;border-radius:8px;background:#f9fafb;font-size:12px">
                <strong>Fact check:</strong>
                <span style="font-weight:600;<?= ($factReport['status'] ?? '') === 'error' ? 'color:#b91c1c' : (($factReport['status'] ?? '') === 'warning' ? 'color:#b45309' : 'color:#047857') ?>">
                  <?= strtoupper((string) ($factReport['status'] ?? $factStatus ?: 'ok')) ?>
                </span>
                · risk <?= (int) ($factReport['risk_score'] ?? 0) ?>
                · <?= e((string) ($factReport['reason'] ?? 'ok')) ?>
                <?php if (!empty($factReport['issues']) && is_array($factReport['issues'])): ?>
                  <?php $firstIssue = $factReport['issues'][0]; ?>
                  <div style="margin-top:6px;color:#b91c1c">
                    Original: <?= e((string) ($firstIssue['original'] ?? '')) ?><br>
                    AI: <?= e((string) ($firstIssue['ai'] ?? '')) ?><br>
                    Status: <?= e((string) ($firstIssue['type'] ?? 'GREŠKA')) ?>
                  </div>
                <?php endif; ?>
                <?php if (!empty($factReport['fact_diff']) && is_array($factReport['fact_diff'])): ?>
                  <?php $diff = $factReport['fact_diff']; ?>
                  <div style="margin-top:8px;padding:8px;border-radius:6px;background:#fff;font-size:12px;border:1px solid #e5e7eb">
                    <strong>Fact Diff:</strong>
                    <span style="font-weight:600;<?= ($diff['status'] ?? 'ok') === 'warning' ? 'color:#b45309' : 'color:#047857' ?>">
                      <?= strtoupper((string) ($diff['status'] ?? 'ok')) ?>
                    </span><br>
                    SOURCE: <?= e(implode(', ', array_slice((array) ($diff['source_numbers'] ?? []), 0, 8))) ?: 'nema' ?><br>
                    OUTPUT: <?= e(implode(', ', array_slice((array) ($diff['output_numbers'] ?? []), 0, 8))) ?: 'nema' ?><br>
                    Dodati brojevi: <?= e(implode(', ', array_slice((array) ($diff['added_numbers'] ?? []), 0, 8))) ?: 'nema' ?><br>
                    Nedostaje: <?= e(implode(', ', array_slice((array) ($diff['missing_numbers'] ?? []), 0, 8))) ?: 'nema' ?>
                  </div>
                <?php endif; ?>
              </div>
            <?php endif; ?>
            <?php if ($styleReport): ?>
              <div style="margin-top:8px;padding:8px;border-radius:8px;background:#f5f3ff;font-size:12px">
                <strong>Style check:</strong>
                <span style="font-weight:600;<?= ($styleReport['status'] ?? '') === 'warning' ? 'color:#b45309' : 'color:#047857' ?>">
                  <?= strtoupper((string) ($styleReport['status'] ?? 'ok')) ?>
                </span>
                <?php if (!empty($styleReport['applied'])): ?> · primenjen<?php endif; ?>
                <?php if (!empty($styleReport['checks']) && is_array($styleReport['checks'])): ?>
                  <div style="margin-top:6px;color:#4b5563">
                    <?php foreach ($styleReport['checks'] as $ck => $ok): ?>
                      <?= !empty($ok) ? '✓' : '✗' ?> <?= e((string) $ck) ?><br>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
              </div>
            <?php endif; ?>
            <?php if ($grammarReport): ?>
              <div style="margin-top:8px;padding:8px;border-radius:8px;background:#ecfeff;font-size:12px">
                <strong>Grammar check:</strong>
                <span style="font-weight:600;<?= ($grammarReport['status'] ?? '') === 'warning' ? 'color:#b45309' : 'color:#047857' ?>">
                  <?= strtoupper((string) ($grammarReport['status'] ?? 'ok')) ?>
                </span>
                <?php if (!empty($grammarReport['applied'])): ?> · primenjen<?php endif; ?>
                <?php if (!empty($grammarReport['issues']) && is_array($grammarReport['issues'])): ?>
                  <?php $gIssue = $grammarReport['issues'][0]; ?>
                  <div style="margin-top:6px;color:#b45309">
                    <?= e((string) ($gIssue['wrong'] ?? '')) ?> → <?= e((string) ($gIssue['hint'] ?? '')) ?>
                  </div>
                <?php endif; ?>
              </div>
            <?php endif; ?>
            <?php if ($seoReport): ?>
              <div style="margin-top:8px;padding:8px;border-radius:8px;background:#fefce8;font-size:12px">
                <strong>SEO:</strong>
                <?= e((string) ($seoReport['seo_title'] ?? '')) ?><br>
                <span class="admin-muted">Slug:</span> <?= e((string) ($seoReport['slug'] ?? '')) ?>
                · Focus: <?= e((string) ($seoReport['focus_keyphrase'] ?? '')) ?>
                <?php if (!empty($seoReport['secondary_keywords']) && is_array($seoReport['secondary_keywords'])): ?>
                  <br><span class="admin-muted">Keywords:</span> <?= e(implode(', ', array_slice($seoReport['secondary_keywords'], 0, 6))) ?>
                <?php endif; ?>
              </div>
            <?php endif; ?>
            <?php if ($entityAnalysis): ?>
              <div style="margin-top:8px;padding:8px;border-radius:8px;background:#fff7ed;font-size:12px">
                <strong>Entity analysis:</strong>
                <span style="font-weight:600;<?= ($entityAnalysis['status'] ?? '') === 'needs_review' ? 'color:#b45309' : 'color:#047857' ?>">
                  <?= strtoupper((string) ($entityAnalysis['status'] ?? 'ok')) ?>
                </span>
                · risk <?= (int) ($entityAnalysis['risk_score'] ?? 0) ?>
                · <?= e((string) ($entityAnalysis['reason'] ?? 'none')) ?>
              </div>
            <?php endif; ?>
            <?php if ($exactPersons): ?>
              <div style="margin-top:8px;padding:8px;border-radius:8px;background:#eff6ff;font-size:12px">
                <strong>Exact match osobe:</strong> <?= e(implode(' · ', array_slice($exactPersons, 0, 8))) ?>
              </div>
            <?php endif; ?>
            <?php if ($mustInclude): ?>
              <div style="margin-top:8px;padding:8px;border-radius:8px;background:#f0fdf4;font-size:12px">
                <strong>MUST INCLUDE:</strong>
                <?php
                  $mustLabels = ['dates' => 'datumi', 'results' => 'rezultati', 'statistics' => 'statistika', 'medals' => 'medalje', 'functions' => 'funkcije', 'mandates' => 'mandat'];
                  $mustParts = [];
                  foreach ($mustInclude as $cat => $items) {
                      if (!is_array($items) || !$items) {
                          continue;
                      }
                      $mustParts[] = ($mustLabels[$cat] ?? $cat) . ': ' . implode(', ', array_slice($items, 0, 6));
                  }
                  echo e(implode(' · ', $mustParts));
                ?>
              </div>
            <?php endif; ?>
            <div style="margin-top:8px;padding:8px;border:1px dashed #d1d5db;border-radius:8px">
              <form method="post" style="display:flex;gap:6px;flex-wrap:wrap;align-items:center">
                <?= csrf_field() ?>
                <input type="hidden" name="fact_guid" value="<?= e($guid) ?>">
                <input type="text" name="fact_wrong" class="admin-input" style="width:180px" placeholder="Pogrešno ime">
                <input type="text" name="fact_right" class="admin-input" style="width:180px" placeholder="Tačno ime">
                <button type="submit" name="avc_entity_fix" class="admin-btn btn-sm">Sačuvaj korekciju entiteta</button>
              </form>
            </div>
          </div>
        </article>
        <?php endforeach; ?>
      </div>

      <div style="display:flex;gap:8px;margin-top:16px;flex-wrap:wrap">
        <button type="submit" name="avc_process_selected" class="admin-btn admin-btn--primary" onclick="return avcConfirmProcess()">AI + objavi izabrane</button>
        <button type="submit" name="avc_process_selected_force" class="admin-btn" onclick="return confirm('Potvrđuješ ručnu objavu i kada fact lock prijavi grešku?')">Potvrdi i objavi (ručno)</button>
        <button type="submit" name="avc_reject_selected" class="admin-btn" onclick="return confirm('Odbiti izabrane vesti?')">Odbij izabrane</button>
      </div>
    </form>

    <form method="post" style="margin-top:16px" onsubmit="return confirm('Isprazniti ceo red bez obrade?')">
      <?= csrf_field() ?>
      <button type="submit" name="avc_clear_queue" class="admin-btn">Isprazni red</button>
    </form>
  <?php endif; ?>
</div>

<?php elseif ($tab === 'settings'): ?>
<form method="post">
<?= csrf_field() ?>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
  <div class="admin-card">
    <h2 style="margin-top:0">AI</h2>
      <table class="admin-form-table">
        <tr><th>Provider</th><td>
          <select name="ai_provider" id="avc-ai-provider" class="admin-input">
            <option value="claude" <?= ($cfg['ai_provider'] ?? 'claude') === 'claude' ? 'selected' : '' ?>>Claude</option>
            <option value="openai" <?= ($cfg['ai_provider'] ?? '') === 'openai' ? 'selected' : '' ?>>OpenAI</option>
          </select>
        </td></tr>
        <tr id="row-claude-key"><th>Claude API</th><td>
          <input type="password" name="api_key" value="" class="admin-input" style="width:100%" autocomplete="new-password" placeholder="<?= ($cfg['ai_provider'] ?? 'claude') === 'claude' && $hasApiKey ? '•••••••• (sačuvano — ostavi prazno)' : 'sk-ant-...' ?>">
          <p class="admin-muted" style="font-size:12px;margin:4px 0 0">Ostavi prazno da zadržiš postojeći ključ.</p>
        </td></tr>
        <tr id="row-claude-model"><th>Claude model</th><td>
          <select name="claude_model" class="admin-input" style="width:100%">
            <?php
            $cm = $cfg['claude_model'] ?? 'claude-sonnet-4-20250514';
            foreach ([
                'claude-haiku-4-5-20251001' => 'Claude Haiku 4.5',
                'claude-sonnet-4-20250514' => 'Claude Sonnet 4',
                'claude-opus-4-6' => 'Claude Opus 4.6',
            ] as $v => $l): ?>
            <option value="<?= e($v) ?>" <?= $cm === $v ? 'selected' : '' ?>><?= e($l) ?></option>
            <?php endforeach; ?>
          </select>
        </td></tr>
        <tr id="row-openai-key" style="display:none"><th>OpenAI API</th><td>
          <input type="password" name="openai_api_key" value="" class="admin-input" style="width:100%" autocomplete="new-password" placeholder="<?= ($cfg['ai_provider'] ?? '') === 'openai' && $hasApiKey ? '•••••••• (sačuvano — ostavi prazno)' : 'sk-...' ?>">
          <p class="admin-muted" style="font-size:12px;margin:4px 0 0">Ostavi prazno da zadržiš postojeći ključ.</p>
        </td></tr>
        <tr id="row-openai-model" style="display:none"><th>OpenAI model</th><td>
          <select name="openai_model" class="admin-input" style="width:100%">
            <?php
            $om = $cfg['openai_model'] ?? 'gpt-4.1-nano';
            $openaiOpts = [
                'gpt-4.1-nano' => 'GPT-4.1 Nano — $0.10/$0.40 (najjeftiniji)',
                'gpt-5.4-nano' => 'GPT-5.4 Nano — $0.20/$1.25 (~$0.002/vest) PREPORUČEN',
                'gpt-4o-mini' => 'GPT-4o Mini — $0.15/$0.60',
                'gpt-4.1-mini' => 'GPT-4.1 Mini — $0.40/$1.60',
                'gpt-4.1' => 'GPT-4.1 — $2.00/$8.00',
                'gpt-4o' => 'GPT-4o — $2.50/$10.00',
                'gpt-5-mini' => 'GPT-5 Mini — jači, skuplji',
                'gpt-5.2' => 'GPT-5.2 — premium kvalitet',
            ];
            foreach ($openaiOpts as $v => $l): ?>
            <option value="<?= e($v) ?>" <?= $om === $v ? 'selected' : '' ?>><?= e($l) ?></option>
            <?php endforeach;
            if ($om !== '' && !isset($openaiOpts[$om])): ?>
            <option value="<?= e($om) ?>" selected><?= e($om) ?> (sačuvano)</option>
            <?php endif; ?>
          </select>
        </td></tr>
      </table>
  </div>

  <div class="admin-card">
    <h2 style="margin-top:0">Objava</h2>
      <table class="admin-form-table">
        <tr><th>Jezik</th><td>
          <select name="lang" class="admin-input"><?php foreach (['bosanski','srpski','hrvatski','engleski'] as $l): ?>
          <option value="<?= $l ?>" <?= ($cfg['lang'] ?? 'bosanski') === $l ? 'selected' : '' ?>><?= ucfirst($l) ?></option>
          <?php endforeach; ?></select>
        </td></tr>
        <tr><th>Status</th><td>
          <select name="status" class="admin-input">
            <option value="draft" <?= ($cfg['status'] ?? 'draft') === 'draft' ? 'selected' : '' ?>>Draft — čeka urednika</option>
            <option value="publish" <?= ($cfg['status'] ?? '') === 'publish' ? 'selected' : '' ?>>Objavi odmah</option>
            <option value="pending" <?= ($cfg['status'] ?? '') === 'pending' ? 'selected' : '' ?>>Na pregled</option>
          </select>
        </td></tr>
        <tr><th>Dužina članka</th><td>
          <input type="number" name="article_min_words" value="<?= (int) ($cfg['article_min_words'] ?? 800) ?>" min="200" max="2000" step="50" class="admin-input" style="width:80px"> –
          <input type="number" name="article_max_words" value="<?= (int) ($cfg['article_max_words'] ?? 1500) ?>" min="400" max="3000" step="50" class="admin-input" style="width:80px"> reči
        </td></tr>
        <tr><th>Opcije</th><td>
          <?php foreach ([
            'use_image' => 'Preuzmi sliku',
            'use_faq' => 'FAQ blok',
            'use_internal_links' => 'Interni linkovi',
            'use_youtube' => 'Video embed',
            'use_dup_check' => 'Duplikat provjera',
            'use_full_article' => 'Puni tekst pri povlačenju',
            'show_source_footer' => 'Izvor na dnu članka',
            'fact_protection_enabled' => 'Fact lock (Source of Truth)',
            'fact_protection_enforce' => 'Blokiraj objavu kod ERROR',
            'fact_protection_block_on_new_person' => 'Nova osoba = warning/error',
            'post_process_editor_enabled' => 'Post-process editor (stil posle fact check)',
            'grammar_polish_enabled' => 'Grammar polish (padeži i pravopis)',
            'seo_layer_enabled' => 'SEO layer (meta posle članka)',
          ] as $k => $label): ?>
          <label style="display:block;margin-bottom:6px"><input type="checkbox" name="<?= e($k) ?>" value="1" <?= !empty($cfg[$k]) ? 'checked' : '' ?>> <?= e($label) ?></label>
          <?php endforeach; ?>
        </td></tr>
        <tr><th>Vijesti od</th><td><input type="date" name="from_date" value="<?= e($cfg['from_date'] ?? '') ?>" class="admin-input"></td></tr>
        <tr><th>Max / povlačenje</th><td><input type="number" name="max_fetch_per_run" value="<?= $maxFetch ?>" min="1" max="50" class="admin-input" style="width:80px"></td></tr>
        <tr><th>Auto povlačenje</th><td>
          <select name="interval_minutes" class="admin-input">
            <?php foreach ([15 => '15 min', 30 => '30 min', 60 => '1 sat', 180 => '3 sata', 360 => '6 sati', 720 => '12 sati'] as $v => $l): ?>
            <option value="<?= $v ?>" <?= $interval === $v ? 'selected' : '' ?>><?= e($l) ?></option>
            <?php endforeach; ?>
          </select>
        </td></tr>
        <tr><th>Autor</th><td>
          <select name="default_author_id" class="admin-input"><option value="">— Prvi u bazi —</option>
          <?php foreach ($authors as $a): ?>
          <option value="<?= e($a['id']) ?>" <?= ($cfg['default_author_id'] ?? '') === $a['id'] ? 'selected' : '' ?>><?= e($a['name']) ?></option>
          <?php endforeach; ?></select>
        </td></tr>
      </table>
  </div>
</div>

<div class="admin-card" style="margin-top:20px">
  <h2 style="margin-top:0">Feedovi</h2>
  <div id="avc-feeds">
    <?php foreach ($feeds as $i => $row): ?>
    <div class="avc-feed-row" style="display:grid;grid-template-columns:2fr 1fr 1.2fr auto auto;gap:8px;margin-bottom:8px;align-items:center;padding:8px;background:#f9fafb;border-radius:6px">
      <input type="url" name="feed_url[]" value="<?= e($row['url'] ?? '') ?>" placeholder="https://portal.rs/feed" class="admin-input">
      <select name="feed_type[]" class="admin-input">
        <option value="rss" <?= ($row['type'] ?? 'rss') === 'rss' ? 'selected' : '' ?>>RSS</option>
        <option value="scraper" <?= ($row['type'] ?? '') === 'scraper' ? 'selected' : '' ?>>Scraper</option>
        <option value="wp_rest" <?= ($row['type'] ?? '') === 'wp_rest' ? 'selected' : '' ?>>WP REST</option>
      </select>
      <select name="feed_cat[]" class="admin-input">
        <option value="">— Rubrika —</option>
        <?php foreach ($categories as $c): ?>
        <option value="<?= e($c['id']) ?>" <?= ($row['cat'] ?? '') === $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <label><input type="checkbox" name="feed_bp[<?= $i ?>]" value="1" <?= !empty($row['breaking_publish']) && $row['breaking_publish'] === '1' ? 'checked' : '' ?>> Hitna→live</label>
      <button type="button" onclick="this.closest('.avc-feed-row').remove()" class="admin-btn btn-sm" style="color:#c00;border:none;background:none;font-size:22px">&times;</button>
    </div>
    <?php endforeach; ?>
  </div>
  <button type="button" id="avc-add-feed" class="admin-btn btn-sm">+ Dodaj feed</button>
  <p style="margin-top:16px"><button type="submit" name="avc_save" class="admin-btn admin-btn--primary">Sačuvaj podešavanja</button></p>
</div>
</form>

<div class="admin-card" style="margin-top:16px">
  <h3 style="margin-top:0">Alati</h3>
  <div style="display:flex;flex-wrap:wrap;gap:8px">
    <form method="post" onsubmit="return confirm('Reset GUID arhive?')"><?= csrf_field() ?><button name="avc_clear_seen" class="admin-btn">Reset duplikata</button></form>
    <form method="post" onsubmit="return confirm('Ukloniti video embede?')"><?= csrf_field() ?><button name="avc_cleanup_videos" class="admin-btn" style="border-color:#dc2626;color:#dc2626">Ukloni video okvire</button></form>
    <form method="post"><?= csrf_field() ?><button name="avc_fact_run_tests" class="admin-btn">Pokreni fact testove</button></form>
  </div>
  <?php if ($factTests): ?>
    <div style="margin-top:10px;font-size:12px">
      Testovi: <strong><?= (int) ($factTests['passed'] ?? 0) ?>/<?= (int) ($factTests['total'] ?? 0) ?></strong>
      <?php if (!empty($factTests['tests']) && is_array($factTests['tests'])): ?>
        <ul style="margin:6px 0 0 16px">
          <?php foreach ($factTests['tests'] as $t): ?>
            <li><?= e((string) ($t['name'] ?? 'TEST')) ?> — <?= !empty($t['passed']) ? 'OK' : 'FAIL' ?> (<?= e((string) ($t['reason'] ?? '')) ?>)</li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  <?php endif; ?>
  <p class="admin-muted" style="font-size:12px;margin-top:12px">Cron: <code>php database/auto-vesti-run.php</code><br>
  ili <code>GET /api/cron/auto-vesti?key=IMPORT_CRON_SECRET</code> — samo povlači u red.</p>
</div>

<?php elseif ($tab === 'telegram'): ?>
<?php
$hasTgToken = AutoVestiConfig::hasConfiguredTelegramToken();
$whUrl = is_array($tgWebhookInfo) ? (string) ($tgWebhookInfo['url'] ?? '') : '';
$whOk = $whUrl !== '' && str_contains($whUrl, '/api/avm/telegram');
$whErr = is_array($tgWebhookInfo) ? (string) ($tgWebhookInfo['last_error_message'] ?? '') : '';
?>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
  <div class="admin-card">
    <h2 style="margin-top:0">1. Kreiraj bota</h2>
    <ol class="admin-muted" style="font-size:13px;line-height:1.7;padding-left:18px">
      <li>Otvori <a href="https://t.me/BotFather" target="_blank" rel="noopener">@BotFather</a></li>
      <li>Pošalji <code>/newbot</code> i prati uputstva</li>
      <li>Kopiraj <strong>Bot Token</strong> i zalijepi ispod</li>
    </ol>
    <form method="post">
      <?= csrf_field() ?>
      <table class="admin-form-table">
        <tr><th>Bot Token</th><td>
          <input type="password" name="telegram_bot_token" value="" class="admin-input" style="width:100%" autocomplete="new-password" placeholder="<?= $hasTgToken ? '•••••••• (sačuvano — ostavi prazno)' : '123456789:ABC...' ?>">
          <p class="admin-muted" style="font-size:12px;margin:4px 0 0">Ostavi prazno da zadržiš postojeći token.</p>
        </td></tr>
        <tr><th>Obaveštenja</th><td>
          <label style="display:block;margin-bottom:6px"><input type="checkbox" name="telegram_notify" value="1" <?= !empty($cfg['telegram_notify']) ? 'checked' : '' ?>> Automatski pošalji poruku za svaku novu vest u redu</label>
        </td></tr>
        <tr><th>Ručna objava</th><td>
          <label style="display:block;margin-bottom:6px"><input type="checkbox" name="telegram_manual_publish" value="1" <?= !empty($cfg['telegram_manual_publish']) ? 'checked' : '' ?>> Dozvoli /objavi — tekst + slika sa telefona</label>
          <label style="display:block"><input type="checkbox" name="telegram_manual_use_ai" value="1" <?= !empty($cfg['telegram_manual_use_ai']) ? 'checked' : '' ?>> /objavi koristi AI (isključeno = tvoj tekst direktno)</label>
        </td></tr>
        <tr><th>Link vesti</th><td>
          <label><input type="checkbox" name="telegram_link_scrape" value="1" <?= !empty($cfg['telegram_link_scrape']) ? 'checked' : '' ?>> Dozvoli /link — preuzmi vest sa URL-a</label>
        </td></tr>
        <tr><th>Kategorija</th><td>
          <select name="telegram_manual_cat" class="admin-input">
            <option value="">— Podrazumijevano —</option>
            <?php foreach ($categories as $c): ?>
            <option value="<?= e($c['id']) ?>" <?= ($cfg['telegram_manual_cat'] ?? '') === $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </td></tr>
      </table>
      <p><button type="submit" name="avc_save_telegram" class="admin-btn admin-btn--primary">Sačuvaj Telegram</button></p>
    </form>
  </div>

  <div class="admin-card">
    <h2 style="margin-top:0">2. Poveži telefon</h2>
    <?php if (!$hasTgToken): ?>
      <p class="admin-muted">Prvo sačuvaj Bot Token.</p>
    <?php elseif ($tgConnectUrl): ?>
      <p><a href="<?= e($tgConnectUrl) ?>" target="_blank" rel="noopener" class="admin-btn admin-btn--primary">Otvori bota i pošalji /start</a></p>
      <p class="admin-muted" style="font-size:12px">Link važi 1 sat. Pošalji isti link svim urednicima.</p>
      <form method="post" style="margin-top:8px"><?= csrf_field() ?><button type="submit" name="avc_tg_new_link" class="admin-btn btn-sm">Generiši novi link</button></form>
    <?php else: ?>
      <p class="admin-muted">Token nije validan ili bot nije dostupan.</p>
    <?php endif; ?>

    <?php if ($tgChats): ?>
      <h3 style="margin:20px 0 8px;font-size:15px">Povezani urednici (<?= count($tgChats) ?>)</h3>
      <ul style="list-style:none;padding:0;margin:0">
        <?php foreach ($tgChats as $cid): ?>
        <li style="display:flex;align-items:center;gap:12px;padding:8px 0;border-bottom:1px solid #eee">
          <code><?= e($cid) ?></code>
          <form method="post" style="margin:0"><?= csrf_field() ?>
            <input type="hidden" name="chat_id" value="<?= e($cid) ?>">
            <button type="submit" name="avc_tg_disconnect" class="admin-btn btn-sm" style="color:#dc2626">Ukloni</button>
          </form>
        </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>
</div>

<div class="admin-card" style="margin-top:20px">
  <h2 style="margin-top:0">3. Webhook i test</h2>
  <p class="admin-muted" style="font-size:13px">Obavezno nakon promjene tokena ili uploada na server.</p>
  <p style="font-size:12px;word-break:break-all"><strong>Webhook URL:</strong> <code><?= e($tgWebhookUrl) ?></code></p>
  <?php if (is_array($tgWebhookInfo)): ?>
    <p class="admin-muted" style="font-size:13px">
      Status:
      <?php if ($whOk): ?>
        <span style="color:#059669;font-weight:600">Aktivan ✓</span>
      <?php else: ?>
        <span style="color:#dc2626;font-weight:600">Nije registrovan — klikni dugme ispod</span>
      <?php endif; ?>
      <?php if ($whErr): ?><br>Greška: <?= e($whErr) ?><?php endif; ?>
    </p>
  <?php endif; ?>
  <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:12px">
    <form method="post"><?= csrf_field() ?><button type="submit" name="avc_tg_set_webhook" class="admin-btn admin-btn--primary">Registruj webhook</button></form>
    <form method="post"><?= csrf_field() ?><button type="submit" name="avc_tg_test" class="admin-btn">Pošalji test poruku</button></form>
    <?php if ($queue): ?>
    <form method="post" onsubmit="return confirm('Poslati obavještenje za sve vesti u redu?')"><?= csrf_field() ?><button type="submit" name="avc_tg_notify_queue" class="admin-btn">Obavijesti postojeći red</button></form>
    <?php endif; ?>
  </div>
  <p class="admin-muted" style="font-size:12px;margin-top:16px">
    Komande: /link, /objavi, /objavi-ai, /next, /status, /fetch, /help, /otkazi<br>
    Dugmad: ✅ AI + Objavi · 📋 Original · ❌ Odbij · ⏸ Drži · 📂 Kategorija · 🖼 Slika
  </p>
</div>

<?php elseif ($tab === 'log'): ?>
<div class="admin-card">
  <form method="post" style="margin-bottom:12px"><?= csrf_field() ?><button name="avc_clear_log" class="admin-btn">Obriši log</button></form>
  <?php if (!$log): ?>
    <p class="admin-muted">Log je prazan.</p>
  <?php else: ?>
    <div style="max-height:600px;overflow:auto;font-size:12px">
      <table style="width:100%;border-collapse:collapse">
        <?php foreach ($log as $e):
          $m = $e['msg'];
          $cls = '';
          if (str_contains($m, 'DUPLIKAT')) $cls = 'background:#f5f3ff;color:#5b21b6';
          elseif (str_contains($m, 'U red')) $cls = 'background:#eff6ff;color:#1d4ed8';
          elseif (str_starts_with($m, 'OK') || str_starts_with($m, 'Slika')) $cls = 'background:#f0fdf4;color:#166534';
          elseif (str_contains($m, 'greška') || str_contains($m, 'ERROR')) $cls = 'background:#fef2f2;color:#991b1b';
        ?>
        <tr style="border-bottom:1px solid #eee;<?= $cls ?>"><td style="padding:4px 8px;white-space:nowrap;font-size:11px;vertical-align:top"><?= e($e['time']) ?></td><td style="padding:4px 8px;line-height:1.5"><?= e($m) ?></td></tr>
        <?php endforeach; ?>
      </table>
    </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<script>
function avcToggleProvider(v){
  ['row-claude-key','row-claude-model'].forEach(function(id){ var el=document.getElementById(id); if(el) el.style.display=v==='claude'?'':'none'; });
  ['row-openai-key','row-openai-model'].forEach(function(id){ var el=document.getElementById(id); if(el) el.style.display=v==='openai'?'':'none'; });
}
function avcUpdateCount(){
  var n = document.querySelectorAll('.avc-queue-cb:checked').length;
  var el = document.getElementById('avc-count');
  if(el) el.textContent = n;
}
function avcConfirmProcess(){
  var n = document.querySelectorAll('.avc-queue-cb:checked').length;
  if(n===0){ alert('Označi barem jednu vest.'); return false; }
  <?php if (!$hasApiKey): ?>
  alert('API ključ nije podešen. Idi na Podešavanja i unesi Claude ili OpenAI ključ.');
  return false;
  <?php endif; ?>
  return confirm('Poslati '+n+' vesti u AI i objaviti?');
}
document.addEventListener('DOMContentLoaded',function(){
  var s=document.getElementById('avc-ai-provider'); if(s){ avcToggleProvider(s.value); s.addEventListener('change',function(){ avcToggleProvider(this.value); }); }
  var all=document.getElementById('avc-check-all');
  if(all){
    all.addEventListener('change',function(){
      document.querySelectorAll('.avc-queue-cb').forEach(function(cb){ cb.checked=all.checked; });
      avcUpdateCount();
    });
  }
  document.querySelectorAll('.avc-queue-cb').forEach(function(cb){ cb.addEventListener('change', avcUpdateCount); });
  var addBtn=document.getElementById('avc-add-feed');
  if(addBtn){
    var cats=<?= json_encode(array_map(static fn ($c) => ['id' => $c['id'], 'name' => $c['name']], $categories), JSON_UNESCAPED_UNICODE) ?>;
    addBtn.addEventListener('click',function(){
      var n=document.querySelectorAll('#avc-feeds .avc-feed-row').length;
      var opts='<option value="">— Rubrika —</option>'; cats.forEach(function(c){ opts+='<option value="'+c.id+'">'+c.name+'</option>'; });
      var d=document.createElement('div'); d.className='avc-feed-row'; d.style.cssText='display:grid;grid-template-columns:2fr 1fr 1.2fr auto auto;gap:8px;margin-bottom:8px;align-items:center;padding:8px;background:#f9fafb;border-radius:6px';
      d.innerHTML='<input type="url" name="feed_url[]" placeholder="https://..." class="admin-input">'
        +'<select name="feed_type[]" class="admin-input"><option value="rss">RSS</option><option value="scraper">Scraper</option><option value="wp_rest">WP REST</option></select>'
        +'<select name="feed_cat[]" class="admin-input">'+opts+'</select>'
        +'<label><input type="checkbox" name="feed_bp['+n+']" value="1"> Hitna→live</label>'
        +'<button type="button" onclick="this.closest(\'.avc-feed-row\').remove()" class="admin-btn btn-sm" style="color:#c00;border:none;background:none;font-size:22px">&times;</button>';
      document.getElementById('avc-feeds').appendChild(d);
    });
  }
});
</script>
