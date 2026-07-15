<?php

declare(strict_types=1);

/**
 * Briše duple članke po naslovu (zadrži najstariji).
 *
 * Primjer:
 *   php database/purge-duplicate-articles.php "Košarkaškog kluba Pazar"
 *   php database/purge-duplicate-articles.php "KK Pazar" --dry-run
 */
require_once __DIR__ . '/../app/bootstrap.php';

$args = array_slice($argv, 1);
$dryRun = in_array('--dry-run', $args, true);
$args = array_values(array_filter($args, static fn ($a) => $a !== '--dry-run'));

if ($args === []) {
    fwrite(STDERR, "Upotreba: php database/purge-duplicate-articles.php \"dio naslova\" [--dry-run]\n");
    exit(1);
}

$needle = $args[0];
$pdo = Database::connection();

$stmt = $pdo->prepare(
    "SELECT id, title, slug, publishedAt, createdAt
     FROM articles
     WHERE title LIKE ?
     ORDER BY COALESCE(publishedAt, createdAt) ASC"
);
$stmt->execute(['%' . $needle . '%']);
$rows = $stmt->fetchAll();

if (count($rows) < 2) {
    echo "Nađeno " . count($rows) . " član(aka) za \"{$needle}\" — nema duplikata za brisanje.\n";
    exit(0);
}

$keep = array_shift($rows);
echo "ZADRŽAVAM (najstariji):\n  {$keep['id']} | {$keep['publishedAt']} | {$keep['title']}\n\n";
echo "BRIŠEM " . count($rows) . " duplikat(a):\n";

foreach ($rows as $r) {
    echo "  {$r['id']} | {$r['publishedAt']} | {$r['title']}\n";
}

if ($dryRun) {
    echo "\n--dry-run: ništa nije obrisano.\n";
    exit(0);
}

function delete_article_full(PDO $pdo, string $id): void
{
    $pdo->prepare('DELETE FROM article_tags WHERE articleId = ?')->execute([$id]);
    $pdo->prepare('DELETE FROM comments WHERE articleId = ?')->execute([$id]);
    $pdo->prepare('DELETE FROM articles WHERE id = ?')->execute([$id]);
}

$pdo->beginTransaction();
try {
    foreach ($rows as $r) {
        delete_article_full($pdo, (string) $r['id']);
    }
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, "Greška: " . $e->getMessage() . "\n");
    exit(1);
}

$cacheDir = config('cache_dir');
if (is_dir($cacheDir)) {
    foreach (glob($cacheDir . '/*.cache') ?: [] as $f) {
        @unlink($f);
    }
}

echo "\nGotovo. Obrisano: " . count($rows) . " član(aka).\n";
