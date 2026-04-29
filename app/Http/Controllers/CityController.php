<?php

namespace App\Http\Controllers;

use App\BraStatistik;
use App\MCFStatistik;
use App\CrimeEvent;
use App\Models\DailySummary;
use App\Services\WikidataService;
use App\Tier1;
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
     * Normalisera city-slug: lowercase + ASCII (ö → o, å → a, ä → a).
     * "Malmö" → "malmo", "Göteborg" → "goteborg".
     */
    private function normalizeCitySlug($citySlug)
    {
        $lowercase = mb_strtolower($citySlug);
        return \App\Helper::toAscii($lowercase);
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

        if (!Tier1::isTier1($normalizedSlug)) {
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

        $city = Tier1::find($normalizedSlug);
        if ($city === null) {
            abort(404);
        }

        // todo #25/#33: ?page=N-paginering ersatt av månadsvyer. 301:a
        // sida 2+ till stadens startsida så Google rensar äldre indexerade
        // pagineringssidor. Användare som vill bläddra äldre händelser
        // navigerar via månads-arkivet i sidopanelen eller botten-navet.
        if ((int) $request->query('page', 1) > 1) {
            return redirect()->route('city', ['city' => $normalizedSlug], 301);
        }
        
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

        $bra = null;
        $braLanGrannar = null;
        $braRikssnitt = null;
        $mcf = null;
        if (!empty($city['kommunKod'])) {
            $bra = BraStatistik::forKommun($city['kommunKod']);
            if ($bra) {
                $braLanGrannar = BraStatistik::lanGrannar($city['kommunKod'], $bra->ar);
                $braRikssnitt = BraStatistik::rikssnitt($bra->ar);
            }
            $mcf = MCFStatistik::forKommun($city['kommunKod']);
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

        // Wikidata-fakta + SCB-befolkning (todo #27 Lager 2). Två källor:
        // - Wikidata för description, grundat-år, yta (cache 30d)
        // - SCB scb_kommuner för befolkning (alltid färskare än Wikidata
        //   för svenska kommuner, cache 7d)
        $cityFacts = !empty($city['wikidataQid'])
            ? WikidataService::getCityFacts($city['wikidataQid'])
            : null;
        $kommunInfo = !empty($city['kommunKod'])
            ? BraStatistik::kommunInfo($city['kommunKod'])
            : null;

        // OBS: AI-månadssammanfattning visas INTE på Tier 1 startsidan.
        // Startsidan är "live" — användaren vill ha dagsfärsk info.
        // Månadssumma hör hemma på /<stad>/handelser/{år}/{månad}.

        return view('city', [
            'city' => $city,
            'citySlug' => $normalizedSlug,
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
            'mcf' => $mcf,
            'trendCounts' => $trendCounts,
            'topCrimeTypes' => $topCrimeTypes,
            'mostReadEvents' => $mostReadEvents,
            'cityName' => explode(' och ', $city['name'])[0],
            'cityFacts' => $cityFacts,
            'kommunInfo' => $kommunInfo,
        ]);
    }
}
