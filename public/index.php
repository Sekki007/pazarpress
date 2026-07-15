<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

if (config('app_debug')) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
}

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
require_once __DIR__ . '/../app/AdminService.php';

$method = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
$query = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_QUERY);
$uri = rtrim($uri, '/') ?: '/';

if ($uri !== '/' && str_ends_with(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '', '/')) {
    redirect($uri . ($query ? '?' . $query : ''));
}

if (str_starts_with($uri, '/api/')) {
    require __DIR__ . '/../app/routes/api.php';
    exit;
}

if (str_starts_with($uri, '/admin')) {
    require __DIR__ . '/../app/routes/admin.php';
    exit;
}

if (str_starts_with($uri, '/moj-meni')) {
    if (!restaurants_enabled()) {
        not_found();
    }
    require __DIR__ . '/../app/routes/restaurant-owner.php';
    exit;
}

require __DIR__ . '/../app/routes/public.php';
