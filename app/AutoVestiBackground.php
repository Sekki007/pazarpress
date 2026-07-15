<?php

declare(strict_types=1);

final class AutoVestiBackground
{
    private const STATE_FILE = __DIR__ . '/../storage/auto-vesti-bg-state.json';

    /** @var array<string, mixed>|null */
    private static ?array $pendingPayload = null;

    private static bool $shutdownRegistered = false;

    /** @param array<string, mixed> $payload */
    public static function dispatch(array $payload): void
    {
        self::$pendingPayload = $payload;
        self::registerShutdown();
        HttpClient::postJsonAsync(absolute_url('/api/avm/process-background'), $payload);
    }

    private static function registerShutdown(): void
    {
        if (self::$shutdownRegistered) {
            return;
        }
        self::$shutdownRegistered = true;
        register_shutdown_function([self::class, 'runShutdown']);
    }

    public static function runShutdown(): void
    {
        $payload = self::$pendingPayload;
        self::$pendingPayload = null;
        if (!$payload) {
            return;
        }
        self::run($payload);
    }

    /** @param array<string, mixed> $payload */
    public static function run(array $payload): void
    {
        if (function_exists('fastcgi_finish_request')) {
            @fastcgi_finish_request();
        }
        ignore_user_abort(true);
        @set_time_limit(600);
        AutoVestiProcessor::executeBackground($payload);
    }

    public static function tryClaim(string $guid, string $mode): ?string
    {
        $key = substr(md5($guid . '|' . $mode), 0, 20);
        $fp = self::openState();
        if (!$fp) {
            return $key;
        }
        flock($fp, LOCK_EX);
        $state = self::readState($fp);
        $now = time();
        $cur = $state[$key] ?? null;
        if (is_array($cur)) {
            if (($cur['s'] ?? '') === 'done' && ($now - (int) ($cur['t'] ?? 0)) < 900) {
                flock($fp, LOCK_UN);
                fclose($fp);
                return null;
            }
            if (($cur['s'] ?? '') === 'run' && ($now - (int) ($cur['t'] ?? 0)) < 600) {
                flock($fp, LOCK_UN);
                fclose($fp);
                return null;
            }
        }
        $state[$key] = ['t' => $now, 's' => 'run'];
        self::writeState($fp, $state);
        flock($fp, LOCK_UN);
        fclose($fp);
        return $key;
    }

    public static function finish(?string $key, bool $success): void
    {
        if ($key === null || $key === '') {
            return;
        }
        $fp = self::openState();
        if (!$fp) {
            return;
        }
        flock($fp, LOCK_EX);
        $state = self::readState($fp);
        if ($success) {
            $state[$key] = ['t' => time(), 's' => 'done'];
        } else {
            unset($state[$key]);
        }
        self::writeState($fp, $state);
        flock($fp, LOCK_UN);
        fclose($fp);
    }

    /** @return resource|false */
    private static function openState()
    {
        $dir = dirname(self::STATE_FILE);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        return @fopen(self::STATE_FILE, 'c+');
    }

    /** @param resource $fp @return array<string, array{t:int,s:string}> */
    private static function readState($fp): array
    {
        rewind($fp);
        $raw = stream_get_contents($fp);
        if ($raw === false || trim($raw) === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    /** @param resource $fp @param array<string, array{t:int,s:string}> $state */
    private static function writeState($fp, array $state): void
    {
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($state, JSON_UNESCAPED_UNICODE));
        fflush($fp);
    }
}
