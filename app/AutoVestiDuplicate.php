<?php

declare(strict_types=1);

final class AutoVestiDuplicate
{
    private const RECENT_HOURS = 72;
    private const TITLE_MATCH_PCT = 75;
    private const KEYWORD_MIN_SHARED = 4;

    /** Brza provjera prije AI obrade (feed stavka). Vraća razlog ili null. */
    public static function checkItem(array $item): ?string
    {
        $link = self::normalizeUrl((string) ($item['link'] ?? ''));
        if ($link !== '' && self::sourceUrlExists($link)) {
            return 'isti izvor (URL)';
        }

        $title = (string) ($item['title'] ?? '');
        if ($title !== '') {
            $hit = self::matchAgainstRecent($title);
            if ($hit !== null) {
                return $hit['reason'];
            }
        }

        return null;
    }

    /** Provjera nakon AI prerade, prije snimanja u bazu. */
    public static function checkRewritten(string $title, string $sourceUrl = ''): ?string
    {
        $link = self::normalizeUrl($sourceUrl);
        if ($link !== '' && self::sourceUrlExists($link)) {
            return 'isti izvor (URL)';
        }

        $hit = self::matchAgainstRecent($title);
        return $hit['reason'] ?? null;
    }

    /**
     * @return array{is_dup:bool,score:int,matched:string,method:string}
     */
    public static function check(string $newTitle, string $apiKey, string $provider): array
    {
        $hit = self::matchAgainstRecent($newTitle);
        if ($hit !== null) {
            return [
                'is_dup' => true,
                'score' => $hit['score'],
                'matched' => $hit['matched'],
                'method' => $hit['method'],
            ];
        }

        if ($apiKey !== '') {
            $titles = self::recentTitles();
            if ($titles) {
                $list = implode("\n", array_slice($titles, 0, 30));
                if (AutoVestiAi::askDuplicate($newTitle, $list, $provider, $apiKey)) {
                    return ['is_dup' => true, 'score' => 50, 'matched' => '', 'method' => $provider];
                }
            }
        }

        return ['is_dup' => false, 'score' => 0, 'matched' => '', 'method' => 'ok'];
    }

    /** @return array{reason:string,score:int,matched:string,method:string}|null */
    private static function matchAgainstRecent(string $title): ?array
    {
        $titles = self::recentTitles();
        if (!$titles) {
            return null;
        }

        $newN = self::normalizeTitle($title);
        $newWords = self::titleWords($newN);
        $bestScore = 0;
        $bestTitle = '';

        foreach ($titles as $exTitle) {
            $exN = self::normalizeTitle((string) $exTitle);
            $exWords = self::titleWords($exN);

            similar_text($newN, $exN, $pct);
            $score = (int) $pct;

            $shared = count(array_intersect($newWords, $exWords));
            $union = count(array_unique(array_merge($newWords, $exWords)));
            $jaccard = $union > 0 ? (int) round(($shared / $union) * 100) : 0;
            $score = max($score, $jaccard);

            // Samo sadržajne riječi — lokacije/desk ne smiju same dići prag
            if ($shared >= self::KEYWORD_MIN_SHARED && $jaccard >= 35) {
                $score = max($score, 75);
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestTitle = (string) $exTitle;
            }

            if ($score >= self::TITLE_MATCH_PCT) {
                return [
                    'reason' => 'sličan naslov (' . $score . '%): "' . $bestTitle . '"',
                    'score' => $score,
                    'matched' => $bestTitle,
                    'method' => 'title+jaccard',
                ];
            }
        }

        return null;
    }

    /** @return list<string> */
    private static function recentTitles(): array
    {
        $pdo = Database::connection();
        $since = date('Y-m-d H:i:s', time() - self::RECENT_HOURS * 3600);
        $stmt = $pdo->prepare(
            "SELECT title FROM articles WHERE publishedAt >= ? OR createdAt >= ?
             ORDER BY COALESCE(publishedAt, createdAt) DESC LIMIT 120"
        );
        $stmt->execute([$since, $since]);
        $rows = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

        return array_values(array_filter(array_map('strval', $rows)));
    }

    private static function sourceUrlExists(string $normalizedUrl): bool
    {
        if ($normalizedUrl === '' || !self::hasSourceUrlColumn()) {
            return false;
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM articles WHERE sourceUrl = ?');
        $stmt->execute([$normalizedUrl]);
        if ((int) $stmt->fetchColumn() > 0) {
            return true;
        }

        $stmt = $pdo->query("SELECT sourceUrl FROM articles WHERE sourceUrl IS NOT NULL AND sourceUrl != '' ORDER BY createdAt DESC LIMIT 200");
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $existing) {
            if (self::normalizeUrl((string) $existing) === $normalizedUrl) {
                return true;
            }
        }

        return false;
    }

    private static function hasSourceUrlColumn(): bool
    {
        static $has = null;
        if ($has !== null) {
            return $has;
        }

        try {
            $pdo = Database::connection();
            if (is_mysql()) {
                $db = (string) (config('db')['mysql']['database'] ?? '');
                $stmt = $pdo->prepare(
                    'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?'
                );
                $stmt->execute([$db, 'articles', 'sourceUrl']);
                $has = (int) $stmt->fetchColumn() > 0;
            } else {
                $cols = array_column($pdo->query('PRAGMA table_info(articles)')->fetchAll(), 'name');
                $has = in_array('sourceUrl', $cols, true);
            }
        } catch (Throwable) {
            $has = false;
        }

        return $has;
    }

    public static function normalizeUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }

        $parts = parse_url($url);
        if (!$parts || empty($parts['host'])) {
            return $url;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? 'https'));
        $host = strtolower((string) $parts['host']);
        $path = rtrim((string) ($parts['path'] ?? '/'), '/');
        if ($path === '') {
            $path = '/';
        }

        $query = [];
        if (!empty($parts['query'])) {
            parse_str((string) $parts['query'], $query);
            foreach (array_keys($query) as $k) {
                $lk = strtolower((string) $k);
                if (str_starts_with($lk, 'utm_') || in_array($lk, ['fbclid', 'gclid', 'igshid', 'mc_cid', 'mc_eid'], true)) {
                    unset($query[$k]);
                }
            }
        }

        $q = http_build_query($query);
        return $scheme . '://' . $host . $path . ($q !== '' ? '?' . $q : '');
    }

    private static function normalizeTitle(string $t): string
    {
        $map = ['š' => 's', 'đ' => 'd', 'č' => 'c', 'ć' => 'c', 'ž' => 'z'];
        $t = strtr(mb_strtolower($t, 'UTF-8'), $map);

        foreach (self::locationPhrases() as $phrase) {
            $t = str_replace($phrase, ' ', $t);
        }

        $words = preg_split('/\s+/', preg_replace('/[^\w\s]/u', '', $t) ?? '') ?: [];
        $filtered = array_filter($words, static fn ($w) => mb_strlen($w) > 2 && !in_array($w, self::stopWords(), true));

        return implode(' ', array_values($filtered));
    }

    /** @return list<string> */
    private static function locationPhrases(): array
    {
        return [
            'novi pazar', 'novi sad', 'nova varos', 'bosna i hercegovina', 'crna gora',
            'sandzak', 'beograd', 'sjenica', 'tutin', 'prijepolje', 'priboj', 'raska',
            'srbija', 'hrvatska', 'region', 'radio televizija novi pazar', 'radio televizija',
        ];
    }

    /** @return list<string> */
    private static function stopWords(): array
    {
        return [
            'u', 'i', 'je', 'na', 'se', 'su', 'za', 'da', 'od', 'do', 'iz', 'sa', 'po', 'o', 'a', 'ne',
            'ali', 'ili', 'te', 'ni', 'niti', 'kao', 'jos', 'vec', 'tek', 'li', 'bi', 'ce', 'cu',
            'ovo', 'ova', 'ovaj', 'taj', 'ta', 'to', 'koji', 'koja', 'koje', 'ovde', 'tamo', 'godina', 'godine',
            'the', 'in', 'of', 'to', 'is', 'and', 'for', 'with', 'that', 'this', 'from',
            'video', 'foto', 'galeri', 'galerija',
            // Lokacije i desk prefiksi — ne smiju sami dići skor duplikata
            'novi', 'pazar', 'novog', 'novom', 'novim', 'pazara', 'pazaru', 'pazarem',
            'sandzak', 'sandzaka', 'sandzaku', 'sandzakom',
            'sjenica', 'sjenici', 'sjenice', 'sjenicom',
            'tutin', 'tutina', 'tutinu', 'tutinom',
            'prijepolje', 'prijepolju', 'prijepolja',
            'priboj', 'priboju', 'priboja',
            'raska', 'raske', 'raski', 'raskom',
            'beograd', 'beogradu', 'beograda',
            'srbija', 'srbiji', 'srbije', 'srbijom',
            'grad', 'gradu', 'grada', 'gradom',
            'opstina', 'opstini', 'opstine', 'opstinom',
            'region', 'regionu',
            // Medijski sufiksi u naslovu
            'radio', 'televizija', 'rtv', 'rtnp',
        ];
    }

    /** @return list<string> */
    private static function titleWords(string $normalizedTitle): array
    {
        $words = preg_split('/\s+/', trim($normalizedTitle)) ?: [];
        return array_values(array_filter($words, static fn ($w) => $w !== ''));
    }
}
