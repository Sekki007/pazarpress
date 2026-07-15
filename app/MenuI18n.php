<?php

declare(strict_types=1);

final class MenuI18n
{
    public const LANGS = [
        'bs' => 'Bosanski',
        'en' => 'English',
        'tr' => 'Türkçe',
        'de' => 'Deutsch',
    ];

  /** @var array<string, array<string, string>> */
    private const UI = [
        'bs' => [
            'hours' => 'Radno vrijeme',
            'today' => 'Danas',
            'location' => 'Lokacija',
            'navigation' => 'Navigacija',
            'open_map' => 'Mapa',
            'apple_maps' => 'Apple Maps',
            'menu' => 'Meni',
            'all' => 'Sve',
            'reviews' => 'Recenzije',
            'share' => 'Podijeli',
            'unavailable' => 'Nedostupno',
            'menu_items' => 'stavki',
            'call' => 'Poziv',
            'how_to_get' => 'Kako doći',
            'digital_menu' => 'Digitalni meni',
            'leave_review' => 'Ostavite ocjenu',
            'your_name' => 'Ime',
            'rating' => 'Ocjena',
            'comment' => 'Komentar',
            'send' => 'Pošalji',
            'no_reviews' => 'Još nema recenzija.',
            'menu_empty' => 'Meni još nije popunjen.',
            'review_moderated' => 'Recenzije se moderiraju.',
            'lang_label' => 'Jezik menija',
            'about' => 'O restoranu',
        ],
        'en' => [
            'hours' => 'Opening hours',
            'today' => 'Today',
            'location' => 'Location',
            'navigation' => 'Directions',
            'open_map' => 'Open map',
            'apple_maps' => 'Apple Maps',
            'menu' => 'Menu',
            'all' => 'All',
            'reviews' => 'Reviews',
            'share' => 'Share',
            'unavailable' => 'Unavailable',
            'menu_items' => 'items',
            'call' => 'Call',
            'how_to_get' => 'Directions',
            'digital_menu' => 'Digital menu',
            'leave_review' => 'Leave a review',
            'your_name' => 'Name',
            'rating' => 'Rating',
            'comment' => 'Comment',
            'send' => 'Send',
            'no_reviews' => 'No reviews yet.',
            'menu_empty' => 'Menu not available yet.',
            'review_moderated' => 'Reviews are moderated.',
            'lang_label' => 'Menu language',
            'about' => 'About',
        ],
        'tr' => [
            'hours' => 'Çalışma saatleri',
            'today' => 'Bugün',
            'location' => 'Konum',
            'navigation' => 'Yol tarifi',
            'open_map' => 'Haritayı aç',
            'apple_maps' => 'Apple Maps',
            'menu' => 'Menü',
            'all' => 'Tümü',
            'reviews' => 'Yorumlar',
            'share' => 'Paylaş',
            'unavailable' => 'Mevcut değil',
            'menu_items' => 'ürün',
            'call' => 'Ara',
            'how_to_get' => 'Yol tarifi',
            'digital_menu' => 'Dijital menü',
            'leave_review' => 'Yorum bırakın',
            'your_name' => 'İsim',
            'rating' => 'Puan',
            'comment' => 'Yorum',
            'send' => 'Gönder',
            'no_reviews' => 'Henüz yorum yok.',
            'menu_empty' => 'Menü henüz hazır değil.',
            'review_moderated' => 'Yorumlar moderasyon sonrası yayınlanır.',
            'lang_label' => 'Menü dili',
            'about' => 'Hakkında',
        ],
        'de' => [
            'hours' => 'Öffnungszeiten',
            'today' => 'Heute',
            'location' => 'Standort',
            'navigation' => 'Route',
            'open_map' => 'Karte',
            'apple_maps' => 'Apple Maps',
            'menu' => 'Speisekarte',
            'all' => 'Alle',
            'reviews' => 'Bewertungen',
            'share' => 'Teilen',
            'unavailable' => 'Nicht verfügbar',
            'menu_items' => 'Gerichte',
            'call' => 'Anruf',
            'how_to_get' => 'Route',
            'digital_menu' => 'Digitale Speisekarte',
            'leave_review' => 'Bewertung abgeben',
            'your_name' => 'Name',
            'rating' => 'Bewertung',
            'comment' => 'Kommentar',
            'send' => 'Senden',
            'no_reviews' => 'Noch keine Bewertungen.',
            'menu_empty' => 'Speisekarte noch nicht verfügbar.',
            'review_moderated' => 'Bewertungen werden moderiert.',
            'lang_label' => 'Menüsprache',
            'about' => 'Über uns',
        ],
    ];

    /** @param array<string, mixed> $restaurant */
    public static function enabledLangs(array $restaurant): array
    {
        if (isset($restaurant['menuLangs']) && is_array($restaurant['menuLangs'])) {
            $langs = $restaurant['menuLangs'];
        } else {
            $raw = json_decode((string) ($restaurant['menuLangsJson'] ?? ''), true);
            $langs = is_array($raw) ? $raw : ['bs', 'en', 'tr'];
        }
        $langs = array_values(array_unique(array_merge(['bs'], array_filter($langs, static fn ($l) => isset(self::LANGS[$l])))));

        return count($langs) > 1 ? $langs : ['bs'];
    }

    /** @param array<string, mixed> $restaurant */
    public static function resolveLang(array $restaurant, ?string $requested = null): string
    {
        $enabled = self::enabledLangs($restaurant);
        $req = strtolower(trim((string) $requested));
        if ($req !== '' && in_array($req, $enabled, true)) {
            return $req;
        }
        if (!empty($_COOKIE['menu_lang']) && in_array($_COOKIE['menu_lang'], $enabled, true)) {
            return (string) $_COOKIE['menu_lang'];
        }

        return 'bs';
    }

    public static function ui(string $lang, string $key): string
    {
        return self::UI[$lang][$key] ?? self::UI['bs'][$key] ?? $key;
    }

    /** @return array<string, string> */
    public static function uiLabels(string $lang): array
    {
        $out = [];
        foreach (array_keys(self::UI['bs']) as $key) {
            $out[$key] = self::ui($lang, $key);
        }

        return $out;
    }

    /** @param array<string, mixed> $row */
    public static function localizeCategory(array $row, string $lang): array
    {
        $row['name'] = self::pickText($row, $lang, 'name');

        return $row;
    }

    /** @param array<string, mixed> $row */
    public static function localizeItem(array $row, string $lang): array
    {
        $row['name'] = self::pickText($row, $lang, 'name');
        if (array_key_exists('description', $row)) {
            $row['description'] = self::pickText($row, $lang, 'description', true);
        }

        return $row;
    }

    /**
     * @param list<array<string, mixed>> $menu
     * @return list<array<string, mixed>>
     */
    public static function localizeMenu(array $menu, string $lang): array
    {
        if ($lang === 'bs') {
            return $menu;
        }
        foreach ($menu as &$cat) {
            $cat = self::localizeCategory($cat, $lang);
            $cat['items'] = array_map(
                static fn (array $item): array => self::localizeItem($item, $lang),
                $cat['items'] ?? []
            );
        }

        return $menu;
    }

    /** @param array<string, mixed> $row */
    private static function pickText(array $row, string $lang, string $field, bool $allowEmpty = false): string
    {
        if ($lang === 'bs') {
            return (string) ($row[$field] ?? '');
        }
        $translations = is_array($row['translations'] ?? null)
            ? $row['translations']
            : (json_decode((string) ($row['translationsJson'] ?? ''), true) ?: []);
        $val = trim((string) ($translations[$lang][$field] ?? ''));
        if ($val !== '' || $allowEmpty) {
            return $val !== '' ? $val : (string) ($row[$field] ?? '');
        }

        return (string) ($row[$field] ?? '');
    }

    /** @return array<string, array{name?: string, description?: string}> */
    public static function translationsFromPost(array $post, string $prefix = ''): array
    {
        $out = [];
        foreach (self::LANGS as $code => $_label) {
            if ($code === 'bs') {
                continue;
            }
            $name = trim((string) ($post[$prefix . 'name_' . $code] ?? ''));
            $desc = trim((string) ($post[$prefix . 'description_' . $code] ?? ''));
            if ($name !== '' || $desc !== '') {
                $out[$code] = array_filter([
                    'name' => $name ?: null,
                    'description' => $desc ?: null,
                ]);
            }
        }

        return $out;
    }

    /** @param array<string, mixed> $restaurant */
    public static function menuLangsFromPost(array $post): array
    {
        $langs = ['bs'];
        foreach (array_keys(self::LANGS) as $code) {
            if ($code === 'bs') {
                continue;
            }
            if (!empty($post['menu_lang_' . $code])) {
                $langs[] = $code;
            }
        }

        return array_values(array_unique($langs));
    }

    public static function encodeMenuLangs(array $langs): string
    {
        return json_encode(array_values(array_unique(array_merge(['bs'], $langs))), JSON_UNESCAPED_UNICODE);
    }
}
