<?php

declare(strict_types=1);

final class InfoStrip
{
    public static function get(): array
    {
        return cache_remember('info-strip', 1800, function () {
            return [
                'weather' => self::weather(),
                'vaktija' => self::vaktija(),
                'currency' => self::currency(),
            ];
        });
    }

    private static function weather(): array
    {
        $cities = [
            ['name' => 'Novi Pazar', 'lat' => 43.14, 'lon' => 20.51],
            ['name' => 'Sjenica', 'lat' => 43.27, 'lon' => 20.0],
            ['name' => 'Tutin', 'lat' => 42.99, 'lon' => 20.33],
        ];
        $out = [];
        foreach ($cities as $c) {
            try {
                $url = "https://api.open-meteo.com/v1/forecast?latitude={$c['lat']}&longitude={$c['lon']}&current=temperature_2m,weather_code&timezone=Europe/Belgrade";
                $json = @file_get_contents($url);
                if (!$json) {
                    throw new RuntimeException('weather');
                }
                $data = json_decode($json, true);
                $temp = (int) round($data['current']['temperature_2m'] ?? 20);
                $code = (int) ($data['current']['weather_code'] ?? 0);
                $icon = $code <= 1 ? '☀️' : ($code <= 3 ? '⛅' : '🌧️');
                $out[] = ['city' => $c['name'], 'temp' => $temp, 'icon' => $icon];
            } catch (Throwable) {
                $out[] = ['city' => $c['name'], 'temp' => 20, 'icon' => '⛅'];
            }
        }
        return $out;
    }

    private static function vaktija(): array
    {
        try {
            $url = 'https://api.aladhan.com/v1/timingsByCity?city=Novi%20Pazar&country=Serbia&method=13';
            $json = @file_get_contents($url);
            if (!$json) {
                throw new RuntimeException('vaktija');
            }
            $data = json_decode($json, true);
            $timings = $data['data']['timings'] ?? [];
            $labels = [
                'Fajr' => 'Zora', 'Sunrise' => 'Izlazak', 'Dhuhr' => 'Podne',
                'Asr' => 'Ikindija', 'Maghrib' => 'Akšam', 'Isha' => 'Jacija',
            ];
            $now = time();
            $nextName = 'Ikindija';
            $nextTime = '16:42';
            foreach ($labels as $key => $label) {
                if (!isset($timings[$key])) {
                    continue;
                }
                $t = strtotime(date('Y-m-d') . ' ' . $timings[$key]);
                if ($t > $now) {
                    $nextName = $label;
                    $nextTime = substr($timings[$key], 0, 5);
                    break;
                }
            }
            return ['nextName' => $nextName, 'nextTime' => $nextTime];
        } catch (Throwable) {
            return ['nextName' => 'Ikindija', 'nextTime' => '16:42'];
        }
    }

    private static function currency(): array
    {
        try {
            $json = @file_get_contents('https://api.exchangerate-api.com/v4/latest/EUR');
            if (!$json) {
                throw new RuntimeException('currency');
            }
            $data = json_decode($json, true);
            $rsd = $data['rates']['RSD'] ?? 117.18;
            $chf = ($data['rates']['CHF'] ?? 0.95);
            $chfRsd = $rsd / $chf;
            return [
                ['code' => 'EUR', 'rate' => number_format($rsd, 2, ',', '.')],
                ['code' => 'CHF', 'rate' => number_format($chfRsd, 2, ',', '.')],
            ];
        } catch (Throwable) {
            return [
                ['code' => 'EUR', 'rate' => '117,18'],
                ['code' => 'CHF', 'rate' => '125,40'],
            ];
        }
    }
}
