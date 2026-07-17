<?php

declare(strict_types=1);

final class AutoVestiTelegram
{
    public static function webhookUrl(): string
    {
        $secret = AutoVestiConfig::ensureTelegramWebhookSecret();
        return absolute_url('/api/avm/telegram?secret=' . rawurlencode($secret));
    }

    public static function isConfigured(): bool
    {
        return trim((string) AutoVestiConfig::get('telegram_bot_token', '')) !== ''
            && AutoVestiConfig::getTelegramChatIds() !== [];
    }

    /** @return array<string,mixed>|string|null */
    public static function api(string $method, array $body = []): array|string|null
    {
        $token = trim((string) AutoVestiConfig::get('telegram_bot_token', ''));
        if ($token === '') {
            return 'Telegram bot token nije podešen.';
        }
        $result = HttpClient::postJson(
            'https://api.telegram.org/bot' . $token . '/' . $method,
            $body,
            [],
            30
        );
        if (!$result || !empty($result['_error'])) {
            $desc = is_array($result['description'] ?? null) ? json_encode($result['description']) : ($result['description'] ?? 'Telegram API greška');
            return is_string($desc) ? $desc : 'Telegram API greška';
        }
        if (empty($result['ok'])) {
            return (string) ($result['description'] ?? 'Telegram API greška');
        }
        return is_array($result['result'] ?? null) ? $result['result'] : [];
    }

    public static function setWebhook(): ?string
    {
        AutoVestiConfig::ensureTelegramWebhookSecret();
        $result = self::api('setWebhook', [
            'url' => self::webhookUrl(),
            'allowed_updates' => ['message', 'callback_query'],
            'drop_pending_updates' => false,
        ]);
        if (is_string($result)) {
            return $result;
        }
        self::api('setMyCommands', [
            'commands' => [
                ['command' => 'link', 'description' => 'Vest sa URL-a'],
                ['command' => 'objavi', 'description' => 'Ručna objava sa telefona'],
                ['command' => 'next', 'description' => 'Sljedeća vest u redu'],
                ['command' => 'status', 'description' => 'Pregled reda čekanja'],
                ['command' => 'fetch', 'description' => 'Povuci feed vesti'],
                ['command' => 'help', 'description' => 'Pomoć i komande'],
            ],
        ]);
        return null;
    }

    /** @return array<string,mixed>|string|null */
    public static function getWebhookInfo(): array|string|null
    {
        return self::api('getWebhookInfo');
    }

    public static function getBotUsername(): string
    {
        $cached = trim((string) AutoVestiConfig::get('telegram_bot_username', ''));
        if ($cached !== '') {
            return $cached;
        }
        $me = self::api('getMe');
        if (!is_array($me) || empty($me['username'])) {
            return '';
        }
        AutoVestiConfig::updatePartial(['telegram_bot_username' => $me['username']]);
        return (string) $me['username'];
    }

    public static function refreshLinkCode(): string
    {
        $code = bin2hex(random_bytes(6));
        AutoVestiConfig::updatePartial([
            'telegram_link_code' => $code,
            'telegram_link_expires' => time() + 3600,
        ]);
        return $code;
    }

    public static function getConnectUrl(): string
    {
        $username = self::getBotUsername();
        $code = (string) AutoVestiConfig::get('telegram_link_code', '');
        $expires = (int) AutoVestiConfig::get('telegram_link_expires', 0);
        if ($username === '' || $code === '' || $expires < time()) {
            $code = self::refreshLinkCode();
            $username = self::getBotUsername();
        }
        if ($username === '') {
            return '';
        }
        return 'https://t.me/' . rawurlencode($username) . '?start=' . rawurlencode($code);
    }

    /** @param array<string,mixed> $row */
    public static function notifyQueueItem(array $row): void
    {
        if (empty(AutoVestiConfig::get('telegram_notify', true)) || !self::isConfigured()) {
            return;
        }
        self::sendItemPreview($row, AutoVestiConfig::getTelegramChatIds());
    }

    public static function notifyExistingQueue(): int
    {
        $sent = 0;
        foreach (AutoVestiConfig::getQueue() as $row) {
            self::notifyQueueItem($row);
            $sent++;
            sleep(1);
        }
        return $sent;
    }

    /** @param array<string,mixed>|list<string|int> $chatIds */
    public static function sendItemPreview(array $row, array $chatIds): void
    {
        if (!$chatIds) {
            return;
        }
        $caption = self::buildCaption($row);
        $keyboard = self::inlineKeyboard((string) ($row['guid'] ?? ''), (string) ($row['link'] ?? ''));
        $img = (string) ($row['image_url'] ?? '');

        foreach ($chatIds as $chatId) {
            if ($img !== '') {
                $sent = self::api('sendPhoto', [
                    'chat_id' => $chatId,
                    'photo' => $img,
                    'caption' => $caption,
                    'parse_mode' => 'HTML',
                    'reply_markup' => ['inline_keyboard' => $keyboard],
                ]);
                if (is_string($sent)) {
                    self::api('sendMessage', [
                        'chat_id' => $chatId,
                        'text' => $caption,
                        'parse_mode' => 'HTML',
                        'reply_markup' => ['inline_keyboard' => $keyboard],
                    ]);
                }
            } else {
                self::api('sendMessage', [
                    'chat_id' => $chatId,
                    'text' => $caption,
                    'parse_mode' => 'HTML',
                    'reply_markup' => ['inline_keyboard' => $keyboard],
                ]);
            }
        }
    }

    public static function sendText(string|int $chatId, string $text): void
    {
        self::api('sendMessage', [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
        ]);
    }

    public static function sendTest(): ?string
    {
        if (!self::isConfigured()) {
            return 'Podesite token i povežite Telegram.';
        }
        foreach (AutoVestiConfig::getTelegramChatIds() as $chatId) {
            self::sendText($chatId, '✅ <b>Test uspješan!</b> Auto Vesti Manual — Sandžak.net');
        }
        return null;
    }

    /** @param array<string,mixed> $update */
    public static function handleUpdate(array $update): void
    {
        if (!empty($update['callback_query'])) {
            self::handleCallback($update['callback_query']);
            return;
        }
        if (!empty($update['message'])) {
            self::handleMessage($update['message']);
        }
    }

    /** @param array<string,mixed> $message */
    private static function handleMessage(array $message): void
    {
        $chatId = (string) ($message['chat']['id'] ?? '');
        if ($chatId === '') {
            return;
        }

        $text = trim((string) ($message['text'] ?? ''));
        $caption = trim((string) ($message['caption'] ?? ''));
        $cmd = self::normalizeCommand($text);

        if ($cmd !== '' && str_starts_with($cmd, '/start')) {
            $parts = preg_split('/\s+/', $cmd, 2);
            $code = trim($parts[1] ?? '');
            if ($code !== '') {
                self::tryLinkChat($chatId, $code, $message);
                return;
            }
            if (self::isChatAllowed($chatId)) {
                self::sendText($chatId, "✅ Auto Vesti Manual — već ste povezani.\n\n/link — vest sa linka\n/objavi — pošalji vest\n/status — red\n/help — pomoć");
            } else {
                self::sendText($chatId, '👋 Otvorite link za povezivanje iz admin panela (Auto Vesti → Telegram).');
            }
            return;
        }

        if (!self::isChatAllowed($chatId)) {
            if ($text !== '' || $caption !== '' || !empty($message['photo'])) {
                self::sendText($chatId, '⛔ Niste autorizovani. Povežite nalog iz admin panela.');
            }
            return;
        }

        if (!AutoVestiSession::rateLimitOk($chatId)) {
            self::sendText($chatId, '⏳ Previše zahtjeva. Sačekajte minut.');
            return;
        }

        if ($cmd !== '' && preg_match('#^/objavi(?:\s+(.+))?$#u', $cmd, $m)) {
            self::cmdObjavi($chatId, trim($m[1] ?? ''), false);
            return;
        }
        if ($cmd !== '' && preg_match('#^/objavi-ai(?:\s+(.+))?$#u', $cmd, $m)) {
            self::cmdObjavi($chatId, trim($m[1] ?? ''), true);
            return;
        }
        if ($cmd !== '' && preg_match('#^/link(?:\s+(.+))?$#u', $cmd, $m)) {
            self::cmdLink($chatId, trim($m[1] ?? ''));
            return;
        }
        if (self::commandIs($text, '/otkazi')) {
            AutoVestiSession::clearPending($chatId);
            self::sendText($chatId, '❌ Otkazano.');
            return;
        }
        if (self::commandIs($text, '/status') || self::commandIs($text, '/queue')) {
            self::cmdStatus($chatId);
            return;
        }
        if (self::commandIs($text, '/next')) {
            self::cmdNext($chatId);
            return;
        }
        if (self::commandIs($text, '/help')) {
            self::cmdHelp($chatId);
            return;
        }
        if (self::commandIs($text, '/fetch')) {
            $n = AutoVestiRunner::fetchToQueue();
            self::sendText($chatId, $n > 0 ? "📥 Povučeno {$n} novih vesti u red." : '📭 Nema novih vesti.');
            return;
        }
        if (self::commandIs($text, '/ping')) {
            self::sendText($chatId, '🏓 Pong! Bot radi.');
            return;
        }

        if (self::manualEnabled() && self::isPickCatMode($chatId)) {
            self::sendText($chatId, '📂 Prvo izaberi kategoriju (dugmad iznad) ili pošalji npr. <code>/objavi sport</code>');
            return;
        }
        if (self::manualEnabled() && self::isManualMode($chatId)) {
            self::handleManualInput($chatId, $message);
            return;
        }
        if (self::isReplaceImageMode($chatId)) {
            self::handleReplaceImageInput($chatId, $message);
            return;
        }

        if (self::linkEnabled() && $text !== '' && !str_starts_with($text, '/')) {
            $url = self::extractUrl($text);
            if ($url && self::isPlainUrlMessage($text, $url)) {
                self::processLink($chatId, $url, '');
            }
        }
    }

    /** @param array<string,mixed> $callback */
    private static function handleCallback(array $callback): void
    {
        $chatId = (string) ($callback['message']['chat']['id'] ?? '');
        $data = (string) ($callback['data'] ?? '');
        $cbId = (string) ($callback['id'] ?? '');

        if (!self::isChatAllowed($chatId)) {
            self::api('answerCallbackQuery', ['callback_query_id' => $cbId, 'text' => 'Niste autorizovani.', 'show_alert' => true]);
            return;
        }
        if (!AutoVestiSession::rateLimitOk($chatId)) {
            self::api('answerCallbackQuery', ['callback_query_id' => $cbId, 'text' => 'Previše zahtjeva.', 'show_alert' => true]);
            return;
        }

        $parts = explode(':', $data, 3);
        if (count($parts) < 2) {
            return;
        }
        $action = $parts[0];
        $payload = $parts[1];
        $guid = count($parts) === 3 ? $parts[2] : $payload;
        $editor = self::editorFromCallback($callback);

        if ($action === 'qmc' && count($parts) === 3) {
            self::api('answerCallbackQuery', ['callback_query_id' => $cbId, 'text' => 'Kategorija postavljena.']);
            AutoVestiQueue::updateRow($guid, ['feed_cat' => $payload]);
            $row = AutoVestiQueue::getRow($guid);
            if ($row) {
                self::sendItemPreview($row, [$chatId]);
            }
            return;
        }
        if ($action === 'mc') {
            self::onCategorySelected($chatId, $callback, (int) $payload);
            return;
        }
        if ($action === 'ri') {
            self::api('answerCallbackQuery', ['callback_query_id' => $cbId, 'text' => 'Pošalji novu sliku...']);
            self::beginReplaceImage($chatId, $guid);
            return;
        }
        if ($action === 'gi') {
            self::api('answerCallbackQuery', ['callback_query_id' => $cbId, 'text' => 'Generišem AI sliku...']);
            self::generateAiImageForQueue($chatId, $guid);
            return;
        }
        if ($action === 'di') {
            self::api('answerCallbackQuery', ['callback_query_id' => $cbId, 'text' => 'Postavljam default sliku...']);
            self::applyDefaultImageForQueue($chatId, $guid);
            return;
        }
        if ($action === 'qc') {
            self::api('answerCallbackQuery', ['callback_query_id' => $cbId, 'text' => 'Izaberi kategoriju...']);
            self::api('sendMessage', [
                'chat_id' => $chatId,
                'text' => '📂 <b>Izaberi kategoriju</b> za ovu vest:',
                'parse_mode' => 'HTML',
                'reply_markup' => ['inline_keyboard' => self::queueCategoryKeyboard($guid)],
            ]);
            return;
        }
        if ($action === 'hd') {
            AutoVestiQueue::hold($guid);
            self::api('answerCallbackQuery', ['callback_query_id' => $cbId, 'text' => 'Vest stavljena na hold.']);
            self::editCallbackMessage($callback, '⏸ <b>Drži</b> — vest ostaje u redu.');
            return;
        }
        if ($action === 'ap' || $action === 'apn') {
            $lock = AutoVestiQueue::tryLock($guid, $editor);
            if ($lock) {
                self::api('answerCallbackQuery', ['callback_query_id' => $cbId, 'text' => $lock, 'show_alert' => true]);
                return;
            }
            self::api('answerCallbackQuery', ['callback_query_id' => $cbId, 'text' => $action === 'apn' ? 'Objavljujem original...' : 'Šaljem u AI...']);
            self::dispatchProcess($guid, $action === 'apn' ? 'native' : 'ai', $chatId, $editor, $callback);
            return;
        }
        if ($action === 'rj') {
            self::api('answerCallbackQuery', ['callback_query_id' => $cbId, 'text' => 'Odbijam...']);
            AutoVestiProcessor::rejectSelected([$guid], $editor);
            self::editCallbackMessage($callback, '❌ <b>Odbijeno</b>');
            return;
        }
    }

    private static function dispatchProcess(string $guid, string $mode, string $chatId, ?array $editor, ?array $callback): void
    {
        if ($callback) {
            self::editCallbackMessage($callback, '⏳ <b>Obrađujem...</b> (AI može trajati 30–60 sek)');
        }
        $payload = [
            'guid' => $guid,
            'mode' => $mode,
            'chat_id' => $chatId,
            'editor' => $editor,
            'secret' => AutoVestiConfig::get('telegram_webhook_secret', ''),
        ];
        AutoVestiBackground::dispatch($payload);
    }

    private static function cmdLink(string $chatId, string $arg): void
    {
        if (!self::linkEnabled()) {
            self::sendText($chatId, '⛔ Preuzimanje vesti sa linka je isključeno.');
            return;
        }
        $parsed = self::parseLinkArgs($arg);
        if ($parsed['url'] === '') {
            self::sendText($chatId, "🔗 <b>Pošalji link vesti</b>\n\n<code>/link https://primjer.rs/vest</code>\nIli samo zalijepi link.");
            return;
        }
        self::processLink($chatId, $parsed['url'], $parsed['cat_query']);
    }

    private static function processLink(string $chatId, string $url, string $catQuery): void
    {
        self::sendText($chatId, '⏳ Preuzimam vest sa linka...');
        $item = AutoVestiFetcher::fetchArticleFromUrl($url);
        if (is_string($item)) {
            self::sendText($chatId, '❌ <b>Greška:</b> ' . self::escape($item));
            return;
        }

        $catId = '';
        if ($catQuery !== '') {
            $cat = self::resolveCategory($catQuery);
            if ($cat) {
                $catId = (string) $cat['id'];
            } else {
                self::sendText($chatId, '⚠️ Kategorija nije pronađena — koristim podrazumijevanu.');
            }
        }
        if ($catId === '') {
            $catId = trim((string) AutoVestiConfig::get('telegram_manual_cat', ''));
        }

        $row = AutoVestiQueue::add($item, [
            'url' => $url,
            'cat' => $catId,
            'breaking_publish' => '0',
            'source_type' => 'telegram_link',
        ], true, false);

        if (!$row) {
            self::sendText($chatId, '❌ Vest nije dodata u red.');
            return;
        }

        AutoVestiStats::record('link_added', ['title' => $item['title'] ?? '']);
        self::sendText($chatId, "📥 <b>Preuzeto sa linka</b>\n\nPregled ispod — ✅ AI + Objavi, 📋 Original, ili ❌ Odbij.");
        self::sendItemPreview($row, [$chatId]);
        AutoVestiConfig::log('Telegram link u red: ' . ($item['title'] ?? '') . ' — ' . $url);
    }

    private static function cmdObjavi(string $chatId, string $catQuery, bool $forceAi): void
    {
        if (!self::manualEnabled()) {
            self::sendText($chatId, '⛔ Ručna objava sa Telegrama je isključena.');
            return;
        }
        if ($catQuery !== '') {
            $cat = self::resolveCategory($catQuery);
            if ($cat) {
                self::beginManualWithCategory($chatId, (string) $cat['id'], (string) $cat['name'], $forceAi);
                return;
            }
            self::sendText($chatId, '❌ Kategorija nije pronađena. Izaberi ispod:');
        }
        self::sendCategoryPicker($chatId, $forceAi);
    }

    private static function sendCategoryPicker(string $chatId, bool $forceAi): void
    {
        $cats = self::getCategories();
        if (!$cats) {
            self::beginManualWithCategory($chatId, '', 'Bez kategorije', $forceAi);
            return;
        }
        AutoVestiSession::setPending($chatId, [
            'mode' => 'pick_cat',
            'use_ai' => $forceAi ? '1' : '0',
        ]);
        self::api('sendMessage', [
            'chat_id' => $chatId,
            'text' => '📂 <b>Izaberi kategoriju</b>',
            'parse_mode' => 'HTML',
            'reply_markup' => ['inline_keyboard' => self::categoryKeyboard()],
        ]);
    }

    private static function beginManualWithCategory(string $chatId, string $catId, string $catName, bool $forceAi): void
    {
        $useAi = $forceAi ?: !empty(AutoVestiConfig::get('telegram_manual_use_ai', false));
        AutoVestiSession::setPending($chatId, [
            'mode' => 'manual',
            'cat_id' => $catId,
            'cat_name' => $catName,
            'use_ai' => $useAi ? '1' : '0',
        ]);
        self::sendText($chatId,
            "✍️ <b>Pošalji vest</b>\n📂 Kategorija: <b>" . self::escape($catName) . "</b>\n\n" .
            "Prvi red = <b>naslov</b>, ostatak = sadržaj.\n/otkazi — prekid"
        );
    }

    /** @param array<string,mixed> $callback */
    private static function onCategorySelected(string $chatId, array $callback, int $catId): void
    {
        $catName = 'Bez kategorije';
        if ($catId > 0) {
            foreach (self::getCategories() as $cat) {
                if ((string) $cat['id'] === (string) $catId) {
                    $catName = (string) $cat['name'];
                    break;
                }
            }
        }
        self::api('answerCallbackQuery', ['callback_query_id' => $callback['id'], 'text' => 'Kategorija: ' . $catName]);
        $pending = AutoVestiSession::getPending($chatId);
        $forceAi = !empty($pending['use_ai']) && $pending['use_ai'] === '1';
        self::beginManualWithCategory($chatId, $catId > 0 ? (string) $catId : '', $catName, $forceAi);
    }

    /** @param array<string,mixed> $message */
    private static function handleManualInput(string $chatId, array $message): void
    {
        $text = trim((string) ($message['text'] ?? ''));
        $caption = trim((string) ($message['caption'] ?? ''));
        $content = $caption !== '' ? $caption : $text;
        $fileId = self::extractPhotoFileId($message);
        $pending = AutoVestiSession::getPending($chatId);
        $pendingPhoto = (string) ($pending['photo_file_id'] ?? '');

        if ($fileId && $content !== '') {
            self::processManualPublish($chatId, $content, $fileId);
            return;
        }
        if ($fileId && $content === '') {
            AutoVestiSession::setPending($chatId, array_merge($pending, ['photo_file_id' => $fileId]));
            self::sendText($chatId, '📷 Slika primljena. Sada pošalji <b>tekst vesti</b>.');
            return;
        }
        if ($content !== '' && $pendingPhoto !== '') {
            self::processManualPublish($chatId, $content, $pendingPhoto);
            return;
        }
        if ($content !== '') {
            self::processManualPublish($chatId, $content, '');
            return;
        }
        self::sendText($chatId, 'Pošalji sliku sa tekstom (caption) ili samo tekst vesti.');
    }

    private static function processManualPublish(string $chatId, string $text, string $photoFileId): void
    {
        $pending = AutoVestiSession::getPending($chatId);
        $catId = (string) ($pending['cat_id'] ?? '');
        $catName = (string) ($pending['cat_name'] ?? '');
        $useAi = !empty($pending['use_ai']) && $pending['use_ai'] === '1';
        AutoVestiSession::clearPending($chatId);

        $imageUrl = $photoFileId !== '' ? self::getFileUrl($photoFileId) : '';

        // AI pipeline (rewrite + editor + grammar + SEO) traje predugo za Telegram webhook (~60s).
        // Isti background pattern kao ✅ AI + Objavi iz reda.
        if ($useAi) {
            self::sendText($chatId, '⏳ Šaljem u AI i objavljujem... (obično 30–90 sek)');
            AutoVestiBackground::dispatch([
                'type' => 'manual',
                'guid' => 'manual_' . md5($chatId . '|' . microtime(true) . '|' . mb_substr($text, 0, 80, 'UTF-8')),
                'mode' => 'manual_ai',
                'text' => $text,
                'image_url' => $imageUrl,
                'cat_id' => $catId,
                'cat_name' => $catName,
                'use_ai' => true,
                'chat_id' => $chatId,
                'secret' => AutoVestiConfig::get('telegram_webhook_secret', ''),
            ]);
            return;
        }

        self::sendText($chatId, '⏳ Objavljujem tvoj tekst...');
        try {
            @set_time_limit(120);
            $result = AutoVestiProcessor::publishManual($text, $imageUrl, $catId, false);
        } catch (Throwable $e) {
            self::sendText($chatId, '❌ <b>Greška:</b> ' . self::escape($e->getMessage() ?: 'Neočekivana greška.'));
            return;
        }
        if (is_string($result)) {
            self::sendText($chatId, '❌ <b>Greška:</b> ' . self::escape($result));
            return;
        }
        if (($result['status'] ?? '') === 'NEEDS_REVIEW') {
            $errors = isset($result['errors']) && is_array($result['errors'])
                ? implode('; ', array_map('strval', $result['errors']))
                : 'Potreban ručni pregled.';
            self::sendText($chatId, '⚠️ <b>Potreban pregled</b>' . "\n\n" . self::escape($errors));
            return;
        }

        $catLine = $catName !== '' ? "\n📂 " . self::escape($catName) : '';
        self::sendText($chatId,
            "✅ <b>Objavljeno!</b>\n\n📰 " . self::escape((string) ($result['title'] ?? '')) . $catLine .
            "\n🔗 <a href=\"" . e((string) ($result['url'] ?? '')) . "\">Pogledaj na sajtu</a>"
        );
    }

    private static function beginReplaceImage(string $chatId, string $guid): void
    {
        if (!AutoVestiQueue::getRow($guid)) {
            self::sendText($chatId, '❌ Vest više nije u redu.');
            return;
        }
        AutoVestiSession::setPending($chatId, ['mode' => 'replace_image', 'guid' => $guid]);
        self::sendText($chatId, "🖼 <b>Zamjena slike</b>\n\nPošalji novu fotografiju.\n/otkazi — prekid");
    }

    /** @param array<string,mixed> $message */
    private static function handleReplaceImageInput(string $chatId, array $message): void
    {
        $pending = AutoVestiSession::getPending($chatId);
        $guid = (string) ($pending['guid'] ?? '');
        $row = $guid !== '' ? AutoVestiQueue::getRow($guid) : null;
        if (!$row) {
            AutoVestiSession::clearPending($chatId);
            self::sendText($chatId, '❌ Vest više nije u redu.');
            return;
        }
        $fileId = self::extractPhotoFileId($message);
        if ($fileId === '') {
            self::sendText($chatId, 'Pošalji <b>fotografiju</b>.');
            return;
        }
        $tgUrl = self::getFileUrl($fileId);
        if ($tgUrl === '') {
            self::sendText($chatId, '❌ Nisam mogao preuzeti sliku.');
            return;
        }
        $saved = AutoVestiImages::sideload($tgUrl, (string) ($row['title'] ?? ''), config('site_url') . '/');
        if (is_string($saved)) {
            self::sendText($chatId, '❌ ' . self::escape($saved));
            return;
        }
        $row = AutoVestiQueue::updateRow($guid, [
            'image_url' => $saved['url'],
            'image_local_path' => $saved['path'],
            'image_credit' => '',
            'image_ai' => '0',
            'image_default' => '0',
        ]);
        AutoVestiSession::clearPending($chatId);
        self::sendText($chatId, '✅ Slika zamijenjena.');
        if ($row) {
            self::sendItemPreview($row, [$chatId]);
        }
    }

    private static function applyDefaultImageForQueue(string $chatId, string $guid): void
    {
        if (!AutoVestiImages::defaultReady()) {
            self::sendText($chatId, '⛔ Nema default slike. Uploaduj je u admin panelu.');
            return;
        }
        $row = AutoVestiQueue::getRow($guid);
        if (!$row) {
            self::sendText($chatId, '❌ Vest više nije u redu.');
            return;
        }
        $updated = AutoVestiImages::applyDefaultToRow($row);
        $row = AutoVestiQueue::updateRow($guid, [
            'image_url' => $updated['image_url'],
            'image_local_path' => $updated['image_local_path'] ?? AutoVestiImages::defaultPath(),
            'image_default' => '1',
            'image_credit' => '',
            'image_ai' => '0',
        ]);
        self::sendText($chatId, '✅ Default slika postavljena.');
        if ($row) {
            self::sendItemPreview($row, [$chatId]);
        }
    }

    private static function generateAiImageForQueue(string $chatId, string $guid): void
    {
        if (!AutoVestiImages::aiEnabled()) {
            self::sendText($chatId, '⛔ AI slike su isključene ili nema OpenAI ključa.');
            return;
        }
        $row = AutoVestiQueue::getRow($guid);
        if (!$row) {
            self::sendText($chatId, '❌ Vest više nije u redu.');
            return;
        }
        self::sendText($chatId, '🎨 Generišem AI sliku...');
        $saved = AutoVestiImages::generateAi((string) ($row['title'] ?? ''), (string) ($row['preview'] ?? ''));
        if (is_string($saved)) {
            self::sendText($chatId, '❌ ' . self::escape($saved));
            return;
        }
        $row = AutoVestiQueue::updateRow($guid, [
            'image_url' => $saved['url'],
            'image_local_path' => $saved['path'],
            'image_credit' => AutoVestiImages::aiCreditLabel(),
            'image_ai' => '1',
            'image_default' => '0',
        ]);
        self::sendText($chatId, '✅ AI slika spremna.');
        if ($row) {
            self::sendItemPreview($row, [$chatId]);
        }
    }

    /** @param array<string,mixed> $message */
    private static function tryLinkChat(string $chatId, string $code, array $message): void
    {
        $saved = (string) AutoVestiConfig::get('telegram_link_code', '');
        $expires = (int) AutoVestiConfig::get('telegram_link_expires', 0);
        if ($saved === '' || $code !== $saved || $expires < time()) {
            self::sendText($chatId, '❌ Link je istekao. Generišite novi u admin panelu → Telegram.');
            return;
        }
        AutoVestiConfig::addTelegramChatId($chatId);
        $name = trim((string) ($message['from']['first_name'] ?? ''));
        self::sendText($chatId, '✅ ' . ($name !== '' ? "Zdravo {$name}!" : 'Zdravo!') . " Povezani ste.\n\n/status — red\n/help — pomoć");
        AutoVestiConfig::log('Telegram povezan: chat ' . $chatId);
    }

    private static function cmdNext(string $chatId): void
    {
        $row = AutoVestiQueue::getNext(true);
        if (!$row) {
            self::sendText($chatId, '📭 Red čekanja je prazan.');
            return;
        }
        self::sendText($chatId, '📰 <b>Sljedeća vest u redu</b>');
        self::sendItemPreview($row, [$chatId]);
    }

    private static function cmdStatus(string $chatId): void
    {
        $queue = AutoVestiConfig::getQueue();
        $count = count($queue);
        if ($count === 0) {
            self::sendText($chatId, '📭 Red čekanja je prazan.');
            return;
        }
        $lines = ["📋 <b>Red čekanja: {$count}</b>", ''];
        foreach (array_slice($queue, 0, 10) as $i => $row) {
            $lines[] = ($i + 1) . '. ' . self::escape((string) ($row['title'] ?? ''));
        }
        if ($count > 10) {
            $lines[] = '... i još ' . ($count - 10) . ' vesti.';
        }
        $lines[] = '';
        $lines[] = 'Koristi /next za pregled sa dugmadima.';
        self::sendText($chatId, implode("\n", $lines));
    }

    private static function cmdHelp(string $chatId): void
    {
        self::sendText($chatId,
            "ℹ️ <b>Auto Vesti Manual</b>\n\n" .
            "✅ AI + Objavi · 📋 Original · ❌ Odbij · ⏸ Drži\n" .
            "📂 Kategorija · 🖼 Slika · Default · 🎨 AI slika\n\n" .
            "/link [kat] URL — vest sa linka\n" .
            "/objavi [kat] — tvoja vest (bez AI)\n" .
            "/objavi-ai [kat] — vest sa AI\n" .
            "/next · /status · /fetch · /otkazi · /help"
        );
    }

    /** @return array<int, array{id:string,name:string,slug:string}> */
    private static function getCategories(): array
    {
        $pdo = Database::connection();
        return $pdo->query('SELECT id, name, slug FROM categories ORDER BY name LIMIT 24')->fetchAll() ?: [];
    }

    /** @return array{id:string,name:string,slug:string}|null */
    private static function resolveCategory(string $query): ?array
    {
        $query = trim($query);
        if ($query === '') {
            return null;
        }
        $slugQuery = slugify($query);
        foreach (self::getCategories() as $cat) {
            if ($cat['slug'] === $slugQuery || $cat['id'] === $query) {
                return $cat;
            }
        }
        $qNorm = mb_strtolower($query, 'UTF-8');
        foreach (self::getCategories() as $cat) {
            $name = mb_strtolower((string) $cat['name'], 'UTF-8');
            if ($name === $qNorm || str_contains($name, $qNorm)) {
                return $cat;
            }
        }
        return null;
    }

    /** @return list<list<array<string,string>>> */
    private static function categoryKeyboard(): array
    {
        $rows = [[['text' => '— Bez kategorije —', 'callback_data' => 'mc:0']]];
        $row = [];
        foreach (self::getCategories() as $cat) {
            $row[] = ['text' => (string) $cat['name'], 'callback_data' => 'mc:' . $cat['id']];
            if (count($row) >= 2) {
                $rows[] = $row;
                $row = [];
            }
        }
        if ($row) {
            $rows[] = $row;
        }
        return $rows;
    }

    /** @return list<list<array<string,string>>> */
    private static function queueCategoryKeyboard(string $guid): array
    {
        $rows = [[['text' => '— Bez kategorije —', 'callback_data' => 'qmc:0:' . $guid]]];
        $row = [];
        foreach (self::getCategories() as $cat) {
            $row[] = ['text' => (string) $cat['name'], 'callback_data' => 'qmc:' . $cat['id'] . ':' . $guid];
            if (count($row) >= 2) {
                $rows[] = $row;
                $row = [];
            }
        }
        if ($row) {
            $rows[] = $row;
        }
        return $rows;
    }

    /** @return list<list<array<string,string>>> */
    private static function inlineKeyboard(string $guid, string $link): array
    {
        $rows = [
            [
                ['text' => '✅ AI + Objavi', 'callback_data' => 'ap:' . $guid],
                ['text' => '📋 Original', 'callback_data' => 'apn:' . $guid],
            ],
            [
                ['text' => '❌ Odbij', 'callback_data' => 'rj:' . $guid],
                ['text' => '⏸ Drži', 'callback_data' => 'hd:' . $guid],
            ],
            [
                ['text' => '📂 Kategorija', 'callback_data' => 'qc:' . $guid],
                ['text' => '🖼 Slika', 'callback_data' => 'ri:' . $guid],
            ],
        ];
        $imgRow = [];
        if (AutoVestiImages::defaultReady()) {
            $imgRow[] = ['text' => 'Default slika', 'callback_data' => 'di:' . $guid];
        }
        if (AutoVestiImages::aiEnabled()) {
            $imgRow[] = ['text' => '🎨 AI slika', 'callback_data' => 'gi:' . $guid];
        }
        if ($imgRow) {
            $rows[] = $imgRow;
        }
        if ($link !== '') {
            $rows[] = [['text' => '🔗 Original', 'url' => $link]];
        }
        return $rows;
    }

    /** @param array<string,mixed> $row */
    private static function buildCaption(array $row): string
    {
        $title = (string) ($row['title'] ?? '');
        $preview = (string) ($row['preview'] ?? '');
        $source = (string) ($row['source_host'] ?? '');
        $pub = AutoVestiQueue::formatPubDate((string) ($row['pub_date'] ?? ''));
        $breaking = AutoVestiContent::isBreaking($title . ' ' . $preview);
        $lines = [];
        if (($row['source_type'] ?? '') === 'telegram_link') {
            $lines[] = '🔗 <i>Link sa Telegrama</i>';
        }
        if ($breaking) {
            $lines[] = '🔴 <b>HITNA VEST</b>';
        }
        $lines[] = '📰 <b>' . self::escape($title) . '</b>';
        if ($preview !== '') {
            $lines[] = '';
            $lines[] = self::escape($preview);
        }
        $lines[] = '';
        $meta = [];
        if ($source !== '') {
            $meta[] = '🌐 ' . self::escape($source);
        }
        $meta[] = '📅 ' . self::escape($pub);
        $lines[] = implode(' · ', $meta);
        $text = implode("\n", $lines);
        if (mb_strlen($text, 'UTF-8') > 900) {
            $text = mb_substr($text, 0, 897, 'UTF-8') . '...';
        }
        return $text;
    }

    /** @param array<string,mixed> $callback */
    private static function editCallbackMessage(array $callback, string $suffix): void
    {
        $msg = $callback['message'] ?? null;
        if (!is_array($msg)) {
            return;
        }
        $chatId = $msg['chat']['id'] ?? '';
        $msgId = $msg['message_id'] ?? '';
        $old = (string) ($msg['caption'] ?? $msg['text'] ?? '');
        $new = $old . "\n\n—\n" . $suffix;
        if (!empty($msg['photo'])) {
            self::api('editMessageCaption', [
                'chat_id' => $chatId,
                'message_id' => $msgId,
                'caption' => $new,
                'parse_mode' => 'HTML',
                'reply_markup' => ['inline_keyboard' => []],
            ]);
        } else {
            self::api('editMessageText', [
                'chat_id' => $chatId,
                'message_id' => $msgId,
                'text' => $new,
                'parse_mode' => 'HTML',
                'reply_markup' => ['inline_keyboard' => []],
            ]);
        }
    }

    /** @param array<string,mixed> $callback @return array<string,mixed> */
    private static function editorFromCallback(array $callback): array
    {
        $from = $callback['from'] ?? [];
        $name = trim(((string) ($from['first_name'] ?? '')) . ' ' . ((string) ($from['last_name'] ?? '')));
        return [
            'user_id' => (int) ($from['id'] ?? 0),
            'username' => !empty($from['username']) ? '@' . $from['username'] : '',
            'name' => $name,
        ];
    }

    /** @param array<string,mixed> $message */
    private static function extractPhotoFileId(array $message): string
    {
        if (empty($message['photo']) || !is_array($message['photo'])) {
            return '';
        }
        $photo = end($message['photo']);
        return is_array($photo) ? (string) ($photo['file_id'] ?? '') : '';
    }

    private static function getFileUrl(string $fileId): string
    {
        $file = self::api('getFile', ['file_id' => $fileId]);
        if (!is_array($file) || empty($file['file_path'])) {
            return '';
        }
        $token = trim((string) AutoVestiConfig::get('telegram_bot_token', ''));
        return 'https://api.telegram.org/file/bot' . $token . '/' . $file['file_path'];
    }

    public static function escape(string $text): string
    {
        return htmlspecialchars(strip_tags($text), ENT_QUOTES, 'UTF-8');
    }

    private static function isChatAllowed(string $chatId): bool
    {
        return in_array($chatId, AutoVestiConfig::getTelegramChatIds(), true);
    }

    private static function manualEnabled(): bool
    {
        return !empty(AutoVestiConfig::get('telegram_manual_publish', true));
    }

    private static function linkEnabled(): bool
    {
        return !empty(AutoVestiConfig::get('telegram_link_scrape', true));
    }

    private static function isManualMode(string $chatId): bool
    {
        $p = AutoVestiSession::getPending($chatId);
        return ($p['mode'] ?? '') === 'manual' && isset($p['cat_id']);
    }

    private static function isPickCatMode(string $chatId): bool
    {
        return (AutoVestiSession::getPending($chatId)['mode'] ?? '') === 'pick_cat';
    }

    private static function isReplaceImageMode(string $chatId): bool
    {
        $p = AutoVestiSession::getPending($chatId);
        return ($p['mode'] ?? '') === 'replace_image' && !empty($p['guid']);
    }

    private static function normalizeCommand(string $text): string
    {
        $text = trim($text);
        if ($text === '' || !str_starts_with($text, '/')) {
            return $text;
        }
        return preg_replace('#^(/[\w]+)@[\w_]+#u', '$1', $text) ?? $text;
    }

    private static function commandIs(string $text, string $command): bool
    {
        $norm = self::normalizeCommand($text);
        return $norm === $command || str_starts_with($norm, $command . ' ');
    }

    private static function extractUrl(string $text): string
    {
        if (preg_match('#https?://[^\s<>"\']+#i', $text, $m)) {
            return rtrim($m[0], '.,);]>');
        }
        return '';
    }

    private static function isPlainUrlMessage(string $text, string $url): bool
    {
        $trimmed = trim($text);
        return $trimmed === $url || $trimmed === rtrim($url, '/');
    }

    /** @return array{url:string,cat_query:string} */
    private static function parseLinkArgs(string $arg): array
    {
        $arg = trim($arg);
        if ($arg === '') {
            return ['url' => '', 'cat_query' => ''];
        }
        $url = '';
        $catParts = [];
        foreach (preg_split('/\s+/', $arg) ?: [] as $part) {
            if (preg_match('#^https?://#i', $part)) {
                $url = rtrim($part, '.,);]>');
            } else {
                $catParts[] = $part;
            }
        }
        return ['url' => $url, 'cat_query' => implode(' ', $catParts)];
    }
}
