<?php

declare(strict_types=1);

final class FacebookPublisher
{
    private const GRAPH = 'https://graph.facebook.com/v21.0';
    private const SHARES_FILE = __DIR__ . '/../storage/facebook-shares.json';

    public static function isEnabled(): bool
    {
        return (bool) Settings::get('facebook_auto_share', false) && self::credentials() !== null;
    }

    /** @return array{page_id: string, token: string}|null */
    public static function credentials(): ?array
    {
        $pageId = trim((string) (getenv('FB_PAGE_ID') ?: Settings::get('facebook_page_id', '')));
        $token = trim((string) (getenv('FB_PAGE_ACCESS_TOKEN') ?: Settings::get('facebook_page_access_token', '')));
        if ($pageId === '' || $token === '') {
            return null;
        }
        return ['page_id' => $pageId, 'token' => $token];
    }

    /** @return array{ok: bool, message: string} */
    public static function verifyConnection(): array
    {
        $creds = self::credentials();
        if ($creds === null) {
            return ['ok' => false, 'message' => 'Page ID i access token nisu podešeni.'];
        }

        $url = self::GRAPH . '/' . rawurlencode($creds['page_id'])
            . '?fields=id,name&access_token=' . rawurlencode($creds['token']);
        $body = HttpClient::get($url, 20);
        if ($body === null) {
            return ['ok' => false, 'message' => 'Facebook API nije dostupan ili token nije valjan.'];
        }
        $data = json_decode($body, true);
        if (!is_array($data) || isset($data['error'])) {
            $msg = is_array($data) && isset($data['error']['message'])
                ? (string) $data['error']['message']
                : 'Nepoznata greška.';
            return ['ok' => false, 'message' => $msg];
        }

        return [
            'ok' => true,
            'message' => 'Povezano: ' . ($data['name'] ?? $data['id'] ?? $creds['page_id']),
        ];
    }

    public static function shareArticle(string $articleId, bool $force = false): bool
    {
        if (!$force && !self::isEnabled()) {
            return false;
        }
        if (self::credentials() === null) {
            return false;
        }
        if (!$force && self::wasShared($articleId)) {
            return false;
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM articles WHERE id = ? AND status = ? LIMIT 1');
        $stmt->execute([$articleId, 'PUBLISHED']);
        $article = $stmt->fetch();
        if (!$article) {
            return false;
        }

        $url = absolute_url('/vijest/' . rawurlencode((string) $article['slug']));
        $message = self::buildMessage($article);
        $result = self::postLink($message, $url);
        if ($result === null) {
            self::log('Greška pri objavi: ' . $article['title']);
            return false;
        }

        self::markShared($articleId, (string) ($result['id'] ?? ''));
        self::log('Objavljeno na FB: ' . $article['title']);
        return true;
    }

    /** @param array<string, mixed> $article */
    private static function buildMessage(array $article): string
    {
        $title = trim((string) ($article['title'] ?? ''));
        if (!empty($article['isBreaking'])) {
            $title = '🔴 HITNO: ' . $title;
        }

        $lead = trim(strip_tags((string) ($article['lead'] ?? '')));
        if ($lead !== '') {
            $title .= "\n\n" . mb_substr($lead, 0, 400, 'UTF-8');
        }

        return $title;
    }

    /** @return array<string, mixed>|null */
    private static function postLink(string $message, string $link): ?array
    {
        $creds = self::credentials();
        if ($creds === null) {
            return null;
        }

        $endpoint = self::GRAPH . '/' . rawurlencode($creds['page_id']) . '/feed';
        $response = HttpClient::postForm($endpoint, [
            'message' => $message,
            'link' => $link,
            'access_token' => $creds['token'],
        ]);

        if ($response === null || !empty($response['_error'])) {
            $err = is_array($response) ? (string) ($response['_body'] ?? '') : '';
            self::log('FB API: ' . ($err !== '' ? $err : 'nepoznata greška'));
            return null;
        }

        return $response;
    }

    private static function wasShared(string $articleId): bool
    {
        $data = self::loadShares();
        return isset($data[$articleId]);
    }

    private static function markShared(string $articleId, string $postId): void
    {
        $data = self::loadShares();
        $data[$articleId] = [
            'post_id' => $postId,
            'shared_at' => date('Y-m-d H:i:s'),
        ];
        self::saveShares($data);
    }

    /** @return array<string, array<string, string>> */
    private static function loadShares(): array
    {
        if (!is_file(self::SHARES_FILE)) {
            return [];
        }
        $json = json_decode((string) file_get_contents(self::SHARES_FILE), true);
        return is_array($json) ? $json : [];
    }

    /** @param array<string, array<string, string>> $data */
    private static function saveShares(array $data): void
    {
        $dir = dirname(self::SHARES_FILE);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        file_put_contents(
            self::SHARES_FILE,
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
    }

    private static function log(string $msg): void
    {
        $file = __DIR__ . '/../storage/facebook.log';
        $line = date('Y-m-d H:i:s') . "\t" . $msg . "\n";
        file_put_contents($file, $line, FILE_APPEND);
    }
}
