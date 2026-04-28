<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Hämtar strukturerad fakta om geografiska entiteter från Wikidata
 * (todo #27 Lager 2). CC0-data, ingen attribution-pålaga.
 *
 * Vad vi hämtar: grundat-år (P571) + area km² (P2046). Befolkning kommer
 * från SCB (#37) som har färskare svensk data, så P1082 ignoreras.
 *
 * Cache: 30d per Q-id. Wikidata-data ändras sällan för städer.
 */
class WikidataService
{
    private const CACHE_TTL = 30 * 24 * 60 * 60;
    private const API_URL = 'https://www.wikidata.org/w/api.php';
    private const TIMEOUT_SECONDS = 8;

    /**
     * @return array{qid:string,label:?string,description:?string,inception_year:?int,area_km2:?float}|null
     */
    public static function getCityFacts(string $qid): ?array
    {
        if (!preg_match('/^Q\d+$/', $qid)) {
            return null;
        }

        return Cache::remember("wikidata:facts:{$qid}", self::CACHE_TTL, function () use ($qid) {
            try {
                $response = Http::timeout(self::TIMEOUT_SECONDS)
                    ->withUserAgent('Brottsplatskartan/1.0 (https://brottsplatskartan.se)')
                    ->get(self::API_URL, [
                        'action' => 'wbgetentities',
                        'ids' => $qid,
                        'languages' => 'sv',
                        'props' => 'labels|descriptions|claims',
                        'format' => 'json',
                    ]);

                if (!$response->ok()) {
                    Log::warning("Wikidata HTTP {$response->status()} för {$qid}");
                    return null;
                }

                $entity = $response->json("entities.{$qid}");
                if (!$entity || isset($entity['missing'])) {
                    return null;
                }

                return [
                    'qid' => $qid,
                    'label' => data_get($entity, 'labels.sv.value'),
                    'description' => data_get($entity, 'descriptions.sv.value'),
                    'inception_year' => self::extractInceptionYear($entity['claims']['P571'] ?? []),
                    'area_km2' => self::extractAreaKm2($entity['claims']['P2046'] ?? []),
                ];
            } catch (\Throwable $e) {
                Log::warning("Wikidata fetch failed för {$qid}: " . $e->getMessage());
                return null;
            }
        });
    }

    /**
     * P571 (inception) — ta tidigaste preferred/normal-rank entry.
     * Tidsformat: "+1252-00-00T00:00:00Z" eller "+1252-01-01T00:00:00Z".
     *
     * @param array<int, array<string, mixed>> $claims
     */
    private static function extractInceptionYear(array $claims): ?int
    {
        $preferred = self::pickClaim($claims);
        $time = data_get($preferred, 'mainsnak.datavalue.value.time');
        if (!is_string($time)) {
            return null;
        }

        if (!preg_match('/^[+-](\d+)/', $time, $m)) {
            return null;
        }

        $year = (int) $m[1];
        return $year > 0 ? $year : null;
    }

    /**
     * P2046 (area). Värdet är i unit-Q (Q712226 = km², Q35852 = ha,
     * Q11573 = m²). Vi normaliserar till km².
     *
     * @param array<int, array<string, mixed>> $claims
     */
    private static function extractAreaKm2(array $claims): ?float
    {
        $preferred = self::pickClaim($claims);
        $valueObj = data_get($preferred, 'mainsnak.datavalue.value');
        if (!is_array($valueObj)) {
            return null;
        }

        $amount = (float) ($valueObj['amount'] ?? 0);
        if ($amount <= 0) {
            return null;
        }

        // unit är en URL: "http://www.wikidata.org/entity/Q712226"
        $unit = $valueObj['unit'] ?? '';
        $unitQid = is_string($unit) ? basename($unit) : '';

        return match ($unitQid) {
            'Q712226' => $amount,            // km²
            'Q35852' => $amount / 100,       // ha → km²
            'Q11573' => $amount / 1_000_000, // m² → km²
            default => $amount,              // anta km² om okänt
        };
    }

    /**
     * Välj claim med rank=preferred om finns, annars första normal-rank.
     *
     * @param array<int, array<string, mixed>> $claims
     * @return array<string, mixed>|null
     */
    private static function pickClaim(array $claims): ?array
    {
        if ($claims === []) {
            return null;
        }

        foreach ($claims as $claim) {
            if (($claim['rank'] ?? null) === 'preferred') {
                return $claim;
            }
        }

        foreach ($claims as $claim) {
            if (($claim['rank'] ?? null) === 'normal') {
                return $claim;
            }
        }

        return $claims[0];
    }
}
