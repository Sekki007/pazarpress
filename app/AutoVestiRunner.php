<?php

declare(strict_types=1);

final class AutoVestiRunner
{
    public static function run(): int
    {
        return self::fetchToQueue();
    }

    public static function fetchToQueue(): int
    {
        @set_time_limit(300);
        $cfg = AutoVestiConfig::all();
        $feeds = $cfg['feeds_map'] ?? [];
        $maxNew = max(1, (int) ($cfg['max_fetch_per_run'] ?? 20));
        $useImg = !empty($cfg['use_image']);
        $useFull = !empty($cfg['use_full_article']);
        $useYt = !empty($cfg['use_youtube']);

        if (!$feeds) {
            AutoVestiConfig::log('ERROR: Nema konfigurisanih feedova.');
            return 0;
        }

        $added = 0;
        foreach ($feeds as $feedRow) {
            if ($added >= $maxNew) {
                break;
            }
            $feedUrl = trim((string) ($feedRow['url'] ?? ''));
            if ($feedUrl === '') {
                continue;
            }
            $feedMeta = [
                'url' => $feedUrl,
                'cat' => trim((string) ($feedRow['cat'] ?? '')),
                'breaking_publish' => !empty($feedRow['breaking_publish']) && $feedRow['breaking_publish'] === '1' ? '1' : '0',
            ];
            $remaining = max(1, $maxNew - $added);
            $items = AutoVestiFetcher::fetch((string) ($feedRow['type'] ?? 'rss'), $feedUrl, $remaining);
            if (is_string($items)) {
                AutoVestiConfig::log('Feed greška (' . $feedUrl . '): ' . $items);
                continue;
            }
            foreach ($items as $item) {
                if ($added >= $maxNew) {
                    break;
                }
                $guid = (string) ($item['guid'] ?? '');
                if ($guid === '' || AutoVestiConfig::isSeen($guid) || AutoVestiQueue::hasGuid($guid)) {
                    continue;
                }
                if (!self::isFresh((string) ($item['pub_date'] ?? ''))) {
                    AutoVestiConfig::markSeen($guid);
                    continue;
                }
                if ($useImg) {
                    $item['image_url'] = self::resolveItemImageUrl($item);
                }
                if ($useFull && !empty($item['link']) && strlen((string) $item['description']) < 800) {
                    $full = AutoVestiFetcher::fetchFullArticle((string) $item['link']);
                    if ($full && strlen($full) > strlen((string) $item['description'])) {
                        $item['description'] = $full;
                    }
                }
                if ($useYt) {
                    AutoVestiVideo::enrich($item);
                }
                if (AutoVestiQueue::add($item, $feedMeta)) {
                    AutoVestiConfig::log('U red: ' . ($item['title'] ?? ''));
                    $added++;
                }
            }
        }

        AutoVestiConfig::touchLastFetch();
        AutoVestiConfig::log($added === 0 ? 'Nema novih vesti za red.' : 'Dodato u red: ' . $added . ' vesti.');
        if ($added > 0) {
            AutoVestiStats::record('fetched', ['count' => $added]);
        }
        return $added;
    }

    /** @param list<string> $guids @return array{ok:int,err:int,msg:string} */
    public static function processSelected(array $guids, array $args = []): array
    {
        $result = AutoVestiProcessor::processSelected($guids, $args);
        return ['ok' => $result['ok'], 'err' => $result['err'], 'msg' => $result['msg']];
    }

    /** @param list<string> $guids */
    public static function rejectSelected(array $guids, ?array $editor = null): int
    {
        return AutoVestiProcessor::rejectSelected($guids, $editor);
    }

    /** @param array<string,mixed> $item */
    public static function resolveItemImageUrl(array $item): string
    {
        $link = (string) ($item['link'] ?? '');
        if (!empty($item['image_local_path'])) {
            return absolute_url((string) $item['image_local_path']);
        }
        $url = AutoVestiContent::normalizeImageUrl((string) ($item['image_url'] ?? ''), $link);
        if ($url !== '') {
            return $url;
        }
        $html = (string) ($item['_content_html'] ?? $item['description'] ?? '');
        $url = AutoVestiContent::extractImageFromHtml($html, $link);
        if ($url !== '') {
            return $url;
        }
        if ($link !== '') {
            $og = AutoVestiFetcher::scrapeOgImage($link);
            return AutoVestiContent::normalizeImageUrl($og, $link);
        }
        return '';
    }

    private static function isFresh(string $pubDateStr): bool
    {
        $fromDate = (string) AutoVestiConfig::get('from_date', '');
        if ($fromDate === '') {
            return true;
        }
        $fromTs = strtotime($fromDate . ' 00:00:00');
        if (!$fromTs) {
            return true;
        }
        if ($pubDateStr === '') {
            return true;
        }
        $itemTs = strtotime($pubDateStr);
        return $itemTs ? $itemTs >= $fromTs : true;
    }
}

