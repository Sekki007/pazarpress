<?php

declare(strict_types=1);

final class RestaurantService
{
    private const RESERVED_SLUGS = [
        'admin', 'api', 'vijest', 'rubrika', 'tag', 'video', 'pretraga', 'feed', 'rss',
        'sitemap', 'restorani', 'meni', 'moj-meni', 'uploads', 'assets', 'manifest',
    ];

    public static function uniqueSlug(string $base, ?string $excludeId = null): string
    {
        $pdo = Database::connection();
        $slug = slugify($base);
        if ($slug === '' || in_array($slug, self::RESERVED_SLUGS, true)) {
            $slug = 'restoran-' . substr(new_id(), 0, 6);
        }
        $suffix = 2;
        $candidate = $slug;
        while (true) {
            $stmt = $pdo->prepare('SELECT id FROM restaurants WHERE slug = ? LIMIT 1');
            $stmt->execute([$candidate]);
            $row = $stmt->fetch();
            if (!$row || ($excludeId && $row['id'] === $excludeId)) {
                return $candidate;
            }
            $candidate = $slug . '-' . $suffix++;
        }
    }

    public static function uniqueQrCode(): string
    {
        $pdo = Database::connection();
        for ($i = 0; $i < 20; $i++) {
            $code = strtolower(substr(bin2hex(random_bytes(4)), 0, 7));
            $stmt = $pdo->prepare('SELECT id FROM restaurants WHERE qrCode = ? LIMIT 1');
            $stmt->execute([$code]);
            if (!$stmt->fetch()) {
                return $code;
            }
        }

        return new_id();
    }

    public static function publicUrl(array $restaurant): string
    {
        return config('site_url') . '/restorani/' . rawurlencode($restaurant['slug']);
    }

    public static function qrImageUrl(array $restaurant, int $size = 400): string
    {
        $url = self::publicUrl($restaurant);

        return 'https://api.qrserver.com/v1/create-qr-code/?size=' . $size . 'x' . $size
            . '&data=' . rawurlencode($url) . '&margin=10';
    }

    public static function resolveOwnerId(string $email, string $name = 'Vlasnik'): string
    {
        $email = strtolower(trim($email));
        if ($email === '') {
            return self::portalOwnerId();
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT id, role FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $row = $stmt->fetch();
        if ($row) {
            if (($row['role'] ?? '') !== 'restaurant_owner') {
                $pdo->prepare("UPDATE users SET role = 'restaurant_owner' WHERE id = ?")
                    ->execute([$row['id']]);
            }

            return (string) $row['id'];
        }

        $id = Auth::registerOwner($email, bin2hex(random_bytes(8)), $name);

        return $id ?: self::portalOwnerId();
    }

    private static function portalOwnerId(): string
    {
        $pdo = Database::connection();
        $email = 'portal-restorani@sandzak.local';
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $id = $stmt->fetchColumn();
        if ($id) {
            return (string) $id;
        }

        $id = new_id();
        $hash = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
        $pdo->prepare('INSERT INTO users (id, email, passwordHash, name, role) VALUES (?, ?, ?, ?, ?)')
            ->execute([$id, $email, $hash, 'Sandžak portal', 'restaurant_owner']);

        return $id;
    }

    /** @param array<string, mixed> $post */
    public static function profileDataFromPost(array $post): array
    {
        $hours = [];
        foreach (['pon', 'uto', 'sri', 'cet', 'pet', 'sub', 'ned'] as $d) {
            $hours[$d] = trim((string) ($post['hours_' . $d] ?? ''));
        }

        return [
            'name' => trim((string) ($post['name'] ?? '')),
            'slug' => trim((string) ($post['slug'] ?? '')),
            'city' => (string) ($post['city'] ?? 'NOVI_PAZAR'),
            'address' => trim((string) ($post['address'] ?? '')),
            'phone' => trim((string) ($post['phone'] ?? '')),
            'whatsapp' => trim((string) ($post['whatsapp'] ?? '')),
            'description' => trim((string) ($post['description'] ?? '')),
            'logoImage' => trim((string) ($post['logoImage'] ?? '')) ?: null,
            'coverImage' => trim((string) ($post['coverImage'] ?? '')) ?: null,
            'reviewsEnabled' => isset($post['reviewsEnabled']) ? 1 : 0,
            'hours' => $hours,
            'status' => (string) ($post['status'] ?? 'PUBLISHED'),
            'ownerEmail' => trim((string) ($post['ownerEmail'] ?? '')),
            'ownerName' => trim((string) ($post['ownerName'] ?? '')),
            'menuLangs' => MenuI18n::menuLangsFromPost($post),
        ];
    }

    /** Admin: kreira ili uređuje restoran, može odmah objaviti. */
    public static function saveRestaurantAdmin(array $data, ?string $id = null): string
    {
        $pdo = Database::connection();
        $slug = self::uniqueSlug($data['slug'] ?: $data['name'], $id);
        $hoursJson = json_encode($data['hours'] ?? [], JSON_UNESCAPED_UNICODE);
        $menuLangsJson = MenuI18n::encodeMenuLangs($data['menuLangs'] ?? ['bs']);
        $now = date('Y-m-d H:i:s');
        $status = in_array($data['status'] ?? 'PUBLISHED', ['PENDING', 'PUBLISHED', 'REJECTED', 'SUSPENDED'], true)
            ? $data['status'] : 'PUBLISHED';

        if ($id) {
            $existing = RestaurantRepository::getById($id);
            if (!$existing) {
                throw new RuntimeException('Restoran nije pronađen.');
            }
            $ownerId = ($data['ownerEmail'] ?? '') !== ''
                ? self::resolveOwnerId((string) $data['ownerEmail'], (string) ($data['ownerName'] ?? 'Vlasnik'))
                : $existing['ownerId'];
            $publishedAt = $existing['publishedAt'];
            if ($status === 'PUBLISHED' && !$publishedAt) {
                $publishedAt = $now;
            }
            $pdo->prepare(
                'UPDATE restaurants SET ownerId=?, name=?, slug=?, city=?, address=?, phone=?, whatsapp=?,
                 description=?, logoImage=?, coverImage=?, hoursJson=?, menuLangsJson=?, reviewsEnabled=?, status=?, publishedAt=?, updatedAt=?
                 WHERE id = ?'
            )->execute([
                $ownerId, $data['name'], $slug, $data['city'], $data['address'] ?: null,
                $data['phone'] ?: null, $data['whatsapp'] ?: null,
                $data['description'] ?: null, $data['logoImage'] ?: null, $data['coverImage'] ?: null,
                $hoursJson, $menuLangsJson, (int) ($data['reviewsEnabled'] ?? 1), $status, $publishedAt, $now, $id,
            ]);
            cache_flush_prefix('restaurants');
            cache_flush_prefix('home');
            self::ensureAutoCover($id);

            return $id;
        }

        $ownerId = self::resolveOwnerId((string) ($data['ownerEmail'] ?? ''), (string) ($data['ownerName'] ?? 'Vlasnik'));
        $publishedAt = $status === 'PUBLISHED' ? $now : null;
        $id = new_id();
        $pdo->prepare(
            'INSERT INTO restaurants (id, ownerId, name, slug, city, address, phone, whatsapp,
             description, logoImage, coverImage, hoursJson, menuLangsJson, status, qrCode, reviewsEnabled, publishedAt, createdAt, updatedAt)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $id, $ownerId, $data['name'], $slug, $data['city'],
            $data['address'] ?: null, $data['phone'] ?: null, $data['whatsapp'] ?: null,
            $data['description'] ?: null, $data['logoImage'] ?: null, $data['coverImage'] ?: null,
            $hoursJson, $menuLangsJson, $status, self::uniqueQrCode(), (int) ($data['reviewsEnabled'] ?? 1),
            $publishedAt, $now, $now,
        ]);
        cache_flush_prefix('restaurants');
        cache_flush_prefix('home');
        self::ensureAutoCover($id);

        return $id;
    }

    /** @param array<string, mixed> $data */
    public static function saveRestaurant(array $data, string $ownerId, ?string $id = null): string
    {
        $pdo = Database::connection();
        $slug = self::uniqueSlug($data['slug'] ?: $data['name'], $id);
        $hoursJson = json_encode($data['hours'] ?? [], JSON_UNESCAPED_UNICODE);
        $menuLangsJson = MenuI18n::encodeMenuLangs($data['menuLangs'] ?? ['bs']);
        $now = date('Y-m-d H:i:s');

        if ($id) {
            $pdo->prepare(
                'UPDATE restaurants SET name=?, slug=?, city=?, address=?, phone=?, whatsapp=?,
                 description=?, logoImage=?, coverImage=?, hoursJson=?, menuLangsJson=?, reviewsEnabled=?, updatedAt=?
                 WHERE id = ? AND ownerId = ?'
            )->execute([
                $data['name'], $slug, $data['city'], $data['address'] ?: null,
                $data['phone'] ?: null, $data['whatsapp'] ?: null,
                $data['description'] ?: null, $data['logoImage'] ?: null, $data['coverImage'] ?: null,
                $hoursJson, $menuLangsJson, (int) ($data['reviewsEnabled'] ?? 1), $now, $id, $ownerId,
            ]);
            cache_flush_prefix('restaurants');
            self::ensureAutoCover($id);

            return $id;
        }

        $id = new_id();
        $pdo->prepare(
            'INSERT INTO restaurants (id, ownerId, name, slug, city, address, phone, whatsapp,
             description, logoImage, coverImage, hoursJson, menuLangsJson, status, qrCode, reviewsEnabled, createdAt, updatedAt)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $id, $ownerId, $data['name'], $slug, $data['city'],
            $data['address'] ?: null, $data['phone'] ?: null, $data['whatsapp'] ?: null,
            $data['description'] ?: null, $data['logoImage'] ?: null, $data['coverImage'] ?: null,
            $hoursJson, $menuLangsJson, 'PENDING', self::uniqueQrCode(), (int) ($data['reviewsEnabled'] ?? 1), $now, $now,
        ]);
        cache_flush_prefix('restaurants');
        self::ensureAutoCover($id);

        return $id;
    }

    public static function submitForReview(string $restaurantId, string $ownerId): bool
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT id FROM restaurants WHERE id = ? AND ownerId = ? LIMIT 1');
        $stmt->execute([$restaurantId, $ownerId]);
        if (!$stmt->fetch()) {
            return false;
        }
        $pdo->prepare("UPDATE restaurants SET status = 'PENDING', updatedAt = ? WHERE id = ?")
            ->execute([date('Y-m-d H:i:s'), $restaurantId]);
        cache_flush_prefix('restaurants');

        return true;
    }

    public static function setStatus(string $id, string $status): void
    {
        $pdo = Database::connection();
        $publishedAt = $status === 'PUBLISHED' ? date('Y-m-d H:i:s') : null;
        $pdo->prepare('UPDATE restaurants SET status = ?, publishedAt = COALESCE(publishedAt, ?), updatedAt = ? WHERE id = ?')
            ->execute([$status, $publishedAt, date('Y-m-d H:i:s'), $id]);
        cache_flush_prefix('restaurants');
        cache_flush_prefix('home');
    }

    public static function saveCategory(string $restaurantId, string $name, ?string $id = null, array $translations = []): string
    {
        $pdo = Database::connection();
        $translationsJson = $translations !== [] ? json_encode($translations, JSON_UNESCAPED_UNICODE) : null;
        if ($id) {
            $pdo->prepare('UPDATE menu_categories SET name = ?, translationsJson = COALESCE(?, translationsJson) WHERE id = ? AND restaurantId = ?')
                ->execute([trim($name), $translationsJson, $id, $restaurantId]);
            return $id;
        }
        $max = (int) $pdo->prepare('SELECT COALESCE(MAX(sortOrder), 0) FROM menu_categories WHERE restaurantId = ?')
            ->execute([$restaurantId]) ?: 0;
        $stmt = $pdo->prepare('SELECT COALESCE(MAX(sortOrder), 0) FROM menu_categories WHERE restaurantId = ?');
        $stmt->execute([$restaurantId]);
        $max = (int) $stmt->fetchColumn();
        $id = new_id();
        $pdo->prepare('INSERT INTO menu_categories (id, restaurantId, name, translationsJson, sortOrder) VALUES (?, ?, ?, ?, ?)')
            ->execute([$id, $restaurantId, trim($name), $translationsJson, $max + 1]);
        cache_flush_prefix('restaurants');

        return $id;
    }

    public static function deleteCategory(string $restaurantId, string $categoryId): void
    {
        $pdo = Database::connection();
        $pdo->prepare('DELETE FROM menu_items WHERE categoryId = ? AND restaurantId = ?')
            ->execute([$categoryId, $restaurantId]);
        $pdo->prepare('DELETE FROM menu_categories WHERE id = ? AND restaurantId = ?')
            ->execute([$categoryId, $restaurantId]);
        cache_flush_prefix('restaurants');
    }

    /** @param array<string, mixed> $data */
    public static function saveMenuItem(string $restaurantId, array $data, ?string $id = null): string
    {
        $pdo = Database::connection();
        $tagsJson = json_encode($data['tags'] ?? [], JSON_UNESCAPED_UNICODE);
        $translationsJson = json_encode($data['translations'] ?? [], JSON_UNESCAPED_UNICODE);
        $price = isset($data['price']) && $data['price'] !== '' ? (float) $data['price'] : null;
        $now = date('Y-m-d H:i:s');

        if ($id) {
            $pdo->prepare(
                'UPDATE menu_items SET categoryId=?, name=?, description=?, price=?, priceLabel=?,
                 currency=?, image=?, tagsJson=?, translationsJson=?, isAvailable=?, updatedAt=? WHERE id=? AND restaurantId=?'
            )->execute([
                $data['categoryId'], $data['name'], $data['description'] ?: null, $price,
                $data['priceLabel'] ?: null, $data['currency'] ?? 'RSD', $data['image'] ?: null,
                $tagsJson, $translationsJson, (int) ($data['isAvailable'] ?? 1), $now, $id, $restaurantId,
            ]);
            cache_flush_prefix('restaurants');
            return $id;
        }

        $stmt = $pdo->prepare('SELECT COALESCE(MAX(sortOrder), 0) FROM menu_items WHERE categoryId = ?');
        $stmt->execute([$data['categoryId']]);
        $max = (int) $stmt->fetchColumn();
        $id = new_id();
        $pdo->prepare(
            'INSERT INTO menu_items (id, categoryId, restaurantId, name, description, price, priceLabel,
             currency, image, tagsJson, translationsJson, isAvailable, sortOrder, createdAt, updatedAt)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $id, $data['categoryId'], $restaurantId, $data['name'], $data['description'] ?: null,
            $price, $data['priceLabel'] ?: null, $data['currency'] ?? 'RSD', $data['image'] ?: null,
            $tagsJson, $translationsJson, (int) ($data['isAvailable'] ?? 1), $max + 1, $now, $now,
        ]);
        cache_flush_prefix('restaurants');

        return $id;
    }

    public static function deleteMenuItem(string $restaurantId, string $itemId): void
    {
        Database::connection()
            ->prepare('DELETE FROM menu_items WHERE id = ? AND restaurantId = ?')
            ->execute([$itemId, $restaurantId]);
        cache_flush_prefix('restaurants');
    }

    public static function clearMenu(string $restaurantId): void
    {
        $pdo = Database::connection();
        $pdo->prepare('DELETE FROM menu_items WHERE restaurantId = ?')->execute([$restaurantId]);
        $pdo->prepare('DELETE FROM menu_categories WHERE restaurantId = ?')->execute([$restaurantId]);
        cache_flush_prefix('restaurants');
    }

    /**
     * Uvezi AI-skenirani meni.
     * @param array{categories: list<array<string, mixed>>, notes?: string} $scan
     */
    public static function importScannedMenu(string $restaurantId, array $scan, bool $replaceExisting = false): int
    {
        if ($replaceExisting) {
            self::clearMenu($restaurantId);
        }

        $imported = 0;
        foreach ($scan['categories'] as $cat) {
            $catName = trim((string) ($cat['name'] ?? ''));
            if ($catName === '') {
                continue;
            }
            $catId = self::saveCategory($restaurantId, $catName);
            foreach ($cat['items'] ?? [] as $item) {
                $name = trim((string) ($item['name'] ?? ''));
                if ($name === '') {
                    continue;
                }
                $price = $item['price'] ?? null;
                if ($price === '' || $price === null) {
                    $price = null;
                } else {
                    $price = (float) preg_replace('/[^\d.,]/', '', (string) $price);
                    $price = (float) str_replace(',', '.', (string) $price);
                    if ($price <= 0) {
                        $price = null;
                    }
                }
                self::saveMenuItem($restaurantId, [
                    'categoryId' => $catId,
                    'name' => $name,
                    'description' => trim((string) ($item['description'] ?? '')),
                    'price' => $price,
                    'priceLabel' => trim((string) ($item['priceLabel'] ?? '')) ?: null,
                    'currency' => (string) ($item['currency'] ?? 'RSD'),
                    'image' => null,
                    'tags' => is_array($item['tags'] ?? null) ? $item['tags'] : [],
                    'isAvailable' => 1,
                ]);
                $imported++;
            }
        }
        cache_flush_prefix('restaurants');

        return $imported;
    }

    public static function outputQrPng(array $restaurant): void
    {
        $url = self::qrImageUrl($restaurant, 600);
        $img = HttpClient::get($url, 30);
        if ($img === null || $img === '') {
            http_response_code(502);
            exit('QR generisanje nije uspjelo.');
        }
        header('Content-Type: image/png');
        header('Content-Disposition: attachment; filename="qr-' . $restaurant['slug'] . '.png"');
        header('Cache-Control: no-store');
        echo $img;
        exit;
    }

    public static function addReview(string $restaurantId, string $name, int $rating, string $body, string $ipHash): string
    {
        $id = new_id();
        Database::connection()->prepare(
            'INSERT INTO restaurant_reviews (id, restaurantId, name, rating, body, status, ipHash)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        )->execute([$id, $restaurantId, $name, $rating, $body ?: null, 'PENDING', $ipHash]);
        cache_flush_prefix('restaurants');

        return $id;
    }

    public static function setReviewStatus(string $reviewId, string $status): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT restaurantId FROM restaurant_reviews WHERE id = ?');
        $stmt->execute([$reviewId]);
        $restaurantId = $stmt->fetchColumn();
        if (!$restaurantId) {
            return;
        }
        $pdo->prepare('UPDATE restaurant_reviews SET status = ? WHERE id = ?')
            ->execute([$status, $reviewId]);
        RestaurantRepository::recalcRating((string) $restaurantId);
        cache_flush_prefix('restaurants');
    }

    public static function formatPrice(?float $price, ?string $label, string $currency = 'RSD'): string
    {
        if ($label) {
            return $label;
        }
        if ($price === null) {
            return '—';
        }
        $formatted = number_format($price, $price == floor($price) ? 0 : 2, ',', '.');

        return $formatted . ' ' . $currency;
    }

    public static function regenerateCover(string $restaurantId): ?string
    {
        $pdo = Database::connection();
        $pdo->prepare('UPDATE restaurants SET coverImage = NULL WHERE id = ?')->execute([$restaurantId]);
        $r = RestaurantRepository::getById($restaurantId);

        return $r ? RestaurantCoverGenerator::ensureStored($r) : null;
    }

    private static function ensureAutoCover(string $id): void
    {
        $r = RestaurantRepository::getById($id);
        if ($r && empty($r['coverImage'])) {
            RestaurantCoverGenerator::ensureStored($r);
        }
    }
}
