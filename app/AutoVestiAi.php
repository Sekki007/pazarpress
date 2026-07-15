<?php

declare(strict_types=1);

final class AutoVestiAi
{
    private const SYSTEM = 'Piši kao novinar lokalnog portala Sandzak.net. '
        . 'Koristi prirodan novinarski stil i piši kao iskusan lokalni novinar. '
        . 'Nemoj praviti generički AI tekst. '
        . 'Vraćaš ISKLJUČIVO validan JSON bez markdown backtick-a.';

    /**
     * @param array<int, array{title:string,url:string}> $existingPosts
     * @param array<string,mixed>|null $factLock
     */
    public static function rewrite(array $item, string $lang, bool $useFaq, array $existingPosts, string $provider, string $apiKey, ?array $factLock = null): array|string
    {
        $prompt = self::buildPrompt($item, $lang, $useFaq, $existingPosts, $factLock);

        if ($provider === 'openai') {
            return self::callOpenAi($prompt, $apiKey);
        }
        return self::callClaude($prompt, $apiKey);
    }

    /**
     * @param array<string, mixed> $item
     * @param array<int, array{title:string,url:string}> $existingPosts
     * @param array<string,mixed>|null $factLock
     */
    private static function buildPrompt(array $item, string $lang, bool $useFaq, array $existingPosts, ?array $factLock = null): string
    {
        $minWords = max(200, (int) AutoVestiConfig::get('article_min_words', 350));
        $maxWords = max($minWords, (int) AutoVestiConfig::get('article_max_words', 900));

        $linksBlock = '';
        if ($existingPosts) {
            $linksBlock = "\n\nINTERNI LINKOVI (umetni 2–3 prirodno u tekst kao <a href=\"URL\" class=\"avc-internal\">ankertekst</a>, samo ako su stvarno relevantni):\n";
            foreach (array_slice($existingPosts, 0, 25) as $p) {
                $linksBlock .= '- ' . $p['title'] . ' | ' . $p['url'] . "\n";
            }
        }

        $faqJson = $useFaq
            ? '"faq":[{"q":"pitanje","a":"odgovor"},{"q":"pitanje","a":"odgovor"},{"q":"pitanje","a":"odgovor"}],'
            : '"faq":[],';

        $sourceTitle = trim((string) ($item['title'] ?? ''));
        $source = self::cleanSourceText((string) ($item['description'] ?? ''));
        if (mb_strlen($source) < 400 && !empty($item['_content_html'])) {
            $plain = self::cleanSourceText(strip_tags((string) $item['_content_html']));
            if (mb_strlen($plain) > mb_strlen($source)) {
                $source = mb_substr($plain, 0, 3500);
            }
        }

        $factLockBlock = '';
        if ($factLock && !empty($factLock['protected']) && is_array($factLock['protected'])) {
            $protected = $factLock['protected'];
            $factLockBlock = "\nSOURCE OF TRUTH / ENTITY PROTECTION LAYER:\n"
                . "Ovo su zaključani entiteti iz originalnog teksta. NE SMEŠ menjati ni preimenovati:\n"
                . '- PERSON: ' . implode(', ', array_slice((array) ($protected['persons'] ?? []), 0, 40)) . "\n"
                . '- ORGANIZATION: ' . implode(', ', array_slice((array) ($protected['organizations'] ?? []), 0, 40)) . "\n"
                . '- LOCATION: ' . implode(', ', array_slice((array) ($protected['locations'] ?? []), 0, 40)) . "\n"
                . '- TIME: ' . implode(', ', array_slice((array) ($protected['times'] ?? []), 0, 40)) . "\n"
                . '- NUMBERS: ' . implode(', ', array_slice((array) ($protected['numbers'] ?? []), 0, 40)) . "\n"
                . '- FUNCTIONS: ' . implode(', ', array_slice((array) ($protected['functions'] ?? []), 0, 40)) . "\n"
                . '- EVENTS: ' . implode(', ', array_slice((array) ($protected['events'] ?? []), 0, 40)) . "\n"
                . "Ako nema podataka za nešto, ostavi stil prirodan ali ne dodaj nove činjenice.\n\n";
        }

        $entityContextBlock = '';
        if (!empty($factLock['entity_context']) && is_array($factLock['entity_context'])) {
            $entityContextBlock = "ENTITY CONTEXT LOCK:\n";
            foreach (array_slice($factLock['entity_context'], 0, 20) as $ctx) {
                if (!is_array($ctx) || empty($ctx['name'])) {
                    continue;
                }
                $entityContextBlock .= '- PERSON: ' . (string) ($ctx['name'] ?? '')
                    . ' | field=' . (string) ($ctx['field'] ?? '')
                    . ' | discipline=' . (string) ($ctx['discipline'] ?? '')
                    . ' | organization=' . (string) ($ctx['organization'] ?? '')
                    . ' | function=' . (string) ($ctx['function'] ?? '') . "\n";
            }
            $entityContextBlock .= "Pravila za PERSON:\n"
                . "- Ne menjaj puno ime osobe.\n"
                . "- Ne menjaj oblast delovanja (field).\n"
                . "- Ne menjaj profesiju/funkciju.\n"
                . "- Ne dodaj biografske podatke koji nisu potvrđeni izvorom.\n"
                . "- Ako postoji više osoba sa istim imenom, koristi isključivo kontekst iz izvora.\n\n";
        }

        $exactMatchBlock = '';
        $exactPersons = (array) ($factLock['protected']['persons_exact'] ?? $factLock['protected']['persons'] ?? []);
        if ($exactPersons) {
            $exactMatchBlock = "ENTITY EXACT MATCH (obavezno):\n";
            foreach (array_slice($exactPersons, 0, 30) as $person) {
                $exactMatchBlock .= '- ' . (string) $person . "\n";
            }
            $exactMatchBlock .= "Pravila:\n"
                . "- Svako ime mora ostati identično (ista slova, ista prezimena).\n"
                . "- Ne skraćuj prezimena (npr. Anić -> Ani je zabranjeno).\n"
                . "- Ne menjaj slova ni praviti slična imena.\n"
                . "- U tekstu koristi tačan oblik imena bar jednom.\n\n";
        }

        $mustIncludeBlock = '';
        if (!empty($factLock['must_include']) && is_array($factLock['must_include'])) {
            $mustIncludeBlock = "MUST INCLUDE (obavezno uključi u članak):\n";
            foreach ($factLock['must_include'] as $category => $items) {
                if (!is_array($items) || !$items) {
                    continue;
                }
                $mustIncludeBlock .= '- ' . strtoupper((string) $category) . ': ' . implode(', ', array_slice($items, 0, 20)) . "\n";
            }
            $mustIncludeBlock .= "Ne izbacuj ključne činjenice iz izvora: datume, rezultate, statistiku, medalje, funkcije i trajanje mandata.\n\n";
        }

        $sourceFactMapBlock = '';
        if (!empty($factLock['source_fact_map']) && is_array($factLock['source_fact_map'])) {
            $sourceFactMapBlock = "SOURCE FACT MAP (koristi isključivo ove činjenice):\n";
            foreach (['persons', 'numbers', 'dates', 'locations', 'events'] as $k) {
                $vals = (array) ($factLock['source_fact_map'][$k] ?? []);
                if ($vals) {
                    $sourceFactMapBlock .= '- ' . strtoupper($k) . ': ' . implode(', ', array_slice($vals, 0, 40)) . "\n";
                }
            }
            $sourceFactMapBlock .= "\n";
        }

        $neverInventBlock = "NEVER INVENT MODE (strogo):\n"
            . "- Ako podatak ne postoji u izvornom tekstu: NE DODAVAJ ga.\n"
            . "- Ne procenjuj, ne nagađaj, ne generiši primer i ne popunjavaj praznine.\n"
            . "- Bolje kraći članak nego dodatne neproverene rečenice.\n"
            . "- Zabranjeno generičko produžavanje teksta radi dužine.\n\n";
        $strictSourceModeBlock = "STRICT SOURCE MODE:\n"
            . "- Koristi ISKLJUČIVO informacije iz originalnog teksta i SOURCE FACT MAP.\n"
            . "- Dozvoljeno: stil, redosled, kraćenje, profesionalno uređivanje.\n"
            . "- Zabranjeno: novi brojevi, procenti, datumi, osobe, lokacije, događaji.\n\n";

        $numbersLockBlock = '';
        if ($factLock && !empty($factLock['protected']['numbers']) && is_array($factLock['protected']['numbers'])) {
            $numbersLockBlock = "NUMBERS LOCK (strogo):\n"
                . "- Brojevi iz izvora moraju ostati isti.\n"
                . "- Zabranjeno je dodavati nove brojeve (npr. procente, rezultate, statistiku) koji nisu u izvoru.\n"
                . "- Ako nisi siguran, izbaci rečenicu umesto da dodaš broj.\n"
                . "- Zaključani brojevi: " . implode(', ', array_slice((array) $factLock['protected']['numbers'], 0, 40)) . "\n\n";
        }

        return 'Ti si profesionalni novinar i urednik portala.' . "\n\n"
            . 'Na osnovu teksta ispod napiši potpuno novu verziju članka na srpskom jeziku.' . "\n\n"
            . 'Evo originalnog teksta:' . "\n"
            . 'Naslov izvora: ' . $sourceTitle . "\n"
            . 'Sadržaj izvora: ' . $source . "\n\n"
            . $factLockBlock
            . $entityContextBlock
            . $exactMatchBlock
            . $mustIncludeBlock
            . $sourceFactMapBlock
            . $neverInventBlock
            . $strictSourceModeBlock
            . $numbersLockBlock
            . "Obavezna pravila:\n"
            . "- Nemoj kopirati rečenice niti pasuse iz originala.\n"
            . "- Zadrži isključivo proverljive činjenice, datume, imena i citate (ako postoje).\n"
            . "- Promeni redosled informacija i strukturu teksta.\n"
            . "- Piši prirodnim novinarskim stilom na srpskom jeziku.\n"
            . "- Koristi drugačije formulacije i sinonime.\n"
            . "- Dodaj kratak uvod koji privlači pažnju čitaoca.\n"
            . "- Napravi logične međunaslove ako je tekst duži.\n"
            . "- Završetak članka napiši svojim rečima.\n"
            . "- Ne izmišljaj informacije koje nisu navedene u originalu.\n"
            . "- AI sme menjati samo stil, redosled rečenica i dužinu teksta.\n"
            . "- AI ne sme menjati: imena, brojeve, datume, rezultate, funkcije, mesta.\n"
            . "- Izbaci nepotrebna ponavljanja i reklame iz originalnog teksta.\n"
            . "- Optimizuj članak za čitanje na internetu (kratki pasusi od 2–4 rečenice).\n"
            . "- Dužina članka: između {$minWords} i {$maxWords} reči.\n"
            . "- Ako nema dovoljno informacija, napiši kratku vest i ne produžavaj tekst veštački.\n"
            . "- Prioritet je: 1) informacija 2) lokalni kontekst 3) čitljivost 4) SEO optimizacija.\n"
            . "- Strogo izbegavaj fraze: \"u dostupnom materijalu nema...\", \"za sada se može reći...\", \"javnost je reagovala...\".\n\n"
            . "Format content polja:\n"
            . "- Koristi validan HTML sa <p> i po potrebi <h2>/<h3>.\n"
            . "- Ne koristi <h1> u content (naslov ide u title).\n"
            . "- Ne koristi inline CSS.\n\n"
            . "Na kraju obavezno pripremi:\n"
            . "1) SEO naslov (do 60 karaktera)\n"
            . "2) Meta opis (do 160 karaktera)\n"
            . "3) SEO URL (latinica, mala slova, sa crticama)\n"
            . "4) Kratak sažetak od 2 rečenice\n"
            . "5) 10 SEO ključnih reči\n\n"
            . 'Vrati ISKLJUČIVO JSON:' . "\n"
            . '{"title":"novi naslov članka","content":"HTML sa <p> i po potrebi <h2>/<h3>, bez h1",'
            . '"excerpt":"kratak sažetak od 2 rečenice","social_excerpt":"kratak opis za društvene mreže do 280 znakova",'
            . '"seo_title":"do 60 karaktera","meta_description":"do 160 karaktera","focus_keyphrase":"glavna ključna reč",'
            . '"slug":"seo-url-slug","tags":["tag1","tag2","tag3","tag4"],"is_breaking":false,'
            . '"image_alt":"alt tekst za naslovnu fotografiju",'
            . '"suggested_category":"predlog kategorije","external_sources":[{"name":"izvor","url":"https://..."}],'
            . '"suggested_internal_links":[{"title":"naslov","url":"https://..."}],'
            . $faqJson
            . '"schema_keywords":["ključna1","ključna2","ključna3","ključna4","ključna5","ključna6","ključna7","ključna8","ključna9","ključna10"]}'
            . $linksBlock;
    }

    private static function cleanSourceText(string $text): string
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
        if (preg_match('/\b(?:retyped pass|invalid pass pattern|red hat display|zilla slab)\b/iu', $lower)) {
            return true;
        }
        preg_match_all('/\b(?:naslovna|vesti|drustvo|društvo|novi pazar|politika|sport|hronika|region|crna gora|bosna i hercegovina|hrvatska|planeta)\b/iu', $lower, $m);
        if (count($m[0] ?? []) >= 4) {
            return true;
        }
        return false;
    }

    /** @return array<string, mixed>|string */
    private static function callClaude(string $prompt, string $apiKey): array|string
    {
        $model = (string) AutoVestiConfig::get('claude_model', 'claude-sonnet-4-20250514');
        $body = HttpClient::postJson(
            'https://api.anthropic.com/v1/messages',
            [
                'model' => $model,
                'max_tokens' => 4800,
                'system' => self::SYSTEM,
                'messages' => [['role' => 'user', 'content' => $prompt]],
            ],
            [
                'x-api-key: ' . $apiKey,
                'anthropic-version: 2023-06-01',
            ]
        );
        if (!$body) {
            return 'Claude API nije odgovorio.';
        }
        if (!empty($body['_error'])) {
            $msg = is_array($body['_body'] ?? null) ? json_encode($body['_body']) : (string) ($body['_body'] ?? 'HTTP greška');
            return 'Claude API: ' . $msg;
        }
        $text = $body['content'][0]['text'] ?? '';
        return self::parseJson($text);
    }

    /** @return array<string, mixed>|string */
    private static function callOpenAi(string $prompt, string $apiKey): array|string
    {
        $model = (string) AutoVestiConfig::get('openai_model', 'gpt-4o-mini');
        $body = HttpClient::postJson(
            'https://api.openai.com/v1/chat/completions',
            [
                'model' => $model,
                'max_completion_tokens' => 4800,
                'temperature' => 0.65,
                'messages' => [
                    ['role' => 'system', 'content' => self::SYSTEM],
                    ['role' => 'user', 'content' => $prompt],
                ],
            ],
            ['Authorization: Bearer ' . $apiKey]
        );
        if (!$body) {
            return 'OpenAI API nije odgovorio.';
        }
        if (!empty($body['_error'])) {
            $err = $body['error']['message'] ?? (string) ($body['_body'] ?? 'HTTP greška');
            return 'OpenAI API: ' . $err;
        }
        $text = $body['choices'][0]['message']['content'] ?? '';
        return self::parseJson($text);
    }

    /** @return array<string, mixed>|string */
    public static function parseJson(string $text): array|string
    {
        $text = preg_replace('/```(?:json)?\s*/i', '', $text) ?? $text;
        $text = trim(str_replace('```', '', $text));
        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if ($start !== false && $end !== false) {
            $text = substr($text, $start, $end - $start + 1);
        }
        $data = json_decode($text, true);
        if (!is_array($data) || empty($data['title']) || empty($data['content'])) {
            return 'Ne mogu parsirati AI odgovor: ' . mb_substr($text, 0, 150);
        }
        return $data;
    }

    public static function askDuplicate(string $newTitle, string $titles, string $provider, string $apiKey): bool
    {
        $prompt = 'Da li je nova vijest ista tema/dogadjaj kao neka od objavljenih? Ignorisi razlike u formulaciji.' . "\n"
            . 'Odgovori SAMO sa: DA ili NE' . "\n\nNOVA VIJEST: \"" . $newTitle . "\"\n\nVEC OBJAVLJENE:\n" . $titles;

        if ($provider === 'openai') {
            $body = HttpClient::postJson(
                'https://api.openai.com/v1/chat/completions',
                [
                    'model' => AutoVestiConfig::get('openai_model', 'gpt-4o-mini'),
                    'max_completion_tokens' => 5,
                    'messages' => [
                        ['role' => 'system', 'content' => 'Odgovori samo sa DA ili NE.'],
                        ['role' => 'user', 'content' => $prompt],
                    ],
                ],
                ['Authorization: Bearer ' . $apiKey],
                15
            );
            $ans = strtoupper(trim((string) ($body['choices'][0]['message']['content'] ?? '')));
        } else {
            $body = HttpClient::postJson(
                'https://api.anthropic.com/v1/messages',
                [
                    'model' => 'claude-haiku-4-5-20251001',
                    'max_tokens' => 5,
                    'messages' => [['role' => 'user', 'content' => $prompt]],
                ],
                [
                    'x-api-key: ' . $apiKey,
                    'anthropic-version: 2023-06-01',
                ],
                15
            );
            $ans = strtoupper(trim((string) ($body['content'][0]['text'] ?? '')));
        }
        return str_contains($ans, 'DA');
    }
}
