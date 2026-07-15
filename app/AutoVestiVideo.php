<?php

declare(strict_types=1);

final class AutoVestiVideo
{
    private const FETCH_UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/122.0.0.0 Safari/537.36';

    /** @return array{type:string,url:string,embed_id?:string} */
    public static function extract(string $html): array
    {
        $empty = ['type' => '', 'url' => ''];
        if ($html === '') {
            return $empty;
        }

        $ytId = self::extractYoutubeId($html);
        if ($ytId !== null) {
            $url = 'https://www.youtube.com/watch?v=' . $ytId;
            return ['type' => 'youtube', 'url' => $url, 'embed_id' => $ytId];
        }
        if (preg_match('/vimeo\.com\/(?:video\/)?(\d+)/', $html, $m)) {
            return ['type' => 'vimeo', 'url' => 'https://vimeo.com/' . $m[1], 'embed_id' => $m[1]];
        }
        if (preg_match('/dai\.ly\/([a-zA-Z0-9]+)/', $html, $m)) {
            return ['type' => 'dailymotion', 'url' => 'https://www.dailymotion.com/video/' . $m[1], 'embed_id' => $m[1]];
        }
        if (preg_match('/dailymotion\.com\/video\/([a-zA-Z0-9]+)/', $html, $m)) {
            return ['type' => 'dailymotion', 'url' => 'https://www.dailymotion.com/video/' . $m[1], 'embed_id' => $m[1]];
        }
        if (preg_match('/facebook\.com\/[^"\']+\/videos\/(\d+)/', $html, $m)) {
            return ['type' => 'facebook', 'url' => 'https://www.facebook.com/video.php?v=' . $m[1], 'embed_id' => $m[1]];
        }
        if (preg_match('/fb\.watch\/([a-zA-Z0-9_-]+)/', $html, $m)) {
            return ['type' => 'facebook', 'url' => 'https://fb.watch/' . $m[1], 'embed_id' => $m[1]];
        }
        if (preg_match('/<video[^>]+src=["\']([^"\']+\.mp4[^"\']*)["\']/i', $html, $m)) {
            return ['type' => 'mp4', 'url' => $m[1]];
        }
        if (preg_match('/(https?:\/\/[^\s"\'<>]+\.mp4(?:\?[^\s"\'<>]*)?)/i', $html, $m)) {
            return ['type' => 'mp4', 'url' => $m[1]];
        }

        return $empty;
    }

    /** @param array<string, mixed> $item */
    public static function enrich(array &$item): void
    {
        $video = self::get($item);
        if (!empty($video['type'])) {
            return;
        }
        $sources = [
            (string) ($item['_content_html'] ?? ''),
            (string) ($item['description'] ?? ''),
        ];
        if (!empty($item['link'])) {
            $html = HttpClient::get((string) $item['link'], 20, self::FETCH_UA);
            if ($html) {
                $sources[] = $html;
                if (empty($item['_content_html'])) {
                    $item['_content_html'] = $html;
                }
            }
        }
        foreach ($sources as $src) {
            $found = self::extract($src);
            if (!empty($found['type'])) {
                self::apply($item, $found);
                return;
            }
        }
    }

    /** @param array<string, mixed> $item @param array{type:string,url:string,embed_id?:string} $video */
    public static function apply(array &$item, array $video): void
    {
        if (empty($video['type'])) {
            return;
        }
        $item['_video'] = $video;
        if ($video['type'] === 'youtube') {
            $item['youtube_url'] = $video['url'];
        }
    }

    /** @param array<string, mixed> $item @return array{type:string,url:string,embed_id?:string} */
    public static function get(array $item): array
    {
        if (!empty($item['_video']) && is_array($item['_video'])) {
            return $item['_video'];
        }
        if (!empty($item['youtube_url'])) {
            $v = self::extract((string) $item['youtube_url']);
            if ($v['type'] !== '') {
                return $v;
            }
        }
        return ['type' => '', 'url' => ''];
    }

    /** @param array{type:string,url:string,embed_id?:string} $video */
    public static function insertIntoContent(string $content, array $video): string
    {
        $embed = self::embedHtml($video);
        return $embed !== '' ? $embed . "\n" . $content : $content;
    }

    /** @param array{type:string,url:string,embed_id?:string} $video */
    public static function embedHtml(array $video): string
    {
        $type = $video['type'] ?? '';
        $id = $video['embed_id'] ?? '';
        $url = $video['url'] ?? '';

        return match ($type) {
            'youtube' => $id !== ''
                ? '<div class="avc-video-wrap"><iframe src="https://www.youtube.com/embed/' . e($id) . '" allowfullscreen loading="lazy"></iframe></div>'
                : '',
            'vimeo' => $id !== ''
                ? '<div class="avc-video-wrap"><iframe src="https://player.vimeo.com/video/' . e($id) . '" allowfullscreen loading="lazy"></iframe></div>'
                : '',
            'dailymotion' => $id !== ''
                ? '<div class="avc-video-wrap"><iframe src="https://www.dailymotion.com/embed/video/' . e($id) . '" allowfullscreen loading="lazy"></iframe></div>'
                : '',
            'facebook' => $url !== ''
                ? '<div class="avc-video-wrap"><iframe src="https://www.facebook.com/plugins/video.php?href=' . rawurlencode($url) . '&show_text=0" allowfullscreen loading="lazy"></iframe></div>'
                : '',
            'mp4' => $url !== ''
                ? '<div class="avc-mp4-wrap"><video controls playsinline preload="metadata"><source src="' . e($url) . '" type="video/mp4"></video></div>'
                : '',
            default => '',
        };
    }

    /** @param array{type:string,url:string} $video */
    public static function schemaObject(array $video, string $title, string $pageUrl): ?array
    {
        if (empty($video['type']) || empty($video['url'])) {
            return null;
        }
        $obj = [
            '@type' => 'VideoObject',
            'name' => $title,
            'uploadDate' => date('c'),
            'contentUrl' => $video['url'],
            'embedUrl' => $video['url'],
        ];
        if ($video['type'] === 'youtube' && !empty($video['embed_id'])) {
            $obj['embedUrl'] = 'https://www.youtube.com/embed/' . $video['embed_id'];
        }
        $obj['mainEntityOfPage'] = ['@type' => 'WebPage', '@id' => $pageUrl];
        return $obj;
    }

    private static function extractYoutubeId(string $html): ?string
    {
        $patterns = [
            '/(?:youtube-nocookie\.com|youtube\.com)\/(?:embed\/|watch\?[^"\']*?v=|shorts\/|live\/|v\/)([a-zA-Z0-9_-]{11})/i',
            '/youtu\.be\/([a-zA-Z0-9_-]{11})/i',
            '/(?:data-(?:src|url|lazy-src)|src|href)\s*=\s*["\'][^"\']*(?:youtube-nocookie\.com|youtube\.com|youtu\.be)[^"\']*(?:embed\/|watch\?[^"\']*?v=|shorts\/|live\/|v\/|youtu\.be\/)([a-zA-Z0-9_-]{11})/i',
            '/property=["\']og:video(?::url)?["\'][^>]+content=["\'][^"\']*(?:youtube[^"\']*)([a-zA-Z0-9_-]{11})/i',
            '/"embedUrl"\s*:\s*"https?:\/\/(?:www\.)?(?:youtube-nocookie\.com|youtube\.com)\/embed\/([a-zA-Z0-9_-]{11})/i',
            '/"contentUrl"\s*:\s*"https?:\/\/(?:www\.)?(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]{11})/i',
            '/\[embed\]\s*(https?:\/\/[^\]\s]+)\s*\[\/embed\]/i',
        ];

        foreach ($patterns as $pattern) {
            if (!preg_match($pattern, $html, $m)) {
                continue;
            }
            $candidate = $m[1];
            if (strlen($candidate) === 11 && preg_match('/^[a-zA-Z0-9_-]+$/', $candidate)) {
                return $candidate;
            }
            if (str_starts_with($candidate, 'http')) {
                $nested = self::extractYoutubeId($candidate);
                if ($nested !== null) {
                    return $nested;
                }
            }
        }

        return null;
    }
}
