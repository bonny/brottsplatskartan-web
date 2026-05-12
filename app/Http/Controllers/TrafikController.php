<?php

namespace App\Http\Controllers;

use App\CrimeEvent;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Controller för Trafikverket + polishändelser aggregat-vyer.
 *
 * Routes:
 * - GET /trafik             → index() — hela Sverige (Fas 1 pilot, indexerbar)
 * - GET /trafik/{id}        → show()  — Trafikverket-permalink (Fas 1)
 * - GET /{lan}/trafik       → lan()   — per-län aggregat (Fas 2, noindex initialt)
 *
 * Filter:
 * - ?typ=olycka — bara Trafikolycka-händelser (Polisen) + Olycka (Trafikverket).
 *   noindex,follow eftersom det är en filter-vy som inte ska konkurrera med
 *   huvudsidan om söktrafik.
 */
class TrafikController extends Controller
{
    /**
     * Län-slugs med editorial intro-text + indexerbara `/{lan}/trafik`-vyer.
     * Lyfts en i taget enligt lift-checklisten i todo #50 Fas 2:
     *   text granskad → sitemap-entry → noindex lyfts → internlänk.
     * Tier 2/3-län ligger kvar med noindex tills text skrivs.
     */
    public const TIER1_INDEXABLE_LAN_SLUGS = [
        'stockholms-lan',
        'vastra-gotalands-lan',
        'skane-lan',
    ];

    /**
     * Mappar länets långa namn till slug för `/{slug}/trafik`. Returnerar
     * null om länet inte är Tier 1-indexerbart (då ska ingen internlänk
     * från `/lan/{lan}` renderas).
     */
    public static function tier1LanSlug(string $lanName): ?string
    {
        $map = [
            'Stockholms län' => 'stockholms-lan',
            'Västra Götalands län' => 'vastra-gotalands-lan',
            'Skåne län' => 'skane-lan',
        ];
        return $map[$lanName] ?? null;
    }

    /**
     * Polisen-parsed-titlar som räknas som trafik-händelser.
     * Verifierat mot 90d data 2026-05-12 (todo #50, Fas 2).
     */
    private const POLISEN_TRAFIK_TITLES = [
        'Trafikolycka',
        'Trafikolycka, personskada',
        'Trafikolycka, singel',
        'Trafikolycka, vilt',
        'Trafikolycka, smitning från',
        'Trafikbrott',
        'Trafikkontroll',
        'Trafikhinder',
    ];

    /**
     * Polisen-parsed-titlar för bara olyckor (filter ?typ=olycka).
     */
    private const POLISEN_OLYCKA_TITLES = [
        'Trafikolycka',
        'Trafikolycka, personskada',
        'Trafikolycka, singel',
        'Trafikolycka, vilt',
        'Trafikolycka, smitning från',
    ];

    /**
     * Trafikverket message_types för olyckor (filter ?typ=olycka).
     */
    private const TRAFIKVERKET_OLYCKA_TYPES = ['Olycka'];

    /**
     * /trafik — hela Sverige (Fas 1 pilot, behålls indexerbar).
     */
    public function index(): \Illuminate\Contracts\View\View
    {
        $cacheKey = 'trafik:pilot:v1';
        $events = Cache::remember($cacheKey, 5 * 60, function () {
            return Event::active()
                ->forSource('trafikverket')
                ->orderByRaw("FIELD(message_type, 'Olycka', 'Hinder', 'Trafikmeddelande', 'Restriktion', 'Viktig trafikinformation', 'Vägarbete')")
                ->orderByDesc('start_time')
                ->limit(500)
                ->get()
                ->groupBy('message_type');
        });

        return view('trafik', [
            'eventsByType' => $events,
        ]);
    }

    /**
     * /trafik/{id} — Trafikverket-permalink (Fas 1).
     */
    public function show(int $id): \Illuminate\Contracts\View\View
    {
        $event = Event::where('source', 'trafikverket')
            ->where('id', $id)
            ->firstOrFail();

        return view('trafik-detail', ['event' => $event]);
    }

    /**
     * /{lan}/trafik — per-län aggregat (Fas 2).
     *
     * Mixar Trafikverket + polishändelser. noindex initialt — lyfts manuellt
     * per län när editorial intro-text är skriven. Se todo #50 Fas 2.
     */
    public function lan(Request $request, string $lan): \Illuminate\Contracts\View\View
    {
        $typ = $request->query('typ');

        // Resolva slug → län-namn → county_no.
        $slugMap = \App\Helper::getLanSlugsToNameArray();
        $lanName = $slugMap[$lan] ?? null;
        if (!$lanName) {
            abort(404);
        }

        $countyNo = Event::getCountyNoForLanName($lanName);
        if (!$countyNo) {
            abort(404);
        }

        $polisenTitles = $typ === 'olycka'
            ? self::POLISEN_OLYCKA_TITLES
            : self::POLISEN_TRAFIK_TITLES;

        $trafikverketFilter = $typ === 'olycka'
            ? self::TRAFIKVERKET_OLYCKA_TYPES
            : null; // null = alla message_types

        $cacheKey = "trafik:lan:v1:{$lan}:" . ($typ ?: 'alla');
        $data = Cache::remember($cacheKey, 5 * 60, function () use ($lanName, $countyNo, $polisenTitles, $trafikverketFilter) {
            $polisenEvents = CrimeEvent::orderByDesc('created_at')
                ->where('administrative_area_level_1', $lanName)
                ->whereIn('parsed_title', $polisenTitles)
                ->limit(100)
                ->get();

            $trafikverketQuery = Event::active()
                ->forSource('trafikverket')
                ->forCounty($countyNo)
                ->orderByDesc('start_time');

            if ($trafikverketFilter) {
                $trafikverketQuery->whereIn('message_type', $trafikverketFilter);
            }

            $trafikverketEvents = $trafikverketQuery->limit(200)->get();

            return [
                'polisenEvents' => $polisenEvents,
                'trafikverketEvents' => $trafikverketEvents,
            ];
        });

        $breadcrumbs = new \Creitive\Breadcrumbs\Breadcrumbs();
        $breadcrumbs->setDivider('›');
        $breadcrumbs->addCrumb('Hem', '/');
        $breadcrumbs->addCrumb($lanName, '/lan/' . $lan);
        $breadcrumbs->addCrumb('Trafik', '');

        // Indexerbar om Tier 1-län OCH ingen filter-vy. Filter-vyer
        // (?typ=...) förblir permanent noindex så de inte kannibaliserar
        // huvudaggregatet om söktrafik. Tier 2/3-län noindex tills text skrivs.
        $robotsNoindex = !in_array($lan, self::TIER1_INDEXABLE_LAN_SLUGS, true)
            || $typ !== null;

        return view('trafik.lan', [
            'lan' => $lan,
            'lanName' => $lanName,
            'countyNo' => $countyNo,
            'typ' => $typ,
            'polisenEvents' => $data['polisenEvents'],
            'trafikverketEvents' => $data['trafikverketEvents'],
            'breadcrumbs' => $breadcrumbs,
            'robotsNoindex' => $robotsNoindex,
        ]);
    }
}
