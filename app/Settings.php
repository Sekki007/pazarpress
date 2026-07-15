<?php

declare(strict_types=1);

final class Settings
{
    private const FILE = __DIR__ . '/../storage/settings.json';

    private static function defaults(): array
    {
        return [
            'analytics_provider' => '', // plausible | matomo | ''
            'analytics_id' => '',
            'ad_sidebar_html' => '',
            'ad_article_html' => '',
            'ad_home_html' => '',
            'newsletter_confirm' => true,
            'site_tagline' => 'Lokalni news portal — Grad, našim očima.',
            'og_default_image' => '',
            'facebook_page_url' => '',
            'facebook_auto_share' => false,
            'facebook_page_id' => '',
            'facebook_page_access_token' => '',
            'auto_feature_today' => true,
            'feature_rotate_hours' => 3,
            'restaurants_enabled' => false,
        ];
    }

    public static function all(): array
    {
        $data = self::defaults();
        if (is_file(self::FILE)) {
            $json = json_decode((string) file_get_contents(self::FILE), true);
            if (is_array($json)) {
                $data = array_merge($data, $json);
            }
        }
        return $data;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::all()[$key] ?? $default;
    }

    public static function save(array $input): void
    {
        $allowed = array_keys(self::defaults());
        $current = self::all();
        foreach ($allowed as $key) {
            if (array_key_exists($key, $input)) {
                $current[$key] = $input[$key];
            }
        }
        $dir = dirname(self::FILE);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        file_put_contents(self::FILE, json_encode($current, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}
