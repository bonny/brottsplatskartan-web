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
        $cacheKey = __METHOD__ . "_{$daysBack}";

        $events = Cache::remember($cacheKey, $cacheSeconds, function () use ($daysBack) {
            return CrimeEvent::orderBy("created_at", "desc")
                ->where('created_at', '>=', now()->subDays($daysBack))
                ->limit(500)
                ->get();
        });

        $json = [
            "data" => [],
        ];

        /** 
         * Create array with data is a format more suited for app and web
         * @var array<CrimeEvent> $events
         * @var CrimeEvent $item
         */
        foreach ($events as $item) {
            $event = [
                "id" => $item->id,
                'time' => $item->getParsedDateInFormat('%H:%M'),
                'time_human' => $item->getParsedDateFormattedForHumans(),
                'headline' => $item->getHeadline(),
                "type" => $item->parsed_title,
                "locations" => $item->getLocationString(includeAdministrativeAreaLevel1Locations: false),
                "lat" => (float) $item->location_lat,
                "lng" => (float) $item->location_lng,
                "image" => $item->getStaticImageSrc(320, 320, 2),
                "permalink" => $item->getPermalink(true),
            ];

            $json["data"][] = $event;
        }

        // return json or jsonp if ?callback is set
        return $json;
    }
}
