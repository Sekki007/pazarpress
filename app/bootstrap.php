<?php

declare(strict_types=1);

$envFile = __DIR__ . '/../.env';
if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value, " \t\"'");
        if ($key !== '' && getenv($key) === false) {
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
        }
    }
}

session_start();

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/constants.php';
require_once __DIR__ . '/ArticleRepository.php';
require_once __DIR__ . '/InfoStrip.php';
require_once __DIR__ . '/ImageWatermark.php';
require_once __DIR__ . '/ImageProcessor.php';
require_once __DIR__ . '/RateLimiter.php';
require_once __DIR__ . '/Settings.php';
if (is_file(__DIR__ . '/FacebookPublisher.php')) {
    require_once __DIR__ . '/FacebookPublisher.php';
}
require_once __DIR__ . '/HomeFeaturedService.php';
require_once __DIR__ . '/MenuI18n.php';
require_once __DIR__ . '/RestaurantRepository.php';
require_once __DIR__ . '/RestaurantService.php';
require_once __DIR__ . '/AutoVestiConfig.php';
require_once __DIR__ . '/AutoVestiQueue.php';
require_once __DIR__ . '/HttpClient.php';
require_once __DIR__ . '/RestaurantMenuScan.php';
require_once __DIR__ . '/RestaurantCoverGenerator.php';

$config = require __DIR__ . '/config.php';

if (!is_dir($config['cache_dir'])) {
    mkdir($config['cache_dir'], 0775, true);
}
if (!is_dir($config['upload_dir'])) {
    mkdir($config['upload_dir'], 0775, true);
}
