<?php

declare(strict_types=1);

/**
 * SEO optimization layer — runs after article text is finalized.
 * Generates SEO metadata only; never changes title, content, excerpt or facts.
 */
final class AutoVestiSeoLayer
{
    private const SYSTEM = 'Ti si SEO urednik lokalnog portala. Generišeš samo SEO metapodatke. '
        . 'Ne menjaš tekst članka ni činjenice. Vraćaš ISKLJUČIVO validan JSON.';

    /**
     * @param array<string,mixed> $aiData
     * @param array<string,mixed>|null $factLock
     * @return array{data:array<string,mixed>,seo_report:array<string,mixed>,applied:bool}
     */
    public static function apply(
        array $aiData,
        ?array $factLock,
        string $provider,
        string $apiKey
    ): array {
        $original = $aiData;
        $generated = self::callAi(self::buildPrompt($aiData, $factLock), $provider, $apiKey);

        if (!is_array($generated)) {
            $fallback = self::fallbackSeo($aiData, $factLock);
            return [
                'data' => self::mergeSeoOnly($aiData, $fallback),
                'seo_report' => self::buildReport($fallback, false, ['message' => (string) $generated]),
                'applied' => false,
            ];
        }

        $seo = self::normalizeSeo($generated, $aiData, $factLock);
        $merged = self::mergeSeoOnly($aiData, $seo);
        $report = self::buildReport($seo, true);

        if (!self::articleTextUnchanged($original, $merged)) {
            return [
                'data' => $original,
                'seo_report' => self::buildReport($seo, false, ['message' => 'Revert: SEO sloj je pokušao promenu teksta članka.']),
                'applied' => false,
            ];
        }

        return [
            'data' => $merged,
            'seo_report' => $report,
            'applied' => true,
        ];
    }

    /**
     * @param array<string,mixed> $aiData
     * @param array<string,mixed>|null $factLock
     */
    private static function buildPrompt(array $aiData, ?array $factLock): string
    {
        $context = '';
        if ($factLock && !empty($factLock['protected']['persons'])) {
            $context .= 'Glavne osobe: ' . implode(', ', array_slice((array) $factLock['protected']['persons'], 0, 5)) . "\n";
        }

        $article = json_encode([
            'title' => (string) ($aiData['title'] ?? ''),
            'excerpt' => (string) ($aiData['excerpt'] ?? ''),
            'content' => strip_tags((string) ($aiData['content'] ?? '')),
        ], JSON_UNESCAPED_UNICODE);

        return "SEO OPTIMIZATION LAYER\n\n"
            . "Na osnovu gotovog članka generiši SEO metapodatke.\n"
            . "NE MENJAJ tekst članka (title, excerpt, content ostaju kakvi jesu u izvoru).\n\n"
            . $context
            . "Pravila:\n"
            . "1) seo_title: max 65 karaktera, mora sadržati glavnu osobu ili temu\n"
            . "2) meta_description: 140-160 karaktera, prirodan opis\n"
            . "3) slug: latinica, mala slova, bez nepotrebnih reči\n"
            . "4) focus_keyphrase: glavna pretraga korisnika\n"
            . "5) secondary_keywords: 5-8 povezanih tema (niz stringova)\n"
            . "6) image_alt: opis slike sa ključnim rečima\n\n"
            . "Članak:\n" . $article . "\n\n"
            . 'Vrati ISKLJUČIVO JSON: {"seo_title":"...","meta_description":"...","slug":"...","focus_keyphrase":"...",'
            . '"secondary_keywords":["..."],"image_alt":"..."}';
    }

    /**
     * @param array<string,mixed> $seo
     * @param array<string,mixed> $aiData
     * @param array<string,mixed>|null $factLock
     * @return array<string,mixed>
     */
    private static function normalizeSeo(array $seo, array $aiData, ?array $factLock): array
    {
        $title = (string) ($aiData['title'] ?? '');
        $excerpt = (string) ($aiData['excerpt'] ?? '');

        $seoTitle = trim((string) ($seo['seo_title'] ?? $title));
        if (mb_strlen($seoTitle, 'UTF-8') > 65) {
            $seoTitle = mb_substr($seoTitle, 0, 65, 'UTF-8');
        }

        $persons = array_values(array_filter(array_map('strval', (array) (($factLock['protected'] ?? [])['persons'] ?? []))));
        if ($persons !== [] && !self::containsAnyPersonOrTopic($seoTitle, $persons, $title)) {
            $main = (string) $persons[0];
            $seoTitle = mb_substr($main . ': ' . $seoTitle, 0, 65, 'UTF-8');
        }

        $meta = trim((string) ($seo['meta_description'] ?? $excerpt));
        $metaLen = mb_strlen($meta, 'UTF-8');
        if ($metaLen < 140) {
            $meta = mb_substr($meta . ' ' . $excerpt, 0, 160, 'UTF-8');
        }
        if (mb_strlen($meta, 'UTF-8') > 160) {
            $meta = mb_substr($meta, 0, 160, 'UTF-8');
        }

        $slug = slugify(trim((string) ($seo['slug'] ?? $seoTitle ?: $title)));
        if ($slug === '') {
            $slug = slugify($title);
        }

        $secondary = $seo['secondary_keywords'] ?? $seo['schema_keywords'] ?? [];
        if (!is_array($secondary)) {
            $secondary = [];
        }
        $secondary = array_values(array_filter(array_map(static fn ($v): string => trim((string) $v), $secondary)));

        $focus = trim((string) ($seo['focus_keyphrase'] ?? ($secondary[0] ?? '')));
        $imageAlt = trim((string) ($seo['image_alt'] ?? $seoTitle));

        return [
            'seo_title' => $seoTitle,
            'meta_description' => $meta,
            'slug' => $slug,
            'focus_keyphrase' => $focus,
            'secondary_keywords' => array_slice($secondary, 0, 10),
            'schema_keywords' => array_slice($secondary, 0, 10),
            'image_alt' => $imageAlt,
        ];
    }

    /**
     * @param array<string,mixed> $aiData
     * @param array<string,mixed>|null $factLock
     * @return array<string,mixed>
     */
    private static function fallbackSeo(array $aiData, ?array $factLock): array
    {
        $title = (string) ($aiData['title'] ?? '');
        $excerpt = (string) ($aiData['excerpt'] ?? '');
        $persons = array_values(array_filter(array_map('strval', (array) (($factLock['protected'] ?? [])['persons'] ?? []))));
        $seoTitle = $persons ? mb_substr((string) $persons[0] . ': ' . $title, 0, 65, 'UTF-8') : mb_substr($title, 0, 65, 'UTF-8');
        $meta = mb_substr($excerpt !== '' ? $excerpt : strip_tags((string) ($aiData['content'] ?? '')), 0, 160, 'UTF-8');

        return [
            'seo_title' => $seoTitle,
            'meta_description' => $meta,
            'slug' => slugify($title),
            'focus_keyphrase' => $persons[0] ?? mb_substr($title, 0, 40, 'UTF-8'),
            'secondary_keywords' => array_slice($persons, 0, 5),
            'schema_keywords' => array_slice($persons, 0, 5),
            'image_alt' => $seoTitle,
        ];
    }

    /** @param array<string,mixed> $aiData @param array<string,mixed> $seo @return array<string,mixed> */
    private static function mergeSeoOnly(array $aiData, array $seo): array
    {
        $out = $aiData;
        foreach (['seo_title', 'meta_description', 'slug', 'focus_keyphrase', 'image_alt'] as $key) {
            if (!empty($seo[$key]) && is_string($seo[$key])) {
                $out[$key] = trim($seo[$key]);
            }
        }
        if (!empty($seo['secondary_keywords']) && is_array($seo['secondary_keywords'])) {
            $out['secondary_keywords'] = $seo['secondary_keywords'];
            $out['schema_keywords'] = $seo['secondary_keywords'];
        }
        if (!empty($seo['schema_keywords']) && is_array($seo['schema_keywords'])) {
            $out['schema_keywords'] = $seo['schema_keywords'];
        }
        return $out;
    }

    /** @param array<string,mixed> $before @param array<string,mixed> $after */
    private static function articleTextUnchanged(array $before, array $after): bool
    {
        foreach (['title', 'excerpt', 'content'] as $key) {
            if (trim((string) ($before[$key] ?? '')) !== trim((string) ($after[$key] ?? ''))) {
                return false;
            }
        }
        return true;
    }

    /** @param list<string> $persons */
    private static function containsAnyPersonOrTopic(string $seoTitle, array $persons, string $articleTitle): bool
    {
        $hay = mb_strtolower($seoTitle . ' ' . $articleTitle, 'UTF-8');
        foreach ($persons as $person) {
            $parts = preg_split('/\s+/u', trim($person)) ?: [];
            foreach ($parts as $part) {
                if (mb_strlen($part, 'UTF-8') >= 3 && str_contains($hay, mb_strtolower($part, 'UTF-8'))) {
                    return true;
                }
            }
        }
        $topicWords = preg_split('/\s+/u', mb_strtolower($articleTitle, 'UTF-8')) ?: [];
        foreach ($topicWords as $word) {
            if (mb_strlen($word, 'UTF-8') >= 5 && str_contains(mb_strtolower($seoTitle, 'UTF-8'), $word)) {
                return true;
            }
        }
        return false;
    }

    /** @return array<string,mixed>|string */
    private static function callAi(string $prompt, string $provider, string $apiKey): array|string
    {
        if ($provider === 'openai') {
            $body = HttpClient::postJson(
                'https://api.openai.com/v1/chat/completions',
                [
                    'model' => AutoVestiConfig::get('openai_model', 'gpt-4o-mini'),
                    'max_completion_tokens' => 1200,
                    'temperature' => 0.35,
                    'messages' => [
                        ['role' => 'system', 'content' => self::SYSTEM],
                        ['role' => 'user', 'content' => $prompt],
                    ],
                ],
                ['Authorization: Bearer ' . $apiKey]
            );
            if (!$body || !empty($body['_error'])) {
                return 'SEO layer API greška.';
            }
            $text = (string) ($body['choices'][0]['message']['content'] ?? '');
        } else {
            $body = HttpClient::postJson(
                'https://api.anthropic.com/v1/messages',
                [
                    'model' => AutoVestiConfig::get('claude_model', 'claude-sonnet-4-20250514'),
                    'max_tokens' => 1200,
                    'system' => self::SYSTEM,
                    'messages' => [['role' => 'user', 'content' => $prompt]],
                ],
                [
                    'x-api-key: ' . $apiKey,
                    'anthropic-version: 2023-06-01',
                ]
            );
            if (!$body || !empty($body['_error'])) {
                return 'SEO layer API greška.';
            }
            $text = (string) ($body['content'][0]['text'] ?? '');
        }

        $parsed = self::parseSeoJson($text);
        if (is_string($parsed)) {
            return $parsed;
        }
        return $parsed;
    }

    /** @return array<string,mixed>|string */
    private static function parseSeoJson(string $text): array|string
    {
        $text = preg_replace('/```(?:json)?\s*/i', '', $text) ?? $text;
        $text = trim(str_replace('```', '', $text));
        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if ($start !== false && $end !== false) {
            $text = substr($text, $start, $end - $start + 1);
        }
        $data = json_decode($text, true);
        if (!is_array($data) || empty($data['seo_title']) || empty($data['meta_description'])) {
            return 'Ne mogu parsirati SEO odgovor: ' . mb_substr($text, 0, 150);
        }
        return $data;
    }

    /**
     * @param array<string,mixed> $seo
     * @param array<string,mixed> $meta
     * @return array<string,mixed>
     */
    private static function buildReport(array $seo, bool $applied, array $meta = []): array
    {
        $metaLen = mb_strlen((string) ($seo['meta_description'] ?? ''), 'UTF-8');
        return [
            'status' => 'ok',
            'applied' => $applied,
            'editor' => 'seo_layer',
            'seo_title' => (string) ($seo['seo_title'] ?? ''),
            'meta_description' => (string) ($seo['meta_description'] ?? ''),
            'meta_length' => $metaLen,
            'slug' => (string) ($seo['slug'] ?? ''),
            'focus_keyphrase' => (string) ($seo['focus_keyphrase'] ?? ''),
            'secondary_keywords' => (array) ($seo['secondary_keywords'] ?? []),
            'image_alt' => (string) ($seo['image_alt'] ?? ''),
            'checks' => [
                'seo_title_length' => mb_strlen((string) ($seo['seo_title'] ?? ''), 'UTF-8') <= 65,
                'meta_description_length' => $metaLen >= 140 && $metaLen <= 160,
                'slug_latin' => (string) ($seo['slug'] ?? '') !== '' && preg_match('/^[a-z0-9-]+$/', (string) ($seo['slug'] ?? '')) === 1,
                'article_text_unchanged' => true,
            ],
            'meta' => $meta,
            'checked_at' => date('c'),
        ];
    }
}
