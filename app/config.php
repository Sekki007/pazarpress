<?php

declare(strict_types=1);

return [
    'site_name' => 'Pazar Press',
    'site_url' => rtrim(getenv('SITE_URL') ?: 'http://localhost:8080', '/'),
    'app_debug' => filter_var(getenv('APP_DEBUG') ?: '1', FILTER_VALIDATE_BOOLEAN),
    'mail_from' => getenv('MAIL_FROM') ?: 'noreply@pazarpress.local',
    'db' => [
        'driver' => getenv('DB_DRIVER') ?: 'sqlite',
        'sqlite' => __DIR__ . '/../database/pazarpress.sqlite',
        'mysql' => [
            'host' => getenv('DB_HOST') ?: '127.0.0.1',
            'port' => getenv('DB_PORT') ?: '3306',
            'database' => getenv('DB_DATABASE') ?: 'pazarpress',
            'username' => getenv('DB_USERNAME') ?: 'root',
            'password' => getenv('DB_PASSWORD') ?: '',
            'charset' => 'utf8mb4',
        ],
    ],
    'upload_dir' => __DIR__ . '/../public/uploads',
    'upload_max_bytes' => 5 * 1024 * 1024,
    'cache_dir' => __DIR__ . '/../storage/cache',
];
