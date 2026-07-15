<?php

declare(strict_types=1);

final class AutoVestiStats
{
    /** @param array<string, mixed> $meta */
    public static function record(string $event, array $meta = []): void
    {
        $data = AutoVestiConfig::all();
        $stats = is_array($data['stats'] ?? null) ? $data['stats'] : [];
        $today = gmdate('Y-m-d');
        if (!isset($stats[$today]) || !is_array($stats[$today])) {
            $stats[$today] = [];
        }
        $stats[$today][$event] = (int) ($stats[$today][$event] ?? 0) + 1;
        $recent = is_array($stats['_recent'] ?? null) ? $stats['_recent'] : [];
        array_unshift($recent, [
            'time' => date('Y-m-d H:i:s'),
            'event' => $event,
            'meta' => $meta,
        ]);
        $stats['_recent'] = array_slice($recent, 0, 50);
        $keys = array_filter(array_keys($stats), static fn ($k) => $k !== '_recent');
        if (count($keys) > 45) {
            sort($keys);
            foreach (array_slice($keys, 0, count($keys) - 45) as $old) {
                unset($stats[$old]);
            }
        }
        AutoVestiConfig::updatePartial(['stats' => $stats]);
    }

    /** @return array<string, int> */
    public static function summary(int $days = 7): array
    {
        $stats = AutoVestiConfig::get('stats', []);
        if (!is_array($stats)) {
            $stats = [];
        }
        $totals = [
            'published_ai' => 0,
            'published_native' => 0,
            'rejected' => 0,
            'skipped' => 0,
            'fetched' => 0,
            'link_added' => 0,
        ];
        for ($i = 0; $i < $days; $i++) {
            $day = gmdate('Y-m-d', strtotime("-{$i} days"));
            if (empty($stats[$day]) || !is_array($stats[$day])) {
                continue;
            }
            foreach ($totals as $key => $val) {
                $totals[$key] += (int) ($stats[$day][$key] ?? 0);
            }
        }
        return $totals;
    }

    /** @return array<int, array<string, mixed>> */
    public static function recent(int $limit = 15): array
    {
        $stats = AutoVestiConfig::get('stats', []);
        if (!is_array($stats) || empty($stats['_recent'])) {
            return [];
        }
        return array_slice($stats['_recent'], 0, $limit);
    }
}
