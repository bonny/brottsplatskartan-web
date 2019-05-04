<?php

namespace App\Http\Controllers;

use DB;
use App\CrimeEvent;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;

/**
 * Startsidan
 */
class GeoController extends Controller
{
    public function geoDetect(Request $request) {
        return view('geo-detect');
    }

    public function nara(Request $request)
    {
        $data = [];
        $events = null;
    
        $lat = (float)$request->input("lat");
        $lng = (float)$request->input("lng");
        $error = (bool)$request->input("error");
    
        $lat = round($lat, 5);
        $lng = round($lng, 5);
    
        if ($lat && $lng && !$error) {
            // works, but cant use "having"
            #$events = CrimeEvent::selectRaw('*, ( 6371 * acos( cos( radians(?) ) * cos( radians( location_lat ) ) * cos( radians( location_lng ) - radians(?) ) + sin( radians(?) ) * sin( radians( location_lat ) ) ) ) AS distance', [ $lat, $lng, $lat ])
            #->orderBy("distance", "ASC")
            #->paginate(10);
            $numTries = 0;
    
            // try 2, works but does not support pagination
            $nearbyCount = 25;
    
            // Start by showing pretty close, like 5 km
            // If no hits then move out until we have more
            $nearbyInKm = 5;
    
            $events = CrimeEvent::getEventsNearLocation(
                $lat,
                $lng,
                $nearbyCount,
                $nearbyInKm
            );
            $numTries++;
    
            // we want to show at least 5 events
            // if less than 5 events is found then increase the range by nn km, until a hit is found
            while ($events->count() < 5) {
                $nearbyInKm = $nearbyInKm + 10;
                $events = CrimeEvent::getEventsNearLocation(
                    $lat,
                    $lng,
                    $nearbyCount,
                    $nearbyInKm
                );
                $numTries++;
            }
    
            $data["nearbyInKm"] = $nearbyInKm;
            $data["nearbyCount"] = $nearbyCount;
            $data["numTries"] = $numTries;
        } else {
            $data["error"] = true;
        }
    
        /*
        Raw sql that seems to work:
    
        SELECT
        title, ( 6371 * acos( cos( radians(59.316) ) * cos( radians( location_lat ) ) * cos( radians( location_lng ) - radians(18.08) ) + sin( radians(59.316) ) * sin( radians( location_lat ) ) ) ) AS distance
        FROM crime_events
        HAVING distance < 25
        ORDER BY distance LIMIT 0, 50;
        */
    
        $data["events"] = $events;
    
        if ($events) {
            $eventsByDay = $events->groupBy(function ($item, $key) {
                return date('Y-m-d', strtotime($item->created_at));
            });
        } else {
            $eventsByDay = null;
        }
    
        $data['eventsByDay'] = $eventsByDay;
    
        $breadcrumbs = new \Creitive\Breadcrumbs\Breadcrumbs();
        $breadcrumbs->setDivider('›');
        $breadcrumbs->addCrumb('Hem', '/');
        $breadcrumbs->addCrumb('Nära dig', route("geo"));
    
        $data["breadcrumbs"] = $breadcrumbs;
        $data['mostViewedEvents'] = \App\Helper::getMostViewedEvents(Carbon::now(), 5);
        $data['latestEvents'] = \App\Helper::getLatestEvents(5);
    
        return view('geo', $data);
    }
}
