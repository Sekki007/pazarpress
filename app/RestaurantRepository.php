<?php

declare(strict_types=1);

final class RestaurantRepository
{
    public static function getBySlug(string $slug, bool $publishedOnly = true): ?array
    {
        $pdo = Database::connection();
        $sql = 'SELECT * FROM restaurants WHERE slug = ?';
        if ($publishedOnly) {
            $sql .= " AND status = 'PUBLISHED'";
        }
        $sql .= ' LIMIT 1';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$slug]);
        $row = $stmt->fetch();

        return $row ? self::mapRestaurant($row) : null;
    }

    public static function getById(string $id): ?array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM restaurants WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row ? self::mapRestaurant($row) : null;
    }

    public static function getByOwnerId(string $ownerId): ?array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM restaurants WHERE ownerId = ? ORDER BY createdAt DESC LIMIT 1');
        $stmt->execute([$ownerId]);
        $row = $stmt->fetch();

        return $row ? self::mapRestaurant($row) : null;
    }

    public static function getByQrCode(string $code): ?array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare("SELECT * FROM restaurants WHERE qrCode = ? AND status = 'PUBLISHED' LIMIT 1");
        $stmt->execute([$code]);
        $row = $stmt->fetch();

        return $row ? self::mapRestaurant($row) : null;
    }

    /** @return list<array> */
    public static function listPublished(?string $city = null, int $limit = 50, int $offset = 0): array
    {
        $pdo = Database::connection();
        $where = ["status = 'PUBLISHED'"];
        $params = [];
        if ($city) {
            $where[] = 'city = ?';
            $params[] = $city;
        }
        $sql = 'SELECT * FROM restaurants WHERE ' . implode(' AND ', $where)
            . ' ORDER BY avgRating DESC, name ASC LIMIT ? OFFSET ?';
        $params[] = $limit;
        $params[] = $offset;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return array_map([self::class, 'mapRestaurant'], $stmt->fetchAll());
    }

    /** @return list<array> */
    public static function listForAdmin(?string $status = null): array
    {
        $pdo = Database::connection();
        $sql = 'SELECT r.*, u.email AS ownerEmail, u.name AS ownerName FROM restaurants r JOIN users u ON u.id = r.ownerId';
        if ($status) {
            $stmt = $pdo->prepare($sql . ' WHERE r.status = ? ORDER BY r.updatedAt DESC');
            $stmt->execute([$status]);
        } else {
            $stmt = $pdo->query($sql . ' ORDER BY r.updatedAt DESC');
        }

        return array_map(static function (array $row): array {
            $r = self::mapRestaurant($row);
            $r['ownerEmail'] = $row['ownerEmail'] ?? '';
            $r['ownerName'] = $row['ownerName'] ?? '';

            return $r;
        }, $stmt->fetchAll());
    }

    public static function countPending(): int
    {
        return (int) Database::connection()
            ->query("SELECT COUNT(*) FROM restaurants WHERE status = 'PENDING'")
            ->fetchColumn();
    }

    /** @return list<array> */
    public static function getMenuCategories(string $restaurantId): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM menu_categories WHERE restaurantId = ? ORDER BY sortOrder ASC, name ASC');
        $stmt->execute([$restaurantId]);

        return array_map([self::class, 'mapMenuCategory'], $stmt->fetchAll());
    }

    /** @return list<array> */
    public static function getMenuItems(string $restaurantId, ?string $categoryId = null): array
    {
        $pdo = Database::connection();
        $sql = 'SELECT * FROM menu_items WHERE restaurantId = ?';
        $params = [$restaurantId];
        if ($categoryId) {
            $sql .= ' AND categoryId = ?';
            $params[] = $categoryId;
        }
        $sql .= ' ORDER BY sortOrder ASC, name ASC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return array_map([self::class, 'mapMenuItem'], $stmt->fetchAll());
    }

    public static function getFullMenu(string $restaurantId): array
    {
        $categories = self::getMenuCategories($restaurantId);
        $items = self::getMenuItems($restaurantId);
        $byCat = [];
        foreach ($items as $item) {
            $byCat[$item['categoryId']][] = $item;
        }
        foreach ($categories as &$cat) {
            $cat['items'] = $byCat[$cat['id']] ?? [];
        }

        return $categories;
    }

    /** @return list<array> */
    public static function getReviews(string $restaurantId, bool $approvedOnly = true): array
    {
        $pdo = Database::connection();
        $sql = 'SELECT * FROM restaurant_reviews WHERE restaurantId = ?';
        if ($approvedOnly) {
            $sql .= " AND status = 'APPROVED'";
        }
        $sql .= ' ORDER BY createdAt DESC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$restaurantId]);

        return $stmt->fetchAll();
    }

    /** @return list<array> */
    public static function listPendingReviews(): array
    {
        $pdo = Database::connection();
        $rows = $pdo->query(
            "SELECT rv.*, r.name AS restaurantName, r.slug AS restaurantSlug
             FROM restaurant_reviews rv
             JOIN restaurants r ON r.id = rv.restaurantId
             WHERE rv.status = 'PENDING'
             ORDER BY rv.createdAt DESC"
        )->fetchAll();

        return $rows;
    }

    public static function incrementViews(string $id): void
    {
        Database::connection()
            ->prepare('UPDATE restaurants SET viewCount = viewCount + 1 WHERE id = ?')
            ->execute([$id]);
    }

    public static function recalcRating(string $restaurantId): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            "SELECT AVG(rating) AS avgR, COUNT(*) AS cnt FROM restaurant_reviews
             WHERE restaurantId = ? AND status = 'APPROVED'"
        );
        $stmt->execute([$restaurantId]);
        $row = $stmt->fetch();
        $pdo->prepare('UPDATE restaurants SET avgRating = ?, reviewCount = ? WHERE id = ?')
            ->execute([
                $row['cnt'] ? round((float) $row['avgR'], 1) : null,
                (int) ($row['cnt'] ?? 0),
                $restaurantId,
            ]);
    }

    /** @param array<string, mixed> $row */
    private static function mapRestaurant(array $row): array
    {
        $row['reviewsEnabled'] = (bool) ($row['reviewsEnabled'] ?? 1);
        $row['hours'] = json_decode((string) ($row['hoursJson'] ?? ''), true) ?: [];
        $row['avgRating'] = $row['avgRating'] !== null ? (float) $row['avgRating'] : null;
        $row['reviewCount'] = (int) ($row['reviewCount'] ?? 0);
        $row['viewCount'] = (int) ($row['viewCount'] ?? 0);
        $langs = json_decode((string) ($row['menuLangsJson'] ?? ''), true);
        $row['menuLangs'] = is_array($langs) && $langs !== [] ? $langs : ['bs', 'en', 'tr'];

        return $row;
    }

    /** @param array<string, mixed> $row */
    private static function mapMenuCategory(array $row): array
    {
        $row['translations'] = json_decode((string) ($row['translationsJson'] ?? ''), true) ?: [];

        return $row;
    }

    /** @param array<string, mixed> $row */
    private static function mapMenuItem(array $row): array
    {
        $row['isAvailable'] = (bool) ($row['isAvailable'] ?? 1);
        $row['price'] = $row['price'] !== null ? (float) $row['price'] : null;
        $row['tags'] = json_decode((string) ($row['tagsJson'] ?? ''), true) ?: [];
        $row['translations'] = json_decode((string) ($row['translationsJson'] ?? ''), true) ?: [];

        return $row;
    }
}
