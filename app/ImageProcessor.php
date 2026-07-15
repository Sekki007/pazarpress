<?php

declare(strict_types=1);

final class ImageProcessor
{
    /** Generiše WebP varijante za responsive slike. */
    public static function process(string $filePath): array
    {
        $variants = [];
        if (!extension_loaded('gd') || !is_file($filePath)) {
            return $variants;
        }

        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $image = match ($ext) {
            'jpg', 'jpeg' => @imagecreatefromjpeg($filePath),
            'png' => @imagecreatefrompng($filePath),
            'webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($filePath) : false,
            'gif' => @imagecreatefromgif($filePath),
            default => false,
        };
        if (!$image) {
            return $variants;
        }

        $base = preg_replace('/\.[^.]+$/', '', $filePath) ?: $filePath;
        foreach ([160 => 'sm', 400 => 'thumb', 800 => 'md', 1200 => 'lg'] as $width => $suffix) {
            $out = $base . '-' . $suffix . '.webp';
            $quality = match ($suffix) {
                'sm' => 68,
                'thumb' => 72,
                'md' => 78,
                default => 82,
            };
            if (self::saveResizedWebp($image, $filePath, $out, $width, $quality)) {
                $variants[$width] = self::publicUrl($out);
            }
        }
        imagedestroy($image);
        return $variants;
    }

    private static function saveResizedWebp($source, string $sourcePath, string $outPath, int $maxWidth, int $quality = 82): bool
    {
        if (!function_exists('imagewebp')) {
            return false;
        }
        $srcW = imagesx($source);
        $srcH = imagesy($source);
        if ($srcW <= 0 || $srcH <= 0) {
            return false;
        }
        if ($srcW <= $maxWidth) {
            $newW = $srcW;
            $newH = $srcH;
            $resized = $source;
            $destroy = false;
        } else {
            $newW = $maxWidth;
            $newH = (int) round($srcH * ($maxWidth / $srcW));
            $resized = imagecreatetruecolor($newW, $newH);
            if (!$resized) {
                return false;
            }
            imagealphablending($resized, true);
            imagesavealpha($resized, true);
            imagecopyresampled($resized, $source, 0, 0, 0, 0, $newW, $newH, $srcW, $srcH);
            $destroy = true;
        }
        $ok = imagewebp($resized, $outPath, $quality);
        if ($destroy) {
            imagedestroy($resized);
        }
        return $ok;
    }

    private static function publicUrl(string $absolutePath): string
    {
        $uploadDir = realpath(config('upload_dir')) ?: config('upload_dir');
        $real = realpath($absolutePath) ?: $absolutePath;
        $rel = str_replace('\\', '/', substr($real, strlen($uploadDir)));
        return '/uploads' . ltrim($rel, '/');
    }
}
