<?php

declare(strict_types=1);

final class FeedParser
{
    /** @return array<int, array{externalId:string,url:string,title:string,summary:string,body:string,image:?string,publishedAt:?string}> */
    public static function parseRss(string $xml): array
    {
        libxml_use_internal_errors(true);
        $feed = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
        if (!$feed) {
            return [];
        }

        $items = [];
        $nodes = $feed->channel->item ?? $feed->entry ?? [];
        foreach ($nodes as $item) {
            $title = self::str($item->title ?? '');
            $link = self::itemLink($item);
            if ($title === '' || $link === '') {
                continue;
            }
            $guid = self::str($item->guid ?? $item->id ?? $link);
            $summary = self::plain($item->description ?? $item->summary ?? '');
            $content = trim((string) ($item->children('content', true)->encoded ?? ''));
            if ($content === '') {
                $content = '<p>' . e($summary) . '</p>';
            }
            $image = self::itemImage($item, $content);
            $published = self::str($item->pubDate ?? $item->published ?? $item->updated ?? '');
            $items[] = [
                'externalId' => hash('sha256', $guid),
                'url' => $link,
                'title' => $title,
                'summary' => strip_tags($summary),
                'body' => $content,
                'image' => $image,
                'publishedAt' => $published !== '' ? date('Y-m-d H:i:s', strtotime($published) ?: time()) : null,
            ];
        }
        return $items;
    }

    private static function itemLink(SimpleXMLElement $item): string
    {
        if (!empty($item->link)) {
            $href = (string) $item->link;
            if ($href !== '') {
                return $href;
            }
            foreach ($item->link->attributes() as $name => $val) {
                if (strtolower((string) $name) === 'href') {
                    return (string) $val;
                }
            }
        }
        return '';
    }

    private static function itemImage(SimpleXMLElement $item, string $html): ?string
    {
        $media = $item->children('media', true);
        if ($media && !empty($media->content)) {
            foreach ($media->content->attributes() as $name => $val) {
                if (strtolower((string) $name) === 'url') {
                    return (string) $val;
                }
            }
        }
        if (!empty($item->enclosure)) {
            foreach ($item->enclosure->attributes() as $name => $val) {
                if (strtolower((string) $name) === 'url' && str_starts_with((string) $val, 'http')) {
                    return (string) $val;
                }
            }
        }
        if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $html, $m)) {
            return $m[1];
        }
        return null;
    }

    private static function plain(mixed $value): string
    {
        return trim(html_entity_decode(strip_tags((string) $value), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    private static function str(mixed $value): string
    {
        return self::plain($value);
    }
}
