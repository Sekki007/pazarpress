<?php

declare(strict_types=1);

$method = $_SERVER['REQUEST_METHOD'];
$uri = rtrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/', '/') ?: '/';

if ($uri === '/api/vaktija' && $method === 'GET') {
    $data = InfoStrip::get();
    json_response(['prayers' => [], 'nextName' => $data['vaktija']['nextName'], 'nextTime' => $data['vaktija']['nextTime']]);
}

if ($uri === '/api/newsletter' && $method === 'POST') {
    if (!RateLimiter::hit(RateLimiter::clientKey('newsletter'), 5, 3600)) {
        json_response(['error' => 'Previše pokušaja. Pokušajte kasnije.'], 429);
    }
    $input = json_decode((string) file_get_contents('php://input'), true) ?: $_POST;
    $email = strtolower(trim((string) ($input['email'] ?? '')));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        json_response(['error' => 'Nevaljan email.'], 400);
    }
    $pdo = Database::connection();
    $token = bin2hex(random_bytes(16));
    $confirmRequired = (bool) Settings::get('newsletter_confirm', true);
    try {
        $pdo->prepare('INSERT INTO newsletter_subscribers (id, email, confirmed, token) VALUES (?, ?, ?, ?)')
            ->execute([new_id(), $email, $confirmRequired ? 0 : 1, $token]);
    } catch (PDOException) {
        json_response(['error' => 'Email je već prijavljen.'], 400);
    }
    if ($confirmRequired) {
        $link = absolute_url('/newsletter/potvrdi?token=' . urlencode($token));
        $sent = send_mail($email, 'Potvrdite Sandžak.net newsletter', "Kliknite za potvrdu:\n\n{$link}\n\nAko niste vi prijavili, ignorišite poruku.");
        $msg = $sent
            ? 'Provjerite email za potvrdu prijave.'
            : 'Prijavljeni ste — email nije poslan (server mail nije podešen). Koristite link iz admina ili isključite potvrdu u postavkama.';
        if (!$sent && config('app_debug')) {
            $msg .= ' Dev link: ' . $link;
        }
        json_response(['ok' => true, 'message' => $msg]);
    }
    json_response(['ok' => true, 'message' => 'Hvala na prijavi!']);
}

if ($uri === '/api/poll/vote' && $method === 'POST') {
    if (!RateLimiter::hit(RateLimiter::clientKey('poll'), 20, 3600)) {
        json_response(['error' => 'Previše pokušaja.'], 429);
    }
    $input = json_decode((string) file_get_contents('php://input'), true) ?: $_POST;
    $optionId = (string) ($input['optionId'] ?? '');
    $ipHash = hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . ($_SERVER['HTTP_USER_AGENT'] ?? ''));
    $pdo = Database::connection();
    try {
        $pdo->prepare('INSERT INTO poll_votes (id, pollOptionId, ipHash) VALUES (?, ?, ?)')
            ->execute([new_id(), $optionId, $ipHash]);
        cache_forget('home:poll');
        json_response(['ok' => true]);
    } catch (PDOException) {
        json_response(['error' => 'Već ste glasali.'], 400);
    }
}

if ($uri === '/api/comments' && $method === 'POST') {
    if (!RateLimiter::hit(RateLimiter::clientKey('comment'), 8, 3600)) {
        json_response(['error' => 'Previše komentara. Pokušajte kasnije.'], 429);
    }
    $input = json_decode((string) file_get_contents('php://input'), true) ?: $_POST;
    $articleId = trim((string) ($input['articleId'] ?? ''));
    $name = trim((string) ($input['name'] ?? ''));
    $body = trim((string) ($input['body'] ?? ''));
    if ($articleId === '' || $name === '' || $body === '') {
        json_response(['error' => 'Popunite sva polja.'], 400);
    }
    if (mb_strlen($name) > 80 || mb_strlen($body) > 2000) {
        json_response(['error' => 'Komentar je predugačak.'], 400);
    }
    $pdo = Database::connection();
    $exists = $pdo->prepare("SELECT id FROM articles WHERE id = ? AND status = 'PUBLISHED'");
    $exists->execute([$articleId]);
    if (!$exists->fetchColumn()) {
        json_response(['error' => 'Članak nije pronađen.'], 404);
    }
    ArticleRepository::addComment($articleId, $name, $body);
    json_response(['ok' => true, 'message' => 'Komentar je poslan na moderaciju.']);
}

if ($uri === '/api/articles/more' && $method === 'GET') {
    $citySlug = isset($_GET['grad']) ? trim((string) $_GET['grad']) : '';
    $city = $citySlug !== '' ? slug_to_city($citySlug) : null;
    if ($citySlug !== '' && !$city) {
        json_response(['error' => 'Nevaljan grad.'], 400);
    }
    $cursor = $_GET['cursor'] ?? null;
    $result = ArticleRepository::getLatest($city, $cursor, 6);
    $items = array_map(static function (array $a): array {
        return [
            'slug' => $a['slug'],
            'title' => $a['title'],
            'category' => $a['category'],
            'city' => $a['city'],
            'publishedAt' => $a['publishedAt'],
            'coverImage' => $a['coverImage'] ?? null,
        ];
    }, $result['items']);
    json_response(['items' => $items, 'nextCursor' => $result['nextCursor']]);
}

if ($uri === '/api/cron/auto-vesti' && $method === 'GET') {
    $secret = getenv('IMPORT_CRON_SECRET') ?: '';
    $key = (string) ($_GET['key'] ?? '');
    if ($secret === '' || !hash_equals($secret, $key)) {
        json_response(['error' => 'Unauthorized'], 401);
    }
    require_once __DIR__ . '/../AutoVestiConfig.php';
    require_once __DIR__ . '/../AutoVestiQueue.php';
    require_once __DIR__ . '/../HttpClient.php';
    require_once __DIR__ . '/../FeedParser.php';
    require_once __DIR__ . '/../AdminService.php';
    require_once __DIR__ . '/../AutoVestiFetcher.php';
    require_once __DIR__ . '/../AutoVestiAi.php';
    require_once __DIR__ . '/../AutoVestiDuplicate.php';
    require_once __DIR__ . '/../AutoVestiContent.php';
    require_once __DIR__ . '/../AutoVestiVideo.php';
    require_once __DIR__ . '/../AutoVestiFacts.php';
    require_once __DIR__ . '/../AutoVestiImages.php';
    require_once __DIR__ . '/../AutoVestiStats.php';
    require_once __DIR__ . '/../AutoVestiSession.php';
    require_once __DIR__ . '/../AutoVestiTelegram.php';
    require_once __DIR__ . '/../AutoVestiRunner.php';
    $n = AutoVestiRunner::fetchToQueue();
    json_response(['ok' => true, 'fetched' => $n]);
}

if ($uri === '/api/avm/telegram' && $method === 'POST') {
    require_once __DIR__ . '/../AutoVestiConfig.php';
    require_once __DIR__ . '/../AutoVestiQueue.php';
    require_once __DIR__ . '/../HttpClient.php';
    require_once __DIR__ . '/../FeedParser.php';
    require_once __DIR__ . '/../AdminService.php';
    require_once __DIR__ . '/../AutoVestiFetcher.php';
    require_once __DIR__ . '/../AutoVestiAi.php';
    require_once __DIR__ . '/../AutoVestiDuplicate.php';
    require_once __DIR__ . '/../AutoVestiContent.php';
    require_once __DIR__ . '/../AutoVestiVideo.php';
    require_once __DIR__ . '/../AutoVestiFacts.php';
    require_once __DIR__ . '/../AutoVestiImages.php';
    require_once __DIR__ . '/../AutoVestiStats.php';
    require_once __DIR__ . '/../AutoVestiSession.php';
    require_once __DIR__ . '/../AutoVestiProcessor.php';
    require_once __DIR__ . '/../AutoVestiBackground.php';
    require_once __DIR__ . '/../AutoVestiTelegram.php';
    require_once __DIR__ . '/../AutoVestiRunner.php';
    $secret = (string) ($_GET['secret'] ?? '');
    $expected = (string) AutoVestiConfig::get('telegram_webhook_secret', '');
    if ($expected === '' || !hash_equals($expected, $secret)) {
        json_response(['error' => 'Unauthorized'], 401);
    }
    $input = json_decode((string) file_get_contents('php://input'), true);
    if (is_array($input)) {
        AutoVestiTelegram::handleUpdate($input);
    }
    json_response(['ok' => true]);
}

if ($uri === '/api/avm/process-background' && $method === 'POST') {
    require_once __DIR__ . '/../AutoVestiConfig.php';
    require_once __DIR__ . '/../AutoVestiQueue.php';
    require_once __DIR__ . '/../HttpClient.php';
    require_once __DIR__ . '/../FeedParser.php';
    require_once __DIR__ . '/../AdminService.php';
    require_once __DIR__ . '/../AutoVestiFetcher.php';
    require_once __DIR__ . '/../AutoVestiAi.php';
    require_once __DIR__ . '/../AutoVestiDuplicate.php';
    require_once __DIR__ . '/../AutoVestiContent.php';
    require_once __DIR__ . '/../AutoVestiVideo.php';
    require_once __DIR__ . '/../AutoVestiFacts.php';
    require_once __DIR__ . '/../AutoVestiImages.php';
    require_once __DIR__ . '/../AutoVestiStats.php';
    require_once __DIR__ . '/../AutoVestiSession.php';
    require_once __DIR__ . '/../AutoVestiProcessor.php';
    require_once __DIR__ . '/../AutoVestiBackground.php';
    require_once __DIR__ . '/../AutoVestiTelegram.php';
    @set_time_limit(300);
    $input = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($input)) {
        json_response(['error' => 'Invalid payload'], 400);
    }
    $secret = (string) ($input['secret'] ?? '');
    $expected = (string) AutoVestiConfig::get('telegram_webhook_secret', '');
    if ($expected === '' || !hash_equals($expected, $secret)) {
        json_response(['error' => 'Unauthorized'], 401);
    }
    AutoVestiProcessor::executeBackground($input);
    json_response(['ok' => true]);
}

http_response_code(404);
json_response(['error' => 'Not found'], 404);
