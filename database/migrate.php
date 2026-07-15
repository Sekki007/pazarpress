<?php

declare(strict_types=1);

/**
 * Pokreni nakon pull-a: php database/migrate.php
 */
require_once __DIR__ . '/../app/bootstrap.php';

$pdo = Database::connection();
$driver = (string) (config('db')['driver'] ?? 'sqlite');
$isMysql = $driver === 'mysql';

/**
 * @return list<string>
 */
function migrate_columns(PDO $pdo, string $table, bool $isMysql): array
{
    if (!migrate_table_exists($pdo, $table, $isMysql)) {
        return [];
    }

    if ($isMysql) {
        $safeTable = str_replace('`', '``', $table);
        return array_column($pdo->query("SHOW COLUMNS FROM `{$safeTable}`")->fetchAll(), 'Field');
    }

    return array_column($pdo->query("PRAGMA table_info({$table})")->fetchAll(), 'name');
}

function migrate_table_exists(PDO $pdo, string $table, bool $isMysql): bool
{
    if ($isMysql) {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?'
        );
        $stmt->execute([$table]);

        return (int) $stmt->fetchColumn() > 0;
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = ?");
    $stmt->execute([$table]);

    return (int) $stmt->fetchColumn() > 0;
}

function migrate_add_column(
    PDO $pdo,
    string $table,
    string $column,
    string $sqliteDef,
    string $mysqlDef,
    bool $isMysql
): void {
    $cols = migrate_columns($pdo, $table, $isMysql);
    if ($cols === [] || in_array($column, $cols, true)) {
        return;
    }

    $def = $isMysql ? $mysqlDef : $sqliteDef;
    $pdo->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$def}");
    echo "Added {$table}.{$column}\n";
}

migrate_add_column($pdo, 'articles', 'sourceUrl', 'TEXT', 'TEXT NULL', $isMysql);
migrate_add_column($pdo, 'articles', 'sourceName', 'TEXT', 'VARCHAR(190) NULL', $isMysql);
migrate_add_column($pdo, 'articles', 'importSchema', 'TEXT', 'LONGTEXT NULL', $isMysql);
migrate_add_column($pdo, 'articles', 'seoTitle', 'TEXT', 'TEXT NULL', $isMysql);
migrate_add_column($pdo, 'articles', 'seoDescription', 'TEXT', 'TEXT NULL', $isMysql);

migrate_add_column($pdo, 'restaurants', 'menuLangsJson', 'TEXT', 'JSON NULL', $isMysql);
migrate_add_column($pdo, 'menu_categories', 'translationsJson', 'TEXT', 'JSON NULL', $isMysql);
migrate_add_column($pdo, 'menu_items', 'translationsJson', 'TEXT', 'JSON NULL', $isMysql);

if ($isMysql) {
    $pdo->exec('CREATE TABLE IF NOT EXISTS auto_vesti_settings (
        settingKey VARCHAR(128) PRIMARY KEY,
        value LONGTEXT NOT NULL,
        updatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )');
} else {
    $pdo->exec('CREATE TABLE IF NOT EXISTS auto_vesti_settings (
        settingKey TEXT PRIMARY KEY,
        value TEXT NOT NULL,
        updatedAt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )');
}
echo "Ensured auto_vesti_settings\n";

if (!$isMysql) {
    $migrations = glob(__DIR__ . '/migrations/*.sql') ?: [];
    sort($migrations);
    foreach ($migrations as $file) {
        $pdo->exec((string) file_get_contents($file));
        echo 'Applied: ' . basename($file) . "\n";
    }
} else {
    echo "MySQL: SQLite .sql migracije preskočene (šema preko schema.mysql.sql + ALTER iznad).\n";
}

echo "Migrations done.\n";
