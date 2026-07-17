<?php

declare(strict_types=1);

require_once __DIR__ . '/AutoVestiPostEditor.php';
require_once __DIR__ . '/AutoVestiGrammarPolish.php';
require_once __DIR__ . '/AutoVestiSeoLayer.php';

final class AutoVestiProcessor
{
    /**
     * @param list<string> $guids
     * @param array{mode?:string,editor?:array<string,mixed>|null,force_review?:bool} $args
     * @return array{ok:int,err:int,msg:string,posts:array<int,array<string,mixed>>,skip_reasons:array<int,string>}
     */
    public static function processSelected(array $guids, array $args = []): array
    {
        @set_time_limit(600);
        $guids = array_values(array_filter(array_map('strval', $guids)));
        if (!$guids) {
            return self::result(0, 0, 'Nijedna vest nije izabrana.');
        }

        $mode = ($args['mode'] ?? 'ai') === 'native' ? 'native' : 'ai';
        $forceReview = !empty($args['force_review']);
        $editor = isset($args['editor']) && is_array($args['editor']) ? $args['editor'] : null;
        $cfg = AutoVestiConfig::all();
        $provider = (string) ($cfg['ai_provider'] ?? 'claude');
        $apiKey = trim($provider === 'openai'
            ? (string) ($cfg['openai_api_key'] ?? '')
            : (string) ($cfg['api_key'] ?? ''));

        if ($mode === 'ai' && $apiKey === '') {
            return self::result(0, 0, 'API ključ nije podešen.');
        }

        $lang = (string) ($cfg['lang'] ?? 'bosanski');
        $status = self::mapStatus((string) ($cfg['status'] ?? 'draft'));
        $useFaq = !empty($cfg['use_faq']);
        $useLinks = !empty($cfg['use_internal_links']);
        $useDup = !empty($cfg['use_dup_check']);
        $useImg = !empty($cfg['use_image']);
        $factEnabled = !empty($cfg['fact_protection_enabled']);
        $factEnforce = !empty($cfg['fact_protection_enforce']);
        $factBlockNewPerson = !empty($cfg['fact_protection_block_on_new_person']);
        $postEditorEnabled = !empty($cfg['post_process_editor_enabled']);
        $grammarPolishEnabled = !empty($cfg['grammar_polish_enabled']);
        $seoLayerEnabled = !empty($cfg['seo_layer_enabled']);
        $existingPosts = ($mode === 'ai' && $useLinks) ? AutoVestiContent::recentPostsForLinking(40) : [];

        $guidMap = array_flip($guids);
        $toProcess = [];
        foreach (AutoVestiConfig::getQueue() as $row) {
            $guid = (string) ($row['guid'] ?? '');
            if ($guid !== '' && isset($guidMap[$guid])) {
                $toProcess[] = $row;
            }
        }
        if (!$toProcess) {
            return self::result(0, 0, 'Izabrane vesti nisu u redu.');
        }

        $ok = 0;
        $err = 0;
        $messages = [];
        $posts = [];
        $processedGuids = [];

        foreach ($toProcess as $row) {
            $styleReport = null;
            $grammarReport = null;
            $seoReport = null;
            $item = is_array($row['item'] ?? null) ? $row['item'] : [];
            $feedCat = trim((string) ($row['feed_cat'] ?? ''));
            $bpPublish = !empty($row['breaking_publish']) && $row['breaking_publish'] === '1';
            $postStatus = $status;
            $isBreaking = AutoVestiContent::isBreaking((string) ($row['title'] ?? '') . ' ' . (string) ($row['preview'] ?? ''));

            if ($useDup) {
                $preDup = AutoVestiDuplicate::checkItem($item ?: [
                    'title' => $row['title'] ?? '',
                    'link' => $row['link'] ?? '',
                ]);
                if ($preDup !== null) {
                    $messages[] = 'Duplikat (' . $preDup . '): ' . ($row['title'] ?? '');
                    AutoVestiConfig::log('DUPLIKAT (' . $preDup . '): ' . ($row['title'] ?? ''));
                    AutoVestiStats::record('skipped', ['reason' => 'duplicate_url']);
                    $err++;
                    continue;
                }
                if ($mode === 'ai') {
                    $dup = AutoVestiDuplicate::check((string) ($row['title'] ?? ''), $apiKey, $provider);
                    if ($dup['is_dup']) {
                        $messages[] = 'Sličan naslov (' . $dup['score'] . '%): ' . ($row['title'] ?? '');
                        AutoVestiStats::record('skipped', ['reason' => 'duplicate_title']);
                        $err++;
                        continue;
                    }
                }
            }

            if ($isBreaking && $bpPublish) {
                $postStatus = 'PUBLISHED';
            }

            $factLock = $factEnabled ? AutoVestiFacts::extractFactLock($item) : ['source_of_truth' => '', 'protected' => []];
            $savedCorrections = [];
            if (!empty($row['fact_corrections']) && is_array($row['fact_corrections'])) {
                foreach ($row['fact_corrections'] as $old => $new) {
                    $savedCorrections[(string) $old] = (string) $new;
                }
            }
            if ($savedCorrections) {
                $factLock = AutoVestiFacts::applyCorrections($factLock, $savedCorrections);
            }
            $identityAnalysis = is_array($factLock['identity_analysis'] ?? null)
                ? $factLock['identity_analysis']
                : ['status' => 'ok', 'risk_score' => 0, 'reason' => 'none'];
            if ($factEnabled) {
                AutoVestiQueue::updateRow((string) ($row['guid'] ?? ''), [
                    'fact_lock' => $factLock,
                    'entity_analysis' => $identityAnalysis,
                    'entity_status' => (string) ($identityAnalysis['status'] ?? 'ok'),
                ]);
            }
            if ($factEnabled && $factEnforce && ($identityAnalysis['status'] ?? 'ok') === 'needs_review' && !$forceReview) {
                $messages[] = 'Entity context needs review: ' . ($row['title'] ?? '');
                AutoVestiStats::record('skipped', ['reason' => 'entity_context_review']);
                $err++;
                continue;
            }

            if ($mode === 'native') {
                $result = self::buildNativePostData($row, $item);
            } else {
                $result = AutoVestiAi::rewrite($item, $lang, $useFaq, $existingPosts, $provider, $apiKey, $factLock);
            }

            if (is_string($result)) {
                $messages[] = ($mode === 'native' ? 'Greška: ' : 'AI greška: ') . $result;
                AutoVestiStats::record('skipped', ['reason' => 'error']);
                $err++;
                continue;
            }

            $validation = [
                'status' => 'ok',
                'risk_score' => 0,
                'reason' => 'ok',
                'issues' => [],
                'warnings' => [],
            ];
            if ($mode === 'ai') {
                if ($factEnabled) {
                    $validation = AutoVestiFacts::validate($factLock, $result, $factBlockNewPerson);
                    AutoVestiQueue::updateRow((string) ($row['guid'] ?? ''), [
                        'fact_lock' => $factLock,
                        'fact_report' => $validation,
                        'fact_status' => !empty($validation['issues']) ? 'needs_review' : (string) ($validation['status'] ?? 'ok'),
                    ]);
                }

                if ($factEnabled && $factEnforce && !AutoVestiFacts::publishAllowed($validation, $forceReview)) {
                    $messages[] = 'Fact lock blokirao objavu (' . ($validation['reason'] ?? 'validation_error') . '): ' . ($row['title'] ?? '');
                    AutoVestiStats::record('skipped', ['reason' => 'fact_lock_block']);
                    $err++;
                    continue;
                }

                $styleReport = null;
                $factPassedForStyle = !$factEnabled || ($validation['status'] ?? 'ok') !== 'error';
                if ($postEditorEnabled && $factPassedForStyle) {
                    $editResult = AutoVestiPostEditor::apply(
                        $result,
                        $factEnabled ? $factLock : null,
                        $provider,
                        $apiKey,
                        $factBlockNewPerson
                    );
                    $styleReport = $editResult['style_report'] ?? null;
                    if (!empty($editResult['applied']) && is_array($editResult['data'] ?? null)) {
                        $result = $editResult['data'];
                    }
                    if ($styleReport) {
                        AutoVestiQueue::updateRow((string) ($row['guid'] ?? ''), [
                            'style_report' => $styleReport,
                        ]);
                    }
                }

                if ($grammarPolishEnabled && $factPassedForStyle) {
                    $grammarResult = AutoVestiGrammarPolish::apply(
                        $result,
                        $factEnabled ? $factLock : null,
                        $provider,
                        $apiKey
                    );
                    $grammarReport = $grammarResult['grammar_report'] ?? null;
                    if (!empty($grammarResult['applied']) && is_array($grammarResult['data'] ?? null)) {
                        $result = $grammarResult['data'];
                    }
                    if ($grammarReport) {
                        AutoVestiQueue::updateRow((string) ($row['guid'] ?? ''), [
                            'grammar_report' => $grammarReport,
                        ]);
                    }
                }

                if ($seoLayerEnabled && $factPassedForStyle) {
                    $seoResult = AutoVestiSeoLayer::apply(
                        $result,
                        $factEnabled ? $factLock : null,
                        $provider,
                        $apiKey
                    );
                    $seoReport = $seoResult['seo_report'] ?? null;
                    if (is_array($seoResult['data'] ?? null)) {
                        $result = $seoResult['data'];
                    }
                    if ($seoReport) {
                        AutoVestiQueue::updateRow((string) ($row['guid'] ?? ''), [
                            'seo_report' => $seoReport,
                        ]);
                    }
                }

                $postDup = AutoVestiDuplicate::checkRewritten(
                    (string) ($result['title'] ?? ''),
                    (string) ($item['link'] ?? $row['link'] ?? '')
                );
                if ($postDup !== null) {
                    $messages[] = 'Duplikat poslije AI (' . $postDup . '): ' . ($result['title'] ?? '');
                    AutoVestiStats::record('skipped', ['reason' => 'duplicate_ai']);
                    $err++;
                    continue;
                }
            }

            if (!empty($result['is_breaking'])) {
                $isBreaking = true;
                if ($bpPublish) {
                    $postStatus = 'PUBLISHED';
                }
            }

            if (!empty($row['image_url'])) {
                $item['image_url'] = (string) $row['image_url'];
            }
            if (!empty($row['image_local_path'])) {
                $item['image_local_path'] = (string) $row['image_local_path'];
            }

            $articleId = self::createArticle($result, $item, $postStatus, $feedCat, $isBreaking, $useImg, $mode === 'native');
            if ($articleId === null) {
                $messages[] = 'Greška pri snimanju: ' . ($row['title'] ?? '');
                $err++;
                continue;
            }

            AutoVestiConfig::markSeen((string) $row['guid']);
            $processedGuids[] = (string) $row['guid'];
            AutoVestiQueue::unlock((string) $row['guid']);

            $slugStmt = Database::connection()->prepare('SELECT slug FROM articles WHERE id = ?');
            $slugStmt->execute([$articleId]);
            $savedSlug = (string) $slugStmt->fetchColumn();
            $posts[] = [
                'article_id' => $articleId,
                'title' => (string) ($result['title'] ?? ''),
                'url' => absolute_url('/vijest/' . $savedSlug),
                'status' => strtolower($postStatus),
                'mode' => $mode,
            ];

            $editorLabel = self::formatEditorLabel($editor);
            AutoVestiConfig::log('OBJAVLJENO [' . strtoupper($mode) . ']' . ($isBreaking ? ' [HITNA]' : '') . ': ID ' . $articleId . ' — ' . ($result['title'] ?? '') . ($editorLabel ? ' — ' . $editorLabel : ''));
            AutoVestiStats::record($mode === 'native' ? 'published_native' : 'published_ai', [
                'article_id' => $articleId,
                'title' => $result['title'] ?? '',
            ]);
            AutoVestiFacts::appendAudit([
                'created_at' => date('c'),
                'guid' => (string) ($row['guid'] ?? ''),
                'source_text' => $factLock['source_of_truth'] ?? '',
                'ai_text' => strip_tags((string) (($result['title'] ?? '') . "\n" . ($result['content'] ?? ''))),
                'fact_lock' => $factLock,
                'entity_context_lock' => $factLock['entity_context'] ?? [],
                'entity_analysis' => $identityAnalysis,
                'validation' => $validation,
                'style_report' => $styleReport ?? null,
                'grammar_report' => $grammarReport ?? null,
                'seo_report' => $seoReport ?? null,
                'approved_by' => $editorLabel,
                'forced_publish' => $forceReview ? 1 : 0,
                'article_id' => $articleId,
            ]);
            $ok++;

            if (count($toProcess) > 1) {
                sleep(2);
            }
        }

        if ($processedGuids) {
            AutoVestiQueue::remove($processedGuids);
        }

        $msg = $ok . ' objavljeno, ' . $err . ' preskočeno/greška.';
        if ($messages) {
            $msg = implode(' | ', array_slice($messages, 0, 3));
            if (count($messages) > 3) {
                $msg .= ' …';
            }
        }

        return self::result($ok, $err, $msg, $posts, $messages);
    }

    /** @param list<string> $guids @param array<string,mixed>|null $editor */
    public static function rejectSelected(array $guids, ?array $editor = null): int
    {
        foreach ($guids as $guid) {
            AutoVestiConfig::markSeen($guid);
            AutoVestiQueue::unlock($guid);
        }
        AutoVestiQueue::remove($guids);
        $n = count($guids);
        if ($n > 0) {
            $label = self::formatEditorLabel($editor);
            AutoVestiConfig::log('Odbijeno iz reda: ' . $n . ' vesti.' . ($label ? ' — ' . $label : ''));
            AutoVestiStats::record('rejected', ['count' => $n]);
        }
        return $n;
    }

    /** @param array<string,mixed> $payload */
    public static function executeBackground(array $payload): void
    {
        if (($payload['type'] ?? '') === 'manual') {
            self::executeManualBackground($payload);
            return;
        }

        $guid = trim((string) ($payload['guid'] ?? ''));
        $mode = ($payload['mode'] ?? 'ai') === 'native' ? 'native' : 'ai';
        $chatId = (string) ($payload['chat_id'] ?? '');
        $editor = isset($payload['editor']) && is_array($payload['editor']) ? $payload['editor'] : null;

        if ($guid === '') {
            return;
        }

        $jobKey = AutoVestiBackground::tryClaim($guid, $mode);
        if ($jobKey === null) {
            return;
        }

        $result = self::processSelected([$guid], ['mode' => $mode, 'editor' => $editor]);
        $success = $result['ok'] > 0 && !empty($result['posts'][0]);

        if (!$success && $result['msg'] === 'Izabrane vesti nisu u redu.' && AutoVestiConfig::isSeen($guid)) {
            AutoVestiBackground::finish($jobKey, true);
            return;
        }

        if ($chatId === '') {
            AutoVestiBackground::finish($jobKey, $success);
            return;
        }

        $editorLine = $editor ? "\n👤 " . AutoVestiTelegram::escape(self::formatEditorLabel($editor)) : '';

        if ($success) {
            $p = $result['posts'][0];
            $label = $p['mode'] === 'native' ? 'Original objavljeno' : 'AI objavljeno';
            AutoVestiTelegram::sendText($chatId,
                '✅ <b>' . AutoVestiTelegram::escape($label) . '</b>' . $editorLine . "\n\n" .
                '📰 ' . AutoVestiTelegram::escape($p['title']) . "\n" .
                '🔗 <a href="' . e($p['url']) . '">Pogledaj na sajtu</a>'
            );
        } elseif ($result['err'] > 0) {
            AutoVestiTelegram::sendText($chatId, '⚠️ <b>Preskočeno</b>' . $editorLine . "\n\n" . AutoVestiTelegram::escape($result['msg']));
        } else {
            AutoVestiTelegram::sendText($chatId, '❌ <b>Greška</b>' . $editorLine . "\n\n" . AutoVestiTelegram::escape($result['msg']));
        }

        AutoVestiBackground::finish($jobKey, $success);
    }

    /** @param array<string, mixed> $payload */
    private static function executeManualBackground(array $payload): void
    {
        $chatId = (string) ($payload['chat_id'] ?? '');
        $catName = (string) ($payload['cat_name'] ?? '');
        $useAi = !empty($payload['use_ai']);
        $guid = trim((string) ($payload['guid'] ?? ''));
        if ($guid === '') {
            $guid = 'manual_' . md5(microtime(true) . '|' . substr((string) ($payload['text'] ?? ''), 0, 80));
        }
        $mode = $useAi ? 'manual_ai' : 'manual_native';

        $jobKey = AutoVestiBackground::tryClaim($guid, $mode);
        if ($jobKey === null) {
            return;
        }

        try {
            @set_time_limit(600);
            $result = self::publishManual(
                (string) ($payload['text'] ?? ''),
                (string) ($payload['image_url'] ?? ''),
                (string) ($payload['cat_id'] ?? ''),
                $useAi
            );

            if (is_string($result)) {
                if ($chatId !== '') {
                    AutoVestiTelegram::sendText($chatId, '❌ <b>Greška:</b> ' . AutoVestiTelegram::escape($result));
                }
                AutoVestiBackground::finish($jobKey, false);
                return;
            }

            if (($result['status'] ?? '') === 'NEEDS_REVIEW') {
                $errors = isset($result['errors']) && is_array($result['errors'])
                    ? implode('; ', array_map('strval', $result['errors']))
                    : 'Fact lock traži ručni pregled.';
                if ($chatId !== '') {
                    AutoVestiTelegram::sendText(
                        $chatId,
                        '⚠️ <b>Potreban pregled</b> — nije objavljeno automatski.' . "\n\n" .
                        AutoVestiTelegram::escape($errors)
                    );
                }
                AutoVestiBackground::finish($jobKey, false);
                return;
            }

            if ($chatId !== '') {
                $catLine = $catName !== '' ? "\n📂 " . AutoVestiTelegram::escape($catName) : '';
                AutoVestiTelegram::sendText(
                    $chatId,
                    '✅ <b>Objavljeno!</b>' . $catLine . "\n\n📰 " .
                    AutoVestiTelegram::escape((string) ($result['title'] ?? '')) .
                    "\n🔗 <a href=\"" . e((string) ($result['url'] ?? '')) . "\">Pogledaj na sajtu</a>"
                );
            }
            AutoVestiBackground::finish($jobKey, true);
        } catch (Throwable $e) {
            AutoVestiConfig::log('Telegram manual publish fail: ' . $e->getMessage());
            if ($chatId !== '') {
                AutoVestiTelegram::sendText(
                    $chatId,
                    '❌ <b>Greška:</b> ' . AutoVestiTelegram::escape($e->getMessage() ?: 'Neočekivana greška pri objavi.')
                );
            }
            AutoVestiBackground::finish($jobKey, false);
        }
    }

    /** @return array<string,mixed>|string */
    public static function publishManual(string $rawText, string $imageUrl = '', string $categoryId = '', ?bool $useAi = null): array|string
    {
        @set_time_limit(600);
        if ($useAi === null) {
            $useAi = !empty(AutoVestiConfig::get('telegram_manual_use_ai', false));
        }

        $lines = preg_split('/\r\n|\r|\n/', trim($rawText), 2);
        $titleHint = trim($lines[0] ?? '');
        $body = trim($lines[1] ?? '');
        if ($body === '') {
            $body = trim($rawText);
        }

        $item = [
            'guid' => md5('tg_manual_' . microtime(true) . random_int(1, 99999)),
            'title' => mb_substr($titleHint, 0, 200, 'UTF-8'),
            'description' => mb_substr($body, 0, 8000, 'UTF-8'),
            'link' => config('site_url') . '/',
            'pub_date' => date('Y-m-d H:i:s'),
            'image_url' => $imageUrl,
        ];

        $status = self::mapStatus((string) AutoVestiConfig::get('status', 'draft'));
        $isBreaking = AutoVestiContent::isBreaking($rawText);

        if ($categoryId === '') {
            $categoryId = trim((string) AutoVestiConfig::get('telegram_manual_cat', ''));
        }

        if (!$useAi) {
            $result = self::buildManualPostData($rawText);
        } else {
            $provider = (string) AutoVestiConfig::get('ai_provider', 'claude');
            $apiKey = trim($provider === 'openai'
                ? (string) AutoVestiConfig::get('openai_api_key', '')
                : (string) AutoVestiConfig::get('api_key', ''));
            if ($apiKey === '') {
                return 'AI API ključ nije podešen.';
            }
            $lang = (string) AutoVestiConfig::get('lang', 'bosanski');
            $useFaq = !empty(AutoVestiConfig::get('use_faq', true));
            $useLinks = !empty(AutoVestiConfig::get('use_internal_links', true));
            $existing = $useLinks ? AutoVestiContent::recentPostsForLinking(40) : [];
            $factLock = AutoVestiFacts::extractFactLock($item);
            $result = AutoVestiAi::rewrite($item, $lang, $useFaq, $existing, $provider, $apiKey, $factLock);
            if (is_string($result)) {
                return $result;
            }
            $validation = AutoVestiFacts::validate(
                $factLock,
                $result,
                !empty(AutoVestiConfig::get('fact_protection_block_on_new_person', true))
            );
            if (!empty($validation['issues'])) {
                return [
                    'status' => 'NEEDS_REVIEW',
                    'errors' => $validation['issues'],
                ];
            }
            if (!empty(AutoVestiConfig::get('post_process_editor_enabled', true))) {
                $editResult = AutoVestiPostEditor::apply(
                    $result,
                    $factLock,
                    $provider,
                    $apiKey,
                    !empty(AutoVestiConfig::get('fact_protection_block_on_new_person', true))
                );
                if (!empty($editResult['applied']) && is_array($editResult['data'] ?? null)) {
                    $result = $editResult['data'];
                }
            }
            if (!empty(AutoVestiConfig::get('grammar_polish_enabled', true))) {
                $grammarResult = AutoVestiGrammarPolish::apply($result, $factLock, $provider, $apiKey);
                if (!empty($grammarResult['applied']) && is_array($grammarResult['data'] ?? null)) {
                    $result = $grammarResult['data'];
                }
            }
            if (!empty(AutoVestiConfig::get('seo_layer_enabled', true))) {
                $seoResult = AutoVestiSeoLayer::apply($result, $factLock, $provider, $apiKey);
                if (is_array($seoResult['data'] ?? null)) {
                    $result = $seoResult['data'];
                }
            }
            if (!empty($result['is_breaking'])) {
                $isBreaking = true;
            }
        }

        if (is_string($result)) {
            return $result;
        }

        $articleId = self::createArticle(
            $result,
            $item,
            $status,
            $categoryId,
            $isBreaking,
            true,
            !$useAi
        );
        if ($articleId === null) {
            return 'Greška pri snimanju članka.';
        }

        $slugStmt = Database::connection()->prepare('SELECT slug, status FROM articles WHERE id = ?');
        $slugStmt->execute([$articleId]);
        $saved = $slugStmt->fetch();
        AutoVestiStats::record($useAi ? 'published_ai' : 'published_native', ['source' => 'telegram_objavi']);

        return [
            'article_id' => $articleId,
            'title' => (string) ($result['title'] ?? ''),
            'url' => absolute_url('/vijest/' . ($saved['slug'] ?? '')),
            'status' => strtolower((string) ($saved['status'] ?? 'DRAFT')),
            'mode' => $useAi ? 'ai' : 'native',
        ];
    }

    /** @param array<string,mixed>|null $editor */
    public static function formatEditorLabel(?array $editor): string
    {
        if (!$editor) {
            return '';
        }
        if (!empty($editor['username'])) {
            return (string) $editor['username'];
        }
        if (!empty($editor['name'])) {
            return (string) $editor['name'];
        }
        return !empty($editor['user_id']) ? 'ID ' . $editor['user_id'] : '';
    }

    /** @param array<string,mixed> $row @param array<string,mixed> $item @return array<string,mixed>|string */
    private static function buildNativePostData(array $row, array $item): array|string
    {
        $body = '';
        if (!empty($item['description'])) {
            $body = trim(strip_tags((string) $item['description']));
        } elseif (!empty($row['preview'])) {
            $body = trim(strip_tags((string) $row['preview']));
        }
        $body = trim(preg_replace('/\s+/u', ' ', $body) ?? '');
        if (mb_strlen($body, 'UTF-8') < 40) {
            return 'Tekst izvora je prekratak za original objavu.';
        }

        $title = (string) ($row['title'] ?? '');
        $parts = preg_split('/(?<=[.!?])\s+/u', $body, 3);
        $lead = trim($parts[0] ?? $body);
        $rest = trim(implode(' ', array_slice($parts, 1)));

        $html = '<p class="avc-lead"><strong>' . e($lead) . '</strong></p>';
        if ($rest !== '') {
            foreach (preg_split('/(?<=[.!?])\s+/u', $rest) as $sentence) {
                $sentence = trim($sentence);
                if (mb_strlen($sentence, 'UTF-8') >= 20) {
                    $html .= '<p>' . e($sentence) . '</p>';
                }
            }
        }

        return [
            'title' => $title,
            'content' => $html,
            'excerpt' => mb_substr($body, 0, 200, 'UTF-8'),
            'social_excerpt' => mb_substr($body, 0, 280, 'UTF-8'),
            'slug' => slugify($title),
            'tags' => [],
            'faq' => [],
            'is_breaking' => AutoVestiContent::isBreaking($title . ' ' . $body),
            'image_alt' => $title,
        ];
    }

    /** @return array<string,mixed>|string */
    private static function buildManualPostData(string $rawText): array|string
    {
        $rawText = trim(strip_tags($rawText));
        $lines = preg_split('/\r\n|\r|\n/', $rawText, 2);
        $title = trim($lines[0] ?? '');
        $body = trim($lines[1] ?? '');
        if (mb_strlen($title, 'UTF-8') < 5) {
            return 'Naslov je prekratak (minimum 5 znakova). Prvi red = naslov.';
        }
        if ($body === '') {
            $body = $title;
        }
        if (mb_strlen($body, 'UTF-8') < 20) {
            return 'Tekst vesti je prekratak.';
        }

        $paras = preg_split('/\n\s*\n/u', $body);
        if (count($paras) <= 1) {
            $paras = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/u', $body))));
        }
        $html = '';
        $i = 0;
        foreach ($paras as $para) {
            if ($para === '') {
                continue;
            }
            $html .= $i === 0
                ? '<p class="avc-lead"><strong>' . e($para) . '</strong></p>'
                : '<p>' . e($para) . '</p>';
            $i++;
        }
        if ($html === '') {
            $html = '<p class="avc-lead"><strong>' . e($body) . '</strong></p>';
        }

        return [
            'title' => mb_substr($title, 0, 200, 'UTF-8'),
            'content' => $html,
            'excerpt' => mb_substr($body, 0, 200, 'UTF-8'),
            'social_excerpt' => mb_substr($body, 0, 280, 'UTF-8'),
            'slug' => slugify($title),
            'tags' => [],
            'faq' => [],
            'is_breaking' => AutoVestiContent::isBreaking($title . ' ' . $body),
            'image_alt' => $title,
        ];
    }

    /**
     * Preferira sadržaj (ključne reči / AI predlog), pa eksplicitnu rubriku feeda/Telegrama.
     *
     * @param array<string,mixed> $data
     */
    private static function resolveAutoCategory(string $preferred, array $data): string
    {
        $title = (string) ($data['title'] ?? '');
        $lead = (string) ($data['excerpt'] ?? $data['meta_description'] ?? '');
        $body = (string) ($data['content'] ?? '');
        $ai = isset($data['suggested_category']) ? (string) $data['suggested_category'] : null;

        $detected = AutoVestiContent::detectCategorySlug($title, $lead, $body, $ai);
        if ($detected !== null) {
            AutoVestiConfig::log(
                'Auto-rubrika: ' . $detected
                . ($preferred !== '' && $preferred !== $detected ? ' (feed bio: ' . $preferred . ')' : '')
                . ' — ' . mb_substr($title, 0, 90, 'UTF-8')
            );
            return $detected;
        }

        return $preferred;
    }

    /** @param array<string,mixed> $data @param array<string,mixed> $original */
    public static function createArticle(
        array $data,
        array $original,
        string $status,
        string $categoryId,
        bool $isBreaking,
        bool $useImg,
        bool $native = false
    ): ?string {
        $content = (string) ($data['content'] ?? '');
        if (!empty(AutoVestiConfig::get('use_youtube', true))) {
            AutoVestiVideo::enrich($original);
            $video = AutoVestiVideo::get($original);
            if (!empty($video['type'])) {
                $content = AutoVestiVideo::insertIntoContent($content, $video);
            }
        }
        if (!empty(AutoVestiConfig::get('show_source_footer', true))) {
            $content = AutoVestiContent::appendSourceFooter($content, $original);
        }

        $tags = isset($data['tags']) && is_array($data['tags']) ? $data['tags'] : [];
        if ($isBreaking) {
            $tags[] = 'Hitna vest';
        }

        $cover = null;
        $imageAlt = (string) ($data['image_alt'] ?? $data['title'] ?? '');
        if ($useImg) {
            if (!empty($original['image_local_path'])) {
                $cover = (string) $original['image_local_path'];
            } else {
                $imageUrl = AutoVestiRunner::resolveItemImageUrl($original);
                if ($imageUrl !== '') {
                    $dl = AutoVestiContent::downloadCover($imageUrl, (string) $data['title'], (string) ($original['link'] ?? ''));
                    $cover = $dl['path'];
                }
            }
            if ($cover) {
                $content = AutoVestiContent::injectCoverIntoBody($content, $cover, $imageAlt);
            }
        }

        if (!empty($data['faq']) && is_array($data['faq']) && !$isBreaking) {
            $content .= AutoVestiContent::buildFaqBlock($data['faq']);
        }

        $pdo = Database::connection();
        $categoryId = self::resolveAutoCategory($categoryId, $data);
        if ($categoryId === '') {
            $categoryId = 'vijesti';
        }
        $stmt = $pdo->prepare('SELECT id FROM categories WHERE id = ? OR slug = ? LIMIT 1');
        $stmt->execute([$categoryId, $categoryId]);
        $found = $stmt->fetchColumn();
        if ($found) {
            $categoryId = (string) $found;
        } else {
            $categoryId = (string) $pdo->query("SELECT id FROM categories WHERE slug = 'vijesti' LIMIT 1")->fetchColumn();
            if ($categoryId === '') {
                $categoryId = (string) $pdo->query('SELECT id FROM categories ORDER BY name LIMIT 1')->fetchColumn();
            }
        }

        $authorId = (string) AutoVestiConfig::get('default_author_id', '');
        if ($authorId === '') {
            $authorId = (string) $pdo->query('SELECT id FROM authors ORDER BY name LIMIT 1')->fetchColumn();
        }
        if ($authorId === '' || $categoryId === '') {
            AutoVestiConfig::log('Greška: nema autora ili kategorije.');
            return null;
        }

        $lead = (string) ($data['excerpt'] ?? $data['meta_description'] ?? '');
        if ($lead === '') {
            $lead = mb_substr(strip_tags($content), 0, 200);
        }

        try {
            $id = AdminService::saveArticle([
                'title' => (string) $data['title'],
                'slug' => (string) ($data['slug'] ?? slugify((string) $data['title'])),
                'lead' => $lead,
                'body' => $content,
                'categoryId' => $categoryId,
                'city' => (string) AutoVestiConfig::get('default_city', 'NOVI_PAZAR'),
                'authorId' => $authorId,
                'status' => $status,
                'isBreaking' => $isBreaking ? 1 : 0,
                'isFeatured' => 0,
                'coverImage' => $cover,
                'coverCaption' => $imageAlt,
                'tags' => $tags,
                'publishedAt' => $status === 'PUBLISHED' ? date('Y-m-d H:i:s') : null,
                'sourceUrl' => AutoVestiDuplicate::normalizeUrl((string) ($original['link'] ?? '')),
                'sourceName' => parse_url((string) ($original['link'] ?? ''), PHP_URL_HOST) ?: '',
            ]);

            $slugStmt = Database::connection()->prepare('SELECT slug FROM articles WHERE id = ?');
            $slugStmt->execute([$id]);
            $savedSlug = (string) $slugStmt->fetchColumn();
            $schema = AutoVestiContent::buildSchema($data, $original, $id, $savedSlug, $isBreaking);
            if ($schema !== '') {
                AdminService::setImportSchema($id, $schema);
            }
            AdminService::setSeoFields(
                $id,
                isset($data['seo_title']) ? (string) $data['seo_title'] : null,
                isset($data['meta_description']) ? (string) $data['meta_description'] : null
            );
            return $id;
        } catch (Throwable $e) {
            AutoVestiConfig::log('Greška pri snimanju: ' . $e->getMessage());
            return null;
        }
    }

    private static function mapStatus(string $status): string
    {
        return match (strtolower($status)) {
            'publish', 'published' => 'PUBLISHED',
            default => 'DRAFT',
        };
    }

    /** @param array<int,array<string,mixed>> $posts @param array<int,string> $skip */
    private static function result(int $ok, int $err, string $msg, array $posts = [], array $skip = []): array
    {
        return [
            'ok' => $ok,
            'err' => $err,
            'msg' => $msg,
            'posts' => $posts,
            'skip_reasons' => $skip,
        ];
    }
}
