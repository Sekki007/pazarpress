<?php

declare(strict_types=1);

final class AutoVestiImages
{
    public static function aiEnabled(): bool
    {
        return !empty(AutoVestiConfig::get('use_ai_image', true))
            && trim((string) AutoVestiConfig::get('openai_api_key', '')) !== '';
    }

    public static function defaultReady(): bool
    {
        $path = trim((string) AutoVestiConfig::get('default_image_path', ''));
        if ($path === '') {
            return false;
        }
        $full = self::uploadPath($path);
        return is_file($full);
    }

    public static function defaultAutoApply(): bool
    {
        return !empty(AutoVestiConfig::get('use_default_image', true)) && self::defaultReady();
    }

    public static function defaultPath(): string
    {
        return trim((string) AutoVestiConfig::get('default_image_path', ''));
    }

    public static function defaultUrl(): string
    {
        $path = self::defaultPath();
        return $path !== '' ? absolute_url($path) : '';
    }

    public static function defaultLabel(): string
    {
        $label = trim((string) AutoVestiConfig::get('default_image_label', ''));
        return $label !== '' ? $label : ('Najnovija vijest ' . (config('site_name') ?: 'Sandžak.net'));
    }

    public static function aiCreditLabel(): string
    {
        $label = trim((string) AutoVestiConfig::get('ai_image_credit_label', 'Ilustracija (AI)'));
        return $label !== '' ? $label : 'Ilustracija (AI)';
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    public static function applyDefaultToRow(array $row): array
    {
        if (!self::defaultReady()) {
            return $row;
        }
        $path = self::defaultPath();
        $row['image_url'] = self::defaultUrl();
        $row['image_local_path'] = $path;
        $row['image_default'] = '1';
        $row['image_credit'] = '';
        $row['image_ai'] = '0';
        if (isset($row['item']) && is_array($row['item'])) {
            $row['item']['image_url'] = $row['image_url'];
        }
        return $row;
    }

    /** @return array{path:string,url:string}|string error */
    public static function sideload(string $url, string $title, string $pageUrl = ''): array|string
    {
        $dl = AutoVestiContent::downloadCover($url, $title, $pageUrl);
        if (!$dl['path']) {
            return $dl['error'] ?: 'Upload slike nije uspio.';
        }
        return ['path' => $dl['path'], 'url' => absolute_url($dl['path'])];
    }

    /** @return string|array{path:string,url:string} */
    public static function generateAi(string $title, string $preview = ''): string|array
    {
        if (!self::aiEnabled()) {
            return 'OpenAI ključ nije podešen ili su AI slike isključene.';
        }
        $prompt = 'Profesionalna fotorealisticna novinska ilustracija za informativni portal. '
            . 'Bez teksta, natpisa, logotipa i watermarka. Neutralan novinarski ton. Tema: '
            . mb_substr(trim($title . '. ' . $preview), 0, 400, 'UTF-8');

        $model = (string) AutoVestiConfig::get('ai_image_model', 'gpt-image-1');
        $size = (string) AutoVestiConfig::get('ai_image_size', '1024x1024');
        $quality = (string) AutoVestiConfig::get('ai_image_quality', 'medium');
        $apiKey = trim((string) AutoVestiConfig::get('openai_api_key', ''));

        $body = HttpClient::postJson(
            'https://api.openai.com/v1/images/generations',
            [
                'model' => $model,
                'prompt' => $prompt,
                'n' => 1,
                'size' => $size,
                'quality' => $quality,
            ],
            ['Authorization: Bearer ' . $apiKey],
            120
        );
        if (!$body || !empty($body['_error'])) {
            $msg = is_array($body['error'] ?? null) ? ($body['error']['message'] ?? 'HTTP greška') : 'HTTP greška';
            return 'AI slika: ' . $msg;
        }
        if (!empty($body['data'][0]['b64_json'])) {
            $binary = base64_decode((string) $body['data'][0]['b64_json']);
            if ($binary === false || strlen($binary) < 200) {
                return 'AI slika: prazan odgovor.';
            }
            $uploadDir = rtrim(config('upload_dir'), '/\\');
            $name = 'avm-ai-' . time() . '-' . bin2hex(random_bytes(4)) . '.png';
            $dest = $uploadDir . DIRECTORY_SEPARATOR . $name;
            if (@file_put_contents($dest, $binary) === false) {
                return 'AI slika: snimanje nije uspjelo.';
            }
            ImageWatermark::apply($dest);
            ImageProcessor::process($dest);
            $path = '/uploads/' . $name;
            return ['path' => $path, 'url' => absolute_url($path)];
        }
        if (!empty($body['data'][0]['url'])) {
            return self::sideload((string) $body['data'][0]['url'], $title);
        }
        return 'OpenAI nije vratio sliku.';
    }

    /** @return array{path:string,url:string}|string */
    public static function saveDefaultUpload(array $file): array|string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || empty($file['tmp_name'])) {
            return 'Nije izabrana datoteka.';
        }
        $ext = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
            return 'Dozvoljeni formati: JPG, PNG, WEBP, GIF.';
        }
        $uploadDir = rtrim(config('upload_dir'), '/\\');
        $name = 'avm-default.' . ($ext === 'jpeg' ? 'jpg' : $ext);
        $dest = $uploadDir . DIRECTORY_SEPARATOR . $name;
        if (!move_uploaded_file((string) $file['tmp_name'], $dest)) {
            return 'Upload nije uspio.';
        }
        ImageWatermark::apply($dest);
        ImageProcessor::process($dest);
        $path = '/uploads/' . $name;
        AutoVestiConfig::updatePartial(['default_image_path' => $path]);
        AutoVestiConfig::log('Default slika uploadovana.');
        return ['path' => $path, 'url' => absolute_url($path)];
    }

    private static function uploadPath(string $webPath): string
    {
        $webPath = '/' . ltrim($webPath, '/');
        if (str_starts_with($webPath, '/uploads/')) {
            return rtrim(config('upload_dir'), '/\\') . DIRECTORY_SEPARATOR . basename($webPath);
        }
        return $webPath;
    }
}
