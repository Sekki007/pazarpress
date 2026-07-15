<?php

declare(strict_types=1);

final class RestaurantCoverGenerator
{
    private const WIDTH = 1200;
    private const HEIGHT = 630;

    /** @var list<array{0: string, 1: string, 2: string}> */
    private const PALETTES = [
        ['#0a4234', '#0e5a48', '#1b7a63'],
        ['#1a3a32', '#0e5a48', '#2fa98c'],
        ['#3d2e14', '#8b5a14', '#e9a13b'],
        ['#1e2a3a', '#2d4a7a', '#5b8fd4'],
        ['#3a1f28', '#8b2942', '#d45a72'],
        ['#1f2937', '#374151', '#6b7280'],
    ];

    /**
     * Generiše cover ako nema upload — snima SVG u uploads i ažurira bazu.
     * @param array<string, mixed> $restaurant
     */
    public static function ensureStored(array $restaurant): ?string
    {
        if (!empty($restaurant['coverImage'])) {
            return (string) $restaurant['coverImage'];
        }

        $id = (string) ($restaurant['id'] ?? '');
        $name = trim((string) ($restaurant['name'] ?? ''));
        $slug = trim((string) ($restaurant['slug'] ?? ''));
        if ($id === '' || $name === '' || $slug === '') {
            return null;
        }

        $city = city_label((string) ($restaurant['city'] ?? 'OTHER'));
        $path = self::writeCoverFile($name, $city, $slug);
        if ($path === null) {
            return null;
        }

        Database::connection()
            ->prepare('UPDATE restaurants SET coverImage = ?, updatedAt = ? WHERE id = ? AND (coverImage IS NULL OR coverImage = \'\')')
            ->execute([$path, date('Y-m-d H:i:s'), $id]);

        cache_flush_prefix('restaurants');
        cache_flush_prefix('home');

        return $path;
    }

    public static function writeCoverFile(string $name, string $city, string $slug): ?string
    {
        $svg = self::buildSvg($name, $city, $slug);
        $dir = config('upload_dir');
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $safeSlug = preg_replace('/[^a-z0-9-]/', '', slugify($slug)) ?: 'restoran';
        $file = $dir . '/rest-cover-' . $safeSlug . '.svg';
        if (file_put_contents($file, $svg) === false) {
            return null;
        }

        return '/uploads/rest-cover-' . $safeSlug . '.svg';
    }

    public static function buildSvg(string $name, string $city, string $slug): string
    {
        $palette = self::PALETTES[crc32($slug) % count(self::PALETTES)];
        [$c1, $c2, $c3] = $palette;
        $lines = self::wrapName($name, 22);
        $lineCount = count($lines);
        $startY = 300 - ($lineCount - 1) * 28;

        $titleSvg = '';
        foreach ($lines as $i => $line) {
            $y = $startY + $i * 56;
            $titleSvg .= '<text x="600" y="' . $y . '" text-anchor="middle" font-family="Segoe UI, system-ui, sans-serif" font-size="48" font-weight="700" fill="#ffffff">' . self::xml($line) . '</text>';
        }

        $initial = mb_strtoupper(mb_substr(trim($name), 0, 1));

        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<svg xmlns="http://www.w3.org/2000/svg" width="' . self::WIDTH . '" height="' . self::HEIGHT . '" viewBox="0 0 ' . self::WIDTH . ' ' . self::HEIGHT . '">'
            . '<defs><linearGradient id="bg" x1="0%" y1="0%" x2="100%" y2="100%">'
            . '<stop offset="0%" stop-color="' . $c1 . '"/><stop offset="55%" stop-color="' . $c2 . '"/><stop offset="100%" stop-color="' . $c3 . '"/>'
            . '</linearGradient></defs>'
            . '<rect width="' . self::WIDTH . '" height="' . self::HEIGHT . '" fill="url(#bg)"/>'
            . '<circle cx="180" cy="120" r="90" fill="rgba(255,255,255,.06)"/>'
            . '<circle cx="1020" cy="520" r="120" fill="rgba(255,255,255,.05)"/>'
            . '<rect x="80" y="80" width="140" height="140" rx="28" fill="rgba(255,255,255,.12)"/>'
            . '<text x="150" y="175" text-anchor="middle" font-family="Segoe UI, system-ui, sans-serif" font-size="72" font-weight="800" fill="#ffffff" opacity=".95">' . self::xml($initial) . '</text>'
            . $titleSvg
            . '<text x="600" y="' . ($startY + $lineCount * 56 + 10) . '" text-anchor="middle" font-family="Segoe UI, system-ui, sans-serif" font-size="26" font-weight="500" fill="rgba(255,255,255,.75)">' . self::xml($city) . ' · Digitalni meni</text>'
            . '<text x="600" y="590" text-anchor="middle" font-family="Segoe UI, system-ui, sans-serif" font-size="18" fill="rgba(255,255,255,.45)">Sandžak.net</text>'
            . '</svg>';
    }

    /** @return list<string> */
    private static function wrapName(string $name, int $maxLen): array
    {
        $name = trim(preg_replace('/\s+/', ' ', $name) ?? $name);
        if ($name === '') {
            return ['Restoran'];
        }
        if (mb_strlen($name) <= $maxLen) {
            return [$name];
        }

        $words = explode(' ', $name);
        $lines = [];
        $current = '';
        foreach ($words as $word) {
            $try = $current === '' ? $word : $current . ' ' . $word;
            if (mb_strlen($try) <= $maxLen) {
                $current = $try;
            } else {
                if ($current !== '') {
                    $lines[] = $current;
                }
                $current = mb_strlen($word) > $maxLen ? mb_substr($word, 0, $maxLen - 1) . '…' : $word;
            }
        }
        if ($current !== '') {
            $lines[] = $current;
        }

        return $lines ?: [mb_substr($name, 0, $maxLen)];
    }

    private static function xml(string $text): string
    {
        return htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
