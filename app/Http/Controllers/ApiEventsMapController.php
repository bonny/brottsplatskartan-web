<?php

namespace App\Http\Controllers;

use App\CrimeEvent;
use Cache;

class ApiEventsMapController extends Controller {
    /**
     * Hämta data för eventsMap-komponenten.
     */
    public function index() {
        $cacheSeconds = 5 * 60;
        $daysBack = 3;
        $cacheKey = __METHOD__ . "_{$daysBack}_{$cacheSeconds}";

        // Cacha både query OCH transformation för att undvika tunga metodanrop vid varje request
        $transformedEvents = Cache::remember($cacheKey, $cacheSeconds, function () use ($daysBack) {
            $events = CrimeEvent::orderBy("created_at", "desc")
                ->where('created_at', '>=', now()->subDays($daysBack))
                ->with('locations') // Eager load för att undvika N+1 query problem
                ->limit(500)
                ->get();

            // Transformera data INNE i cachen så metodanrop (getHeadline, getLocationString, etc.)
            // bara körs en gång per cache-period istället för vid varje request
            return $events->map(function ($item) {
                return [
                    "id" => $item->id,
                    'time' => $item->getParsedDateInFormat('HH:mm'), // Carbon isoFormat, inte strftime
                    'time_human' => $item->getParsedDateFormattedForHumans(),
                    'headline' => $item->getHeadline(),
                    "type" => $item->parsed_title,
                    "locations" => $item->getLocationString(includeAdministrativeAreaLevel1Locations: false),
                    "lat" => (float) $item->location_lat,
                    "lng" => (float) $item->location_lng,
                    "image" => $item->getStaticImageSrc(320, 320, 2),
                    "permalink" => $item->getPermalink(true),
                ];
            })->toArray();
        });

        $json = [
            "data" => $transformedEvents,
        ];

        // return json or jsonp if ?callback is set
        return $json;
    }
}
