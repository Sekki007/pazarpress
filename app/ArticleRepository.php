<?php

declare(strict_types=1);

final class ArticleRepository
{
    private static function baseSelect(): string
    {
        return 'SELECT a.*, c.name AS categoryName, c.slug AS categorySlug,
                au.name AS authorName
                FROM articles a
                JOIN categories c ON c.id = a.categoryId
                JOIN authors au ON au.id = a.authorId';
    }

    private static function mapRow(array $row): array
    {
        $row['category'] = ['name' => $row['categoryName'], 'slug' => $row['categorySlug']];
        $row['author'] = ['name' => $row['authorName']];
        $row['isBreaking'] = (bool) $row['isBreaking'];
        $row['isFeatured'] = (bool) $row['isFeatured'];
        return $row;
    }

    public static function getFeatured(): ?array
    {
        $pdo = Database::connection();
        $sql = self::baseSelect() . " WHERE a.status = 'PUBLISHED' AND a.isFeatured = 1
                ORDER BY a.publishedAt DESC LIMIT 1";
        $row = $pdo->query($sql)->fetch();

        if (!$row && HomeFeaturedService::isEnabled()) {
            $sql = self::baseSelect() . " WHERE a.status = 'PUBLISHED' AND a.publishedAt >= ?
                    ORDER BY a.publishedAt DESC LIMIT 1";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([HomeFeaturedService::todayStart()]);
            $row = $stmt->fetch();
        }

        return $row ? self::mapRow($row) : null;
    }

    public static function getBreaking(int $limit = 10): array
    {
        $pdo = Database::connection();
        $lim = sql_limit($limit);
        $stmt = $pdo->prepare(self::baseSelect() . " WHERE a.status = 'PUBLISHED' AND a.isBreaking = 1
                ORDER BY a.publishedAt DESC LIMIT {$lim}");
        $stmt->execute();
        return array_map([self::class, 'mapRow'], $stmt->fetchAll());
    }

    /**
     * Do 4 istaknute vijesti za flash grid: hitne → najčitanije → najnovije.
     * @return list<array>
     */
    public static function getFlashHighlights(?string $city = null, int $limit = 4, ?string $excludeSlug = null): array
    {
        $out = [];
        $seen = [];

        $take = static function (array $rows) use (&$out, &$seen, $limit, $excludeSlug): void {
            foreach ($rows as $row) {
                if (count($out) >= $limit) {
                    return;
                }
                if ($excludeSlug && ($row['slug'] ?? '') === $excludeSlug) {
                    continue;
                }
                $id = $row['id'] ?? '';
                if ($id === '' || isset($seen[$id])) {
                    continue;
                }
                $seen[$id] = true;
                $out[] = $row;
            }
        };

        $sinceBreaking = date('Y-m-d H:i:s', time() - 3 * 86400);
        $pdo = Database::connection();
        $where = ["a.status = 'PUBLISHED'", 'a.isBreaking = 1', 'a.publishedAt >= ?'];
        $params = [$sinceBreaking];
        if ($city) {
            $where[] = 'a.city = ?';
            $params[] = $city;
        }
        $lim = sql_limit($limit * 2);
        $stmt = $pdo->prepare(
            self::baseSelect() . ' WHERE ' . implode(' AND ', $where) . " ORDER BY a.publishedAt DESC LIMIT {$lim}"
        );
        $stmt->execute($params);
        $take(array_map([self::class, 'mapRow'], $stmt->fetchAll()));

        if (count($out) < $limit) {
            $take(self::getTop24h($limit * 2));
        }
        if (count($out) < $limit) {
            $take(self::getLatest($city, null, $limit * 2)['items']);
        }

        return array_slice($out, 0, $limit);
    }

    public static function getLatest(?string $city = null, ?string $cursor = null, int $limit = 4): array
    {
        $pdo = Database::connection();
        $where = ["a.status = 'PUBLISHED'"];
        $params = [];
        if ($city) {
            $where[] = 'a.city = ?';
            $params[] = $city;
        }
        if ($cursor) {
            $where[] = 'a.publishedAt < ?';
            $params[] = $cursor;
        }
        $sql = self::baseSelect() . ' WHERE ' . implode(' AND ', $where) .
            ' ORDER BY a.publishedAt DESC LIMIT ' . ($limit + 1);
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = array_map([self::class, 'mapRow'], $stmt->fetchAll());
        $hasMore = count($rows) > $limit;
        $items = $hasMore ? array_slice($rows, 0, $limit) : $rows;
        $nextCursor = $hasMore && $items ? $items[count($items) - 1]['publishedAt'] : null;
        return ['items' => $items, 'nextCursor' => $nextCursor];
    }

    public static function getTop24h(int $limit = 4): array
    {
        $pdo = Database::connection();
        $since = date('Y-m-d H:i:s', time() - 86400);
        $lim = sql_limit($limit);
        $stmt = $pdo->prepare(self::baseSelect() . " WHERE a.status = 'PUBLISHED' AND a.publishedAt >= ?
                ORDER BY a.viewCount DESC LIMIT {$lim}");
        $stmt->execute([$since]);
        return array_map([self::class, 'mapRow'], $stmt->fetchAll());
    }

    public static function getByCategory(string $slug, int $limit = 2): array
    {
        $pdo = Database::connection();
        $lim = sql_limit($limit);
        $stmt = $pdo->prepare(self::baseSelect() . " WHERE a.status = 'PUBLISHED' AND c.slug = ?
                ORDER BY a.publishedAt DESC LIMIT {$lim}");
        $stmt->execute([$slug]);
        return array_map([self::class, 'mapRow'], $stmt->fetchAll());
    }

    public static function getCategoryBySlug(string $slug): ?array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM categories WHERE slug = ? LIMIT 1');
        $stmt->execute([$slug]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function getTagBySlug(string $slug): ?array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM tags WHERE slug = ? LIMIT 1');
        $stmt->execute([$slug]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function getTagArticles(string $slug, int $page, int $perPage = 12): array
    {
        $pdo = Database::connection();
        $params = [$slug];
        $countSql = "SELECT COUNT(*) FROM articles a
            JOIN article_tags at ON at.articleId = a.id
            JOIN tags t ON t.id = at.tagId
            WHERE a.status = 'PUBLISHED' AND t.slug = ?";
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $offset = max(0, ($page - 1) * $perPage);
        $sql = self::baseSelect() . "
            JOIN article_tags at ON at.articleId = a.id
            JOIN tags t ON t.id = at.tagId
            WHERE a.status = 'PUBLISHED' AND t.slug = ?
            ORDER BY a.publishedAt DESC LIMIT " . (int) $perPage . ' OFFSET ' . (int) $offset;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $items = array_map([self::class, 'mapRow'], $stmt->fetchAll());
        $pages = max(1, (int) ceil($total / $perPage));
        return ['items' => $items, 'total' => $total, 'page' => $page, 'pages' => $pages, 'perPage' => $perPage];
    }

    public static function getTagsForSitemap(): array
    {
        $pdo = Database::connection();
        return $pdo->query(
            "SELECT t.slug, MAX(a.updatedAt) AS updatedAt
             FROM tags t
             JOIN article_tags at ON at.tagId = t.id
             JOIN articles a ON a.id = at.articleId AND a.status = 'PUBLISHED'
             GROUP BY t.slug
             ORDER BY t.slug"
        )->fetchAll();
    }

    public static function getCategoryArticles(string $slug, ?string $city, int $page, int $perPage = 12): array
    {
        $pdo = Database::connection();
        $where = ["a.status = 'PUBLISHED'", 'c.slug = ?'];
        $params = [$slug];
        if ($city) {
            $where[] = 'a.city = ?';
            $params[] = $city;
        }
        $offset = max(0, ($page - 1) * $perPage);
        $countSql = 'SELECT COUNT(*) FROM articles a JOIN categories c ON c.id = a.categoryId WHERE ' . implode(' AND ', $where);
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $sql = self::baseSelect() . ' WHERE ' . implode(' AND ', $where) .
            ' ORDER BY a.publishedAt DESC LIMIT ' . (int) $perPage . ' OFFSET ' . (int) $offset;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $items = array_map([self::class, 'mapRow'], $stmt->fetchAll());
        $pages = max(1, (int) ceil($total / $perPage));
        return ['items' => $items, 'total' => $total, 'page' => $page, 'pages' => $pages, 'perPage' => $perPage];
    }

    public static function getApprovedComments(string $articleId): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare("SELECT * FROM comments WHERE articleId = ? AND status = 'APPROVED' ORDER BY createdAt DESC");
        $stmt->execute([$articleId]);
        return $stmt->fetchAll();
    }

    public static function addComment(string $articleId, string $name, string $body): void
    {
        $pdo = Database::connection();
        $pdo->prepare('INSERT INTO comments (id, articleId, name, body, status) VALUES (?, ?, ?, ?, ?)')
            ->execute([new_id(), $articleId, $name, $body, 'PENDING']);
    }

    public static function getBySlug(string $slug, bool $admin = false): ?array
    {
        $pdo = Database::connection();
        $sql = self::baseSelect() . ' WHERE a.slug = ?';
        if (!$admin) {
            $sql .= " AND a.status = 'PUBLISHED'";
        }
        $stmt = $pdo->prepare($sql . ' LIMIT 1');
        $stmt->execute([$slug]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }
        $article = self::mapRow($row);
        $tagStmt = $pdo->prepare(
            'SELECT t.name, t.slug FROM tags t
             JOIN article_tags at ON at.tagId = t.id
             WHERE at.articleId = ?'
        );
        $tagStmt->execute([$article['id']]);
        $article['tags'] = $tagStmt->fetchAll();
        return $article;
    }

    public static function incrementViews(string $id): void
    {
        $pdo = Database::connection();
        $pdo->prepare('UPDATE articles SET viewCount = viewCount + 1 WHERE id = ?')->execute([$id]);
    }

    public static function getRelated(string $categorySlug, string $excludeId, int $limit = 3): array
    {
        $pdo = Database::connection();
        $lim = sql_limit($limit);
        $stmt = $pdo->prepare(self::baseSelect() . " WHERE a.status = 'PUBLISHED' AND c.slug = ?
                AND a.id != ? ORDER BY a.publishedAt DESC LIMIT {$lim}");
        $stmt->execute([$categorySlug, $excludeId]);
        return array_map([self::class, 'mapRow'], $stmt->fetchAll());
    }

    public static function getLatestVideos(int $limit = 3): array
    {
        $pdo = Database::connection();
        $lim = sql_limit($limit);
        $stmt = $pdo->prepare("SELECT * FROM videos ORDER BY publishedAt DESC LIMIT {$lim}");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function getVideosPage(int $page, int $perPage = 12): array
    {
        $pdo = Database::connection();
        $total = (int) $pdo->query('SELECT COUNT(*) FROM videos')->fetchColumn();
        $offset = max(0, ($page - 1) * $perPage);
        $lim = sql_limit($perPage);
        $stmt = $pdo->prepare("SELECT * FROM videos ORDER BY publishedAt DESC LIMIT {$lim} OFFSET " . (int) $offset);
        $stmt->execute();
        $items = $stmt->fetchAll();
        $pages = max(1, (int) ceil($total / $perPage));
        return ['items' => $items, 'total' => $total, 'page' => $page, 'pages' => $pages];
    }

    public static function search(string $query, int $limit = 24): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }
        $safe = str_replace(['%', '_'], '', $query);
        $term = '%' . $safe . '%';
        $pdo = Database::connection();
        $lim = sql_limit($limit);
        $stmt = $pdo->prepare(
            self::baseSelect() . " WHERE a.status = 'PUBLISHED' AND (
                a.title LIKE ? OR a.`lead` LIKE ? OR a.body LIKE ?
            ) ORDER BY a.publishedAt DESC LIMIT {$lim}"
        );
        $stmt->execute([$term, $term, $term]);
        return array_map([self::class, 'mapRow'], $stmt->fetchAll());
    }

    public static function getActivePoll(): ?array
    {
        $pdo = Database::connection();
        $poll = $pdo->query("SELECT * FROM polls WHERE active = 1 ORDER BY createdAt DESC LIMIT 1")->fetch();
        if (!$poll) {
            return null;
        }
        $stmt = $pdo->prepare(
            'SELECT po.id, po.text, COUNT(pv.id) AS votes
             FROM poll_options po
             LEFT JOIN poll_votes pv ON pv.pollOptionId = po.id
             WHERE po.pollId = ?
             GROUP BY po.id ORDER BY po.id'
        );
        $stmt->execute([$poll['id']]);
        $poll['options'] = $stmt->fetchAll();
        return $poll;
    }
}
