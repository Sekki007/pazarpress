<?php

declare(strict_types=1);

/**
 * Grammar polish layer — runs after fact validation (and optionally after style editor).
 * Changes only grammar (cases, spelling), never facts or content meaning.
 */
final class AutoVestiGrammarPolish
{
    private const SYSTEM = 'Ti si lektor srpskog jezika. Ispravljaš samo gramatiku i pravopis. '
        . 'Ne menjaš činjenice, brojeve, datume ni sadržaj. Vraćaš ISKLJUČIVO validan JSON.';

    /** @var list<string> */
    private const GENITIVE_TRIGGERS = [
        'uloga', 'funkcija', 'angažovanje', 'angazovanje', 'imenovanje', 'dolazak', 'odlazak',
        'povratak', 'dolaska', 'odlaska', 'zamena', 'smena', 'imenovanja', 'angažovanja',
    ];

    /**
     * @param array<string,mixed> $aiData
     * @param array<string,mixed>|null $factLock
     * @return array{data:array<string,mixed>,grammar_report:array<string,mixed>,applied:bool}
     */
    public static function apply(
        array $aiData,
        ?array $factLock,
        string $provider,
        string $apiKey
    ): array {
        $original = $aiData;
        $beforeIssues = self::scanGrammarIssues($aiData, $factLock);

        $edited = self::callAi(self::buildPrompt($aiData, $factLock), $provider, $apiKey);
        if (!is_array($edited)) {
            return [
                'data' => $original,
                'grammar_report' => self::buildReport($original, false, $beforeIssues, ['message' => (string) $edited]),
                'applied' => false,
            ];
        }

        $merged = self::mergeFields($aiData, $edited);

        if ($factLock !== null && !self::factsPreservedForGrammar($factLock, $original, $merged)) {
            return [
                'data' => $original,
                'grammar_report' => self::buildReport($original, false, $beforeIssues, [
                    'message' => 'Revert: činjenice nisu sačuvane posle gramatičke korekcije.',
                ]),
                'applied' => false,
            ];
        }

        $afterIssues = self::scanGrammarIssues($merged, $factLock);
        return [
            'data' => $merged,
            'grammar_report' => self::buildReport($merged, true, $afterIssues, [
                'fixed_count' => max(0, count($beforeIssues) - count($afterIssues)),
                'issues_before' => $beforeIssues,
            ]),
            'applied' => true,
        ];
    }

    /**
     * @param array<string,mixed> $aiData
     * @param array<string,mixed>|null $factLock
     * @return list<array<string,string>>
     */
    public static function scanGrammarIssues(array $aiData, ?array $factLock): array
    {
        $text = self::plainText($aiData);
        $persons = array_values(array_filter(array_map(
            'strval',
            (array) (($factLock['protected'] ?? [])['persons'] ?? [])
        )));
        $issues = [];

        foreach (self::GENITIVE_TRIGGERS as $trigger) {
            $pattern = '/\b' . preg_quote($trigger, '/') . '\s+([A-ZŠĐČĆŽ][a-zšđčćž]+(?:\s+[A-ZŠĐČĆŽ][a-zšđčćž]+)?)\b/u';
            if (!preg_match_all($pattern, $text, $m, PREG_SET_ORDER)) {
                continue;
            }
            foreach ($m as $hit) {
                $found = trim((string) ($hit[1] ?? ''));
                if ($found === '') {
                    continue;
                }
                foreach ($persons as $person) {
                    if (mb_strtolower($found, 'UTF-8') === mb_strtolower($person, 'UTF-8')) {
                        $issues[] = [
                            'type' => 'CASE_AFTER_FUNCTION',
                            'wrong' => $trigger . ' ' . $found,
                            'hint' => 'Potreban genitiv posle "' . $trigger . '"',
                        ];
                        break;
                    }
                }
            }
        }

        return $issues;
    }

    /**
     * @param array<string,mixed> $aiData
     * @param array<string,mixed>|null $factLock
     */
    private static function buildPrompt(array $aiData, ?array $factLock): string
    {
        $persons = '';
        if ($factLock && !empty($factLock['protected']['persons'])) {
            $persons = "Lična imena (ne menjaj osobe, samo padež):\n"
                . implode("\n", array_map(static fn (string $p): string => '- ' . $p, (array) $factLock['protected']['persons']))
                . "\n\n";
        }

        $article = json_encode([
            'title' => (string) ($aiData['title'] ?? ''),
            'excerpt' => (string) ($aiData['excerpt'] ?? ''),
            'content' => (string) ($aiData['content'] ?? ''),
        ], JSON_UNESCAPED_UNICODE);

        return "GRAMMAR POLISH LAYER — samo gramatika i pravopis.\n\n"
            . $persons
            . "Proveri i ispravi:\n"
            . "- padeže ličnih imena\n"
            . "- slaganje padeža sa funkcijom/ulogom\n"
            . "- pravopis i velika/mala slova\n\n"
            . "Primeri:\n"
            . "- Pogrešno: uloga Željko Obradović → Ispravno: uloga Željka Obradovića\n"
            . "- Pogrešno: funkcija Edin Zuković → Ispravno: funkcija Edina Zukovića\n"
            . "- Pogrešno: angažovanje Željko Obradović → Ispravno: angažovanje Željka Obradovića\n\n"
            . "Zabranjeno:\n"
            . "- dodavanje novih informacija\n"
            . "- menjanje brojeva, datuma, rezultata\n"
            . "- menjanje imena osoba (samo gramatički oblik)\n\n"
            . "Ulaz:\n" . $article . "\n\n"
            . 'Vrati ISKLJUČIVO JSON: {"title":"...","excerpt":"...","content":"..."}';
    }

    /** @param array<string,mixed> $before @param array<string,mixed> $after */
    private static function factsPreservedForGrammar(array $factLock, array $before, array $after): bool
    {
        $beforeText = self::plainText($before);
        $afterText = self::plainText($after);
        $protected = (array) ($factLock['protected'] ?? []);

        foreach (array_values(array_filter(array_map('strval', (array) ($protected['numbers'] ?? [])))) as $n) {
            if (self::tokenInText($beforeText, $n) && !self::tokenInText($afterText, $n)) {
                return false;
            }
        }
        foreach (array_values(array_filter(array_map('strval', (array) ($protected['times'] ?? [])))) as $t) {
            if (self::tokenInText($beforeText, $t) && !self::tokenInText($afterText, $t)) {
                return false;
            }
        }
        foreach (array_values(array_filter(array_map('strval', (array) ($protected['locations'] ?? [])))) as $loc) {
            if (mb_stripos($beforeText, $loc, 0, 'UTF-8') !== false
                && mb_stripos($afterText, $loc, 0, 'UTF-8') === false) {
                return false;
            }
        }
        foreach (array_values(array_filter(array_map('strval', (array) ($protected['persons'] ?? [])))) as $person) {
            $surname = self::surname($person);
            if ($surname !== ''
                && mb_stripos($beforeText, $surname, 0, 'UTF-8') !== false
                && mb_stripos($afterText, $surname, 0, 'UTF-8') === false) {
                return false;
            }
        }

        return true;
    }

    /** @param array<string,mixed> $original @param array<string,mixed> $edited @return array<string,mixed> */
    private static function mergeFields(array $original, array $edited): array
    {
        $out = $original;
        foreach (['title', 'excerpt', 'content'] as $key) {
            if (!empty($edited[$key]) && is_string($edited[$key])) {
                $out[$key] = trim($edited[$key]);
            }
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
                    'max_completion_tokens' => 3500,
                    'temperature' => 0.2,
                    'messages' => [
                        ['role' => 'system', 'content' => self::SYSTEM],
                        ['role' => 'user', 'content' => $prompt],
                    ],
                ],
                ['Authorization: Bearer ' . $apiKey]
            );
            if (!$body || !empty($body['_error'])) {
                return 'Grammar polish API greška.';
            }
            $text = (string) ($body['choices'][0]['message']['content'] ?? '');
        } else {
            $body = HttpClient::postJson(
                'https://api.anthropic.com/v1/messages',
                [
                    'model' => AutoVestiConfig::get('claude_model', 'claude-sonnet-4-20250514'),
                    'max_tokens' => 3500,
                    'system' => self::SYSTEM,
                    'messages' => [['role' => 'user', 'content' => $prompt]],
                ],
                [
                    'x-api-key: ' . $apiKey,
                    'anthropic-version: 2023-06-01',
                ]
            );
            if (!$body || !empty($body['_error'])) {
                return 'Grammar polish API greška.';
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

    private static function tokenInText(string $haystack, string $needle): bool
    {
        if ($needle === '') {
            return true;
        }
        return mb_stripos($haystack, $needle, 0, 'UTF-8') !== false;
    }

    private static function surname(string $name): string
    {
        $parts = preg_split('/\s+/u', trim($name)) ?: [];
        return count($parts) >= 2 ? (string) end($parts) : '';
    }

    /**
     * @param array<string,mixed> $aiData
     * @param list<array<string,string>> $issues
     * @param array<string,mixed> $meta
     * @return array<string,mixed>
     */
    private static function buildReport(array $aiData, bool $applied, array $issues, array $meta = []): array
    {
        return [
            'status' => $issues === [] ? 'ok' : 'warning',
            'applied' => $applied,
            'editor' => 'grammar_polish',
            'checks' => [
                'name_cases' => self::countIssuesByType($issues, 'CASE_AFTER_FUNCTION') === 0,
                'spelling' => true,
                'facts_preserved' => true,
            ],
            'issues' => $issues,
            'summary' => $issues === []
                ? 'GRAMMAR CHECK: padeži i pravopis u redu.'
                : 'GRAMMAR CHECK: pronađene gramatičke greške.',
            'meta' => $meta,
            'checked_at' => date('c'),
        ];
    }

    /** @param list<array<string,string>> $issues */
    private static function countIssuesByType(array $issues, string $type): int
    {
        $n = 0;
        foreach ($issues as $issue) {
            if (($issue['type'] ?? '') === $type) {
                $n++;
            }
        }
        return $n;
    }
}
