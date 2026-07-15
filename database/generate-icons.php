<?php

declare(strict_types=1);

if (!extension_loaded('gd')) {
    fwrite(STDERR, "GD extension required\n");
    exit(1);
}

function make_icon(int $size, string $path): void
{
    $im = imagecreatetruecolor($size, $size);
    if ($im === false) {
        throw new RuntimeException('Cannot create image');
    }
    $green = imagecolorallocate($im, 14, 90, 72);
    $white = imagecolorallocate($im, 255, 255, 255);
    $amber = imagecolorallocate($im, 233, 161, 59);
    imagefilledrectangle($im, 0, 0, $size, $size, $green);
    $r = (int) round($size * 0.11);
    imagefilledellipse($im, (int) round($size * 0.74), (int) round($size * 0.2), $r * 2, $r * 2, $amber);
    $font = 5;
    $text = 'S';
    $tw = imagefontwidth($font) * strlen($text);
    $th = imagefontheight($font);
    imagestring($im, $font, (int) (($size - $tw) / 2), (int) (($size - $th) / 2), $text, $white);
    imagepng($im, $path);
    imagedestroy($im);
}

$dir = __DIR__ . '/../public/assets/img';
make_icon(48, $dir . '/icon-48.png');
make_icon(192, $dir . '/icon-192.png');
echo "Icons written to {$dir}\n";
