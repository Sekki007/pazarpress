<?php

declare(strict_types=1);

final class AdminService
{
    public static function uniqueSlug(string $base, ?string $excludeId = null): string
    {
        $pdo = Database::connection();
        $slug = slugify($base);
        $suffix = 2;
        while (true) {
            $stmt = $pdo->prepare('SELECT id FROM articles WHERE slug = ? LIMIT 1');
            $stmt->execute([$slug]);
            $row = $stmt->fetch();
            if (!$row || ($excludeId && $row['id'] === $excludeId)) {
                return $slug;
            }
            $slug = slugify($base) . '-' . $suffix++;
        }
    }

    public static function syncTags(string $articleId, array $tagNames): void
    {
        $pdo = Database::connection();
        $pdo->prepare('DELETE FROM article_tags WHERE articleId = ?')->execute([$articleId]);
        foreach ($tagNames as $name) {
            $name = trim($name);
            if ($name === '') {
                continue;
            }
            $slug = slugify($name);
            $pdo->prepare(sql_insert_ignore() . ' INTO tags (id, name, slug) VALUES (?, ?, ?)')
                ->execute([new_id(), $name, $slug]);
            $stmt = $pdo->prepare('SELECT id FROM tags WHERE slug = ?');
            $stmt->execute([$slug]);
            $tagId = $stmt->fetchColumn();
            $pdo->prepare('INSERT INTO article_tags (articleId, tagId) VALUES (?, ?)')
                ->execute([$articleId, $tagId]);
        }
    }

    public static function saveArticle(array $data, ?string $id = null): string
    {
        $pdo = Database::connection();
        $slug = self::uniqueSlug($data['slug'] ?: $data['title'], $id);
        $reading = compute_reading_time($data['body']);
        $publishedAt = $data['status'] === 'PUBLISHED'
            ? ($data['publishedAt'] ?: date('Y-m-d H:i:s'))
            : null;

        $sourceUrl = isset($data['sourceUrl']) ? trim((string) $data['sourceUrl']) : null;
        $sourceName = isset($data['sourceName']) ? trim((string) $data['sourceName']) : null;
        $hasSourceCols = self::hasSourceColumns();
        $previousStatus = null;
        if ($id) {
            $prevStmt = $pdo->prepare('SELECT status FROM articles WHERE id = ? LIMIT 1');
            $prevStmt->execute([$id]);
            $previousStatus = (string) ($prevStmt->fetchColumn() ?: '');
        }

        if ($id) {
            if ($hasSourceCols) {
                $pdo->prepare(
                    'UPDATE articles SET slug=?, title=?, `lead`=?, body=?, categoryId=?, city=?, authorId=?,
                     status=?, isBreaking=?, isFeatured=?, coverImage=?, coverCaption=?, readingTimeMin=?,
                     publishedAt=?, sourceUrl=?, sourceName=?, updatedAt=CURRENT_TIMESTAMP WHERE id=?'
                )->execute([
                    $slug, $data['title'], $data['lead'], $data['body'], $data['categoryId'],
                    $data['city'], $data['authorId'], $data['status'], (int) $data['isBreaking'],
                    (int) $data['isFeatured'], $data['coverImage'], $data['coverCaption'],
                    $reading, $publishedAt, $sourceUrl ?: null, $sourceName ?: null, $id,
                ]);
            } else {
                $pdo->prepare(
                    'UPDATE articles SET slug=?, title=?, `lead`=?, body=?, categoryId=?, city=?, authorId=?,
                     status=?, isBreaking=?, isFeatured=?, coverImage=?, coverCaption=?, readingTimeMin=?,
                     publishedAt=?, updatedAt=CURRENT_TIMESTAMP WHERE id=?'
                )->execute([
                    $slug, $data['title'], $data['lead'], $data['body'], $data['categoryId'],
                    $data['city'], $data['authorId'], $data['status'], (int) $data['isBreaking'],
                    (int) $data['isFeatured'], $data['coverImage'], $data['coverCaption'],
                    $reading, $publishedAt, $id,
                ]);
            }
            self::syncTags($id, $data['tags']);
            self::afterPublish($id, $data['status'], $previousStatus);
            return $id;
        }

        $id = new_id();
        if ($hasSourceCols) {
            $pdo->prepare(
                'INSERT INTO articles (id, slug, title, `lead`, body, categoryId, city, authorId, status,
                 isBreaking, isFeatured, coverImage, coverCaption, readingTimeMin, publishedAt, sourceUrl, sourceName)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                $id, $slug, $data['title'], $data['lead'], $data['body'], $data['categoryId'],
                $data['city'], $data['authorId'], $data['status'], (int) $data['isBreaking'],
                (int) $data['isFeatured'], $data['coverImage'], $data['coverCaption'],
                $reading, $publishedAt, $sourceUrl ?: null, $sourceName ?: null,
            ]);
        } else {
            $pdo->prepare(
                'INSERT INTO articles (id, slug, title, `lead`, body, categoryId, city, authorId, status,
                 isBreaking, isFeatured, coverImage, coverCaption, readingTimeMin, publishedAt)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                $id, $slug, $data['title'], $data['lead'], $data['body'], $data['categoryId'],
                $data['city'], $data['authorId'], $data['status'], (int) $data['isBreaking'],
                (int) $data['isFeatured'], $data['coverImage'], $data['coverCaption'],
                $reading, $publishedAt,
            ]);
        }
        self::syncTags($id, $data['tags']);
        self::afterPublish($id, $data['status'], null);
        return $id;
    }

    private static function afterPublish(string $id, string $status, ?string $previousStatus): void
    {
        $isNewPublish = $status === 'PUBLISHED'
            && ($previousStatus === null || $previousStatus !== 'PUBLISHED');

        if ($isNewPublish) {
            HomeFeaturedService::onArticlePublished($id);
        }

        cache_flush_content();

        if ($isNewPublish && class_exists('FacebookPublisher')) {
            FacebookPublisher::shareArticle($id);
        }
    }

    public static function setImportSchema(string $articleId, string $schema): void
    {
        if (!self::hasImportSchemaColumn()) {
            return;
        }
        Database::connection()
            ->prepare('UPDATE articles SET importSchema = ? WHERE id = ?')
            ->execute([$schema, $articleId]);
    }

    public static function setSeoFields(string $articleId, ?string $seoTitle, ?string $seoDescription): void
    {
        if (!self::hasSeoColumns()) {
            return;
        }
        Database::connection()
            ->prepare('UPDATE articles SET seoTitle = ?, seoDescription = ? WHERE id = ?')
            ->execute([$seoTitle ?: null, $seoDescription ?: null, $articleId]);
    }

    public static function hasSeoColumns(): bool
    {
        static $has = null;
        if ($has !== null) {
            return $has;
        }
        $cols = self::articleColumns();
        $has = in_array('seoTitle', $cols, true) && in_array('seoDescription', $cols, true);
        return $has;
    }

    private static function hasSourceColumns(): bool
    {
        static $has = null;
        if ($has !== null) {
            return $has;
        }
        $cols = self::articleColumns();
        $has = in_array('sourceUrl', $cols, true) && in_array('sourceName', $cols, true);
        return $has;
    }

    public static function hasImportSchemaColumn(): bool
    {
        static $has = null;
        if ($has !== null) {
            return $has;
        }
        $cols = self::articleColumns();
        $has = in_array('importSchema', $cols, true);
        return $has;
    }

    /** @return list<string> */
    private static function articleColumns(): array
    {
        static $cols = null;
        if ($cols !== null) {
            return $cols;
        }

        $pdo = Database::connection();
        if (is_mysql()) {
            $db = (string) (config('db')['mysql']['database'] ?? '');
            $stmt = $pdo->prepare(
                'SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?'
            );
            $stmt->execute([$db, 'articles']);
            $cols = array_column($stmt->fetchAll(), 'COLUMN_NAME');
        } else {
            $cols = array_column($pdo->query('PRAGMA table_info(articles)')->fetchAll(), 'name');
        }

        return $cols;
    }
}
