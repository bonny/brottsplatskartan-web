<?php

namespace App\Http\Controllers;

use App\CrimeEvent;
use Illuminate\Http\Request;
use Creitive\Breadcrumbs\Breadcrumbs;

class CityController extends Controller
{
    private $cities = [
        'stockholm' => [
            'name' => 'Stockholm',
            'lat' => 59.328930,
            'lng' => 18.064910,
            'distance' => 5, // km
            'title' => 'Händelser i Stockholm',
            'description' => 'Se de senaste polishändelserna i Stockholm',
            'pageTitle' => 'Händelser från Polisen i Stockholm'
        ]
    ];

    public function show($citySlug)
    {
        if (!isset($this->cities[$citySlug])) {
            abort(404);
        }

        $city = $this->cities[$citySlug];
        
        $events = CrimeEvent::getEventsNearLocation(
            $city['lat'],
            $city['lng'],
            25, // number of events
            $city['distance']
        );

        $breadcrumbs = new Breadcrumbs();
        $breadcrumbs->setDivider('›');
        $breadcrumbs->addCrumb('Hem', '/');
        $breadcrumbs->addCrumb($city['name'], route('city', ['city' => $citySlug]));

        return view('city', [
            'city' => $city,
            'events' => $events,
            'breadcrumbs' => $breadcrumbs,
            'pageTitle' => $city['pageTitle']
        ]);
    }
}
