<?php

declare(strict_types=1);

final class HomeFeaturedService
{
    private const STATE_FILE = __DIR__ . '/../storage/home-featured.json';

    public static function todayStart(): string
    {
        return date('Y-m-d 00:00:00');
    }

    public static function isEnabled(): bool
    {
        return (bool) Settings::get('auto_feature_today', true);
    }

    /** @return list<string> */
    public static function getTodayPublishedIds(): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            "SELECT id FROM articles WHERE status = 'PUBLISHED' AND publishedAt >= ? ORDER BY publishedAt DESC"
        );
        $stmt->execute([self::todayStart()]);

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public static function clearStaleFeatured(): void
    {
        $pdo = Database::connection();
        $pdo->prepare(
            "UPDATE articles SET isFeatured = 0 WHERE isFeatured = 1 AND (publishedAt IS NULL OR publishedAt < ?)"
        )->execute([self::todayStart()]);
    }

    public static function currentFeaturedId(): ?string
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            "SELECT id FROM articles WHERE status = 'PUBLISHED' AND isFeatured = 1 AND publishedAt >= ? LIMIT 1"
        );
        $stmt->execute([self::todayStart()]);
        $id = $stmt->fetchColumn();

        return $id ? (string) $id : null;
    }

    public static function setFeatured(string $articleId): void
    {
        $pdo = Database::connection();
        $pdo->exec('UPDATE articles SET isFeatured = 0');
        $pdo->prepare('UPDATE articles SET isFeatured = 1 WHERE id = ?')->execute([$articleId]);
        self::touchRotateState($articleId);
        cache_flush_content();
    }

    /** Nasumično bira jednu od današnjih objavljenih vesti za hero. */
    public static function pickRandomToday(?string $triggerArticleId = null): ?string
    {
        if (!self::isEnabled()) {
            return null;
        }

        self::clearStaleFeatured();
        $ids = self::getTodayPublishedIds();
        if ($ids === []) {
            return null;
        }

        $pick = $ids[array_rand($ids)];
        self::setFeatured($pick);

        return $pick;
    }

    /** Osigurava da hero prikazuje današnju vest; periodično rotira po podešavanju. */
    public static function ensureTodayFeatured(): void
    {
        if (!self::isEnabled()) {
            return;
        }

        self::clearStaleFeatured();
        $current = self::currentFeaturedId();
        $rotateHours = max(0, (int) Settings::get('feature_rotate_hours', 3));

        if ($current !== null) {
            if ($rotateHours > 0 && self::shouldRotate($rotateHours)) {
                self::pickRandomToday();
            }
            return;
        }

        self::pickRandomToday();
    }

    public static function onArticlePublished(string $articleId): void
    {
        if (!self::isEnabled()) {
            return;
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            "SELECT publishedAt FROM articles WHERE id = ? AND status = 'PUBLISHED' LIMIT 1"
        );
        $stmt->execute([$articleId]);
        $publishedAt = $stmt->fetchColumn();
        if (!$publishedAt || (string) $publishedAt < self::todayStart()) {
            return;
        }

        self::pickRandomToday($articleId);
    }

    private static function shouldRotate(int $hours): bool
    {
        $state = self::readState();
        $last = (int) ($state['rotated_at'] ?? 0);

        return $last === 0 || (time() - $last) >= ($hours * 3600);
    }

    private static function touchRotateState(string $articleId): void
    {
        $dir = dirname(self::STATE_FILE);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        file_put_contents(self::STATE_FILE, json_encode([
            'article_id' => $articleId,
            'rotated_at' => time(),
            'day' => date('Y-m-d'),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    /** @return array<string, mixed> */
    private static function readState(): array
    {
        if (!is_file(self::STATE_FILE)) {
            return [];
        }
        $json = json_decode((string) file_get_contents(self::STATE_FILE), true);
        if (!is_array($json)) {
            return [];
        }
        if (($json['day'] ?? '') !== date('Y-m-d')) {
            return ['rotated_at' => 0];
        }

        return $json;
    }
}
