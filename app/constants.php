<?php

declare(strict_types=1);

const CATEGORIES = [
    ['name' => 'Vijesti', 'slug' => 'vijesti'],
    ['name' => 'Hronika', 'slug' => 'hronika'],
    ['name' => 'Politika', 'slug' => 'politika'],
    ['name' => 'Društvo', 'slug' => 'drustvo'],
    ['name' => 'Ekonomija', 'slug' => 'ekonomija'],
    ['name' => 'Sport', 'slug' => 'sport'],
    ['name' => 'Kultura', 'slug' => 'kultura'],
    ['name' => 'Dijaspora', 'slug' => 'dijaspora'],
    ['name' => 'Video', 'slug' => 'video'],
];

const CITY_LABELS = [
    'NOVI_PAZAR' => 'Novi Pazar',
    'SJENICA' => 'Sjenica',
    'TUTIN' => 'Tutin',
    'PRIJEPOLJE' => 'Prijepolje',
    'PRIBOJ' => 'Priboj',
    'NOVA_VAROS' => 'Nova Varoš',
    'ROZAJE' => 'Rožaje',
    'BERANE' => 'Berane',
    'OTHER' => 'Ostalo',
];

const CITY_SLUGS = [
    'NOVI_PAZAR' => 'novi-pazar',
    'SJENICA' => 'sjenica',
    'TUTIN' => 'tutin',
    'PRIJEPOLJE' => 'prijepolje',
    'PRIBOJ' => 'priboj',
    'NOVA_VAROS' => 'nova-varos',
    'ROZAJE' => 'rozaje',
    'BERANE' => 'berane',
    'OTHER' => 'ostalo',
];

const CITIES_ORDER = [
    'NOVI_PAZAR', 'SJENICA', 'TUTIN', 'PRIJEPOLJE',
    'PRIBOJ', 'NOVA_VAROS', 'ROZAJE', 'BERANE',
];

function slug_to_city(string $slug): ?string
{
    static $map = null;
    if ($map === null) {
        $map = array_flip(CITY_SLUGS);
    }
    return $map[$slug] ?? null;
}
