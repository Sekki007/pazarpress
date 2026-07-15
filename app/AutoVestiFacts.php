<?php

declare(strict_types=1);

final class AutoVestiFacts
{
    private const AUDIT_FILE = __DIR__ . '/../storage/auto-vesti-ai-audit.jsonl';

    /** @return array<string,mixed> */
    public static function extractFactLock(array $item): array
    {
        $source = self::sourceText($item);
        $persons = self::extractPersons($source);
        $organizations = self::extractOrganizations($source);
        $locations = self::extractLocations($source);
        $times = self::extractTimes($source);
        $numbers = self::extractNumbers($source);
        $functions = self::extractFunctions($source);
        $events = self::extractEvents($source);

        return [
            'source_of_truth' => $source,
            'protected' => [
                'persons' => $persons,
                'persons_exact' => $persons,
                'organizations' => $organizations,
                'locations' => $locations,
                'times' => $times,
                'numbers' => $numbers,
                'functions' => $functions,
                'events' => $events,
            ],
            'entity_context' => self::extractEntityContext($source),
            'identity_analysis' => self::analyzeIdentityRisk($source),
            'must_include' => self::extractMustInclude($source),
            'source_fact_map' => self::buildSourceFactMap(
                $persons,
                $numbers,
                $times,
                $locations,
                $events
            ),
            'created_at' => date('c'),
        ];
    }

    /** @param array<string,mixed> $factLock @param array<string,string> $corrections @return array<string,mixed> */
    public static function applyCorrections(array $factLock, array $corrections): array
    {
        if (empty($factLock['source_of_truth']) || !$corrections) {
            return $factLock;
        }
        $text = (string) $factLock['source_of_truth'];
        foreach ($corrections as $wrong => $right) {
            if ($wrong === '' || $right === '') {
                continue;
            }
            $text = str_ireplace($wrong, $right, $text);
        }

        return self::extractFactLock(['description' => $text]);
    }

    /**
     * @param array<string,mixed> $factLock
     * @param array<string,mixed> $aiData
     * @return array<string,mixed>
     */
    public static function validate(array $factLock, array $aiData, bool $blockOnNewPerson = true): array
    {
        $aiText = trim(
            (string) ($aiData['title'] ?? '') . "\n" .
            (string) ($aiData['excerpt'] ?? '') . "\n" .
            strip_tags((string) ($aiData['content'] ?? ''))
        );
        $protected = (array) ($factLock['protected'] ?? []);

        $issues = [];
        $warnings = [];
        $risk = 0;

        $exactValidation = self::validateExactPersonNames(
            array_values(array_filter(array_map('strval', (array) ($protected['persons_exact'] ?? $protected['persons'] ?? [])))),
            $aiText
        );
        if (!empty($exactValidation['issues'])) {
            foreach ($exactValidation['issues'] as $issue) {
                $issues[] = $issue;
                $risk += (int) ($issue['risk'] ?? 50);
            }
        }

        $mustIncludeValidation = self::validateMustInclude(
            (array) ($factLock['must_include'] ?? []),
            $aiText
        );
        if (!empty($mustIncludeValidation['issues'])) {
            foreach ($mustIncludeValidation['issues'] as $issue) {
                $issues[] = $issue;
                $risk += (int) ($issue['risk'] ?? 20);
            }
        }
        if (!empty($mustIncludeValidation['warnings'])) {
            foreach ($mustIncludeValidation['warnings'] as $warn) {
                $warnings[] = $warn;
                $risk += (int) ($warn['risk'] ?? 8);
            }
        }

        $sourcePersons = array_values(array_filter(array_map('strval', (array) ($protected['persons'] ?? []))));
        $aiPersons = self::extractPersons($aiText);
        $sourcePersonMapBySurname = self::mapPersonsBySurname($sourcePersons);

        foreach ($aiPersons as $p) {
            if (in_array($p, $sourcePersons, true)) {
                continue;
            }
            $surname = self::surname($p);
            if ($surname !== '' && isset($sourcePersonMapBySurname[$surname])) {
                if (self::isSoftPersonVariant($p, $sourcePersonMapBySurname[$surname])) {
                    $warnings[] = [
                        'type' => 'ERROR_CHANGED_ENTITY',
                        'original' => $sourcePersonMapBySurname[$surname],
                        'ai' => $p,
                        'status' => 'WARNING',
                        'risk' => 10,
                    ];
                    $risk += 10;
                } else {
                    $issues[] = [
                        'type' => 'ERROR_CHANGED_ENTITY',
                        'original' => $sourcePersonMapBySurname[$surname],
                        'ai' => $p,
                        'status' => 'ERROR',
                    ];
                    $risk += 45;
                }
            } else {
                if ($blockOnNewPerson) {
                    $issues[] = [
                        'type' => 'ERROR_NEW_PERSON',
                        'original' => '',
                        'ai' => $p,
                        'status' => 'ERROR',
                    ];
                    $risk += 35;
                } else {
                    $warnings[] = [
                        'type' => 'ERROR_NEW_PERSON',
                        'original' => '',
                        'ai' => $p,
                        'status' => 'WARNING',
                    ];
                    $risk += 20;
                }
            }
        }

        $contextValidation = self::validateEntityContext(
            (array) ($factLock['entity_context'] ?? []),
            $aiText
        );
        if (!empty($contextValidation['issues']) && is_array($contextValidation['issues'])) {
            foreach ($contextValidation['issues'] as $issue) {
                $issues[] = $issue;
                $risk += (int) ($issue['risk'] ?? 25);
            }
        }
        if (!empty($contextValidation['warnings']) && is_array($contextValidation['warnings'])) {
            foreach ($contextValidation['warnings'] as $warn) {
                $warnings[] = $warn;
                $risk += (int) ($warn['risk'] ?? 10);
            }
        }

        $sourceLocations = array_values(array_filter(array_map('strval', (array) ($protected['locations'] ?? []))));
        $aiLocations = self::extractLocations($aiText);
        foreach ($aiLocations as $loc) {
            if (in_array($loc, $sourceLocations, true)) {
                continue;
            }
            if ($sourceLocations !== []) {
                $issues[] = [
                    'type' => 'ERROR_CHANGED_ENTITY',
                    'original' => implode(', ', array_slice($sourceLocations, 0, 3)),
                    'ai' => $loc,
                    'status' => 'ERROR',
                ];
                $risk += 30;
            }
        }

        $sourceTimes = array_values(array_filter(array_map('strval', (array) ($protected['times'] ?? []))));
        $aiTimes = self::extractTimes($aiText);
        foreach ($aiTimes as $t) {
            if ($sourceTimes !== [] && !in_array($t, $sourceTimes, true)) {
                $issues[] = [
                    'type' => 'ERROR_CHANGED_ENTITY',
                    'original' => implode(', ', array_slice($sourceTimes, 0, 3)),
                    'ai' => $t,
                    'status' => 'ERROR',
                ];
                $risk += 25;
            }
        }

        $numbersValidation = self::validateNumbersLock(
            array_values(array_filter(array_map('strval', (array) ($protected['numbers'] ?? [])))),
            self::extractNumbers($aiText)
        );
        if (!empty($numbersValidation['issues'])) {
            foreach ($numbersValidation['issues'] as $issue) {
                $issues[] = $issue;
                $risk += (int) ($issue['risk'] ?? 25);
            }
        }
        if (!empty($numbersValidation['warnings'])) {
            foreach ($numbersValidation['warnings'] as $warn) {
                $warnings[] = $warn;
                $risk += (int) ($warn['risk'] ?? 10);
            }
        }

        $sourceOrgs = array_values(array_filter(array_map('strval', (array) ($protected['organizations'] ?? []))));
        $aiOrgs = self::extractOrganizations($aiText);
        foreach ($aiOrgs as $org) {
            if ($sourceOrgs !== [] && !in_array($org, $sourceOrgs, true)) {
                $issues[] = [
                    'type' => 'ERROR_CHANGED_ENTITY',
                    'original' => implode(', ', array_slice($sourceOrgs, 0, 3)),
                    'ai' => $org,
                    'status' => 'ERROR',
                ];
                $risk += 20;
            }
        }

        $sourceEvents = array_values(array_filter(array_map('strval', (array) ($protected['events'] ?? []))));
        $aiEvents = self::extractEvents($aiText);
        foreach ($aiEvents as $event) {
            if ($sourceEvents !== [] && !in_array($event, $sourceEvents, true)) {
                $issues[] = [
                    'type' => 'ERROR_CHANGED_ENTITY',
                    'original' => implode(', ', array_slice($sourceEvents, 0, 3)),
                    'ai' => $event,
                    'status' => 'ERROR',
                ];
                $risk += 20;
            }
        }

        $status = $issues ? 'error' : ($warnings ? 'warning' : 'ok');
        $strictStatus = $issues ? 'NEEDS_REVIEW' : 'OK';
        $risk = max(0, min(100, $risk));
        $reason = 'ok';
        if ($issues) {
            $reason = strtolower((string) ($issues[0]['type'] ?? 'entity_changed'));
        } elseif ($warnings) {
            $reason = strtolower((string) ($warnings[0]['type'] ?? 'entity_warning'));
        }

        $factDiff = self::buildFactDiff(
            $factLock,
            $aiText,
            $numbersValidation,
            $mustIncludeValidation
        );

        return [
            'status' => $status,
            'risk_score' => $risk,
            'reason' => $reason,
            'issues' => $issues,
            'warnings' => $warnings,
            'identity_analysis' => $factLock['identity_analysis'] ?? ['status' => 'ok', 'risk_score' => 0, 'reason' => 'none'],
            'entity_context' => $factLock['entity_context'] ?? [],
            'must_include' => $factLock['must_include'] ?? [],
            'fact_diff' => $factDiff,
            'strict_source_result' => [
                'status' => $strictStatus,
                'errors' => $issues,
            ],
            'checked_at' => date('c'),
            'fact_check' => [
                'status' => $status,
                'risk_score' => $risk,
                'reason' => $reason,
            ],
        ];
    }

    /**
     * @return array{status:string,risk_score:int,reason:string,matches:array<int,array<string,mixed>>}
     */
    public static function analyzeIdentityRisk(string $sourceText): array
    {
        $contexts = self::extractEntityContext($sourceText);
        $matches = [];
        $max = 0;
        foreach ($contexts as $ctx) {
            $field = mb_strtolower((string) ($ctx['field'] ?? ''), 'UTF-8');
            if ($field === '') {
                continue;
            }
            $alt = self::possibleAltFields($field);
            if ($alt !== []) {
                $risk = min(100, 55 + count($alt) * 10);
                $max = max($max, $risk);
                $matches[] = [
                    'name' => $ctx['name'] ?? '',
                    'field' => $field,
                    'possible_collisions' => $alt,
                    'risk_score' => $risk,
                ];
            }
        }
        if ($matches === []) {
            return ['status' => 'ok', 'risk_score' => 0, 'reason' => 'none', 'matches' => []];
        }
        return [
            'status' => 'needs_review',
            'risk_score' => $max,
            'reason' => 'identity_collision_risk',
            'matches' => $matches,
        ];
    }

    public static function publishAllowed(array $validation, bool $force = false): bool
    {
        if ($force) {
            return true;
        }
        return (($validation['status'] ?? 'ok') !== 'error');
    }

    /** @param array<string,mixed> $payload */
    public static function appendAudit(array $payload): void
    {
        $dir = dirname(self::AUDIT_FILE);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $line = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if ($line === false) {
            return;
        }
        @file_put_contents(self::AUDIT_FILE, $line . PHP_EOL, FILE_APPEND);
    }

    /** @return array<string,mixed> */
    public static function runTests(): array
    {
        $tests = [];

        $lock1 = self::extractFactLock(['description' => 'Edin Zuković privremeno napušta funkciju.']);
        $t1 = self::validate($lock1, ['title' => 'Zoran Zuković napušta funkciju', 'content' => '<p>Zoran Zuković...</p>']);
        $tests[] = self::testResult('TEST 1', 'Edin Zuković -> Zoran Zuković', $t1['status'] === 'error', $t1);

        $lock2 = self::extractFactLock(['description' => 'Incident se dogodio u Novom Pazaru.']);
        $t2 = self::validate($lock2, ['title' => 'Incident u Novom Sadu', 'content' => '<p>Novi Sad...</p>']);
        $tests[] = self::testResult('TEST 2', 'Novi Pazar -> Novi Sad', $t2['status'] === 'error', $t2);

        $lock3 = self::extractFactLock(['description' => 'Utakmica je odigrana 2026. godine.']);
        $t3 = self::validate($lock3, ['title' => 'Meč 2025', 'content' => '<p>Tokom 2025...</p>']);
        $tests[] = self::testResult('TEST 3', '2026 -> 2025', $t3['status'] === 'error', $t3);

        $lock4 = self::extractFactLock(['description' => 'Edin Zuković je govorio u Novom Pazaru 2026. godine.']);
        $t4 = self::validate($lock4, ['title' => 'Govor u Novom Pazaru', 'content' => '<p>U Novom Pazaru, Edin Zuković je govorio tokom 2026.</p>']);
        $tests[] = self::testResult('TEST 4', 'Reformulacija bez promene činjenica', $t4['status'] === 'ok', $t4);

        $lock5 = self::extractFactLock(['description' => 'Edin Zuković je dao izjavu.']);
        $t5 = self::validate($lock5, ['title' => 'Marko Marković i Edin Zuković', 'content' => '<p>Marko Marković i Edin Zuković...</p>']);
        $tests[] = self::testResult('TEST 5', 'Dodavanje nepostojeće osobe', in_array($t5['status'], ['warning', 'error'], true), $t5);

        $lock6 = self::extractFactLock([
            'description' => 'Željko Obradović, atletičar i reprezentativac u atletici, nastupa u disciplini horizontalni skokovi.',
        ]);
        $t6 = self::validate($lock6, [
            'title' => 'Željko Obradović, košarkaški trener',
            'content' => '<p>Željko Obradović je poznati košarkaški trener.</p>',
        ]);
        $tests[] = self::testResult('TEST 6', 'Željko Obradović atletika -> košarka', $t6['status'] === 'error', $t6);

        $lock7 = self::extractFactLock(['description' => 'Lazar Anić osvojio je zlatnu medalju sa rezultatom 7,45 m na takmičenju 12.03.2026.']);
        $t7 = self::validate($lock7, ['title' => 'Uspeh atletičara', 'content' => '<p>Lazar Ani je skočio 7,45 m.</p>']);
        $tests[] = self::testResult('TEST 7', 'Lazar Anić -> Lazar Ani', $t7['status'] === 'error', $t7);

        $lock8 = self::extractFactLock(['description' => 'Rezultat je bio 2:1, a mandat traje do 2028. godine.']);
        $t8 = self::validate($lock8, ['title' => 'Utakmica', 'content' => '<p>Rezultat je bio 2:1, mandat traje do 2028.</p>']);
        $tests[] = self::testResult('TEST 8', 'Must include činjenice zadržane', $t8['status'] === 'ok', $t8);

        $lock9 = self::extractFactLock(['description' => 'Sportistkinja ima 44 međunarodne medalje.']);
        $t9 = self::validate($lock9, ['title' => 'Nova statistika', 'content' => '<p>Sportistkinja ima 44 međunarodne medalje i uspešnost 100%.</p>']);
        $tests[] = self::testResult('TEST 9', 'Dodavanje novog broja (100%)', $t9['status'] === 'error', $t9);

        $noiseSource = 'Naslovna Vesti Dru&scaron;tvo Novi Pazar Politika Sport Hronika Region Crna Gora Bosna i Hercegovina Hrvatska Planeta .tdb_module_header{width:100%;padding-bottom:0} Zuković privremeno napušta mesto selektora Srbije, na čelo reprezentacije dolazi Željko Obradović.';
        $lock10 = self::extractFactLock(['description' => $noiseSource]);
        $noNoisePerson = !in_array('Hercegovina Hrvatska Planeta', (array) (($lock10['protected'] ?? [])['persons'] ?? []), true);
        $tests[] = self::testResult('TEST 10', 'Noise meni/CSS ne ulazi u PERSON', $noNoisePerson, ['status' => $noNoisePerson ? 'ok' : 'error', 'reason' => $noNoisePerson ? 'ok' : 'noise_detected', 'risk_score' => $noNoisePerson ? 0 : 100]);

        $passed = count(array_filter($tests, static fn (array $t): bool => !empty($t['passed'])));
        return [
            'total' => count($tests),
            'passed' => $passed,
            'all_passed' => $passed === count($tests),
            'tests' => $tests,
            'ran_at' => date('c'),
        ];
    }

    /** @return list<string> */
    private static function extractPersons(string $text): array
    {
        preg_match_all('/\b([A-ZŠĐČĆŽ][a-zšđčćž]+(?:\s+[A-ZŠĐČĆŽ][a-zšđčćž]+)+)\b/u', $text, $m);
        $candidates = [];
        foreach (array_map('trim', $m[1] ?? []) as $raw) {
            foreach (self::personVariants($raw) as $variant) {
                $candidates[] = $variant;
            }
        }
        $persons = array_values(array_filter($candidates, static function (string $name): bool {
            $parts = preg_split('/\s+/', $name) ?: [];
            if (count($parts) < 2 || count($parts) > 4) {
                return false;
            }
            $bad = ['Novi Pazar', 'Novi Sad', 'Sandzak Net', 'Auto Vesti', 'Bosna i Hercegovina'];
            if (in_array($name, $bad, true)) {
                return false;
            }
            $badTokens = [
                'naslovna', 'vesti', 'drustvo', 'društvo', 'politika', 'sport', 'hronika',
                'region', 'planeta', 'hrvatska', 'hercegovina', 'bosna', 'crna', 'gora',
                'srpski', 'srbije', 'reprezentativci', 'impressum', 'kontakt', 'uslovi',
                'podeli', 'autor', 'pošalji', 'posalji', 'možete', 'mozete', 'tri',
                'izdvajamo', 'partner', 'novog', 'pazara',
            ];
            $partsLower = array_map(static fn (string $p): string => mb_strtolower($p, 'UTF-8'), $parts);
            $hits = count(array_intersect($partsLower, $badTokens));
            if ($hits > 0) {
                return false;
            }
            $joined = mb_strtolower($name, 'UTF-8');
            if (preg_match('/\b(?:kontakt|impressum|uslovi|politika|planeta|hronika|naslovna)\b/iu', $joined)) {
                return false;
            }
            return true;
        }));
        return array_values(array_unique($persons));
    }

    /** @return list<string> */
    private static function personVariants(string $raw): array
    {
        $parts = preg_split('/\s+/u', trim($raw)) ?: [];
        if (count($parts) < 2) {
            return [];
        }
        $stop = [
            'srbije', 'srpski', 'reprezentacije', 'reprezentativci', 'novog', 'pazara',
            'izdvajamo', 'partner', 'impressum', 'kontakt', 'uslovi', 'autor',
            'podeli', 'pošalji', 'posalji', 'možete', 'mozete',
        ];
        $out = [];
        $joined = implode(' ', $parts);
        $out[] = $joined;
        if (count($parts) >= 3) {
            for ($i = 0; $i <= count($parts) - 2; $i++) {
                $pair = [$parts[$i], $parts[$i + 1]];
                $pairLower = [mb_strtolower($pair[0], 'UTF-8'), mb_strtolower($pair[1], 'UTF-8')];
                if (in_array($pairLower[0], $stop, true) || in_array($pairLower[1], $stop, true)) {
                    continue;
                }
                $out[] = implode(' ', $pair);
            }
        }
        return array_values(array_unique(array_filter($out, static fn (string $v): bool => $v !== '')));
    }

    /** @return list<string> */
    private static function extractOrganizations(string $text): array
    {
        preg_match_all('/\b(?:Ministarstvo|Vlada|Skupština|Opština|Grad|FK|KK|JP|AD|DOO|SDA|SDP|SNS|MUP|Klinički centar)[^.,;\n]*/u', $text, $m);
        $orgs = array_values(array_filter(array_map(static fn (string $v): string => trim($v), $m[0] ?? [])));
        return array_values(array_unique($orgs));
    }

    /** @return list<string> */
    private static function extractLocations(string $text): array
    {
        $known = [
            'Novi Pazar', 'Novi Sad', 'Beograd', 'Sjenica', 'Tutin', 'Prijepolje',
            'Priboj', 'Nova Varoš', 'Raška', 'Srbija', 'Sandžak', 'Bosna i Hercegovina',
        ];
        $hits = [];
        foreach ($known as $loc) {
            if (mb_stripos($text, $loc, 0, 'UTF-8') !== false) {
                $hits[] = $loc;
            }
        }
        return array_values(array_unique($hits));
    }

    /** @return list<string> */
    private static function extractTimes(string $text): array
    {
        $out = [];
        preg_match_all('/\b\d{1,2}\.\d{1,2}\.\d{4}\.?\b/u', $text, $m1);
        preg_match_all('/\b(19|20)\d{2}\b/u', $text, $m2);
        foreach (array_merge($m1[0] ?? [], $m2[0] ?? []) as $v) {
            $out[] = trim((string) $v);
        }
        return array_values(array_unique($out));
    }

    /** @return list<string> */
    private static function extractNumbers(string $text): array
    {
        $segments = preg_split('/(?<=[\.\!\?;])\s+|\n+/u', $text) ?: [$text];
        $numbers = [];
        foreach ($segments as $segment) {
            $segment = trim($segment);
            if ($segment === '') {
                continue;
            }
            $lower = mb_strtolower($segment, 'UTF-8');
            if (self::isNoiseLine($segment)) {
                continue;
            }
            if (preg_match('/\b(?:td-module|tdb_module|wp-content|wp-json|stylesheet|font-family|display|position|width|height|padding|margin|container|viewbox|svg)\b/iu', $lower)) {
                continue;
            }
            if (!preg_match('/[a-zšđčćž]/iu', $segment)) {
                continue;
            }
            preg_match_all('/\b\d+(?:[.,]\d+)?\b/u', $segment, $m);
            foreach ($m[0] ?? [] as $n) {
                $n = (string) $n;
                if (strlen($n) >= 6) {
                    continue;
                }
                $numbers[] = $n;
            }
        }
        return array_values(array_unique($numbers));
    }

    /** @return list<string> */
    private static function extractFunctions(string $text): array
    {
        preg_match_all('/\b(predsednik|predsjednik|gradonačelnik|ministar|direktor|selektor|trener|portparol)\b[^.,;\n]*/iu', $text, $m);
        return array_values(array_unique(array_map(static fn (string $v): string => trim($v), $m[0] ?? [])));
    }

    /** @return list<string> */
    private static function extractEvents(string $text): array
    {
        preg_match_all('/\b(sastanak|sednica|sjednica|utakmica|izbori|protest|konferencija|nesreća|hapšenje|smotra|festival)\b[^.,;\n]*/iu', $text, $m);
        return array_values(array_unique(array_map(static fn (string $v): string => trim($v), $m[0] ?? [])));
    }

    /**
     * @return list<array{name:string,type:string,field:string,discipline:string,organization:string,function:string}>
     */
    private static function extractEntityContext(string $text): array
    {
        $persons = self::extractPersons($text);
        $contexts = [];
        foreach ($persons as $person) {
            $window = self::contextWindow($text, $person);
            $field = self::guessField($window);
            $discipline = self::guessDiscipline($window, $field);
            $org = self::guessOrganizationFromWindow($window);
            $fn = self::guessFunctionFromWindow($window);
            $contexts[] = [
                'name' => $person,
                'type' => 'person',
                'field' => $field,
                'discipline' => $discipline,
                'organization' => $org,
                'function' => $fn,
            ];
        }
        return $contexts;
    }

    /**
     * @param list<array{name:string,type:string,field:string,discipline:string,organization:string,function:string}> $contexts
     * @return array{issues:array<int,array<string,mixed>>,warnings:array<int,array<string,mixed>>}
     */
    private static function validateEntityContext(array $contexts, string $aiText): array
    {
        $issues = [];
        $warnings = [];
        $lower = mb_strtolower($aiText, 'UTF-8');

        foreach ($contexts as $ctx) {
            $name = (string) ($ctx['name'] ?? '');
            $field = (string) ($ctx['field'] ?? '');
            if ($name === '' || $field === '') {
                continue;
            }
            if (mb_stripos($lower, mb_strtolower($name, 'UTF-8'), 0, 'UTF-8') === false) {
                continue;
            }

            $alts = self::possibleAltFields($field);
            foreach ($alts as $alt) {
                if (mb_stripos($lower, $alt, 0, 'UTF-8') !== false) {
                    $issues[] = [
                        'type' => 'PERSON_FIELD_CHANGED',
                        'original' => $name . ' [' . $field . ']',
                        'ai' => $name . ' [' . $alt . ']',
                        'status' => 'ERROR',
                        'risk' => 35,
                    ];
                    break;
                }
            }

            $fn = mb_strtolower((string) ($ctx['function'] ?? ''), 'UTF-8');
            if ($fn !== '' && mb_stripos($lower, $fn, 0, 'UTF-8') === false) {
                $warnings[] = [
                    'type' => 'PERSON_FUNCTION_MISSING',
                    'original' => $name . ' [' . $fn . ']',
                    'ai' => $name,
                    'status' => 'WARNING',
                    'risk' => 10,
                ];
            }
        }
        return ['issues' => $issues, 'warnings' => $warnings];
    }

    /** @return list<string> */
    private static function possibleAltFields(string $field): array
    {
        $f = mb_strtolower($field, 'UTF-8');
        return match ($f) {
            'atletika' => ['košarka', 'kosarka', 'fudbal', 'tenis', 'rukomet'],
            'košarka', 'kosarka' => ['atletika', 'fudbal', 'tenis', 'rukomet'],
            'fudbal' => ['košarka', 'kosarka', 'atletika', 'tenis'],
            default => [],
        };
    }

    private static function contextWindow(string $text, string $needle): string
    {
        $pos = mb_stripos($text, $needle, 0, 'UTF-8');
        if ($pos === false) {
            return $text;
        }
        $start = max(0, $pos - 140);
        return mb_substr($text, $start, 280, 'UTF-8');
    }

    private static function guessField(string $window): string
    {
        $w = mb_strtolower($window, 'UTF-8');
        if (str_contains($w, 'atleti')) {
            return 'atletika';
        }
        if (str_contains($w, 'košark') || str_contains($w, 'kosark')) {
            return 'košarka';
        }
        if (str_contains($w, 'fudbal')) {
            return 'fudbal';
        }
        if (str_contains($w, 'tenis')) {
            return 'tenis';
        }
        if (str_contains($w, 'rukomet')) {
            return 'rukomet';
        }
        if (str_contains($w, 'polit')) {
            return 'politika';
        }
        return '';
    }

    private static function guessDiscipline(string $window, string $field): string
    {
        $w = mb_strtolower($window, 'UTF-8');
        if ($field === 'atletika') {
            if (str_contains($w, 'horizontalni skok')) {
                return 'horizontalni skokovi';
            }
            if (str_contains($w, 'sprinter')) {
                return 'sprint';
            }
        }
        return '';
    }

    private static function guessOrganizationFromWindow(string $window): string
    {
        $orgs = self::extractOrganizations($window);
        return $orgs[0] ?? '';
    }

    private static function guessFunctionFromWindow(string $window): string
    {
        $functions = self::extractFunctions($window);
        return $functions[0] ?? '';
    }

    /** @return array<string,list<string>> */
    private static function extractMustInclude(string $text): array
    {
        $text = self::normalizeSourceInput($text);
        $must = [
            'dates' => self::extractTimes($text),
            'results' => [],
            'statistics' => [],
            'medals' => [],
            'functions' => self::extractFunctions($text),
            'mandates' => [],
        ];

        if (preg_match_all('/\b\d{1,2}:\d{1,2}\b/u', $text, $m)) {
            $must['results'] = array_merge($must['results'], array_map('strval', $m[0]));
        }
        if (preg_match_all('/\b\d+(?:[.,]\d+)?\s*(?:m|km|kg|cm|mm|sek|min|%)\b/iu', $text, $m)) {
            $must['results'] = array_merge($must['results'], array_map(static fn (string $v): string => trim($v), $m[0]));
        }
        if (preg_match_all('/\b\d+(?:[.,]\d+)?\s*%/u', $text, $m)) {
            $must['statistics'] = array_merge($must['statistics'], array_map('strval', $m[0]));
        }
        if (preg_match_all('/\b(zlatn[aou]?|srebrn[aou]?|bronzan[aou]?|medalj[aue]?)\b[^.,;\n]*/iu', $text, $m)) {
            $must['medals'] = array_values(array_unique(array_map(static fn (string $v): string => trim($v), $m[0])));
        }
        if (preg_match_all('/\b(?:mandat|mandata)\b[^.,;\n]{0,120}/iu', $text, $m)) {
            $must['mandates'] = array_values(array_unique(array_map(static fn (string $v): string => trim($v), $m[0])));
        }
        if (preg_match_all('/\bdo\s+(?:\d{4}\.\s*godine|\d{1,2}\.\d{1,2}\.\d{4}\.?)\b/iu', $text, $m)) {
            $must['mandates'] = array_merge($must['mandates'], array_map('strval', $m[0]));
        }

        foreach ($must as $key => $values) {
            $must[$key] = array_values(array_unique(array_filter(array_map('trim', $values), static fn (string $v): bool => $v !== '')));
        }
        return $must;
    }

    /**
     * @param list<string> $exactPersons
     * @return array{issues:array<int,array<string,mixed>>}
     */
    private static function validateExactPersonNames(array $exactPersons, string $aiText): array
    {
        $issues = [];
        foreach ($exactPersons as $exact) {
            if ($exact === '') {
                continue;
            }
            if (self::containsExactPersonName($aiText, $exact)) {
                continue;
            }

            $truncated = self::findTruncatedPersonVariant($aiText, $exact);
            if ($truncated !== null) {
                $issues[] = [
                    'type' => 'ERROR_PERSON_NAME_CHANGED',
                    'original' => $exact,
                    'ai' => $truncated,
                    'status' => 'ERROR',
                    'risk' => 50,
                ];
                continue;
            }

            $issues[] = [
                'type' => 'ERROR_PERSON_NAME_MISSING',
                'original' => $exact,
                'ai' => '',
                'status' => 'ERROR',
                'risk' => 35,
            ];
        }
        return ['issues' => $issues];
    }

    /** @param array<string,list<string>> $mustInclude */
    private static function validateMustInclude(array $mustInclude, string $aiText): array
    {
        $issues = [];
        $warnings = [];
        $labels = [
            'dates' => 'datum',
            'results' => 'rezultat',
            'statistics' => 'statistika',
            'medals' => 'medalja',
            'functions' => 'funkcija',
            'mandates' => 'mandat',
        ];

        foreach ($mustInclude as $category => $items) {
            if (!is_array($items) || $items === []) {
                continue;
            }
            foreach ($items as $item) {
                $item = trim((string) $item);
                if ($item === '') {
                    continue;
                }
                if (self::containsMustIncludeFact($aiText, $item)) {
                    continue;
                }
                $payload = [
                    'type' => 'MUST_INCLUDE_MISSING',
                    'category' => $category,
                    'original' => $item,
                    'ai' => '',
                    'status' => 'ERROR',
                    'risk' => 20,
                    'label' => $labels[$category] ?? $category,
                ];
                if (in_array($category, ['dates', 'statistics', 'mandates'], true)) {
                    $warnings[] = array_merge($payload, ['status' => 'WARNING', 'risk' => 8]);
                } else {
                    $issues[] = $payload;
                }
            }
        }

        return ['issues' => $issues, 'warnings' => $warnings];
    }

    /**
     * @param list<string> $sourceNumbers
     * @param list<string> $aiNumbers
     * @return array{issues:array<int,array<string,mixed>>,warnings:array<int,array<string,mixed>>,added:list<string>,missing:list<string>}
     */
    private static function validateNumbersLock(array $sourceNumbers, array $aiNumbers): array
    {
        $issues = [];
        $warnings = [];
        $added = [];
        $missing = [];

        foreach ($sourceNumbers as $n) {
            if (!in_array($n, $aiNumbers, true)) {
                $missing[] = $n;
                $issues[] = [
                    'type' => 'ERROR_CHANGED_ENTITY',
                    'original' => $n,
                    'ai' => '',
                    'status' => 'ERROR',
                    'risk' => 22,
                ];
            }
        }
        foreach ($aiNumbers as $n) {
            if (!in_array($n, $sourceNumbers, true)) {
                $added[] = $n;
                $issues[] = [
                    'type' => 'ERROR_NEW_NUMBER',
                    'original' => implode(', ', array_slice($sourceNumbers, 0, 8)),
                    'ai' => $n,
                    'status' => 'ERROR',
                    'risk' => 30,
                ];
            }
        }

        return [
            'issues' => $issues,
            'warnings' => $warnings,
            'added' => array_values(array_unique($added)),
            'missing' => array_values(array_unique($missing)),
        ];
    }

    /**
     * @param array<string,mixed> $factLock
     * @param array{issues:array<int,array<string,mixed>>,warnings:array<int,array<string,mixed>>,added:list<string>,missing:list<string>} $numbersValidation
     * @param array{issues:array<int,array<string,mixed>>,warnings:array<int,array<string,mixed>>} $mustIncludeValidation
     * @return array<string,mixed>
     */
    private static function buildFactDiff(array $factLock, string $aiText, array $numbersValidation, array $mustIncludeValidation): array
    {
        $sourceNumbers = array_values(array_filter(array_map('strval', (array) (($factLock['protected'] ?? [])['numbers'] ?? []))));
        $sourceMust = (array) ($factLock['must_include'] ?? []);
        $missingFacts = [];
        foreach (array_merge((array) ($mustIncludeValidation['issues'] ?? []), (array) ($mustIncludeValidation['warnings'] ?? [])) as $issue) {
            if (($issue['type'] ?? '') !== 'MUST_INCLUDE_MISSING') {
                continue;
            }
            $orig = trim((string) ($issue['original'] ?? ''));
            if ($orig !== '') {
                $missingFacts[] = $orig;
            }
        }

        $status = ($numbersValidation['added'] || $numbersValidation['missing'] || $missingFacts) ? 'warning' : 'ok';
        return [
            'status' => $status,
            'source_numbers' => array_slice($sourceNumbers, 0, 20),
            'output_numbers' => array_slice(self::extractNumbers($aiText), 0, 20),
            'added_numbers' => array_slice((array) ($numbersValidation['added'] ?? []), 0, 20),
            'missing_numbers' => array_slice((array) ($numbersValidation['missing'] ?? []), 0, 20),
            'must_include_source' => $sourceMust,
            'must_include_missing' => array_slice(array_values(array_unique($missingFacts)), 0, 20),
            'summary' => $status === 'ok'
                ? 'Sve ključne činjenice su zadržane.'
                : 'Otkrivene su razlike između SOURCE i OUTPUT.',
        ];
    }

    /**
     * @param list<string> $persons
     * @param list<string> $numbers
     * @param list<string> $dates
     * @param list<string> $locations
     * @param list<string> $events
     * @return array<string,list<string>>
     */
    private static function buildSourceFactMap(array $persons, array $numbers, array $dates, array $locations, array $events): array
    {
        return [
            'persons' => array_values(array_unique(array_map('strval', $persons))),
            'numbers' => array_values(array_unique(array_map('strval', $numbers))),
            'dates' => array_values(array_unique(array_map('strval', $dates))),
            'locations' => array_values(array_unique(array_map('strval', $locations))),
            'events' => array_values(array_unique(array_map('strval', $events))),
        ];
    }

    private static function containsExactPersonName(string $haystack, string $name): bool
    {
        if (mb_strpos($haystack, $name, 0, 'UTF-8') !== false) {
            return true;
        }
        return mb_stripos($haystack, $name, 0, 'UTF-8') !== false;
    }

    private static function findTruncatedPersonVariant(string $aiText, string $exact): ?string
    {
        $parts = preg_split('/\s+/u', trim($exact)) ?: [];
        if (count($parts) < 2) {
            return null;
        }
        $first = (string) $parts[0];
        $last = (string) end($parts);
        $lastLen = mb_strlen($last, 'UTF-8');
        if ($lastLen < 3) {
            return null;
        }
        for ($len = $lastLen - 1; $len >= 3; $len--) {
            $partial = mb_substr($last, 0, $len, 'UTF-8');
            $pattern = '/\b' . preg_quote($first, '/') . '\s+' . preg_quote($partial, '/') . '\b/iu';
            if (preg_match($pattern, $aiText)) {
                $found = $first . ' ' . $partial;
                if (!self::containsExactPersonName($aiText, $exact)) {
                    return $found;
                }
            }
        }
        return null;
    }

    private static function containsMustIncludeFact(string $haystack, string $needle): bool
    {
        if (mb_stripos($haystack, $needle, 0, 'UTF-8') !== false) {
            return true;
        }
        $normalizedHay = self::normalizeFactToken($haystack);
        $normalizedNeedle = self::normalizeFactToken($needle);
        return $normalizedNeedle !== '' && str_contains($normalizedHay, $normalizedNeedle);
    }

    private static function normalizeFactToken(string $value): string
    {
        $value = mb_strtolower($value, 'UTF-8');
        $value = str_replace([',', ' '], ['.', ''], $value);
        return preg_replace('/[^a-z0-9\.:%]/u', '', $value) ?? '';
    }

    /** @param list<string> $persons @return array<string,string> */
    private static function mapPersonsBySurname(array $persons): array
    {
        $map = [];
        foreach ($persons as $p) {
            $surname = self::surname($p);
            if ($surname !== '' && !isset($map[$surname])) {
                $map[$surname] = $p;
            }
        }
        return $map;
    }

    private static function surname(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        return count($parts) >= 2 ? (string) end($parts) : '';
    }

    private static function isSoftPersonVariant(string $candidate, string $original): bool
    {
        $cand = mb_strtolower(trim($candidate), 'UTF-8');
        $orig = mb_strtolower(trim($original), 'UTF-8');
        if ($cand === $orig) {
            return true;
        }
        if (str_contains($cand, $orig) || str_contains($orig, $cand)) {
            return true;
        }
        $prefixes = ['uloga', 'gospodin', 'gđa', 'gdja', 'mr', 'dr'];
        foreach ($prefixes as $prefix) {
            if (str_starts_with($cand, $prefix . ' ')) {
                $trimmed = trim(mb_substr($cand, mb_strlen($prefix, 'UTF-8'), null, 'UTF-8'));
                if ($trimmed === $orig) {
                    return true;
                }
            }
        }
        return false;
    }

    private static function sourceText(array $item): string
    {
        $parts = [
            (string) ($item['title'] ?? ''),
            self::normalizeSourceInput((string) ($item['description'] ?? '')),
            self::normalizeSourceInput(strip_tags((string) ($item['_content_html'] ?? ''))),
        ];
        return trim(preg_replace('/\s+/u', ' ', implode("\n", $parts)) ?? '');
    }

    private static function normalizeSourceInput(string $text): string
    {
        $decoded = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $decoded = str_replace(["\xc2\xa0", '&nbsp;'], ' ', $decoded);
        $decoded = preg_replace('/\.[a-z0-9_-]+\{[^}]{0,800}\}/iu', ' ', $decoded) ?? $decoded;
        $decoded = preg_replace('/@[^{]+\{[^}]{0,1200}\}/iu', ' ', $decoded) ?? $decoded;
        $decoded = preg_replace('/\b(?:font-family|line-height|padding|margin|display|width|height|position)\s*:\s*[^;]+;?/iu', ' ', $decoded) ?? $decoded;
        $decoded = str_replace(['|', '↗', '🌐', '📅', '📥'], "\n", $decoded);
        $chunks = preg_split('/[\r\n]+/u', $decoded) ?: [];
        $clean = [];
        foreach ($chunks as $chunk) {
            $line = trim($chunk);
            if ($line === '') {
                continue;
            }
            if (self::isNoiseLine($line)) {
                continue;
            }
            $clean[] = $line;
        }
        return trim(preg_replace('/\s+/u', ' ', implode("\n", $clean)) ?? '');
    }

    private static function isNoiseLine(string $line): bool
    {
        $lower = mb_strtolower($line, 'UTF-8');
        if (str_contains($lower, '.tdb_') || str_contains($lower, '{') || str_contains($lower, '}')) {
            return true;
        }
        if (preg_match('/\b(?:td-module|tdb_module|wp-content|wp-json|viewbox|svg|stylesheet|font-family|line-height|display:flex|position:|width:|height:)\b/iu', $lower)) {
            return true;
        }
        if (preg_match('/\b(?:retyped pass|invalid pass pattern|red hat display|zilla slab)\b/iu', $lower)) {
            return true;
        }
        preg_match_all('/\b(?:naslovna|vesti|drustvo|društvo|novi pazar|politika|sport|hronika|region|crna gora|bosna i hercegovina|hrvatska|planeta)\b/iu', $lower, $m);
        if (count($m[0] ?? []) >= 4) {
            return true;
        }
        preg_match_all('/\b\d+(?:[.,:x]\d+)?\b/u', $line, $nums);
        $numCount = count($nums[0] ?? []);
        if ($numCount >= 6 && !preg_match('/[a-zšđčćž]/iu', $line)) {
            return true;
        }
        if ($numCount >= 4 && preg_match('/\b(?:width|height|padding|margin|module|container|display|position|flex)\b/iu', $lower)) {
            return true;
        }
        return false;
    }

    /** @param array<string,mixed> $result @return array<string,mixed> */
    private static function testResult(string $name, string $case, bool $passed, array $result): array
    {
        return [
            'name' => $name,
            'case' => $case,
            'passed' => $passed,
            'status' => $result['status'] ?? 'unknown',
            'reason' => $result['reason'] ?? '',
            'risk_score' => $result['risk_score'] ?? 0,
        ];
    }
}

