<?php

namespace App;

/**
 * Länsgeometri från resources/geo/ (todo #78), genererad av
 * deploy/lansgeometri.py — se docs/lansgeometri.md.
 *
 * - lanscirklar.json: en "områdescirkel" per län (mitt + radie) för
 *   kartbilder av sammanfattningar som gäller hela länet.
 * - lansgranser.geojson: länens gränser som polygoner (OSM, förenklade).
 *   Används för punkt-i-polygon (innehaller(), länstestet i
 *   geocode:halsa). OBS: gränserna inkluderar havsområdet.
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
            // Trasig eller saknad fil ska ge kartbild utan markering, inte 500.
            $json = @file_get_contents(resource_path('geo/lanscirklar.json'));
            $decoded = $json === false ? null : json_decode($json, true);
            self::$cirklar = is_array($decoded) ? $decoded : [];
        }

        return self::$cirklar[$lan] ?? null;
    }

    public static function granserGeojsonPath(): string
    {
        return resource_path('geo/lansgranser.geojson');
    }

    /** @var array<string, array<int, array<int, array{0: float, 1: float}>>>|null Län → ringar (lng, lat) */
    private static ?array $granser = null;

    /**
     * Ligger punkten inom länets gräns? null om länet är okänt.
     *
     * Jämn-udda-regeln över alla ringar i länets (Multi)Polygon, så hål
     * (enklaver) hanteras utan särfall. Gränserna inkluderar havet, så en
     * punkt i skärgården räknas som innanför.
     */
    public static function innehaller(?string $lan, float $lat, float $lng): ?bool
    {
        if (self::$granser === null) {
            self::$granser = [];
            $json = @file_get_contents(self::granserGeojsonPath());
            $decoded = $json === false ? null : json_decode($json, true);
            foreach ($decoded['features'] ?? [] as $feature) {
                $geometry = $feature['geometry'];
                $polygoner = $geometry['type'] === 'MultiPolygon' ? $geometry['coordinates'] : [$geometry['coordinates']];
                self::$granser[$feature['properties']['name']] = array_merge(...$polygoner);
            }
        }

        $ringar = self::$granser[$lan ?? ''] ?? null;
        if ($ringar === null) {
            return null;
        }

        $inuti = false;
        foreach ($ringar as $ring) {
            $antal = count($ring);
            for ($i = 0, $j = $antal - 1; $i < $antal; $j = $i++) {
                [$xi, $yi] = $ring[$i];
                [$xj, $yj] = $ring[$j];
                if (($yi > $lat) !== ($yj > $lat) && $lng < ($xj - $xi) * ($lat - $yi) / ($yj - $yi) + $xi) {
                    $inuti = ! $inuti;
                }
            }
        }

        return $inuti;
    }
}
