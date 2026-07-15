<?php

declare(strict_types=1);

$method = $_SERVER['REQUEST_METHOD'];
$uri = rtrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/', '/') ?: '/';
$pdo = Database::connection();

if ($uri === '/admin/login') {
    if ($method === 'POST') {
        verify_csrf();
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        if (!RateLimiter::hit(RateLimiter::clientKey('admin-login:' . $email), 8, 900)) {
            flash('error', 'Previše pokušaja prijave. Sačekajte 15 minuta.');
            redirect('/admin/login');
        }
        if (Auth::attempt($email, $_POST['password'] ?? '')) {
            redirect($_GET['return'] ?? '/admin');
        }
        flash('error', 'Pogrešan email ili lozinka.');
    }
    if (Auth::check()) {
        redirect('/admin');
    }
    view('admin/login', ['title' => 'Admin prijava'], null);
    exit;
}

if ($uri === '/admin/logout') {
    Auth::logout();
    redirect('/admin/login');
}

Auth::requireAdmin();
$user = Auth::user();

if ($uri === '/admin/cache/flush' && $method === 'POST') {
    verify_csrf();
    cache_flush_content();
    flash('success', 'Keš stranice je obrisan. Početna će prikazati najnovije vijesti.');
    redirect('/admin');
}

if ($uri === '/admin' || $uri === '/admin/') {
    $stats = [
        'articles' => (int) $pdo->query('SELECT COUNT(*) FROM articles')->fetchColumn(),
        'published' => (int) $pdo->query("SELECT COUNT(*) FROM articles WHERE status='PUBLISHED'")->fetchColumn(),
        'drafts' => (int) $pdo->query("SELECT COUNT(*) FROM articles WHERE status='DRAFT'")->fetchColumn(),
        'comments' => (int) $pdo->query("SELECT COUNT(*) FROM comments WHERE status='PENDING'")->fetchColumn(),
        'subscribers' => (int) $pdo->query('SELECT COUNT(*) FROM newsletter_subscribers')->fetchColumn(),
        'submissions' => (int) $pdo->query('SELECT COUNT(*) FROM reader_submissions WHERE `read`=0')->fetchColumn(),
    ];
    $recent = $pdo->query('SELECT id, slug, title, status, updatedAt FROM articles ORDER BY updatedAt DESC LIMIT 5')->fetchAll();
    $stmt = $pdo->prepare('SELECT passwordHash FROM users WHERE id = ?');
    $stmt->execute([$user['id']]);
    $weakPassword = password_verify('admin123', (string) $stmt->fetchColumn());
    $gdMissing = !extension_loaded('gd');
    admin_view('dashboard', compact('user', 'stats', 'recent', 'weakPassword', 'gdMissing') + ['title' => 'Pregled', 'active' => 'dashboard']);
    exit;
}

if ($uri === '/admin/clanci') {
    $status = $_GET['status'] ?? null;
    $sql = 'SELECT a.*, c.name AS categoryName FROM articles a JOIN categories c ON c.id=a.categoryId';
    if ($status) {
        $stmt = $pdo->prepare($sql . ' WHERE a.status = ? ORDER BY a.updatedAt DESC');
        $stmt->execute([$status]);
        $articles = $stmt->fetchAll();
    } else {
        $articles = $pdo->query($sql . ' ORDER BY a.updatedAt DESC')->fetchAll();
    }
    admin_view('articles', compact('user', 'articles', 'status') + ['title' => 'Članci', 'active' => 'clanci']);
    exit;
}

if (preg_match('#^/admin/clanci/([^/]+)/delete$#', $uri, $dm) && $method === 'POST') {
    verify_csrf();
    $id = $dm[1];
    $pdo->prepare('DELETE FROM article_tags WHERE articleId = ?')->execute([$id]);
    $pdo->prepare('DELETE FROM comments WHERE articleId = ?')->execute([$id]);
    $pdo->prepare('DELETE FROM articles WHERE id = ?')->execute([$id]);
    cache_flush_content();
    flash('success', 'Članak obrisan.');
    redirect('/admin/clanci');
}

if ($uri === '/admin/clanci/novi' || preg_match('#^/admin/clanci/([^/]+)$#', $uri, $m)) {
    $id = $uri === '/admin/clanci/novi' ? null : $m[1];
    if ($method === 'POST') {
        verify_csrf();
        $tags = array_filter(array_map('trim', explode(',', $_POST['tags'] ?? '')));
        $data = [
            'title' => trim($_POST['title'] ?? ''),
            'slug' => trim($_POST['slug'] ?? ''),
            'lead' => trim($_POST['lead'] ?? ''),
            'body' => trim($_POST['body'] ?? ''),
            'categoryId' => $_POST['categoryId'] ?? '',
            'city' => 'NOVI_PAZAR',
            'authorId' => $_POST['authorId'] ?? '',
            'status' => $_POST['status'] ?? 'DRAFT',
            'isBreaking' => isset($_POST['isBreaking']),
            'isFeatured' => isset($_POST['isFeatured']),
            'coverImage' => trim((string) ($_POST['coverImage'] ?? '')) ?: null,
            'coverCaption' => trim((string) ($_POST['coverCaption'] ?? '')) ?: null,
            'publishedAt' => !empty($_POST['publishedAt'])
                ? date('Y-m-d H:i:s', strtotime((string) $_POST['publishedAt']))
                : null,
            'tags' => $tags,
        ];
        $savedId = AdminService::saveArticle($data, $id);
        flash('success', 'Članak sačuvan.');
        redirect('/admin/clanci/' . $savedId);
    }
    $article = null;
    $articleTags = '';
    if ($id) {
        $stmt = $pdo->prepare('SELECT * FROM articles WHERE id = ?');
        $stmt->execute([$id]);
        $article = $stmt->fetch();
        if (!$article) {
            http_response_code(404);
            exit('Članak nije pronađen.');
        }
        $tags = $pdo->prepare('SELECT t.name FROM tags t JOIN article_tags at ON at.tagId=t.id WHERE at.articleId=?');
        $tags->execute([$id]);
        $articleTags = implode(', ', array_column($tags->fetchAll(), 'name'));
    }
    $categories = $pdo->query('SELECT * FROM categories ORDER BY name')->fetchAll();
    $authors = $pdo->query('SELECT * FROM authors ORDER BY name')->fetchAll();
    admin_view('article-form', compact('user', 'article', 'categories', 'authors', 'articleTags', 'id') + ['title' => $id ? 'Uredi članak' : 'Novi članak', 'active' => 'clanci']);
    exit;
}

if (preg_match('#^/admin/preview/([^/]+)$#', $uri, $m)) {
    $article = ArticleRepository::getBySlug(urldecode($m[1]), true);
    if (!$article) {
        http_response_code(404);
        exit('Članak nije pronađen.');
    }
    view('article', [
        'title' => 'Pregled — ' . $article['title'],
        'article' => $article,
        'related' => [],
        'nextArticle' => null,
        'comments' => [],
        'preview' => true,
        'needsSerifFont' => true,
    ]);
    exit;
}

if ($uri === '/admin/rubrike') {
    if ($method === 'POST') {
        verify_csrf();
        $name = trim($_POST['name'] ?? '');
        $slug = trim($_POST['slug'] ?? '') ?: slugify($name);
        $catId = $_POST['id'] ?? null;
        if ($name) {
            if ($catId) {
                $pdo->prepare('UPDATE categories SET name=?, slug=? WHERE id=?')->execute([$name, $slug, $catId]);
            } else {
                $pdo->prepare('INSERT INTO categories (id, name, slug) VALUES (?, ?, ?)')->execute([new_id(), $name, $slug]);
            }
        }
        redirect('/admin/rubrike');
    }
    $categories = $pdo->query('SELECT c.*, (SELECT COUNT(*) FROM articles a WHERE a.categoryId=c.id) AS articleCount FROM categories c ORDER BY name')->fetchAll();
    admin_view('categories', compact('user', 'categories') + ['title' => 'Rubrike', 'active' => 'rubrike']);
    exit;
}

if ($uri === '/admin/komentari') {
    $status = $_GET['status'] ?? 'PENDING';
    if ($method === 'POST') {
        verify_csrf();
        $action = $_POST['action'] ?? '';
        $cid = $_POST['id'] ?? '';
        if ($action === 'approve') {
            $pdo->prepare("UPDATE comments SET status='APPROVED' WHERE id=?")->execute([$cid]);
        } elseif ($action === 'delete') {
            $pdo->prepare('DELETE FROM comments WHERE id=?')->execute([$cid]);
        }
        redirect('/admin/komentari?status=' . urlencode($status));
    }
    $stmt = $pdo->prepare('SELECT cm.*, a.title AS articleTitle, a.slug FROM comments cm JOIN articles a ON a.id=cm.articleId WHERE cm.status=? ORDER BY cm.createdAt DESC');
    $stmt->execute([$status]);
    $comments = $stmt->fetchAll();
    admin_view('comments', compact('user', 'comments', 'status') + ['title' => 'Komentari', 'active' => 'komentari']);
    exit;
}

if ($uri === '/admin/ankete') {
    if ($method === 'POST') {
        verify_csrf();
        if (isset($_POST['activate'])) {
            $pdo->exec('UPDATE polls SET active=0');
            $pdo->prepare('UPDATE polls SET active=1 WHERE id=?')->execute([$_POST['activate']]);
        } elseif (isset($_POST['deactivate'])) {
            $pdo->prepare('UPDATE polls SET active=0 WHERE id=?')->execute([$_POST['deactivate']]);
        } elseif (isset($_POST['delete_poll'])) {
            $pdo->prepare('DELETE FROM polls WHERE id=?')->execute([$_POST['delete_poll']]);
        } else {
            $question = trim($_POST['question'] ?? '');
            $options = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $_POST['options'] ?? '') ?: []));
            if ($question && $options) {
                if (isset($_POST['active'])) {
                    $pdo->exec('UPDATE polls SET active=0');
                }
                $pollId = new_id();
                $pdo->prepare('INSERT INTO polls (id, question, active) VALUES (?, ?, ?)')
                    ->execute([$pollId, $question, isset($_POST['active']) ? 1 : 0]);
                foreach ($options as $text) {
                    $pdo->prepare('INSERT INTO poll_options (id, pollId, text) VALUES (?, ?, ?)')
                        ->execute([new_id(), $pollId, $text]);
                }
            }
        }
        redirect('/admin/ankete');
    }
    $polls = $pdo->query('SELECT * FROM polls ORDER BY createdAt DESC')->fetchAll();
    foreach ($polls as &$poll) {
        $stmt = $pdo->prepare('SELECT po.*, COUNT(pv.id) AS votes FROM poll_options po LEFT JOIN poll_votes pv ON pv.pollOptionId=po.id WHERE po.pollId=? GROUP BY po.id');
        $stmt->execute([$poll['id']]);
        $poll['options'] = $stmt->fetchAll();
    }
    admin_view('polls', compact('user', 'polls') + ['title' => 'Ankete', 'active' => 'ankete']);
    exit;
}

if ($uri === '/admin/video') {
    if ($method === 'POST') {
        verify_csrf();
        if (isset($_POST['delete'])) {
            $pdo->prepare('DELETE FROM videos WHERE id=?')->execute([$_POST['delete']]);
        } else {
            $data = [trim($_POST['title'] ?? ''), $_POST['youtubeId'] ?? null, $_POST['duration'] ?? null, $_POST['publishedAt'] ?? date('Y-m-d H:i:s')];
            if ($_POST['id'] ?? '') {
                $pdo->prepare('UPDATE videos SET title=?, youtubeId=?, duration=?, publishedAt=? WHERE id=?')
                    ->execute([...$data, $_POST['id']]);
            } else {
                $pdo->prepare('INSERT INTO videos (id, title, youtubeId, duration, publishedAt) VALUES (?, ?, ?, ?, ?)')
                    ->execute([new_id(), ...$data]);
            }
        }
        redirect('/admin/video');
    }
    $videos = $pdo->query('SELECT * FROM videos ORDER BY publishedAt DESC')->fetchAll();
    admin_view('videos', compact('user', 'videos') + ['title' => 'Video', 'active' => 'video']);
    exit;
}

if ($uri === '/admin/newsletter/export' || ($uri === '/admin/newsletter' && ($_GET['export'] ?? '') === 'csv')) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="newsletter.csv"');
    $rows = $pdo->query('SELECT email, confirmed, createdAt FROM newsletter_subscribers ORDER BY createdAt DESC')->fetchAll();
    echo "email,confirmed,createdAt\n";
    foreach ($rows as $r) {
        echo $r['email'] . ',' . $r['confirmed'] . ',' . $r['createdAt'] . "\n";
    }
    exit;
}

if ($uri === '/admin/newsletter') {
    if ($method === 'POST' && isset($_POST['delete'])) {
        verify_csrf();
        $pdo->prepare('DELETE FROM newsletter_subscribers WHERE id=?')->execute([$_POST['delete']]);
        redirect('/admin/newsletter');
    }
    $subscribers = $pdo->query('SELECT * FROM newsletter_subscribers ORDER BY createdAt DESC')->fetchAll();
    admin_view('newsletter', compact('user', 'subscribers') + ['title' => 'Newsletter', 'active' => 'newsletter']);
    exit;
}

if ($uri === '/admin/auto-vesti') {
    require_once __DIR__ . '/../AutoVestiConfig.php';
    require_once __DIR__ . '/../AutoVestiQueue.php';
    require_once __DIR__ . '/../HttpClient.php';
    require_once __DIR__ . '/../FeedParser.php';
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

    $requestedTab = (string) ($_GET['tab'] ?? 'queue');
    $tab = in_array($requestedTab, ['queue', 'settings', 'log', 'telegram'], true)
        ? $requestedTab
        : 'queue';

    if ($method === 'POST') {
        verify_csrf();
        if (isset($_POST['avc_cleanup_videos'])) {
            $n = AutoVestiContent::cleanupVideoEmbeds();
            flash('success', 'Očišćeno ' . $n . ' video embeda.');
            redirect('/admin/auto-vesti?tab=' . $tab);
        }
        if (isset($_POST['avc_fetch_now'])) {
            $n = AutoVestiRunner::fetchToQueue();
            flash('success', 'Povučeno ' . $n . ' novih vesti u red čekanja.');
            redirect('/admin/auto-vesti?tab=queue');
        }
        if (isset($_POST['avc_process_selected'])) {
            $selected = isset($_POST['queue_ids']) ? (array) $_POST['queue_ids'] : [];
            $result = AutoVestiProcessor::processSelected($selected, [
                'mode' => 'ai',
                'editor' => is_array($user) ? $user : null,
            ]);
            $type = $result['ok'] > 0 ? 'success' : ($result['err'] > 0 ? 'warning' : 'error');
            flash($type, $result['msg']);
            redirect('/admin/auto-vesti?tab=queue');
        }
        if (isset($_POST['avc_process_selected_force'])) {
            $selected = isset($_POST['queue_ids']) ? (array) $_POST['queue_ids'] : [];
            $result = AutoVestiProcessor::processSelected($selected, [
                'mode' => 'ai',
                'editor' => is_array($user) ? $user : null,
                'force_review' => true,
            ]);
            $type = $result['ok'] > 0 ? 'success' : ($result['err'] > 0 ? 'warning' : 'error');
            flash($type, 'Ručna potvrda: ' . $result['msg']);
            redirect('/admin/auto-vesti?tab=queue');
        }
        if (isset($_POST['avc_reject_selected'])) {
            $selected = isset($_POST['queue_ids']) ? (array) $_POST['queue_ids'] : [];
            $n = AutoVestiRunner::rejectSelected($selected);
            flash('info', 'Odbijeno: ' . $n . ' vesti.');
            redirect('/admin/auto-vesti?tab=queue');
        }
        if (isset($_POST['avc_clear_queue'])) {
            AutoVestiConfig::clearQueue();
            flash('info', 'Red čekanja ispražnjen.');
            redirect('/admin/auto-vesti?tab=queue');
        }
        if (isset($_POST['avc_clear_log'])) {
            AutoVestiConfig::clearLog();
            flash('success', 'Log obrisan.');
            redirect('/admin/auto-vesti?tab=log');
        }
        if (isset($_POST['avc_clear_seen'])) {
            AutoVestiConfig::clearSeen();
            flash('success', 'GUID arhiva resetovana.');
            redirect('/admin/auto-vesti?tab=settings');
        }
        if (isset($_POST['avc_fact_run_tests'])) {
            $tests = AutoVestiFacts::runTests();
            AutoVestiConfig::updatePartial([
                'fact_protection_tests' => $tests,
                'fact_protection_tests_passed' => !empty($tests['all_passed']),
            ]);
            flash(!empty($tests['all_passed']) ? 'success' : 'warning', 'Fact testovi: ' . ($tests['passed'] ?? 0) . '/' . ($tests['total'] ?? 0) . ' prošlo.');
            redirect('/admin/auto-vesti?tab=settings');
        }
        if (isset($_POST['avc_entity_fix'])) {
            $guid = trim((string) ($_POST['fact_guid'] ?? ''));
            $wrong = trim((string) ($_POST['fact_wrong'] ?? ''));
            $right = trim((string) ($_POST['fact_right'] ?? ''));
            $row = AutoVestiQueue::getRow($guid);
            if ($guid === '' || !$row || $wrong === '' || $right === '') {
                flash('error', 'Korekcija nije sačuvana. Provjeri polja.');
                redirect('/admin/auto-vesti?tab=queue');
            }
            $item = is_array($row['item'] ?? null) ? $row['item'] : [];
            foreach (['title', 'description', '_content_html', 'preview'] as $k) {
                if (!empty($item[$k])) {
                    $item[$k] = str_ireplace($wrong, $right, (string) $item[$k]);
                }
            }
            $corrections = [];
            if (!empty($row['fact_corrections']) && is_array($row['fact_corrections'])) {
                foreach ($row['fact_corrections'] as $old => $new) {
                    $corrections[(string) $old] = (string) $new;
                }
            }
            $corrections[$wrong] = $right;
            AutoVestiQueue::updateRow($guid, [
                'item' => $item,
                'preview' => trim(strip_tags((string) ($item['description'] ?? ($row['preview'] ?? '')))),
                'fact_corrections' => $corrections,
            ]);
            flash('success', 'Korekcija entiteta sačuvana. Ponovo pokreni AI obradu.');
            redirect('/admin/auto-vesti?tab=queue');
        }
        if (isset($_POST['avc_save'])) {
            $feedUrls = $_POST['feed_url'] ?? [];
            $feedCats = $_POST['feed_cat'] ?? [];
            $feedBps = $_POST['feed_bp'] ?? [];
            $feedTypes = $_POST['feed_type'] ?? [];
            $feedsMap = [];
            foreach ($feedUrls as $i => $u) {
                $u = trim((string) $u);
                if ($u === '') {
                    continue;
                }
                $type = (string) ($feedTypes[$i] ?? 'rss');
                if (!in_array($type, ['rss', 'scraper', 'wp_rest'], true)) {
                    $type = 'rss';
                }
                $feedsMap[] = [
                    'url' => $u,
                    'type' => $type,
                    'cat' => trim((string) ($feedCats[$i] ?? '')),
                    'breaking_publish' => isset($feedBps[$i]) && $feedBps[$i] === '1' ? '1' : '0',
                ];
            }
            AutoVestiConfig::save([
                'api_key' => trim((string) ($_POST['api_key'] ?? '')),
                'openai_api_key' => trim((string) ($_POST['openai_api_key'] ?? '')),
                'ai_provider' => trim((string) ($_POST['ai_provider'] ?? 'claude')),
                'claude_model' => trim((string) ($_POST['claude_model'] ?? '')),
                'openai_model' => trim((string) ($_POST['openai_model'] ?? '')),
                'feeds_map' => $feedsMap,
                'lang' => trim((string) ($_POST['lang'] ?? 'bosanski')),
                'status' => trim((string) ($_POST['status'] ?? 'draft')),
                'max_fetch_per_run' => max(1, min(50, (int) ($_POST['max_fetch_per_run'] ?? 20))),
                'interval_minutes' => max(1, (int) ($_POST['interval_minutes'] ?? 180)),
                'from_date' => trim((string) ($_POST['from_date'] ?? '')),
                'article_min_words' => max(200, min(2000, (int) ($_POST['article_min_words'] ?? 800))),
                'article_max_words' => max(400, min(3000, (int) ($_POST['article_max_words'] ?? 1500))),
                'use_image' => isset($_POST['use_image']),
                'use_faq' => isset($_POST['use_faq']),
                'use_internal_links' => isset($_POST['use_internal_links']),
                'use_youtube' => isset($_POST['use_youtube']),
                'use_dup_check' => isset($_POST['use_dup_check']),
                'use_full_article' => isset($_POST['use_full_article']),
                'show_source_footer' => isset($_POST['show_source_footer']),
                'fact_protection_enabled' => isset($_POST['fact_protection_enabled']),
                'fact_protection_enforce' => isset($_POST['fact_protection_enforce']),
                'fact_protection_block_on_new_person' => isset($_POST['fact_protection_block_on_new_person']),
                'post_process_editor_enabled' => isset($_POST['post_process_editor_enabled']),
                'grammar_polish_enabled' => isset($_POST['grammar_polish_enabled']),
                'seo_layer_enabled' => isset($_POST['seo_layer_enabled']),
                'default_author_id' => trim((string) ($_POST['default_author_id'] ?? '')),
                'default_city' => 'NOVI_PAZAR',
            ]);
            flash('success', 'Auto Vesti postavke sačuvane.');
            redirect('/admin/auto-vesti?tab=settings');
        }
        if (isset($_POST['avc_save_telegram'])) {
            AutoVestiConfig::saveTelegram([
                'telegram_bot_token' => trim((string) ($_POST['telegram_bot_token'] ?? '')),
                'telegram_notify' => isset($_POST['telegram_notify']),
                'telegram_manual_publish' => isset($_POST['telegram_manual_publish']),
                'telegram_manual_use_ai' => isset($_POST['telegram_manual_use_ai']),
                'telegram_link_scrape' => isset($_POST['telegram_link_scrape']),
                'telegram_manual_cat' => trim((string) ($_POST['telegram_manual_cat'] ?? '')),
            ]);
            $msg = 'Telegram postavke sačuvane.';
            if (AutoVestiConfig::hasConfiguredTelegramToken()) {
                $whErr = AutoVestiTelegram::setWebhook();
                if ($whErr) {
                    flash('warning', $msg . ' Webhook greška: ' . $whErr);
                } else {
                    flash('success', $msg . ' Webhook registrovan.');
                }
            } else {
                flash('success', $msg);
            }
            redirect('/admin/auto-vesti?tab=telegram');
        }
        if (isset($_POST['avc_tg_set_webhook'])) {
            $whErr = AutoVestiTelegram::setWebhook();
            flash($whErr ? 'error' : 'success', $whErr ?: 'Webhook registrovan.');
            redirect('/admin/auto-vesti?tab=telegram');
        }
        if (isset($_POST['avc_tg_test'])) {
            $err = AutoVestiTelegram::sendTest();
            flash($err ? 'error' : 'success', $err ?: 'Test poruka poslata.');
            redirect('/admin/auto-vesti?tab=telegram');
        }
        if (isset($_POST['avc_tg_new_link'])) {
            AutoVestiTelegram::refreshLinkCode();
            flash('success', 'Novi link za povezivanje generisan.');
            redirect('/admin/auto-vesti?tab=telegram');
        }
        if (isset($_POST['avc_tg_notify_queue'])) {
            $n = AutoVestiTelegram::notifyExistingQueue();
            flash('info', 'Poslato obavještenje za ' . $n . ' vesti u redu.');
            redirect('/admin/auto-vesti?tab=telegram');
        }
        if (isset($_POST['avc_tg_disconnect'])) {
            $chatId = trim((string) ($_POST['chat_id'] ?? ''));
            if ($chatId !== '') {
                AutoVestiConfig::removeTelegramChatId($chatId);
                flash('info', 'Chat ' . $chatId . ' uklonjen.');
            }
            redirect('/admin/auto-vesti?tab=telegram');
        }
    }
    $cfg = AutoVestiConfig::all();
    $queue = AutoVestiConfig::getQueue();
    $categories = $pdo->query('SELECT id, name, slug FROM categories ORDER BY name')->fetchAll();
    $authors = $pdo->query('SELECT id, name FROM authors ORDER BY name')->fetchAll();
    $importedCount = (int) $pdo->query("SELECT COUNT(*) FROM articles WHERE sourceUrl IS NOT NULL AND sourceUrl != ''")->fetchColumn();
    $breakingCount = (int) $pdo->query("SELECT COUNT(*) FROM articles WHERE sourceUrl IS NOT NULL AND isBreaking = 1")->fetchColumn();
    $tgConnectUrl = AutoVestiTelegram::getConnectUrl();
    $tgWebhookUrl = AutoVestiTelegram::webhookUrl();
    $tgChats = AutoVestiConfig::getTelegramChatIds();
    $tgWebhookInfo = AutoVestiConfig::hasConfiguredTelegramToken() ? AutoVestiTelegram::getWebhookInfo() : null;
    admin_view('auto-vesti', compact('user', 'cfg', 'queue', 'categories', 'authors', 'importedCount', 'breakingCount', 'tab', 'tgConnectUrl', 'tgWebhookUrl', 'tgChats', 'tgWebhookInfo') + ['title' => 'Auto Vesti', 'active' => 'auto-vesti']);
    exit;
}

if ($uri === '/admin/postavke') {
    if ($method === 'POST') {
        verify_csrf();
        if (isset($_POST['fb_verify'])) {
            if (!class_exists('FacebookPublisher')) {
                flash('error', 'Facebook modul nije instaliran (nedostaje FacebookPublisher.php).');
            } else {
                $check = FacebookPublisher::verifyConnection();
                flash($check['ok'] ? 'success' : 'error', $check['message']);
            }
            redirect('/admin/postavke');
        }
        if (isset($_POST['fb_share_latest'])) {
            if (!class_exists('FacebookPublisher')) {
                flash('error', 'Facebook modul nije instaliran (nedostaje FacebookPublisher.php).');
                redirect('/admin/postavke');
            }
            $latestId = (string) $pdo->query(
                "SELECT id FROM articles WHERE status = 'PUBLISHED' ORDER BY publishedAt DESC LIMIT 1"
            )->fetchColumn();
            if ($latestId === '') {
                flash('error', 'Nema objavljenih članaka za test.');
            } elseif (FacebookPublisher::shareArticle($latestId, true)) {
                flash('success', 'Posljednja vest je poslata na Facebook.');
            } else {
                flash('error', 'Nije poslato. Provjerite token, Page ID i da li je auto-share uključen.');
            }
            redirect('/admin/postavke');
        }
        $current = Settings::all();
        $token = trim((string) ($_POST['facebook_page_access_token'] ?? ''));
        if ($token === '') {
            $token = (string) ($current['facebook_page_access_token'] ?? '');
        }
        Settings::save([
            'analytics_provider' => trim((string) ($_POST['analytics_provider'] ?? '')),
            'analytics_id' => trim((string) ($_POST['analytics_id'] ?? '')),
            'ad_sidebar_html' => (string) ($_POST['ad_sidebar_html'] ?? ''),
            'ad_article_html' => (string) ($_POST['ad_article_html'] ?? ''),
            'ad_home_html' => (string) ($_POST['ad_home_html'] ?? ''),
            'newsletter_confirm' => isset($_POST['newsletter_confirm']),
            'site_tagline' => trim((string) ($_POST['site_tagline'] ?? '')),
            'og_default_image' => trim((string) ($_POST['og_default_image'] ?? '')),
            'facebook_page_url' => trim((string) ($_POST['facebook_page_url'] ?? '')),
            'facebook_auto_share' => isset($_POST['facebook_auto_share']),
            'facebook_page_id' => trim((string) ($_POST['facebook_page_id'] ?? '')),
            'facebook_page_access_token' => $token,
            'auto_feature_today' => isset($_POST['auto_feature_today']),
            'feature_rotate_hours' => max(0, min(24, (int) ($_POST['feature_rotate_hours'] ?? 3))),
            'restaurants_enabled' => isset($_POST['restaurants_enabled']),
        ]);
        flash('success', 'Postavke sačuvane.');
        redirect('/admin/postavke');
    }
    $settings = Settings::all();
    admin_view('settings', compact('user', 'settings') + ['title' => 'Postavke', 'active' => 'postavke']);
    exit;
}

if ($uri === '/admin/profil') {
    if ($method === 'POST') {
        verify_csrf();
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['new_password_confirm'] ?? '';
        $stmt = $pdo->prepare('SELECT passwordHash FROM users WHERE id = ?');
        $stmt->execute([$user['id']]);
        $hash = $stmt->fetchColumn();
        if (!$hash || !password_verify($current, $hash)) {
            flash('error', 'Trenutna lozinka nije tačna.');
            redirect('/admin/profil');
        }
        if (strlen($new) < 8 || $new !== $confirm) {
            flash('error', 'Nova lozinka mora imati min. 8 znakova i mora se poklapati.');
            redirect('/admin/profil');
        }
        $pdo->prepare('UPDATE users SET passwordHash = ?, updatedAt = CURRENT_TIMESTAMP WHERE id = ?')
            ->execute([password_hash($new, PASSWORD_DEFAULT), $user['id']]);
        flash('success', 'Lozinka promijenjena.');
        redirect('/admin/profil');
    }
    admin_view('profile', compact('user') + ['title' => 'Profil', 'active' => 'profil']);
    exit;
}

if ($uri === '/admin/prijave') {
    if ($method === 'POST') {
        verify_csrf();
        if (isset($_POST['mark_read'])) {
            $pdo->prepare('UPDATE reader_submissions SET `read`=1 WHERE id=?')->execute([$_POST['mark_read']]);
        } elseif (isset($_POST['delete'])) {
            $pdo->prepare('DELETE FROM reader_submissions WHERE id=?')->execute([$_POST['delete']]);
        }
        redirect('/admin/prijave');
    }
    $submissions = $pdo->query('SELECT * FROM reader_submissions ORDER BY createdAt DESC')->fetchAll();
    admin_view('submissions', compact('user', 'submissions') + ['title' => 'Prijave', 'active' => 'prijave']);
    exit;
}

if ($uri === '/admin/restorani/novi' || preg_match('#^/admin/restorani/([^/]+)$#', $uri, $restEditM)) {
    $restId = $uri === '/admin/restorani/novi' ? null : $restEditM[1];
    $restaurant = $restId ? RestaurantRepository::getById($restId) : null;
    if ($restId && !$restaurant) {
        not_found();
    }
    if ($method === 'POST') {
        verify_csrf();
        $data = RestaurantService::profileDataFromPost($_POST);
        if ($data['name'] === '') {
            flash('error', 'Naziv restorana je obavezan.');
            redirect($restId ? '/admin/restorani/' . $restId : '/admin/restorani/novi');
        }
        try {
            $savedId = RestaurantService::saveRestaurantAdmin($data, $restId);
            flash('success', $restId ? 'Restoran ažuriran.' : 'Restoran kreiran.');
            redirect('/admin/restorani/' . $savedId . '/meni');
        } catch (Throwable $e) {
            flash('error', 'Greška pri snimanju.');
            redirect($restId ? '/admin/restorani/' . $restId : '/admin/restorani/novi');
        }
    }
    $ownerEmail = '';
    if ($restaurant) {
        $stmt = $pdo->prepare('SELECT email, name FROM users WHERE id = ?');
        $stmt->execute([$restaurant['ownerId']]);
        $ownerRow = $stmt->fetch();
        $ownerEmail = $ownerRow['email'] ?? '';
        if (str_ends_with($ownerEmail, '@sandzak.local')) {
            $ownerEmail = '';
        }
    }
    admin_view('restaurant-form', compact('user', 'restaurant', 'ownerEmail') + [
        'title' => $restaurant ? 'Uredi restoran' : 'Novi restoran',
        'active' => 'restorani',
    ]);
    exit;
}

if (preg_match('#^/admin/restorani/([^/]+)/qr\.png$#', $uri, $restQrM)) {
    $restaurant = RestaurantRepository::getById($restQrM[1]);
    if (!$restaurant) {
        not_found();
    }
    RestaurantService::outputQrPng($restaurant);
}

if (preg_match('#^/admin/restorani/([^/]+)/qr$#', $uri, $restQrM)) {
    $restaurant = RestaurantRepository::getById($restQrM[1]);
    if (!$restaurant) {
        not_found();
    }
    admin_view('restaurant-qr', [
        'user' => $user,
        'restaurant' => $restaurant,
        'publicUrl' => RestaurantService::publicUrl($restaurant),
        'qrUrl' => RestaurantService::qrImageUrl($restaurant, 500),
        'shortUrl' => config('site_url') . '/r/' . $restaurant['qrCode'],
        'downloadUrl' => '/admin/restorani/' . $restaurant['id'] . '/qr.png',
    ] + ['title' => 'QR meni — ' . $restaurant['name'], 'active' => 'restorani']);
    exit;
}

if (preg_match('#^/admin/restorani/([^/]+)/meni/skeniraj$#', $uri, $restScanM) && $method === 'POST') {
    verify_csrf();
    $restaurant = RestaurantRepository::getById($restScanM[1]);
    if (!$restaurant) {
        not_found();
    }
    $file = get_uploaded_image_file();
    if (!$file || !is_valid_upload_image($file) || $file['size'] > config('upload_max_bytes')) {
        flash('error', 'Učitajte jasnu fotografiju menija (JPG/PNG, max 5 MB).');
        redirect('/admin/restorani/' . $restaurant['id'] . '/meni');
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'jpg');
    $tmp = config('upload_dir') . '/scan-' . time() . '-' . bin2hex(random_bytes(3)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $tmp)) {
        flash('error', 'Upload nije uspio.');
        redirect('/admin/restorani/' . $restaurant['id'] . '/meni');
    }
    $result = RestaurantMenuScan::extractFromImagePath($tmp);
    @unlink($tmp);
    if (is_string($result)) {
        flash('error', $result);
        redirect('/admin/restorani/' . $restaurant['id'] . '/meni');
    }
    $replace = isset($_POST['replace_menu']);
    $n = RestaurantService::importScannedMenu($restaurant['id'], $result, $replace);
    $note = trim((string) ($result['notes'] ?? ''));
    flash('success', 'Uvezeno ' . $n . ' stavki iz slike menija.' . ($note ? ' Napomena: ' . $note : ''));
    redirect('/admin/restorani/' . $restaurant['id'] . '/meni');
}

if (preg_match('#^/admin/restorani/([^/]+)/meni$#', $uri, $restMenuM)) {
    $restaurant = RestaurantRepository::getById($restMenuM[1]);
    if (!$restaurant) {
        not_found();
    }
    if ($method === 'POST') {
        verify_csrf();
        if (isset($_POST['add_category'])) {
            RestaurantService::saveCategory($restaurant['id'], (string) ($_POST['category_name'] ?? ''));
            flash('success', 'Kategorija dodana.');
        }
        if (isset($_POST['delete_category'])) {
            RestaurantService::deleteCategory($restaurant['id'], (string) ($_POST['category_id'] ?? ''));
            flash('success', 'Kategorija obrisana.');
        }
        redirect('/admin/restorani/' . $restaurant['id'] . '/meni');
    }
    $categories = RestaurantRepository::getFullMenu($restaurant['id']);
    admin_view('restaurant-menu', compact('user', 'restaurant', 'categories') + [
        'title' => 'Cjenovnik — ' . $restaurant['name'],
        'active' => 'restorani',
    ]);
    exit;
}

if (preg_match('#^/admin/restorani/([^/]+)/stavka(?:/([^/]+))?$#', $uri, $restItemM)) {
    $restaurant = RestaurantRepository::getById($restItemM[1]);
    if (!$restaurant) {
        not_found();
    }
    $itemId = $restItemM[2] ?? null;
    $item = null;
    if ($itemId) {
        foreach (RestaurantRepository::getMenuItems($restaurant['id']) as $row) {
            if ($row['id'] === $itemId) {
                $item = $row;
                break;
            }
        }
        if (!$item) {
            not_found();
        }
    }
    if ($method === 'POST') {
        verify_csrf();
        if (isset($_POST['delete']) && $itemId) {
            RestaurantService::deleteMenuItem($restaurant['id'], $itemId);
            flash('success', 'Stavka obrisana.');
            redirect('/admin/restorani/' . $restaurant['id'] . '/meni');
        }
        $tags = array_values(array_filter(array_map('trim', explode(',', (string) ($_POST['tags'] ?? '')))));
        $itemData = [
            'categoryId' => $_POST['categoryId'] ?? '',
            'name' => trim((string) ($_POST['name'] ?? '')),
            'description' => trim((string) ($_POST['description'] ?? '')),
            'price' => $_POST['price'] ?? '',
            'priceLabel' => trim((string) ($_POST['priceLabel'] ?? '')),
            'currency' => $_POST['currency'] ?? 'RSD',
            'image' => trim((string) ($_POST['image'] ?? '')) ?: null,
            'tags' => $tags,
            'translations' => MenuI18n::translationsFromPost($_POST),
            'isAvailable' => isset($_POST['isAvailable']) ? 1 : 0,
        ];
        if ($itemData['name'] === '' || $itemData['categoryId'] === '') {
            flash('error', 'Naziv i kategorija su obavezni.');
            redirect($uri);
        }
        RestaurantService::saveMenuItem($restaurant['id'], $itemData, $itemId);
        flash('success', 'Stavka sačuvana.');
        redirect('/admin/restorani/' . $restaurant['id'] . '/meni');
    }
    $categories = RestaurantRepository::getMenuCategories($restaurant['id']);
    admin_view('restaurant-item-form', compact('user', 'restaurant', 'item', 'categories') + [
        'title' => $item ? 'Uredi stavku' : 'Nova stavka',
        'active' => 'restorani',
    ]);
    exit;
}

if ($uri === '/admin/restorani') {
    if ($method === 'POST' && isset($_POST['restaurant_id'], $_POST['action'])) {
        verify_csrf();
        $action = (string) $_POST['action'];
        $id = (string) $_POST['restaurant_id'];
        if ($action === 'approve') {
            RestaurantService::setStatus($id, 'PUBLISHED');
            flash('success', 'Restoran odobren i objavljen.');
        } elseif ($action === 'reject') {
            RestaurantService::setStatus($id, 'REJECTED');
            flash('success', 'Restoran odbijen.');
        } elseif ($action === 'suspend') {
            RestaurantService::setStatus($id, 'SUSPENDED');
            flash('success', 'Restoran suspendovan.');
        }
        redirect('/admin/restorani');
    }
    $status = $_GET['status'] ?? null;
    $restaurants = RestaurantRepository::listForAdmin($status ?: null);
    $pendingCount = RestaurantRepository::countPending();
    admin_view('restaurants', compact('user', 'restaurants', 'status', 'pendingCount') + [
        'title' => 'Restorani',
        'active' => 'restorani',
    ]);
    exit;
}

if ($uri === '/admin/restorani-recenzije') {
    if ($method === 'POST' && isset($_POST['review_id'], $_POST['action'])) {
        verify_csrf();
        $action = (string) $_POST['action'];
        $id = (string) $_POST['review_id'];
        if ($action === 'approve') {
            RestaurantService::setReviewStatus($id, 'APPROVED');
            flash('success', 'Recenzija odobrena.');
        } elseif ($action === 'reject') {
            RestaurantService::setReviewStatus($id, 'REJECTED');
            flash('success', 'Recenzija odbijena.');
        }
        redirect('/admin/restorani-recenzije');
    }
    $reviews = RestaurantRepository::listPendingReviews();
    admin_view('restaurant-reviews', compact('user', 'reviews') + [
        'title' => 'Recenzije restorana',
        'active' => 'restorani-recenzije',
    ]);
    exit;
}

if ($uri === '/admin/upload' && $method === 'POST') {
    $file = get_uploaded_image_file();
    if (!$file) {
        json_response(['error' => 'Upload nije uspio.'], 400);
    }
    if (!is_valid_upload_image($file) || $file['size'] > config('upload_max_bytes')) {
        json_response(['error' => 'Dozvoljene su samo slike (JPG, PNG, WebP, GIF) do 5 MB.'], 400);
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'jpg');
    if ($ext === 'jpeg') {
        $ext = 'jpg';
    }
    $name = time() . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
    $dest = config('upload_dir') . '/' . $name;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        json_response(['error' => 'Snimanje nije uspjelo.'], 500);
    }
    ImageWatermark::apply($dest);
    ImageProcessor::process($dest);
    $url = '/uploads/' . $name;
    if (($_GET['editor'] ?? '') === 'jodit') {
        json_response([
            'success' => true,
            'time' => date('c'),
            'data' => [
                'files' => [$name],
                'path' => '/uploads/',
                'baseurl' => '',
                'isImages' => [true],
            ],
        ]);
    }
    json_response(['url' => $url]);
}

http_response_code(404);
echo 'Admin stranica nije pronađena.';
