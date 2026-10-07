<?php

namespace App;

/**
 * Länsgeometri från resources/geo/ (todo #78), genererad av
 * deploy/lansgeometri.py — se docs/lansgeometri.md.
 *
 * - lanscirklar.json: en "områdescirkel" per län (mitt + radie) för
 *   kartbilder av sammanfattningar som gäller hela länet.
 * - lansgranser.geojson: länens gränser som polygoner (OSM, förenklade).
 *   Används inte av appen än — sparad för framtida bruk (punkt-i-polygon,
 *   länskartor). OBS: gränserna inkluderar havsområdet.
 *
 * Nyckel = länsnamnet som det står i administrative_area_level_1, t.ex.
 * "Skåne län" (samma form som Helper::getAllLan()).
 */
class Lansgeometri
{
    /** @var array<string, array{lat: float, lng: float, radie_m: int}>|null */
    private static ?array $cirklar = null;

    /**
     * @return array{lat: float, lng: float, radie_m: int}|null
     */
    public static function cirkel(?string $lan): ?array
    {
        if ($lan === null || $lan === '') {
            return null;
        }

        if (self::$cirklar === null) {
            $json = file_get_contents(resource_path('geo/lanscirklar.json'));
            self::$cirklar = $json === false ? [] : (json_decode($json, true) ?? []);
        }

        return self::$cirklar[$lan] ?? null;
    }

    public static function granserGeojsonPath(): string
    {
        return resource_path('geo/lansgranser.geojson');
    }
}
