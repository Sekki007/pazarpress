<?php

declare(strict_types=1);

final class RestaurantMenuScan
{
    private const PROMPT = <<<'TXT'
Analiziraj sliku cjenovnika / menija restorana. Izvuci sve kategorije i stavke sa cijenama.
Pravila:
- Piši na bosanskom/hrvatskom/srpskom (ijekavica)
- Cijene kao broj (npr. 450) ako su vidljive; inače priceLabel npr. "Po dogovoru"
- Valuta: RSD ako nije drugačije naznačeno
- Ne izmišljaj stavke koje nisu na slici
- Ako tekst nije čitljiv, vrati prazan categories niz i notes sa objašnjenjem

Vrati ISKLJUČIVO JSON:
{"categories":[{"name":"Kategorija","items":[{"name":"Jelo","description":"kratko ili prazno","price":450,"priceLabel":null,"currency":"RSD","tags":[]}]}],"notes":""}
TXT;

    /** @return array{categories: list<array>, notes?: string}|string */
    public static function extractFromImagePath(string $absolutePath): array|string
    {
        if (!is_file($absolutePath)) {
            return 'Slika nije pronađena.';
        }

        $cfg = AutoVestiConfig::all();
        $provider = (string) ($cfg['ai_provider'] ?? 'openai');
        $apiKey = trim($provider === 'openai'
            ? (string) ($cfg['openai_api_key'] ?? '')
            : (string) ($cfg['api_key'] ?? ''));

        if ($apiKey === '') {
            return 'AI API ključ nije podešen. Idite na Admin → Auto Vesti i unesite OpenAI ili Claude ključ.';
        }

        $mime = self::detectMime($absolutePath);
        $b64 = base64_encode((string) file_get_contents($absolutePath));
        if ($b64 === '') {
            return 'Ne mogu učitati sliku.';
        }

        if ($provider === 'openai') {
            return self::openAiVision($b64, $mime, $apiKey);
        }

        return self::claudeVision($b64, $mime, $apiKey);
    }

    /** @return array{categories: list<array>, notes?: string}|string */
    private static function openAiVision(string $b64, string $mime, string $apiKey): array|string
    {
        $model = (string) AutoVestiConfig::get('openai_model', 'gpt-4o-mini');
        if (!str_contains($model, 'gpt-4')) {
            $model = 'gpt-4o-mini';
        }

        $body = HttpClient::postJson(
            'https://api.openai.com/v1/chat/completions',
            [
                'model' => $model,
                'max_completion_tokens' => 4000,
                'temperature' => 0.2,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => [
                            ['type' => 'text', 'text' => self::PROMPT],
                            ['type' => 'image_url', 'image_url' => ['url' => 'data:' . $mime . ';base64,' . $b64]],
                        ],
                    ],
                ],
            ],
            ['Authorization: Bearer ' . $apiKey],
            90
        );

        if (!$body || !empty($body['_error'])) {
            $err = $body['error']['message'] ?? (string) ($body['_body'] ?? 'HTTP greška');

            return 'OpenAI: ' . $err;
        }

        $text = (string) ($body['choices'][0]['message']['content'] ?? '');

        return self::parseMenuJson($text);
    }

    /** @return array{categories: list<array>, notes?: string}|string */
    private static function claudeVision(string $b64, string $mime, string $apiKey): array|string
    {
        $model = (string) AutoVestiConfig::get('claude_model', 'claude-sonnet-4-20250514');
        $body = HttpClient::postJson(
            'https://api.anthropic.com/v1/messages',
            [
                'model' => $model,
                'max_tokens' => 4000,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'image',
                                'source' => [
                                    'type' => 'base64',
                                    'media_type' => $mime,
                                    'data' => $b64,
                                ],
                            ],
                            ['type' => 'text', 'text' => self::PROMPT],
                        ],
                    ],
                ],
            ],
            [
                'x-api-key: ' . $apiKey,
                'anthropic-version: 2023-06-01',
                'content-type: application/json',
            ],
            90
        );

        if (!$body || !empty($body['_error'])) {
            $msg = is_array($body['_body'] ?? null) ? json_encode($body['_body']) : (string) ($body['_body'] ?? 'HTTP greška');

            return 'Claude: ' . $msg;
        }

        $text = (string) ($body['content'][0]['text'] ?? '');

        return self::parseMenuJson($text);
    }

    /** @return array{categories: list<array>, notes?: string}|string */
    private static function parseMenuJson(string $text): array|string
    {
        $text = preg_replace('/```(?:json)?\s*/i', '', $text) ?? $text;
        $text = trim(str_replace('```', '', $text));
        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if ($start !== false && $end !== false) {
            $text = substr($text, $start, $end - $start + 1);
        }
        $data = json_decode($text, true);
        if (!is_array($data) || !isset($data['categories']) || !is_array($data['categories'])) {
            return 'AI nije vratio valjan meni. Pokušajte jasniju fotografiju ili ručni unos.';
        }

        return [
            'categories' => $data['categories'],
            'notes' => (string) ($data['notes'] ?? ''),
        ];
    }

    private static function detectMime(string $path): string
    {
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $path);
            finfo_close($finfo);
            if (is_string($mime) && str_starts_with($mime, 'image/')) {
                return $mime;
            }
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($ext) {
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            default => 'image/jpeg',
        };
    }
}
