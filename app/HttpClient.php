<?php

declare(strict_types=1);

final class HttpClient
{
    public static function get(string $url, int $timeout = 25, ?string $userAgent = null): ?string
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        $ua = $userAgent ?: 'SandzakNetImporter/1.0 (+https://sandzak.net)';

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 5,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_USERAGENT => $ua,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_HTTPHEADER => [
                    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Accept-Language: sr-Latn,sr,bs,hr,en;q=0.8',
                ],
            ]);
            $body = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($body === false || $code >= 400) {
                return null;
            }
            return (string) $body;
        }

        $ctx = stream_context_create([
            'http' => [
                'timeout' => $timeout,
                'header' => 'User-Agent: ' . $ua . "\r\n",
            ],
        ]);
        $body = @file_get_contents($url, false, $ctx);
        return $body !== false ? $body : null;
    }

    /** Preuzimanje binarnih fajlova (slike). Vraća [body, httpCode] ili null. */
    public static function download(string $url, int $timeout = 30, ?string $referer = null, ?string $userAgent = null): ?array
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        $ua = $userAgent ?: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/122.0.0.0 Safari/537.36';
        $headers = ['Accept: image/avif,image/webp,image/apng,image/*,*/*;q=0.8'];
        if ($referer) {
            $headers[] = 'Referer: ' . $referer;
        }

        if (!function_exists('curl_init')) {
            return null;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_USERAGENT => $ua,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_HTTPHEADER => $headers,
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $type = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);

        if ($body === false || $code >= 400) {
            return ['body' => '', 'code' => $code, 'type' => $type];
        }

        return ['body' => (string) $body, 'code' => $code, 'type' => $type];
    }

    /** @param array<string, mixed> $body */
    public static function postJson(string $url, array $body, array $headers, int $timeout = 90): ?array
    {
        $payload = json_encode($body, JSON_UNESCAPED_UNICODE);
        if ($payload === false) {
            return null;
        }
        $hdr = array_merge(['Content-Type: application/json'], $headers);

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_HTTPHEADER => $hdr,
                CURLOPT_CONNECTTIMEOUT => 15,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_SSL_VERIFYPEER => true,
            ]);
            $raw = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($raw === false || $code >= 400) {
                return ['_error' => true, '_code' => $code, '_body' => is_string($raw) ? $raw : ''];
            }
            $decoded = json_decode((string) $raw, true);
            return is_array($decoded) ? $decoded : null;
        }

        return null;
    }

    /** @param array<string, scalar|null> $fields */
    public static function postForm(string $url, array $fields, int $timeout = 60): ?array
    {
        $payload = http_build_query($fields);

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
            ]);
            $raw = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($raw === false || $code >= 400) {
                return ['_error' => true, '_code' => $code, '_body' => is_string($raw) ? $raw : ''];
            }
            $decoded = json_decode((string) $raw, true);
            return is_array($decoded) ? $decoded : null;
        }

        return null;
    }

    /** Fire-and-forget POST — ne čeka završetak AI obrade. */
    public static function postJsonAsync(string $url, array $body, array $headers = []): void
    {
        $payload = json_encode($body, JSON_UNESCAPED_UNICODE);
        if ($payload === false) {
            return;
        }
        $parts = parse_url($url);
        if (!$parts || empty($parts['host'])) {
            return;
        }
        $scheme = ($parts['scheme'] ?? 'http') === 'https' ? 'ssl://' : '';
        $port = (int) ($parts['port'] ?? ($scheme !== '' ? 443 : 80));
        $host = $parts['host'];
        $path = ($parts['path'] ?? '/') . (isset($parts['query']) ? '?' . $parts['query'] : '');
        $fp = @fsockopen($scheme . $host, $port, $errno, $errstr, 3);
        if (!$fp) {
            return;
        }
        $hdr = array_merge(['Content-Type: application/json', 'Connection: Close'], $headers);
        $req = "POST {$path} HTTP/1.1\r\n"
            . "Host: {$host}\r\n"
            . "Content-Length: " . strlen($payload) . "\r\n"
            . implode("\r\n", $hdr) . "\r\n\r\n"
            . $payload;
        stream_set_timeout($fp, 1);
        @fwrite($fp, $req);
        @fclose($fp);
    }
}
