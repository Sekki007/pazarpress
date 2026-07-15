<?php

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');
header('Content-Type: text/plain; charset=utf-8');

echo "Sandzak.net Auto Vesti diagnostic\n";
echo 'PHP ' . PHP_VERSION . "\n\n";

$requires = [
    'AutoVestiConfig.php',
    'AutoVestiQueue.php',
    'HttpClient.php',
    'FeedParser.php',
    'AutoVestiFetcher.php',
    'AutoVestiAi.php',
    'AutoVestiDuplicate.php',
    'AutoVestiContent.php',
    'AutoVestiVideo.php',
    'AutoVestiImages.php',
    'AutoVestiStats.php',
    'AutoVestiSession.php',
    'AutoVestiProcessor.php',
    'AutoVestiBackground.php',
    'AutoVestiTelegram.php',
    'AutoVestiRunner.php',
];

$app = __DIR__ . '/../app';
foreach ($requires as $file) {
    $path = $app . '/' . $file;
    echo $file . ': ';
    if (!is_file($path)) {
        echo "MISSING\n";
        continue;
    }
    try {
        require_once $path;
        echo "OK\n";
    } catch (Throwable $e) {
        echo 'ERROR — ' . $e->getMessage() . "\n";
    }
}

echo "\nView: ";
$view = __DIR__ . '/../views/admin/auto-vesti.php';
echo is_file($view) ? 'OK' : 'MISSING';
echo "\n";

echo "\nAuto-vesti route simulation:\n";
try {
    $cfg = AutoVestiConfig::all();
    $queue = AutoVestiConfig::getQueue();
    echo 'Config OK, queue items: ' . count($queue) . "\n";
    echo "If all files above are OK, /admin/auto-vesti should work.\n";
} catch (Throwable $e) {
    echo 'FATAL: ' . $e->getMessage() . "\n";
    echo $e->getFile() . ':' . $e->getLine() . "\n";
}

echo "\nDelete this file after debugging.\n";
