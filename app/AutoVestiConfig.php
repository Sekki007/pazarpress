<?php

declare(strict_types=1);

final class AutoVestiConfig
{
    private const FILE = __DIR__ . '/../storage/auto-vesti.json';

    /** Runtime stanje — ostaje u JSON fajlu (log, red, seen GUID-ovi). */
    private const RUNTIME_KEYS = [
        'seen_guids',
        'log',
        'last_run_at',
        'last_fetch_at',
        'queue',
        'stats',
    ];

    /** @var list<string> */
    private const PRESERVED_ON_SAVE = [
        'seen_guids',
        'log',
        'last_run_at',
        'last_fetch_at',
        'queue',
        'stats',
        'telegram_chat_ids',
        'telegram_webhook_secret',
        'telegram_link_code',
        'telegram_link_expires',
        'telegram_bot_username',
    ];

    /** @var list<string> */
    private const SECRET_KEYS = [
        'api_key',
        'openai_api_key',
        'telegram_bot_token',
    ];

    private static bool $tableReady = false;
    private static bool $migratedFromFile = false;

    public const BREAKING_KW = [
        'hitno', 'hitna', 'breaking', 'ekskluzivno', 'upravo', 'vanredno',
        'urgent', 'upozorenje', 'katastrofa', 'napad', 'zemljotres', 'poplave',
        'smrt', 'ubojstvo', 'uhapsen', 'poginuo', 'eksplozija', 'pozar', 'nesreca',
    ];

    /**
     * Ključne reči za automatsko dodeljivanje rubrike (slug → keywords).
     * Veći skor = jači signal; title/lead vredi više od body-ja.
     *
     * @var array<string, list<string>>
     */
    public const CATEGORY_KEYWORDS = [
        'sport' => [
            'fudbal', 'nogomet', 'košark', 'kosark', 'odbojk', 'rukomet', 'tenis',
            'utakmic', 'prvenstv', 'liga ', ' lige', 'gol ', 'gole ', 'igrač', 'igrac',
            'trener', 'olimpij', 'fifa', 'uefa', 'fk ', 'kk ', 'atletik', 'skijan',
            'meč', 'mec ', 'derbi', 'sportsk', 'superliga', 'premijer', 'rezultat utak',
            'transfer', 'kapiten', 'stadion', 'navijač', 'navijac', 'penal', 'korner',
        ],
        'politika' => [
            'vlada', 'skupštin', 'skupstin', 'ministar', 'predsednik', 'predsjednik',
            'partij', 'izbori', 'izborn', 'koalicij', 'opozicij', 'poslanik', 'odbornik',
            'politič', 'politic', 'demokratsk', 'referendum', 'kampanj', 'mandat',
            'amandman', 'parlament', 'vlade', 'premijer', 'gradonačelnik', 'gradonacelnik',
            'sdp', 'sns', 'sps', 'ds ', 'stranka', 'stranke', 'kandidat',
        ],
        'hronika' => [
            'ubistv', 'ubojstv', 'nesreć', 'nesrec', 'sudar', 'požar', 'pozar',
            'krađ', 'kradj', 'policij', 'uhapšen', 'uhapsen', 'hapšenj', 'hapsenj',
            'saobraćaj', 'saobracaj', 'incident', 'eksplozij', 'ranjen', 'preminuo',
            'poginuo', 'mrtav', 'krivič', 'krivic', 'tužilašt', 'tuzilast', 'pretres',
            'droga', 'narkotik', 'oružj', 'oruzj', 'pucanj', 'pucnjav', 'maltretir',
            'nasilj', 'pljačk', 'pljack', 'krađa', 'krada', 'povređen', 'povredjen',
        ],
        'ekonomija' => [
            'privred', 'investicij', 'budžet', 'budzet', 'inflacij', 'zaposlen',
            'nezaposlen', 'preduzeć', 'preduzec', 'firma ', 'banka', 'kredit',
            'porez', 'tržišt', 'trzist', 'ekonomij', 'plata', 'plate ', 'eur ',
            'dinar', 'kurs ', 'berza', 'privrednik', 'preduzetnik', 'poslodavac',
        ],
        'kultura' => [
            'festival', 'pozorišt', 'pozorist', 'koncert', 'izložb', 'izlozb',
            'muzej', 'knjig', 'roman ', 'pesnik', 'umetnost', 'umjetnost', 'kulturn',
            'film ', 'filma', 'glumac', 'glumic', 'pevač', 'pjevac', 'pevac',
            'muzič', 'muzic', 'galerij', 'predstav', 'balet', 'opera',
        ],
        'dijaspora' => [
            'dijaspor', 'inostranstv', 'emigr', 'iseljen', 'gastarbajter',
            'švajcar', 'svajcar', 'nemačk', 'nemack', 'austrij', 'dijaspora',
            'radnici u inostr', 'naši u ', 'nasi u ',
        ],
        'drustvo' => [
            'škola', 'skola', 'obrazovanj', 'bolnic', 'zdravstv', 'pacijen',
            'studen', 'univerzitet', 'vrtić', 'vrtic', 'socijaln', 'penzij',
            'građan', 'gradjan', 'lokalna zajednic',
        ],
    ];

    public static function defaults(): array
    {
        return [
            'api_key' => '',
            'openai_api_key' => '',
            'ai_provider' => 'claude',
            'claude_model' => 'claude-sonnet-4-20250514',
            'openai_model' => 'gpt-4o-mini',
            'feeds_map' => [],
            'lang' => 'bosanski',
            'status' => 'draft',
            'max_fetch_per_run' => 20,
            'max_per_run' => 5,
            'interval_minutes' => 180,
            'from_date' => '',
            'article_min_words' => 800,
            'article_max_words' => 1500,
            'use_image' => true,
            'use_faq' => true,
            'use_internal_links' => true,
            'use_youtube' => true,
            'use_dup_check' => true,
            'use_full_article' => true,
            'show_source_footer' => true,
            'fact_protection_enabled' => true,
            'fact_protection_enforce' => false,
            'fact_protection_block_on_new_person' => true,
            'post_process_editor_enabled' => true,
            'grammar_polish_enabled' => true,
            'seo_layer_enabled' => true,
            'fact_protection_tests_passed' => false,
            'fact_protection_tests' => [],
            'use_ai_image' => true,
            'use_default_image' => true,
            'show_image_credit' => true,
            'default_image_path' => '',
            'default_image_label' => '',
            'ai_image_model' => 'gpt-image-1',
            'ai_image_size' => '1024x1024',
            'ai_image_quality' => 'medium',
            'ai_image_credit_label' => 'Ilustracija (AI)',
            'telegram_bot_token' => '',
            'telegram_bot_username' => '',
            'telegram_chat_ids' => [],
            'telegram_webhook_secret' => '',
            'telegram_link_code' => '',
            'telegram_link_expires' => 0,
            'telegram_notify' => true,
            'telegram_manual_publish' => true,
            'telegram_manual_use_ai' => false,
            'telegram_link_scrape' => true,
            'telegram_manual_cat' => '',
            'stats' => [],
            'default_author_id' => '',
            'default_city' => 'NOVI_PAZAR',
            'seen_guids' => [],
            'queue' => [],
            'log' => [],
            'last_run_at' => null,
            'last_fetch_at' => null,
        ];
    }

    public static function all(): array
    {
        self::ensureTable();
        self::migrateFromFileIfNeeded();

        $data = self::defaults();
        $data = array_merge($data, self::loadFromDb());
        $data = array_merge($data, self::loadRuntimeFromFile());

        if (empty($data['max_fetch_per_run']) && !empty($data['max_per_run'])) {
            $data['max_fetch_per_run'] = (int) $data['max_per_run'];
        }

        return $data;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::all()[$key] ?? $default;
    }

    public static function save(array $input): void
    {
        $current = self::all();
        foreach (array_keys(self::defaults()) as $key) {
            if (in_array($key, self::PRESERVED_ON_SAVE, true)) {
                continue;
            }
            if (!array_key_exists($key, $input)) {
                continue;
            }
            if (in_array($key, self::SECRET_KEYS, true) && trim((string) $input[$key]) === '') {
                continue;
            }
            $current[$key] = $input[$key];
        }
        self::write($current);
    }

    /** @param array<string, mixed> $partial */
    public static function updatePartial(array $partial): void
    {
        $current = self::all();
        foreach ($partial as $key => $value) {
            $current[$key] = $value;
        }
        self::write($current);
    }

    public static function ensureTelegramWebhookSecret(): string
    {
        $secret = trim((string) self::get('telegram_webhook_secret', ''));
        if ($secret !== '') {
            return $secret;
        }
        $secret = bin2hex(random_bytes(16));
        self::updatePartial(['telegram_webhook_secret' => $secret]);
        return $secret;
    }

    /** @return list<string> */
    public static function getTelegramChatIds(): array
    {
        $ids = self::get('telegram_chat_ids', []);
        if (!is_array($ids)) {
            return [];
        }
        return array_values(array_filter(array_map('strval', $ids)));
    }

    public static function addTelegramChatId(string $chatId): void
    {
        $ids = self::getTelegramChatIds();
        if (in_array($chatId, $ids, true)) {
            return;
        }
        $ids[] = $chatId;
        self::updatePartial(['telegram_chat_ids' => $ids]);
    }

    public static function removeTelegramChatId(string $chatId): void
    {
        $ids = array_values(array_filter(
            self::getTelegramChatIds(),
            static fn ($id) => $id !== $chatId
        ));
        self::updatePartial(['telegram_chat_ids' => $ids]);
    }

    public static function saveTelegram(array $input): void
    {
        $current = self::all();
        $merge = [
            'telegram_notify' => !empty($input['telegram_notify']),
            'telegram_manual_publish' => !empty($input['telegram_manual_publish']),
            'telegram_manual_use_ai' => !empty($input['telegram_manual_use_ai']),
            'telegram_link_scrape' => !empty($input['telegram_link_scrape']),
            'telegram_manual_cat' => trim((string) ($input['telegram_manual_cat'] ?? '')),
        ];
        $token = trim((string) ($input['telegram_bot_token'] ?? ''));
        if ($token !== '') {
            $merge['telegram_bot_token'] = $token;
            if (($current['telegram_bot_token'] ?? '') !== $token) {
                $merge['telegram_bot_username'] = '';
            }
        }
        self::updatePartial($merge);
        self::ensureTelegramWebhookSecret();
    }

    /** @return array<int, array<string, mixed>> */
    public static function getQueue(): array
    {
        $queue = self::all()['queue'] ?? [];
        return is_array($queue) ? $queue : [];
    }

    /** @param array<int, array<string, mixed>> $queue */
    public static function saveQueue(array $queue): void
    {
        $data = self::all();
        if (count($queue) > 100) {
            $queue = array_slice($queue, 0, 100);
        }
        $data['queue'] = array_values($queue);
        self::write($data);
    }

    public static function clearQueue(): void
    {
        $data = self::all();
        $data['queue'] = [];
        self::write($data);
    }

    public static function log(string $msg): void
    {
        $data = self::all();
        array_unshift($data['log'], ['time' => date('Y-m-d H:i:s'), 'msg' => $msg]);
        $data['log'] = array_slice($data['log'], 0, 200);
        self::write($data);
    }

    public static function touchLastRun(): void
    {
        $data = self::all();
        $data['last_run_at'] = date('Y-m-d H:i:s');
        self::write($data);
    }

    public static function touchLastFetch(): void
    {
        $data = self::all();
        $data['last_fetch_at'] = date('Y-m-d H:i:s');
        $data['last_run_at'] = $data['last_fetch_at'];
        self::write($data);
    }

    public static function clearLog(): void
    {
        $data = self::all();
        $data['log'] = [];
        self::write($data);
    }

    public static function clearSeen(): void
    {
        $data = self::all();
        $data['seen_guids'] = [];
        self::write($data);
    }

    public static function isSeen(string $guid): bool
    {
        return in_array($guid, self::all()['seen_guids'], true);
    }

    public static function markSeen(string $guid): void
    {
        $data = self::all();
        $data['seen_guids'][] = $guid;
        if (count($data['seen_guids']) > 3000) {
            $data['seen_guids'] = array_slice($data['seen_guids'], -3000);
        }
        self::write($data);
    }

    public static function hasConfiguredTelegramToken(): bool
    {
        return trim((string) self::get('telegram_bot_token', '')) !== '';
    }

    public static function hasConfiguredApiKey(): bool
    {
        $cfg = self::all();
        $provider = (string) ($cfg['ai_provider'] ?? 'claude');
        $key = trim($provider === 'openai'
            ? (string) ($cfg['openai_api_key'] ?? '')
            : (string) ($cfg['api_key'] ?? ''));

        return $key !== '';
    }

    /** @return list<string> */
    private static function persistentKeys(): array
    {
        return array_values(array_filter(
            array_keys(self::defaults()),
            static fn (string $key) => !in_array($key, self::RUNTIME_KEYS, true)
        ));
    }

    private static function ensureTable(): void
    {
        if (self::$tableReady) {
            return;
        }

        try {
            $pdo = Database::connection();
            $driver = (string) (config('db')['driver'] ?? 'sqlite');
            if ($driver === 'mysql') {
                $pdo->exec('CREATE TABLE IF NOT EXISTS auto_vesti_settings (
                    settingKey VARCHAR(128) PRIMARY KEY,
                    value LONGTEXT NOT NULL,
                    updatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                )');
            } else {
                $pdo->exec('CREATE TABLE IF NOT EXISTS auto_vesti_settings (
                    settingKey TEXT PRIMARY KEY,
                    value TEXT NOT NULL,
                    updatedAt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
                )');
            }
            self::$tableReady = true;
        } catch (Throwable) {
            self::$tableReady = false;
        }
    }

    /** @return array<string, mixed> */
    private static function loadFromDb(): array
    {
        if (!self::$tableReady) {
            return self::loadPersistentFromFile();
        }

        try {
            $rows = Database::connection()
                ->query('SELECT settingKey, value FROM auto_vesti_settings')
                ->fetchAll();
        } catch (Throwable) {
            return self::loadPersistentFromFile();
        }

        $data = [];
        foreach ($rows as $row) {
            $key = (string) ($row['settingKey'] ?? '');
            if ($key === '' || !in_array($key, self::persistentKeys(), true)) {
                continue;
            }
            $decoded = json_decode((string) ($row['value'] ?? ''), true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $data[$key] = $decoded;
            }
        }

        return $data;
    }

    /** @return array<string, mixed> */
    private static function loadRuntimeFromFile(): array
    {
        $raw = self::readRawFile();
        if ($raw === []) {
            return [];
        }

        $data = [];
        foreach (self::RUNTIME_KEYS as $key) {
            if (array_key_exists($key, $raw)) {
                $data[$key] = $raw[$key];
            }
        }

        return $data;
    }

    /** @return array<string, mixed> */
    private static function loadPersistentFromFile(): array
    {
        $raw = self::readRawFile();
        if ($raw === []) {
            return [];
        }

        $data = [];
        foreach (self::persistentKeys() as $key) {
            if (array_key_exists($key, $raw)) {
                $data[$key] = $raw[$key];
            }
        }

        return $data;
    }

    /** @return array<string, mixed> */
    private static function readRawFile(): array
    {
        if (!is_file(self::FILE)) {
            return [];
        }
        $json = json_decode((string) file_get_contents(self::FILE), true);
        return is_array($json) ? $json : [];
    }

    private static function migrateFromFileIfNeeded(): void
    {
        if (self::$migratedFromFile || !self::$tableReady) {
            return;
        }
        self::$migratedFromFile = true;

        try {
            $count = (int) Database::connection()
                ->query('SELECT COUNT(*) FROM auto_vesti_settings')
                ->fetchColumn();
            if ($count > 0) {
                return;
            }
        } catch (Throwable) {
            return;
        }

        $fromFile = self::loadPersistentFromFile();
        if ($fromFile === []) {
            return;
        }

        self::saveToDb($fromFile);
    }

    /** @param array<string, mixed> $data */
    private static function saveToDb(array $data): void
    {
        if (!self::$tableReady) {
            return;
        }

        try {
            $pdo = Database::connection();
            $driver = (string) (config('db')['driver'] ?? 'sqlite');
            if ($driver === 'mysql') {
                $sql = 'INSERT INTO auto_vesti_settings (settingKey, value, updatedAt)
                        VALUES (:key, :value, :updatedAt)
                        ON DUPLICATE KEY UPDATE value = VALUES(value), updatedAt = VALUES(updatedAt)';
            } else {
                $sql = 'INSERT INTO auto_vesti_settings (settingKey, value, updatedAt)
                        VALUES (:key, :value, :updatedAt)
                        ON CONFLICT(settingKey) DO UPDATE SET value = excluded.value, updatedAt = excluded.updatedAt';
            }
            $stmt = $pdo->prepare($sql);
            $now = date('Y-m-d H:i:s');
            foreach (self::persistentKeys() as $key) {
                if (!array_key_exists($key, $data)) {
                    continue;
                }
                $stmt->execute([
                    ':key' => $key,
                    ':value' => json_encode($data[$key], JSON_UNESCAPED_UNICODE),
                    ':updatedAt' => $now,
                ]);
            }
        } catch (Throwable) {
            // Fallback: persistent keys ostaju u fajlu dok migracija ne prođe.
        }
    }

    /** @param array<string, mixed> $data */
    private static function writeRuntimeToFile(array $data): void
    {
        $runtime = [];
        foreach (self::RUNTIME_KEYS as $key) {
            if (array_key_exists($key, $data)) {
                $runtime[$key] = $data[$key];
            }
        }

        $dir = dirname(self::FILE);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        file_put_contents(
            self::FILE,
            json_encode($runtime, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
    }

    private static function write(array $data): void
    {
        self::ensureTable();
        self::saveToDb($data);
        self::writeRuntimeToFile($data);

        if (!self::$tableReady) {
            self::writeLegacyFile($data);
        }
    }

    /** @param array<string, mixed> $data */
    private static function writeLegacyFile(array $data): void
    {
        $dir = dirname(self::FILE);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        file_put_contents(
            self::FILE,
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
    }
}
