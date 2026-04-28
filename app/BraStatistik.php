<?php

namespace App;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Helper-API för BRÅ:s anmälda brott per kommun (todo #38).
 *
 * Riktig brottsstatistik som komplement till Polisens publicerade händelser
 * (vilka inte är heltäckande). Datan uppdateras 1×/år, så cache 7d är säkert.
 *
 * Använd för "anmälda brott per 100 000 inv." på ortssidor (kommun-nivå),
 * län-aggregat och riksstatistik. INTE för månads- eller stadsdelssidor —
 * granulariteten är kommun + år.
 */
class BraStatistik
{
    private const CACHE_TTL_DAYS = 7;

    /**
     * Polisens taxonomi har possessiv-form ("Stockholms län") som
     * scb_kommuner.lan_namn saknar ("Stockholm"). Mappa explicit för
     * konsekvent UI över hela sajten.
     */
    private const LAN_LABELS = [
        '01' => 'Stockholms län',
        '03' => 'Uppsala län',
        '04' => 'Södermanlands län',
        '05' => 'Östergötlands län',
        '06' => 'Jönköpings län',
        '07' => 'Kronobergs län',
        '08' => 'Kalmar län',
        '09' => 'Gotlands län',
        '10' => 'Blekinge län',
        '12' => 'Skåne län',
        '13' => 'Hallands län',
        '14' => 'Västra Götalands län',
        '17' => 'Värmlands län',
        '18' => 'Örebro län',
        '19' => 'Västmanlands län',
        '20' => 'Dalarnas län',
        '21' => 'Gävleborgs län',
        '22' => 'Västernorrlands län',
        '23' => 'Jämtlands län',
        '24' => 'Västerbottens län',
        '25' => 'Norrbottens län',
    ];

    /**
     * Senaste tillgängliga år i datan. Cachat 7d.
     */
    public static function senasteAr(): ?int
    {
        return Cache::remember('bra:senaste-ar', now()->addDays(self::CACHE_TTL_DAYS), function () {
            return DB::table('bra_anmalda_brott')->max('ar');
        });
    }

    /**
     * Befolkning + namn för en kommun från SCB. Används av city-facts-
     * paragrafen (todo #27 Lager 2) — befolkning kommer alltid från SCB
     * eftersom Wikidata-värden ofta är utdaterade för svenska kommuner.
     *
     * @return array{kommun_namn:string, lan_namn:string, befolkning:int}|null
     */
    public static function kommunInfo(string $kommunKod): ?array
    {
        return Cache::remember("bra:kommun-info:{$kommunKod}", now()->addDays(self::CACHE_TTL_DAYS), function () use ($kommunKod) {
            $row = DB::table('scb_kommuner')
                ->where('kommun_kod', $kommunKod)
                ->select('kommun_namn', 'lan_namn', 'befolkning')
                ->first();

            if (!$row) {
                return null;
            }

            return [
                'kommun_namn' => $row->kommun_namn,
                'lan_namn' => $row->lan_namn,
                'befolkning' => (int) $row->befolkning,
            ];
        });
    }

    /**
     * Län-label i Polisens taxonomi (med possessiv där det behövs).
     * Exempel: '01' → 'Stockholms län', '14' → 'Västra Götalands län'.
     */
    public static function lanLabel(string $lanKod): string
    {
        return self::LAN_LABELS[$lanKod] ?? "Län {$lanKod}";
    }

    /**
     * Resolver bpk-platsnamn → kommun_kod via place_population (#37).
     *
     * - source=scb_kommun → direct mapping
     * - source=scb_tatort → första 4 tecken i tatortskod är kommun_kod
     * - source=scb_lan/none/manual → null (kan inte ge specifik kommun)
     *
     * Strict match först (utf8mb4_bin-collation kräver exakta åäö). Fallback
     * till accent-okänslig fuzzy-match — URL-slugs är ofta ASCII ("norrkoping")
     * men bpk_place_name har åäö ("Norrköping").
     */
    public static function kommunKodForBpkPlaceName(string $bpkPlaceName): ?string
    {
        $cacheKey = 'bra:kommun-kod-for-bpk:' . md5($bpkPlaceName);

        return Cache::remember($cacheKey, now()->addDays(self::CACHE_TTL_DAYS), function () use ($bpkPlaceName) {
            $row = DB::table('place_population')
                ->where('bpk_place_name', $bpkPlaceName)
                ->first(['source', 'scb_kommun_kod', 'scb_tatortskod']);

            if (!$row) {
                // ASCII-slug-fall: "Norrkoping" → "Norrköping". Sök accent-okänsligt
                // genom att normalisera båda sidor i SQL via REPLACE-kedjan.
                $normalized = self::stripAccents(mb_strtolower($bpkPlaceName));
                $row = DB::table('place_population')
                    ->whereRaw(self::accentInsensitiveSqlExpr() . ' = ?', [$normalized])
                    ->first(['source', 'scb_kommun_kod', 'scb_tatortskod']);
            }

            if (!$row) {
                return null;
            }

            if ($row->source === 'scb_kommun' && $row->scb_kommun_kod) {
                return $row->scb_kommun_kod;
            }

            if ($row->source === 'scb_tatort' && $row->scb_tatortskod) {
                return substr($row->scb_tatortskod, 0, 4);
            }

            return null;
        });
    }

    private static function stripAccents(string $s): string
    {
        return strtr($s, ['å' => 'a', 'ä' => 'a', 'ö' => 'o', 'é' => 'e', 'è' => 'e', 'ü' => 'u']);
    }

    /**
     * SQL-uttryck som accent-insensitiviserar bpk_place_name (lowercase + åäö→aao).
     * MariaDB-kompatibel utan att förlita sig på collation-magic.
     */
    private static function accentInsensitiveSqlExpr(): string
    {
        return "LOWER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE("
            . "bpk_place_name, 'å','a'), 'ä','a'), 'ö','o'), 'Å','a'), 'Ä','a'), 'Ö','o'))";
    }

    /**
     * BRÅ-statistik direkt på bpk-platsnamn (för plats-sidor). Returnerar
     * null om platsen är län-mappad eller saknar kommun-koppling.
     */
    public static function forBpkPlaceName(string $bpkPlaceName, ?int $ar = null): ?object
    {
        $kommunKod = self::kommunKodForBpkPlaceName($bpkPlaceName);
        return $kommunKod ? self::forKommun($kommunKod, $ar) : null;
    }

    /**
     * Statistik för en kommun ett specifikt år (default: senaste).
     *
     * Returnerar objekt med {kommun_kod, kommun_namn, lan_namn, ar, antal, per_100k, source_url}
     * eller null om ingen data finns.
     */
    public static function forKommun(string $kommunKod, ?int $ar = null): ?object
    {
        $ar = $ar ?? self::senasteAr();
        if (!$ar) {
            return null;
        }

        $cacheKey = "bra:kommun:{$kommunKod}:{$ar}";

        return Cache::remember($cacheKey, now()->addDays(self::CACHE_TTL_DAYS), function () use ($kommunKod, $ar) {
            $row = DB::table('bra_anmalda_brott as b')
                ->join('scb_kommuner as k', 'b.kommun_kod', '=', 'k.kommun_kod')
                ->where('b.kommun_kod', $kommunKod)
                ->where('b.ar', $ar)
                ->select(
                    'b.kommun_kod',
                    'k.kommun_namn',
                    'k.lan_kod',
                    'k.lan_namn',
                    'b.ar',
                    'b.antal',
                    'b.per_100k',
                    'b.source_url',
                )
                ->first();

            return $row ?: null;
        });
    }

    /**
     * Trend för en kommun över ett år-intervall (inklusive).
     *
     * Returnerar Collection av objekt sorterade kronologiskt.
     */
    public static function trendForKommun(string $kommunKod, int $fromAr, int $toAr): Collection
    {
        $cacheKey = "bra:trend:{$kommunKod}:{$fromAr}-{$toAr}";

        return Cache::remember($cacheKey, now()->addDays(self::CACHE_TTL_DAYS), function () use ($kommunKod, $fromAr, $toAr) {
            return DB::table('bra_anmalda_brott')
                ->where('kommun_kod', $kommunKod)
                ->whereBetween('ar', [$fromAr, $toAr])
                ->orderBy('ar')
                ->get(['ar', 'antal', 'per_100k']);
        });
    }

    /**
     * Topp-N kommuner per_100k för ett år.
     */
    public static function topKommuner(int $limit = 10, ?int $ar = null): Collection
    {
        $ar = $ar ?? self::senasteAr();
        if (!$ar) {
            return collect();
        }

        $cacheKey = "bra:top:{$limit}:{$ar}";

        return Cache::remember($cacheKey, now()->addDays(self::CACHE_TTL_DAYS), function () use ($limit, $ar) {
            return DB::table('bra_anmalda_brott as b')
                ->join('scb_kommuner as k', 'b.kommun_kod', '=', 'k.kommun_kod')
                ->where('b.ar', $ar)
                ->orderByDesc('b.per_100k')
                ->limit($limit)
                ->get(['k.kommun_namn', 'k.kommun_kod', 'k.lan_namn', 'b.antal', 'b.per_100k']);
        });
    }

    /**
     * Botten-N kommuner per_100k för ett år (lugnaste).
     */
    public static function bottomKommuner(int $limit = 10, ?int $ar = null): Collection
    {
        $ar = $ar ?? self::senasteAr();
        if (!$ar) {
            return collect();
        }

        $cacheKey = "bra:bottom:{$limit}:{$ar}";

        return Cache::remember($cacheKey, now()->addDays(self::CACHE_TTL_DAYS), function () use ($limit, $ar) {
            return DB::table('bra_anmalda_brott as b')
                ->join('scb_kommuner as k', 'b.kommun_kod', '=', 'k.kommun_kod')
                ->where('b.ar', $ar)
                ->orderBy('b.per_100k')
                ->limit($limit)
                ->get(['k.kommun_namn', 'k.kommun_kod', 'k.lan_namn', 'b.antal', 'b.per_100k']);
        });
    }

    /**
     * Andra kommuner i samma län, sorterade per_100k. För jämförelsetabellen.
     *
     * Inkluderar referenskommunen så den kan markeras i UI:t.
     */
    public static function lanGrannar(string $kommunKod, ?int $ar = null): Collection
    {
        $ar = $ar ?? self::senasteAr();
        if (!$ar) {
            return collect();
        }

        $cacheKey = "bra:lan-grannar:{$kommunKod}:{$ar}";

        return Cache::remember($cacheKey, now()->addDays(self::CACHE_TTL_DAYS), function () use ($kommunKod, $ar) {
            $lanKod = substr($kommunKod, 0, 2);

            return DB::table('bra_anmalda_brott as b')
                ->join('scb_kommuner as k', 'b.kommun_kod', '=', 'k.kommun_kod')
                ->where('k.lan_kod', $lanKod)
                ->where('b.ar', $ar)
                ->orderBy('b.per_100k')
                ->get(['k.kommun_namn', 'k.kommun_kod', 'b.antal', 'b.per_100k']);
        });
    }

    /**
     * Totalt antal anmälda brott i Sverige för ett år.
     */
    public static function rikstotalAntal(?int $ar = null): ?int
    {
        $ar = $ar ?? self::senasteAr();
        if (!$ar) {
            return null;
        }

        return Cache::remember("bra:rikstotal:{$ar}", now()->addDays(self::CACHE_TTL_DAYS), function () use ($ar) {
            return DB::table('bra_anmalda_brott')->where('ar', $ar)->sum('antal');
        });
    }

    /**
     * Befolkningsviktat rikssnitt per_100k för ett år.
     *
     * (Sum(antal) / Sum(befolkning)) * 100000. Ger ett ärligt nationellt snitt
     * (oviktad medel underskattar pga små kommuner väger lika tungt som stora).
     */
    public static function rikssnitt(?int $ar = null): ?int
    {
        $ar = $ar ?? self::senasteAr();
        if (!$ar) {
            return null;
        }

        $cacheKey = "bra:rikssnitt:{$ar}";

        return Cache::remember($cacheKey, now()->addDays(self::CACHE_TTL_DAYS), function () use ($ar) {
            $sums = DB::table('bra_anmalda_brott as b')
                ->join('scb_kommuner as k', 'b.kommun_kod', '=', 'k.kommun_kod')
                ->where('b.ar', $ar)
                ->selectRaw('SUM(b.antal) as sum_antal, SUM(k.befolkning) as sum_bef')
                ->first();

            if (!$sums || !$sums->sum_bef) {
                return null;
            }

            return (int) round(($sums->sum_antal / $sums->sum_bef) * 100000);
        });
    }

    /**
     * Aggregat för ett län ett år: total antal + befolkningsviktat per_100k +
     * lägsta/högsta kommun.
     */
    public static function lanAggregat(string $lanKod, ?int $ar = null): ?object
    {
        $ar = $ar ?? self::senasteAr();
        if (!$ar) {
            return null;
        }

        $cacheKey = "bra:lan-aggregat:{$lanKod}:{$ar}";

        return Cache::remember($cacheKey, now()->addDays(self::CACHE_TTL_DAYS), function () use ($lanKod, $ar) {
            $kommuner = DB::table('bra_anmalda_brott as b')
                ->join('scb_kommuner as k', 'b.kommun_kod', '=', 'k.kommun_kod')
                ->where('k.lan_kod', $lanKod)
                ->where('b.ar', $ar)
                ->get(['k.kommun_namn', 'k.kommun_kod', 'k.lan_namn', 'k.befolkning', 'b.antal', 'b.per_100k']);

            if ($kommuner->isEmpty()) {
                return null;
            }

            $sumAntal = $kommuner->sum('antal');
            $sumBef = $kommuner->sum('befolkning');
            $per100k = $sumBef > 0 ? (int) round(($sumAntal / $sumBef) * 100000) : 0;

            $sorted = $kommuner->sortBy('per_100k')->values();

            return (object) [
                'lan_kod' => $lanKod,
                'lan_namn' => $kommuner->first()->lan_namn,
                'ar' => $ar,
                'antal' => $sumAntal,
                'per_100k' => $per100k,
                'lagst' => $sorted->first(),
                'hogst' => $sorted->last(),
                'antal_kommuner' => $kommuner->count(),
            ];
        });
    }
}
