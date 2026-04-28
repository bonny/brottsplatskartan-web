<?php

namespace App\Http\Controllers;

use App\BraStatistik;
use App\CrimeEvent;
use App\Models\DailySummary;
use App\Models\MonthlySummary;
use App\Services\WikidataService;
use Illuminate\Http\Request;
use Creitive\Breadcrumbs\Breadcrumbs;
use Carbon\Carbon;

/*
Keywords to focus on:
stockholm
idag
polisen
händelser
polishändelser
blåljus
räddningstjänsten
larm
*/
class CityController extends Controller
{
    /**
     * Tier 1-städer som har dedikerade /<stad>-sidor istället för
     * att ligga under /plats/{stad}. URL-slug är ASCII-only för
     * konsistens (malmö → malmo, göteborg → goteborg).
     *
     * Lägg till nya städer enligt todo #24 efter SEO-utvärdering.
     */
    private $cities = [
        'stockholm' => [
            'name' => 'Stockholm och Stockholms län',
            'lan' => 'Stockholms län',
            'kommunKod' => '0180',
            'lat' => 59.328930,
            'lng' => 18.064910,
            'mapZoom' => 10,
            'distance' => 20,
            'pageTitle' => 'Stockholm: Polishändelser och blåljus',
            'title' => 'Senaste blåljusen och händelser från Polisen.',
            'description' => 'Se aktuella polishändelser och blåljuslarm från räddningstjänsten i Stockholm',
            'wikidataQid' => 'Q1754',
        ],
        'malmo' => [
            'name' => 'Malmö och Skåne län',
            'lan' => 'Skåne län',
            'kommunKod' => '1280',
            'lat' => 55.604981,
            'lng' => 13.003822,
            'mapZoom' => 11,
            'distance' => 15,
            'pageTitle' => 'Malmö: Polishändelser och blåljus',
            'title' => 'Senaste blåljusen och händelser från Polisen i Malmö med omnejd.',
            'description' => 'Se aktuella polishändelser och blåljuslarm från räddningstjänsten i Malmö och Skåne län.',
            'wikidataQid' => 'Q2211',
        ],
        'goteborg' => [
            'name' => 'Göteborg och Västra Götalands län',
            'lan' => 'Västra Götalands län',
            'kommunKod' => '1480',
            'lat' => 57.708870,
            'lng' => 11.974560,
            'mapZoom' => 10,
            'distance' => 20,
            'pageTitle' => 'Göteborg: Polishändelser och blåljus',
            'title' => 'Senaste blåljusen och händelser från Polisen i Göteborg med omnejd.',
            'description' => 'Se aktuella polishändelser och blåljuslarm från räddningstjänsten i Göteborg och Västra Götalands län.',
            'wikidataQid' => 'Q25287',
        ],
        'helsingborg' => [
            'name' => 'Helsingborg och Skåne län',
            'lan' => 'Skåne län',
            'kommunKod' => '1283',
            'lat' => 56.046467,
            'lng' => 12.694512,
            'mapZoom' => 11,
            'distance' => 12,
            'pageTitle' => 'Helsingborg: Polishändelser och blåljus',
            'title' => 'Senaste blåljusen och händelser från Polisen i Helsingborg med omnejd.',
            'description' => 'Se aktuella polishändelser och blåljuslarm från räddningstjänsten i Helsingborg och Skåne län.',
            'wikidataQid' => 'Q25411',
        ],
        'uppsala' => [
            'name' => 'Uppsala och Uppsala län',
            'lan' => 'Uppsala län',
            'kommunKod' => '0380',
            'lat' => 59.858564,
            'lng' => 17.638927,
            'mapZoom' => 11,
            'distance' => 15,
            'pageTitle' => 'Uppsala: Polishändelser och blåljus',
            'title' => 'Senaste blåljusen och händelser från Polisen i Uppsala med omnejd.',
            'description' => 'Se aktuella polishändelser och blåljuslarm från räddningstjänsten i Uppsala och Uppsala län.',
            'wikidataQid' => 'Q25286',
        ],
    ];

    /**
     * Normalisera city-slug: lowercase + ASCII (ö → o, å → a, ä → a).
     * "Malmö" → "malmo", "Göteborg" → "goteborg".
     */
    private function normalizeCitySlug($citySlug)
    {
        $lowercase = mb_strtolower($citySlug);
        return \App\Helper::toAscii($lowercase);
    }

    /**
     * Returnera array med Tier 1-städernas slugs (`['uppsala',
     * 'stockholm', ...]`). Används för URL-namespace-routing
     * (todo #33).
     *
     * @return list<string>
     */
    public static function tier1Slugs(): array
    {
        // Hårdkodad lista — matchar self::$cities-nycklar och
        // CityRedirectMiddleware::REDIRECTS-targets.
        return ['stockholm', 'malmo', 'goteborg', 'helsingborg', 'uppsala'];
    }

    /**
     * Slug → display-form (med åäö) för Tier 1-städer. DB-fälten
     * `parsed_title_location` och `administrative_area_level_2` lagrar
     * display-form, så slug 'malmo' träffar inga rader. Används av
     * PlatsController::getEventsInPlatsForMonth() och
     * AISummaryService::getMonthlyEvents() för att översätta innan
     * query.
     *
     * Returnerar slug oförändrad för slugs som inte är Tier 1 — så
     * helpers kan användas oavsett.
     */
    public static function tier1DisplayName(string $slug): string
    {
        static $map = [
            'stockholm' => 'Stockholm',
            'malmo' => 'Malmö',
            'goteborg' => 'Göteborg',
            'helsingborg' => 'Helsingborg',
            'uppsala' => 'Uppsala',
        ];
        return $map[$slug] ?? $slug;
    }

    /**
     * Månadsvy för Tier 1-stad (todo #33).
     * URL: /{city}/handelser/{year}/{month}
     *
     * Delegerar till PlatsController::month()-logiken — Tier 1-checken
     * där byter prev/next/canonical till cityMonth-routerna.
     */
    public function month(Request $request, $city, $year, $month)
    {
        $normalizedSlug = $this->normalizeCitySlug($city);

        if ($city !== $normalizedSlug) {
            return redirect()->route('cityMonth', [
                'city' => $normalizedSlug,
                'year' => $year,
                'month' => $month,
            ], 301);
        }

        if (!isset($this->cities[$normalizedSlug])) {
            abort(404);
        }

        return app(PlatsController::class)->month($request, $normalizedSlug, $year, $month);
    }

    public function show($citySlug, Request $request)
    {
        $normalizedSlug = $this->normalizeCitySlug($citySlug);

        // If original slug doesn't match normalized, redirect to normalized version
        if ($citySlug !== $normalizedSlug) {
            return redirect()->route('city', ['city' => $normalizedSlug], 301);
        }

        if (!isset($this->cities[$normalizedSlug])) {
            abort(404);
        }

        // todo #25/#33: ?page=N-paginering ersatt av månadsvyer. 301:a
        // sida 2+ till stadens startsida så Google rensar äldre indexerade
        // pagineringssidor. Användare som vill bläddra äldre händelser
        // navigerar via månads-arkivet i sidopanelen eller botten-navet.
        if ((int) $request->query('page', 1) > 1) {
            return redirect()->route('city', ['city' => $normalizedSlug], 301);
        }

        $city = $this->cities[$normalizedSlug];
        
        $city_lan = $city['lan'];
        $policeStations = \App\Helper::getPoliceStationsCached()->first(function ($val, $key) use ($city_lan) {
            return mb_strtolower($val['lanName']) === mb_strtolower($city_lan);
        });

        $events = CrimeEvent::getEventsForCity(
            lat: $city['lat'],
            lng: $city['lng'],
            perPage: 25,
            nearbyInKm: $city['distance'],
            days: 365,
            page: $request->query('page', 1),
        );

        // Hämta AI-sammanfattningar endast på första sidan
        $todaysSummary = null;
        $yesterdaysSummary = null;
        
        if ($request->query('page', 1) == 1) {
            // Hämta både dagens och gårdagens sammanfattning i en query
            $summaries = DailySummary::where('area', $normalizedSlug)
                ->whereIn('summary_date', [Carbon::today()->format('Y-m-d'), Carbon::yesterday()->format('Y-m-d')])
                ->get()
                ->keyBy(function($item) {
                    return $item->summary_date->format('Y-m-d');
                });
            
            $todaysSummary = $summaries->get(Carbon::today()->format('Y-m-d'));
            $yesterdaysSummary = $summaries->get(Carbon::yesterday()->format('Y-m-d'));
        }

        $breadcrumbs = new Breadcrumbs();
        $breadcrumbs->setDivider('›');
        $breadcrumbs->addCrumb('Hem', '/');
        $breadcrumbs->addCrumb($city['name'], route('city', ['city' => $normalizedSlug]));

        // BRÅ:s officiella anmälda brott (todo #38). Bara på sidan 1 — Redis-
        // cachad 7d, så kostnaden är trivial men onödig på paginerade sidor.
        $bra = null;
        $braLanGrannar = null;
        $braRikssnitt = null;
        if (!empty($city['kommunKod'])) {
            $bra = BraStatistik::forKommun($city['kommunKod']);
            if ($bra) {
                $braLanGrannar = BraStatistik::lanGrannar($city['kommunKod'], $bra->ar);
                $braRikssnitt = BraStatistik::rikssnitt($bra->ar);
            }
        }

        // Trend-sparkline + brottstyp-fördelning + mest lästa events
        // (todo #27 Lager 1).
        $trendCounts = \App\Helper::getDailyEventCountsNearby(
            $city['lat'],
            $city['lng'],
            $city['distance'],
            90
        );
        $topCrimeTypes = \App\Helper::getTopCrimeTypesNearby(
            $city['lat'],
            $city['lng'],
            $city['distance'],
            30,
            8
        );
        $mostReadEvents = \App\Helper::getMostReadEventsNearby(
            $city['lat'],
            $city['lng'],
            $city['distance'],
            30,
            5
        );

        // Wikidata-fakta (todo #27 Lager 2): grundat-år + yta. Cache 30d.
        $cityFacts = !empty($city['wikidataQid'])
            ? WikidataService::getCityFacts($city['wikidataQid'])
            : null;

        // Förra månadens AI-sammanfattning (todo #27 Lager 3). Visas på
        // startsidan som "ingång till månadsarkivet". Bara aktuell efter
        // 1:a varje månad när schedulern hunnit generera. Tomt visas inget.
        $prevMonthForSummary = Carbon::now()->subMonth();
        $monthlySummary = MonthlySummary::where('area', $normalizedSlug)
            ->where('year', (int) $prevMonthForSummary->format('Y'))
            ->where('month', (int) $prevMonthForSummary->format('m'))
            ->first();

        return view('city', [
            'city' => $city,
            'events' => $events,
            'breadcrumbs' => $breadcrumbs,
            'pageTitle' => $city['pageTitle'],
            'mapStartLatLng' => [$city['lat'], $city['lng']],
            'mapZoom' => $city['mapZoom'] ?? 12,
            'policeStations' => $policeStations,
            'lan' => $city_lan,
            'lanInfo' => \App\Helper::getSingleLanWithStats($city_lan),
            'todaysSummary' => $todaysSummary,
            'yesterdaysSummary' => $yesterdaysSummary,
            'bra' => $bra,
            'braLanGrannar' => $braLanGrannar,
            'braRikssnitt' => $braRikssnitt,
            'trendCounts' => $trendCounts,
            'topCrimeTypes' => $topCrimeTypes,
            'mostReadEvents' => $mostReadEvents,
            'cityName' => explode(' och ', $city['name'])[0],
            'cityFacts' => $cityFacts,
            'monthlySummary' => $monthlySummary,
        ]);
    }
}
