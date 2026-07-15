<?php

declare(strict_types=1);

final class AutoVestiQueue
{
    public static function hasGuid(string $guid): bool
    {
        foreach (AutoVestiConfig::getQueue() as $row) {
            if (($row['guid'] ?? '') === $guid) {
                return true;
            }
        }
        return false;
    }

    /** @return array<string, mixed>|null */
    public static function getRow(string $guid): ?array
    {
        foreach (AutoVestiConfig::getQueue() as $row) {
            if (($row['guid'] ?? '') === $guid) {
                return $row;
            }
        }
        return null;
    }

    /** @param array<string, mixed> $updates @return array<string, mixed>|null */
    public static function updateRow(string $guid, array $updates): ?array
    {
        $queue = AutoVestiConfig::getQueue();
        $updated = null;
        foreach ($queue as $i => $row) {
            if (($row['guid'] ?? '') !== $guid) {
                continue;
            }
            foreach ($updates as $key => $value) {
                $row[$key] = $value;
                if ($key !== 'item' && isset($row['item']) && is_array($row['item'])) {
                    $row['item'][$key] = $value;
                }
            }
            $queue[$i] = $row;
            $updated = $row;
            break;
        }
        if ($updated) {
            AutoVestiConfig::saveQueue($queue);
        }
        return $updated;
    }

    /** @param array<string, mixed> $item @param array<string, mixed> $feedMeta */
    public static function add(array $item, array $feedMeta, bool $skipSeen = false, bool $notifyTelegram = true): ?array
    {
        $guid = (string) ($item['guid'] ?? '');
        if ($guid === '' || self::hasGuid($guid)) {
            return null;
        }
        if (!$skipSeen && AutoVestiConfig::isSeen($guid)) {
            return null;
        }

        if (class_exists('AutoVestiFetcher', false)) {
            $item['title'] = AutoVestiFetcher::cleanText((string) ($item['title'] ?? ''));
            $item['description'] = AutoVestiFetcher::cleanText((string) ($item['description'] ?? ''));
        } else {
            $item['title'] = trim(strip_tags((string) ($item['title'] ?? '')));
            $item['description'] = trim(strip_tags((string) ($item['description'] ?? '')));
        }

        $preview = trim(strip_tags((string) ($item['description'] ?? '')));
        if ($preview === '' && !empty($item['title'])) {
            $preview = trim(strip_tags((string) $item['title']));
        }
        if (mb_strlen($preview, 'UTF-8') > 280) {
            $preview = mb_substr($preview, 0, 277, 'UTF-8') . '...';
        }

        $link = (string) ($item['link'] ?? '');
        $host = $link !== '' ? (string) parse_url($link, PHP_URL_HOST) : '';

        $row = [
            'guid' => $guid,
            'title' => (string) ($item['title'] ?? ''),
            'preview' => $preview,
            'link' => $link,
            'pub_date' => (string) ($item['pub_date'] ?? ''),
            'image_url' => (string) ($item['image_url'] ?? ''),
            'image_local_path' => (string) ($item['image_local_path'] ?? ''),
            'image_credit' => (string) ($item['image_credit'] ?? ''),
            'image_ai' => !empty($item['image_ai']) ? '1' : '0',
            'image_default' => '0',
            'source_host' => $host !== '' ? $host : (string) parse_url((string) ($feedMeta['url'] ?? ''), PHP_URL_HOST),
            'feed_url' => (string) ($feedMeta['url'] ?? ''),
            'feed_cat' => trim((string) ($feedMeta['cat'] ?? '')),
            'breaking_publish' => !empty($feedMeta['breaking_publish']) && $feedMeta['breaking_publish'] === '1' ? '1' : '0',
            'source_type' => (string) ($feedMeta['source_type'] ?? 'feed'),
            'fetched_at' => date('Y-m-d H:i:s'),
            'held' => '0',
            'locked_by' => '',
            'locked_at' => 0,
            'item' => $item,
        ];

        if ($row['image_url'] === '' && AutoVestiImages::defaultAutoApply()) {
            $row = AutoVestiImages::applyDefaultToRow($row);
        }

        $queue = AutoVestiConfig::getQueue();
        $queue[] = $row;
        AutoVestiConfig::saveQueue($queue);

        if ($notifyTelegram && class_exists('AutoVestiTelegram', false)) {
            AutoVestiTelegram::notifyQueueItem($row);
        }

        return $row;
    }

    /** @param list<string> $guids */
    public static function remove(array $guids): void
    {
        $remove = array_flip($guids);
        $filtered = [];
        foreach (AutoVestiConfig::getQueue() as $row) {
            $guid = (string) ($row['guid'] ?? '');
            if ($guid === '' || !isset($remove[$guid])) {
                $filtered[] = $row;
            }
        }
        AutoVestiConfig::saveQueue($filtered);
    }

    /** @return array<string, mixed>|null */
    public static function getNext(bool $includeHeld = false): ?array
    {
        foreach (AutoVestiConfig::getQueue() as $row) {
            if (!$includeHeld && !empty($row['held']) && $row['held'] === '1') {
                continue;
            }
            return $row;
        }
        return null;
    }

    public static function hold(string $guid): void
    {
        self::updateRow($guid, ['held' => '1']);
    }

    /** @param array<string, mixed>|null $editor */
    public static function tryLock(string $guid, ?array $editor): ?string
    {
        $row = self::getRow($guid);
        if (!$row) {
            return 'Vest više nije u redu.';
        }
        $lockedBy = (string) ($row['locked_by'] ?? '');
        $lockedAt = (int) ($row['locked_at'] ?? 0);
        $me = AutoVestiProcessor::formatEditorLabel($editor);
        if ($lockedBy !== '' && $lockedAt > time() - 300 && $lockedBy !== $me) {
            return 'Obrađuje ' . $lockedBy;
        }
        self::updateRow($guid, ['locked_by' => $me, 'locked_at' => time()]);
        return null;
    }

    public static function unlock(string $guid): void
    {
        self::updateRow($guid, ['locked_by' => '', 'locked_at' => 0]);
    }

    public static function formatPubDate(string $pubDate): string
    {
        if ($pubDate === '') {
            return '—';
        }
        $ts = strtotime($pubDate);
        return $ts ? date('d.m.Y H:i', $ts) : $pubDate;
    }
}
