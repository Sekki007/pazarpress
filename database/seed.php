<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

$pdo = Database::connection();
if (config('db')['driver'] === 'sqlite') { $pdo->exec(file_get_contents(__DIR__ . '/schema.sql')); }

$tables = [
    'poll_votes', 'poll_options', 'polls', 'comments', 'article_tags', 'articles',
    'tags', 'videos', 'newsletter_subscribers', 'reader_submissions', 'authors', 'users', 'categories',
];
foreach ($tables as $t) {
    $pdo->exec("DELETE FROM {$t}");
}

foreach (CATEGORIES as $cat) {
    $pdo->prepare('INSERT INTO categories (id, name, slug) VALUES (?, ?, ?)')
        ->execute([new_id(), $cat['name'], $cat['slug']]);
}

$adminId = new_id();
$pdo->prepare('INSERT INTO users (id, email, passwordHash, name, role) VALUES (?, ?, ?, ?, ?)')
    ->execute([$adminId, 'admin@pazarpress.local', password_hash('admin123', PASSWORD_BCRYPT), 'Admin', 'admin']);

$authors = [
    'Emina Destanović' => new_id(),
    'Redakcija' => new_id(),
    'Amir Kovačević' => new_id(),
    'Selma Hadžić' => new_id(),
];
foreach ($authors as $name => $id) {
    $pdo->prepare('INSERT INTO authors (id, name, bio, userId) VALUES (?, ?, ?, ?)')
        ->execute([$id, $name, 'Pazar Press', $name === 'Redakcija' ? $adminId : null]);
}

$catIds = [];
foreach ($pdo->query('SELECT id, slug FROM categories') as $row) {
    $catIds[$row['slug']] = $row['id'];
}

$articles = require __DIR__ . '/seed-articles.php';

function ensure_tag(PDO $pdo, string $name): string
{
    static $cache = [];
    $slug = slugify($name);
    if (isset($cache[$slug])) {
        return $cache[$slug];
    }
    $id = new_id();
    $sql = config('db')['driver'] === 'mysql'
        ? 'INSERT IGNORE INTO tags (id, name, slug) VALUES (?, ?, ?)'
        : 'INSERT OR IGNORE INTO tags (id, name, slug) VALUES (?, ?, ?)';
    $pdo->prepare($sql)->execute([$id, $name, $slug]);
    $stmt = $pdo->prepare('SELECT id FROM tags WHERE slug = ?');
    $stmt->execute([$slug]);
    $cache[$slug] = $stmt->fetchColumn();
    return $cache[$slug];
}

foreach ($articles as $art) {
    $id = new_id();
    $publishedAt = date('Y-m-d H:i:s', time() - (int) ($art['hoursAgo'] * 3600));
    $pdo->prepare(
        'INSERT INTO articles (id, slug, title, `lead`, body, categoryId, city, authorId, status,
         isBreaking, isFeatured, publishedAt, viewCount, readingTimeMin)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    )->execute([
        $id, $art['slug'], $art['title'], $art['lead'], $art['body'],
        $catIds[$art['categorySlug']], $art['city'], $authors[$art['authorName']],
        'PUBLISHED', (int) ($art['isBreaking'] ?? false), (int) ($art['isFeatured'] ?? false),
        $publishedAt, $art['viewCount'], compute_reading_time($art['body']),
    ]);
    foreach ($art['tags'] as $tag) {
        $tagId = ensure_tag($pdo, $tag);
        $pdo->prepare('INSERT INTO article_tags (articleId, tagId) VALUES (?, ?)')->execute([$id, $tagId]);
    }
}

$videos = [
    ['title' => 'Pazar Press u fokusu — emisija 12', 'youtubeId' => 'dQw4w9WgXcQ', 'duration' => '24:10', 'hoursAgo' => 2, 'viewCount' => 2100],
    ['title' => 'Sportski pregled sedmice', 'youtubeId' => 'dQw4w9WgXcQ', 'duration' => '18:45', 'hoursAgo' => 8, 'viewCount' => 1800],
    ['title' => 'Kulturni kutak: tradicija i savremenost', 'youtubeId' => 'dQw4w9WgXcQ', 'duration' => '15:20', 'hoursAgo' => 24, 'viewCount' => 950],
];
foreach ($videos as $v) {
    $pdo->prepare('INSERT INTO videos (id, title, youtubeId, duration, viewCount, publishedAt) VALUES (?, ?, ?, ?, ?, ?)')
        ->execute([new_id(), $v['title'], $v['youtubeId'], $v['duration'], $v['viewCount'], date('Y-m-d H:i:s', time() - $v['hoursAgo'] * 3600)]);
}

$pollId = new_id();
$pdo->prepare('INSERT INTO polls (id, question, active) VALUES (?, ?, 1)')
    ->execute([$pollId, 'Šta je najveći prioritet za razvoj grada?']);
$options = ['Putevi i infrastruktura', 'Nova radna mesta', 'Obrazovanje i mladi'];
foreach ($options as $text) {
    $optId = new_id();
    $pdo->prepare('INSERT INTO poll_options (id, pollId, text) VALUES (?, ?, ?)')->execute([$optId, $pollId, $text]);
}

echo "Seed završen.\nAdmin: admin@pazarpress.local / admin123\n";
