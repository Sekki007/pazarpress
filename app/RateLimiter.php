<?php

declare(strict_types=1);

final class RateLimiter
{
    public static function hit(string $key, int $maxAttempts, int $windowSeconds): bool
    {
        $dir = config('cache_dir') . '/rate';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $file = $dir . '/' . md5($key) . '.json';
        $now = time();
        $data = ['attempts' => [], 'blocked_until' => 0];
        if (is_file($file)) {
            $decoded = json_decode((string) file_get_contents($file), true);
            if (is_array($decoded)) {
                $data = $decoded;
            }
        }
        if ($data['blocked_until'] > $now) {
            return false;
        }
        $data['attempts'] = array_values(array_filter(
            $data['attempts'],
            static fn (int $ts): bool => $ts > $now - $windowSeconds
        ));
        if (count($data['attempts']) >= $maxAttempts) {
            $data['blocked_until'] = $now + $windowSeconds;
            file_put_contents($file, json_encode($data));
            return false;
        }
        $data['attempts'][] = $now;
        file_put_contents($file, json_encode($data));
        return true;
    }

    public static function clientKey(string $scope): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        return $scope . ':' . $ip;
    }
}
