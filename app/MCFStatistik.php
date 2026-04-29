<?php

namespace App;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Helper-API för MCF (tidigare MSB) räddningstjänstens insatser per kommun
 * (todo #39).
 *
 * Komplement till BRÅ #38 — BRÅ täcker brott, MCF täcker olyckor/räddning.
 * Datan uppdateras 1×/år (~mars), så cache 7d är säkert.
 *
 * Granularitet: kommun × år × månad × händelsetyp. Aggregera via SUM() vid
 * läsning för år-totaler. Använd för ortssidor (kommun-nivå), månadsvyer
 * (#25), och länssidor.
 *
 * OBS: "Automatlarm utan brandtillbud" (typ 14) utgör ofta 30–40 % av alla
 * insatser men är falsklarm — exkludera default i UI för att inte ge ett
 * missvisande "olyckor"-intryck. Visa separat med tydlig märkning.
 */
class MCFStatistik
{
    private const CACHE_TTL_DAYS = 7;

    public const TYP_BRAND_BYGGNAD = 1;
    public const TYP_TRAFIKOLYCKA = 2;
    public const TYP_BRAND_ANNAT = 3;
    public const TYP_UTSLAPP = 4;
    public const TYP_DRUNKNING = 5;
    public const TYP_NODSTALLD_PERSON = 6;
    public const TYP_NODSTALLT_DJUR = 7;
    public const TYP_STORMSKADA = 8;
    public const TYP_RAS = 9;
    public const TYP_OVERSVAMNING = 11;
    public const TYP_VATTENSKADA = 12;
    public const TYP_ANNAN_OLYCKA = 13;
    public const TYP_AUTOMATLARM = 14;
    public const TYP_OVRIGT = 15;

    /**
     * Default exkluderade händelsetyper i sammanställningar:
     * - 14 (Automatlarm utan brandtillbud): falsklarm, missvisande
     * - 15 (Annan händelse utan risk): definitionsmässigt inte olycka
     */
    public const TYPER_EXKLUDERA_DEFAULT = [self::TYP_AUTOMATLARM, self::TYP_OVRIGT];

    /**
     * Senaste tillgängliga år i datan.
     */
    public static function senasteAr(): ?int
    {
        return Cache::remember('mcf:senaste-ar', now()->addDays(self::CACHE_TTL_DAYS), function () {
            return DB::table('mcf_raddningsinsatser')->max('ar');
        });
    }

    /**
     * Sammanställning för en kommun ett år.
     *
     * Returnerar:
     *   - kommun_kod, kommun_namn, ar
     *   - totalt (alla typer inkl. automatlarm)
     *   - olyckor (exkl. automatlarm + "annan händelse utan risk")
     *   - automatlarm
     *   - per_typ (Collection): {handelsetyp_id, handelsetyp_namn, antal}
     */
    public static function forKommun(string $kommunKod, ?int $ar = null): ?object
    {
        $ar = $ar ?? self::senasteAr();
        if (!$ar) {
            return null;
        }

        $cacheKey = "mcf:kommun:{$kommunKod}:{$ar}";

        return Cache::remember($cacheKey, now()->addDays(self::CACHE_TTL_DAYS), function () use ($kommunKod, $ar) {
            $kommun = DB::table('scb_kommuner')
                ->where('kommun_kod', $kommunKod)
                ->select('kommun_namn', 'lan_kod', 'lan_namn', 'befolkning')
                ->first();

            if (!$kommun) {
                return null;
            }

            $rows = DB::table('mcf_raddningsinsatser')
                ->where('kommun_kod', $kommunKod)
                ->where('ar', $ar)
                ->groupBy('handelsetyp_id', 'handelsetyp_namn')
                ->orderBy('handelsetyp_id')
                ->select('handelsetyp_id', 'handelsetyp_namn', DB::raw('SUM(antal) as antal'))
                ->get();

            if ($rows->isEmpty()) {
                return null;
            }

            $totalt = (int) $rows->sum('antal');
            $automatlarm = (int) ($rows->firstWhere('handelsetyp_id', self::TYP_AUTOMATLARM)->antal ?? 0);
            $ovrigt = (int) ($rows->firstWhere('handelsetyp_id', self::TYP_OVRIGT)->antal ?? 0);
            $olyckor = $totalt - $automatlarm - $ovrigt;

            return (object) [
                'kommun_kod' => $kommunKod,
                'kommun_namn' => $kommun->kommun_namn,
                'lan_kod' => $kommun->lan_kod,
                'lan_namn' => $kommun->lan_namn,
                'befolkning' => (int) $kommun->befolkning,
                'ar' => $ar,
                'totalt' => $totalt,
                'olyckor' => $olyckor,
                'automatlarm' => $automatlarm,
                'per_typ' => $rows,
            ];
        });
    }

    /**
     * MCF-statistik direkt på bpk-platsnamn (för plats-sidor). Använder samma
     * mappning som BraStatistik.
     */
    public static function forBpkPlaceName(string $bpkPlaceName, ?int $ar = null): ?object
    {
        $kommunKod = BraStatistik::kommunKodForBpkPlaceName($bpkPlaceName);
        return $kommunKod ? self::forKommun($kommunKod, $ar) : null;
    }

    /**
     * Månads-aggregat för en kommun ett år. För #25 månadsvyer.
     *
     * Returnerar Collection sorterad på månad (1–12), varje rad har
     * {manad, totalt, olyckor, automatlarm}.
     */
    public static function manadsaggregatForKommun(string $kommunKod, int $ar): Collection
    {
        $cacheKey = "mcf:manad-agg:{$kommunKod}:{$ar}";

        return Cache::remember($cacheKey, now()->addDays(self::CACHE_TTL_DAYS), function () use ($kommunKod, $ar) {
            $exkluderaList = implode(',', self::TYPER_EXKLUDERA_DEFAULT);

            $rows = DB::table('mcf_raddningsinsatser')
                ->where('kommun_kod', $kommunKod)
                ->where('ar', $ar)
                ->groupBy('manad')
                ->orderBy('manad')
                ->select(
                    'manad',
                    DB::raw('SUM(antal) as totalt'),
                    DB::raw("SUM(CASE WHEN handelsetyp_id IN ({$exkluderaList}) THEN antal ELSE 0 END) as automatlarm_ovrigt"),
                )
                ->get();

            return $rows->map(function ($row) {
                $totalt = (int) $row->totalt;
                $autoOvrigt = (int) $row->automatlarm_ovrigt;
                return (object) [
                    'manad' => (int) $row->manad,
                    'totalt' => $totalt,
                    'olyckor' => $totalt - $autoOvrigt,
                    'automatlarm' => $autoOvrigt,
                ];
            });
        });
    }

    /**
     * Insatser per typ för en kommun en specifik månad. För #25 månadsvyer.
     *
     * Returnerar envelope med {kommun_namn, ar, manad, per_typ Collection}
     * eller null om månaden saknar data. Automatlarm + övrigt exkluderade.
     */
    public static function forKommunManad(string $kommunKod, int $ar, int $manad): ?object
    {
        $cacheKey = "mcf:kommun-manad:{$kommunKod}:{$ar}:{$manad}";

        return Cache::remember($cacheKey, now()->addDays(self::CACHE_TTL_DAYS), function () use ($kommunKod, $ar, $manad) {
            $perTyp = DB::table('mcf_raddningsinsatser')
                ->where('kommun_kod', $kommunKod)
                ->where('ar', $ar)
                ->where('manad', $manad)
                ->whereNotIn('handelsetyp_id', self::TYPER_EXKLUDERA_DEFAULT)
                ->orderByDesc('antal')
                ->get(['handelsetyp_id', 'handelsetyp_namn', 'antal']);

            if ($perTyp->isEmpty()) {
                return null;
            }

            $kommunNamn = DB::table('scb_kommuner')
                ->where('kommun_kod', $kommunKod)
                ->value('kommun_namn');

            return (object) [
                'kommun_kod' => $kommunKod,
                'kommun_namn' => $kommunNamn,
                'ar' => $ar,
                'manad' => $manad,
                'per_typ' => $perTyp,
            ];
        });
    }

    /**
     * Län-aggregat för ett år. Summerar alla kommuner i länet.
     */
    public static function lanAggregat(string $lanKod, ?int $ar = null): ?object
    {
        $ar = $ar ?? self::senasteAr();
        if (!$ar) {
            return null;
        }

        $cacheKey = "mcf:lan:{$lanKod}:{$ar}";

        return Cache::remember($cacheKey, now()->addDays(self::CACHE_TTL_DAYS), function () use ($lanKod, $ar) {
            $rows = DB::table('mcf_raddningsinsatser as m')
                ->join('scb_kommuner as k', 'm.kommun_kod', '=', 'k.kommun_kod')
                ->where('k.lan_kod', $lanKod)
                ->where('m.ar', $ar)
                ->groupBy('m.handelsetyp_id', 'm.handelsetyp_namn')
                ->orderBy('m.handelsetyp_id')
                ->select('m.handelsetyp_id', 'm.handelsetyp_namn', DB::raw('SUM(m.antal) as antal'))
                ->get();

            if ($rows->isEmpty()) {
                return null;
            }

            $lanNamn = DB::table('scb_kommuner')
                ->where('lan_kod', $lanKod)
                ->value('lan_namn');

            $totalt = (int) $rows->sum('antal');
            $automatlarm = (int) ($rows->firstWhere('handelsetyp_id', self::TYP_AUTOMATLARM)->antal ?? 0);
            $ovrigt = (int) ($rows->firstWhere('handelsetyp_id', self::TYP_OVRIGT)->antal ?? 0);

            return (object) [
                'lan_kod' => $lanKod,
                'lan_namn' => $lanNamn,
                'ar' => $ar,
                'totalt' => $totalt,
                'olyckor' => $totalt - $automatlarm - $ovrigt,
                'automatlarm' => $automatlarm,
                'per_typ' => $rows,
            ];
        });
    }

    /**
     * Topp-kommuner för en händelsetyp ett år. För /statistik-sidan.
     * Per 100k invånare (befolkningsviktat) — inte rena totaler så att
     * Stockholm inte alltid toppar.
     */
    public static function topKommunerPerTyp(int $handelsetypId, int $limit = 10, ?int $ar = null): Collection
    {
        $ar = $ar ?? self::senasteAr();
        if (!$ar) {
            return collect();
        }

        $cacheKey = "mcf:top-typ:{$handelsetypId}:{$limit}:{$ar}";

        return Cache::remember($cacheKey, now()->addDays(self::CACHE_TTL_DAYS), function () use ($handelsetypId, $limit, $ar) {
            return DB::table('mcf_raddningsinsatser as m')
                ->join('scb_kommuner as k', 'm.kommun_kod', '=', 'k.kommun_kod')
                ->where('m.ar', $ar)
                ->where('m.handelsetyp_id', $handelsetypId)
                ->where('k.befolkning', '>', 5000)
                ->groupBy('m.kommun_kod', 'k.kommun_namn', 'k.lan_namn', 'k.befolkning')
                ->orderByRaw('SUM(m.antal) / k.befolkning DESC')
                ->limit($limit)
                ->select(
                    'm.kommun_kod',
                    'k.kommun_namn',
                    'k.lan_namn',
                    'k.befolkning',
                    DB::raw('SUM(m.antal) as antal'),
                    DB::raw('ROUND(SUM(m.antal) / k.befolkning * 100000) as per_100k'),
                )
                ->get();
        });
    }
}
