<?php

declare(strict_types=1);

final class AutoVestiSession
{
    private const FILE = __DIR__ . '/../storage/auto-vesti-tg-session.json';

    /** @return array<string, mixed> */
    private static function all(): array
    {
        if (!is_file(self::FILE)) {
            return [];
        }
        $data = json_decode((string) file_get_contents(self::FILE), true);
        return is_array($data) ? $data : [];
    }

    /** @param array<string, mixed> $data */
    private static function write(array $data): void
    {
        $dir = dirname(self::FILE);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        file_put_contents(self::FILE, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    /** @return array<string, mixed> */
    public static function getPending(string $chatId): array
    {
        $key = 'pending_' . $chatId;
        $row = self::all()[$key] ?? null;
        if (!is_array($row)) {
            return [];
        }
        if (!empty($row['expires']) && (int) $row['expires'] < time()) {
            self::clearPending($chatId);
            return [];
        }
        return is_array($row['data'] ?? null) ? $row['data'] : [];
    }

    /** @param array<string, mixed> $data */
    public static function setPending(string $chatId, array $data, int $ttl = 900): void
    {
        $all = self::all();
        $all['pending_' . $chatId] = [
            'data' => $data,
            'expires' => time() + $ttl,
        ];
        self::write($all);
    }

    public static function clearPending(string $chatId): void
    {
        $all = self::all();
        unset($all['pending_' . $chatId]);
        self::write($all);
    }

    public static function rateLimitOk(string $chatId): bool
    {
        $key = 'rate_' . $chatId;
        $all = self::all();
        $row = $all[$key] ?? ['count' => 0, 'expires' => 0];
        if ((int) ($row['expires'] ?? 0) < time()) {
            $row = ['count' => 0, 'expires' => time() + 60];
        }
        if ((int) $row['count'] >= 30) {
            return false;
        }
        $row['count'] = (int) $row['count'] + 1;
        $all[$key] = $row;
        self::write($all);
        return true;
    }
}
