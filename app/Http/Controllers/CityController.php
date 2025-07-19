<?php

namespace App\Http\Controllers;

use App\CrimeEvent;
use App\Models\DailySummary;
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
    private $cities = [
        'stockholm' => [
            'name' => 'Stockholm och Stockholms län',
            'lan' => 'Stockholms län',
            'lat' => 59.328930,
            'lng' => 18.064910,
            'mapZoom' => 10,
            'distance' => 20, // km
            'pageTitle' => 'Stockholm: Polishändelser och blåljus',
            'title' => 'Senaste blåljusen och händelser från Polisen.',
            'description' => 'Se aktuella polishändelser och blåljuslarm från räddningstjänsten i Stockholm',
        ]
    ];

    /**
     * Normalize city slug to lowercase and handle redirects if needed
     */
    private function normalizeCitySlug($citySlug) 
    {
        $normalized = mb_strtolower($citySlug);
        return $normalized;
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

        return view('city', [
            'city' => $city,
            'events' => $events,
            'breadcrumbs' => $breadcrumbs,
            'pageTitle' => $city['pageTitle'],
            'mapStartLatLng' => [$city['lat'], $city['lng']],
            'mapZoom' => $city['mapZoom'] ?? 12,
            'policeStations' => $policeStations,
            'lan' => $city_lan,
            'chartHtml' => \App\Helper::getStatsChartHtml($city_lan),
            'lanInfo' => \App\Helper::getSingleLanWithStats($city_lan),
            'todaysSummary' => $todaysSummary,
            'yesterdaysSummary' => $yesterdaysSummary
        ]);
    }
}
