<?php

namespace App\Http\Controllers;

use App\CrimeEvent;
use Illuminate\Http\Request;
use Creitive\Breadcrumbs\Breadcrumbs;

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
            'name' => 'Stockholm',
            'lat' => 59.328930,
            'lng' => 18.064910,
            'distance' => 20, // km
            'pageTitle' => 'Polishändelser och blåljus i Stockholm idag',
            'title' => 'Senaste blåljusen och händelser från Polisen idag',
            'description' => 'Se aktuella polishändelser och blåljuslarm från räddningstjänsten i Stockholm idag',
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

    public function show($citySlug)
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
        
        $events = CrimeEvent::getEventsForCity(
            lat: $city['lat'],
            lng: $city['lng'],
            perPage: 5, // number of events
            nearbyInKm: $city['distance']
        );

        $breadcrumbs = new Breadcrumbs();
        $breadcrumbs->setDivider('›');
        $breadcrumbs->addCrumb('Hem', '/');
        $breadcrumbs->addCrumb($city['name'], route('city', ['city' => $normalizedSlug]));

        return view('city', [
            'city' => $city,
            'events' => $events,
            'breadcrumbs' => $breadcrumbs,
            'pageTitle' => $city['pageTitle']
        ]);
    }
}
