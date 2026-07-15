<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Samo CLI.\n");
    exit(1);
}

$dir = rtrim(config('upload_dir'), '/\\');
$dryRun = in_array('--dry-run', $argv, true);
$count = 0;

foreach (glob($dir . '/*') ?: [] as $file) {
    if (!is_file($file)) {
        continue;
    }
    $name = basename($file);
    if (preg_match('/-(sm|thumb|md|lg)\.webp$/i', $name)) {
        continue;
    }
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
        continue;
    }
    if ($dryRun) {
        echo $name . "\n";
        $count++;
        continue;
    }
    ImageProcessor::process($file);
    echo "OK: {$name}\n";
    $count++;
}

echo ($dryRun ? 'Pronađeno: ' : 'Obrađeno: ') . $count . " slika.\n";
