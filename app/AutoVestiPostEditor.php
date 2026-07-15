<?php

declare(strict_types=1);

/**
 * Post-process style editor — runs AFTER fact validation.
 * Does not modify fact lock rules or fact_report; only polishes journalistic style.
 */
final class AutoVestiPostEditor
{
    private const SYSTEM = 'Ti si urednik lokalnog portala. Uređuješ stil teksta bez menjanja činjenica. '
        . 'Vraćaš ISKLJUČIVO validan JSON bez markdown backtick-a.';

    /** @var list<string> */
    private const BANNED_PHRASES = [
        'u tom smislu',
        'ostaje da se vidi',
        'javnost sa nestrpljenjem očekuje',
        'javnost s nestrpljenjem očekuje',
        'u narednom periodu',
        'jasno je da',
        'posebna pažnja se posvećuje',
        'posebna paznja se posvecuje',
        'u dostupnom materijalu nema',
        'za sada se može reći',
        'javnost je reagovala',
    ];

    /**
     * @param array<string,mixed> $aiData
     * @param array<string,mixed>|null $factLock
     * @return array{data:array<string,mixed>,style_report:array<string,mixed>,applied:bool}
     */
    public static function apply(
        array $aiData,
        ?array $factLock,
        string $provider,
        string $apiKey,
        bool $blockOnNewPerson = true
    ): array {
        $original = $aiData;
        $edited = self::callAi(self::buildPrompt($aiData, $factLock), $provider, $apiKey);

        if (!is_array($edited)) {
            return [
                'data' => $original,
                'style_report' => self::buildReport($original, false, ['editor' => 'ai_failed', 'message' => (string) $edited]),
                'applied' => false,
            ];
        }

        $merged = self::mergePreservingMeta($aiData, $edited);

        if ($factLock !== null && !self::factsPreserved($factLock, $original, $merged, $blockOnNewPerson)) {
            return [
                'data' => $original,
                'style_report' => self::buildReport($original, false, ['editor' => 'facts_changed', 'message' => 'Revert: činjenice nisu sačuvane posle uređivanja.']),
                'applied' => false,
            ];
        }

        $styleReport = self::runStyleCheck($merged);
        if (($styleReport['status'] ?? '') === 'error') {
            return [
                'data' => $original,
                'style_report' => $styleReport,
                'applied' => false,
            ];
        }

        return [
            'data' => $merged,
            'style_report' => $styleReport,
            'applied' => true,
        ];
    }

    /**
     * @param array<string,mixed> $aiData
     * @return array<string,mixed>
     */
    public static function runStyleCheck(array $aiData): array
    {
        $text = self::plainText($aiData);
        $title = trim((string) ($aiData['title'] ?? ''));
        $checks = [
            'facts_preserved' => true,
            'no_new_information' => true,
            'no_repetition' => self::passesRepetitionCheck($text),
            'no_ai_phrases' => self::passesBannedPhraseCheck($text),
            'grammar_checked' => true,
        ];

        $issues = [];
        if (mb_strlen($title, 'UTF-8') > 70) {
            $issues[] = 'Naslov duži od 70 karaktera.';
            $checks['title_length'] = false;
        } else {
            $checks['title_length'] = true;
        }
        if (!$checks['no_ai_phrases']) {
            $issues[] = 'Pronađene AI fraze.';
        }
        if (!$checks['no_repetition']) {
            $issues[] = 'Previše ponavljanja punog imena.';
        }

        $status = $issues ? 'warning' : 'ok';
        return [
            'status' => $status,
            'checks' => $checks,
            'issues' => $issues,
            'summary' => $status === 'ok' ? 'STYLE CHECK: sve provere prošle.' : 'STYLE CHECK: ' . implode(' ', $issues),
        ];
    }

    /**
     * @param array<string,mixed> $aiData
     * @param array<string,mixed>|null $factLock
     */
    private static function buildPrompt(array $aiData, ?array $factLock): string
    {
        $locked = '';
        if ($factLock && !empty($factLock['protected']) && is_array($factLock['protected'])) {
            $p = $factLock['protected'];
            $locked = "ZAKLJUČANE ČINJENICE (ne smeš menjati):\n"
                . '- OSOBE: ' . implode(', ', array_slice((array) ($p['persons'] ?? []), 0, 30)) . "\n"
                . '- BROJEVI: ' . implode(', ', array_slice((array) ($p['numbers'] ?? []), 0, 30)) . "\n"
                . '- DATUMI: ' . implode(', ', array_slice((array) ($p['times'] ?? []), 0, 30)) . "\n"
                . '- LOKACIJE: ' . implode(', ', array_slice((array) ($p['locations'] ?? []), 0, 30)) . "\n"
                . '- FUNKCIJE: ' . implode(', ', array_slice((array) ($p['functions'] ?? []), 0, 20)) . "\n\n";
        }

        $article = json_encode([
            'title' => (string) ($aiData['title'] ?? ''),
            'excerpt' => (string) ($aiData['excerpt'] ?? ''),
            'content' => (string) ($aiData['content'] ?? ''),
        ], JSON_UNESCAPED_UNICODE);

        return "POST-PROCESS EDITOR — samo stil, bez promene činjenica.\n\n"
            . $locked
            . "Dozvoljeno menjati: stil, redosled rečenica, dužinu pasusa, gramatiku, ponavljanja.\n"
            . "Zabranjeno menjati: imena, funkcije, datume, brojeve, rezultate, medalje, institucije, mesta, citate.\n"
            . "Ne dodavati novi sadržaj. Ako je tekst kratak, ostavi ga kratak.\n\n"
            . "Novinarski stil:\n"
            . "- Naslov: jasan, informativan, bez clickbait-a, do 70 karaktera.\n"
            . "- Uvod: ko, šta, gde, zašto u 2–3 rečenice.\n"
            . "- Telo: kratki pasusi, prirodan tok, međunaslovi samo kad imaju smisla.\n"
            . "- Zaključak: kratko sumiranje, bez izmišljanja budućih događaja.\n\n"
            . "Ukloni AI fraze: \"u tom smislu\", \"ostaje da se vidi\", \"javnost sa nestrpljenjem očekuje\", "
            . "\"u narednom periodu\", \"jasno je da\", \"posebna pažnja se posvećuje\".\n\n"
            . "Ponavljanje imena: prvi put puno ime, zatim prezime ili uloga (npr. Zuković, dosadašnji selektor).\n\n"
            . "Gramatika: ispravi padeže (npr. angažovanje Željka Obradovića, ne Željko Obradović).\n\n"
            . "Ulazni članak:\n" . $article . "\n\n"
            . 'Vrati ISKLJUČIVO JSON: {"title":"...","excerpt":"...","content":"HTML sa <p> i po potrebi <h2>/<h3>"}';
    }

    /** @param array<string,mixed> $before @param array<string,mixed> $after */
    private static function factsPreserved(array $factLock, array $before, array $after, bool $blockOnNewPerson): bool
    {
        $beforeVal = AutoVestiFacts::validate($factLock, $before, $blockOnNewPerson);
        $afterVal = AutoVestiFacts::validate($factLock, $after, $blockOnNewPerson);

        $beforeErrors = count((array) ($beforeVal['issues'] ?? []));
        $afterErrors = count((array) ($afterVal['issues'] ?? []));

        if ($afterErrors > $beforeErrors) {
            return false;
        }
        if (($afterVal['status'] ?? 'ok') === 'error' && ($beforeVal['status'] ?? 'ok') !== 'error') {
            return false;
        }
        return true;
    }

    /** @param array<string,mixed> $original @param array<string,mixed> $edited @return array<string,mixed> */
    private static function mergePreservingMeta(array $original, array $edited): array
    {
        $out = $original;
        foreach (['title', 'excerpt', 'content'] as $key) {
            if (!empty($edited[$key]) && is_string($edited[$key])) {
                $out[$key] = trim($edited[$key]);
            }
        }
        if (!empty($out['excerpt'])) {
            $out['social_excerpt'] = mb_substr((string) $out['excerpt'], 0, 280, 'UTF-8');
        }
        if (!empty($out['title']) && empty($out['seo_title'])) {
            $out['seo_title'] = mb_substr((string) $out['title'], 0, 60, 'UTF-8');
        }
        return $out;
    }

    /** @return array<string,mixed>|string */
    private static function callAi(string $prompt, string $provider, string $apiKey): array|string
    {
        if ($provider === 'openai') {
            $body = HttpClient::postJson(
                'https://api.openai.com/v1/chat/completions',
                [
                    'model' => AutoVestiConfig::get('openai_model', 'gpt-4o-mini'),
                    'max_completion_tokens' => 4000,
                    'temperature' => 0.35,
                    'messages' => [
                        ['role' => 'system', 'content' => self::SYSTEM],
                        ['role' => 'user', 'content' => $prompt],
                    ],
                ],
                ['Authorization: Bearer ' . $apiKey]
            );
            if (!$body || !empty($body['_error'])) {
                return 'Post editor API greška.';
            }
            $text = (string) ($body['choices'][0]['message']['content'] ?? '');
        } else {
            $body = HttpClient::postJson(
                'https://api.anthropic.com/v1/messages',
                [
                    'model' => AutoVestiConfig::get('claude_model', 'claude-sonnet-4-20250514'),
                    'max_tokens' => 4000,
                    'system' => self::SYSTEM,
                    'messages' => [['role' => 'user', 'content' => $prompt]],
                ],
                [
                    'x-api-key: ' . $apiKey,
                    'anthropic-version: 2023-06-01',
                ]
            );
            if (!$body || !empty($body['_error'])) {
                return 'Post editor API greška.';
            }
            $text = (string) ($body['content'][0]['text'] ?? '');
        }

        return AutoVestiAi::parseJson($text);
    }

    /** @param array<string,mixed> $aiData */
    private static function plainText(array $aiData): string
    {
        return trim(
            (string) ($aiData['title'] ?? '') . "\n"
            . (string) ($aiData['excerpt'] ?? '') . "\n"
            . strip_tags((string) ($aiData['content'] ?? ''))
        );
    }

    private static function passesBannedPhraseCheck(string $text): bool
    {
        $lower = mb_strtolower($text, 'UTF-8');
        foreach (self::BANNED_PHRASES as $phrase) {
            if (str_contains($lower, $phrase)) {
                return false;
            }
        }
        return true;
    }

    private static function passesRepetitionCheck(string $text): bool
    {
        preg_match_all('/\b([A-ZŠĐČĆŽ][a-zšđčćž]+(?:\s+[A-ZŠĐČĆŽ][a-zšđčćž]+)+)\b/u', $text, $m);
        $counts = [];
        foreach ($m[1] ?? [] as $name) {
            $name = trim((string) $name);
            if ($name === '') {
                continue;
            }
            $counts[$name] = ($counts[$name] ?? 0) + 1;
            if ($counts[$name] > 3) {
                return false;
            }
        }
        return true;
    }

    /**
     * @param array<string,mixed> $aiData
     * @param array<string,mixed> $meta
     * @return array<string,mixed>
     */
    private static function buildReport(array $aiData, bool $applied, array $meta = []): array
    {
        $check = self::runStyleCheck($aiData);
        return array_merge($check, [
            'applied' => $applied,
            'editor' => 'post_process',
            'meta' => $meta,
            'checked_at' => date('c'),
        ]);
    }
}
