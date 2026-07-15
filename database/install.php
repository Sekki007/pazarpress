<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/Database.php';

$pdo = Database::connection();
$schema = file_get_contents(__DIR__ . '/schema.sql');
$pdo->exec($schema);
echo "Baza instalirana.\n";
