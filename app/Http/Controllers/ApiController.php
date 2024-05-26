<?php

namespace App\Http\Controllers;

use App\CrimeEvent;
use App\Newsarticle;
use DB;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Helper;
use Cache;
use Illuminate\Http\JsonResponse;

/**
 * Controller för plats, översikt och detalj
 */
class ApiController extends Controller {
    public function eventsNearby(Request $request, Response $response) {
        // The number of events to get. Max 50. Default 10.
        $lat = (float) $request->input("lat");
        $lng = (float) $request->input("lng");

        if (empty($lat) || empty($lng)) {
            abort(404);
        }

        $lat = round($lat, 5);
        $lng = round($lng, 5);

        $numTries = 0;
        $maxNumTries = 20;
        $nearbyCount = 25;

        $nearbyInKm = $request->input('distance');
        $allowNearbyExpand = false;
        if (!is_numeric($nearbyInKm)) {
            $nearbyInKm = 5;
            $allowNearbyExpand = true;
        }

        $nearbyInKm = (int) $nearbyInKm;

        $events = CrimeEvent::getEventsNearLocation($lat, $lng, $nearbyCount, $nearbyInKm);
        $numTries++;

        // we want to show at least 5 events
        // if less than 5 events is found then increase the range by nn km, until a hit is found
        // but limit to $maxNumTries because we don't want to get ddosed
        if ($allowNearbyExpand) {
            while ($events->count() < 5 && $numTries < $maxNumTries) {
                $nearbyInKm = $nearbyInKm + 10;
                $events = CrimeEvent::getEventsNearLocation($lat, $lng, $nearbyCount, $nearbyInKm);
                $numTries++;
            }
        }

        $json = [
            "links" => [],
            "meta" => [
                "nearbyInKm" => $nearbyInKm,
                "nearbyCount" => $nearbyCount,
                "numTries" => $numTries,
            ],
        ];

        $api_app = $request->get('app');

        // create array with data is a format more suited for app and web
        foreach ($events as $item) {
            $permalink = $item->getPermalink(true);

            // Lägg till GA-parametrar för vissa appar.
            if ($api_app === 'hitta.se') {
                $permalink = "{$permalink}?utm_source=hitta_se&utm_medium=website&utm_campaign=footer";
            }

            $event = [
                "id" => $item->id,
                "pubdate_iso8601" => $item->pubdate_iso8601,
                "pubdate_unix" => $item->pubdate,
                "title_type" => $item->parsed_title,
                "title_location" => $item->parsed_title_location,
                "description" => $item->description,
                "content" => $item->parsed_content,
                "locations" => $item->locations,
                "lat" => (float) $item->location_lat,
                "lng" => (float) $item->location_lng,
                "viewport_northeast_lat" => $item->viewport_northeast_lat,
                "viewport_northeast_lng" => $item->viewport_northeast_lng,
                "viewport_southwest_lat" => $item->viewport_southwest_lat,
                "viewport_southwest_lng" => $item->viewport_southwest_lng,
                "image" => $item->getStaticImageSrc(320, 320, 2),
                "permalink" => $permalink,
            ];

            $json["data"][] = $event;
        }

        // return json or jsonp if ?callback is set
        return response()->json($json)->withCallback($request->input('callback'));
    }

    /**
     * Example URL:
     * https://brottsplatskartan.se/api/events?app=bpk4&page=2
     */
    public function events(Request $request, Response $response) {
        // The number of events to get. Max 50. Default 10.
        $limit = (int) $request->input("limit", 10);

        if ($limit > 500) {
            $limit = 500;
        }

        if ($limit <= 0) {
            $limit = 10;
        }

        // ?page=n, picked up by Laravel automatically
        // https://laravel.com/docs/11.x/pagination

        // area = administrative_area_level_1 = "Uppsala län" and so on
        $area = (string) $request->input("area");

        // location = city or street name or whatever, more specific than area (but can be pretty wide too)
        // example: "folkungagatan", "midsommarkransen"
        $location = (string) $request->input("location");

        // type = inbrott, rån, and so on
        $type = (string) $request->input("type");

        // nearby = lat,lng to show events nearby
        // $nearby = (string) $request->input("nearby");

        // get collection with events
        $events = CrimeEvent::orderBy("created_at", "desc");

        if ($area) {
            $events = $events->where("administrative_area_level_1", $area);
        }

        if ($location) {
            $events = $events->whereHas("locations", function ($query) use ($location) {
                $query->where('name', 'like', $location);
            });
        }

        if ($type) {
            $events = $events->where("parsed_title", $type);
        }

        $events = $events->paginate($limit);

        $callback = $request->input('callback');

        $events->appends([
            "limit" => $limit,
            "area" => $area,
            "location" => $location,
            "type" => $type,
            // "callback" => $callback,
        ]);

        $json = [
            "links" => [],
            "data" => [],
        ];

        // convert to array so we can modify data before returning to client
        $eventsAsArray = $events->toArray();

        $json["links"] = $eventsAsArray;
        unset($json["links"]["data"]);

        // create array with data is a format more suited for app and web
        /** @var CrimeEvent $item */
        foreach ($events->items() as $item) {

            /*
            {
            id: 2056,
            created_at: "2016-10-12 21:39:14",
            updated_at: "2016-10-12 21:39:21",
            title: "2016-10-12 21:33, Trafikolycka, Lund",
            description: "Personbil och cyklist kolliderar, Dalbyvägen / Tornavägen.",
            permalink: "http://polisen.se/Stockholms_lan/Aktuellt/Handelser/Skane/2016-10-12-2133-Trafikolycka-Lund/",
            pubdate: "1476301051",
            pubdate_iso8601: "2016-10-12T21:37:31+0200",
            md5: "aa77027ca1f82eb675a6425fd41b23b7",
            parsed_date: "2016-10-12 21:33:00",
            parsed_title_location: "Lund",
            parsed_content: "Larm kommer om en cyklist och personbil som kolliderat på Dalbyvägen / Tornavägen. Polis, räddningstjänst och ambulans åker till platsen. Polisen Skåne",
            location_lng: "13.2087799",
            location_lat: "55.7068088",
            parsed_title: "Trafikolycka",
            parsed_teaser: "Personbil och cyklist kolliderar, Dalbyvägen / Tornavägen.",
            scanned_for_locations: 1,
            geocoded: 1,
            location_geometry_type: "GEOMETRIC_CENTER",
            }
             */
            $event = [
                "id" => $item->id,
                "pubdate_iso8601" => $item->pubdate_iso8601,
                "pubdate_unix" => $item->pubdate,
                "title_type" => $item->parsed_title,
                "title_location" => $item->parsed_title_location,
                'headline' => $item->getHeadline(),
                "description" => $item->description,
                "content" => $item->parsed_content,
                "content_formatted" => $item->getParsedContent(),
                "content_teaser" => $item->getParsedContentTeaser(),
                //"locations" => $item->locations,
                "location_string" => $item->getLocationString(),
                "date_human" => $item->getParsedDateFormattedForHumans(),
                "lat" => (float) $item->location_lat,
                "lng" => (float) $item->location_lng,
                "viewport_northeast_lat" => $item->viewport_northeast_lat,
                "viewport_northeast_lng" => $item->viewport_northeast_lng,
                "viewport_southwest_lat" => $item->viewport_southwest_lat,
                "viewport_southwest_lng" => $item->viewport_southwest_lng,
                "administrative_area_level_1" => $item->administrative_area_level_1,
                "administrative_area_level_2" => $item->administrative_area_level_2,
                "image" => $item->getStaticImageSrc(640, 320, 1),
                "image_far" => $item->getStaticImageSrcFar(640, 320, 2),
                "external_source_link" => $item->permalink,
                "permalink" => $item->getPermalink(true),
            ];

            $json["data"][] = $event;
        }

        // return json or jsonp if ?callback is set
        return response()->json($json)->withCallback($callback);
    }

    public function event(Request $request, Response $response, $eventID) {
        /** @var CrimeEvent $event */
        $event = CrimeEvent::findOrFail($eventID);

        $eventArray = $event->toArray();

        $eventArray = array_only($eventArray, [
            "id",
            "description",
            "permalink",
            "parsed_date",
            "parsed_title_location",
            "parsed_content",
            "location_lng",
            "location_lat",
            "parsed_title",
            "parsed_teaser",
            "location_geometry_type",
            "administrative_area_level_1",
            "administrative_area_level_2",
            "viewport_northeast_lat",
            "viewport_northeast_lng",
            "viewport_southwest_lat",
            "viewport_southwest_lng",
        ]);

        // Append headline, images, content_teaser.
        $eventArray['headline'] = $event->getHeadline();
        $eventArray['image'] = $event->getStaticImageSrc(640, 320, 1);
        $eventArray['image_far'] = $event->getStaticImageSrcFar(640, 320, 2);
        $eventArray['content_teaser'] = $event->getParsedContentTeaser();

        $json = [
            "data" => $eventArray,
        ];

        return response()->json($json)->withCallback($request->input('callback'));
    }

    public function areas(Request $request, Response $response) {
        $data = [
            "data" => [],
        ];

        // Hämta alla län, grupperat på län och antal
        $data["data"]["areas"] = DB::table('crime_events')
            ->select("administrative_area_level_1", DB::Raw("count(administrative_area_level_1) as numEvents"))
            ->groupBy('administrative_area_level_1')
            ->orderBy('administrative_area_level_1', 'asc')
            ->where('administrative_area_level_1', "!=", "")
            ->get();

        return response()->json($data)->withCallback($request->input('callback'));
    }

    /**
     * Hämta händelser som förekommer i media.
     *
     * @param  Request  $request  [description]
     * @param  Response $response [description]
     * @return JsonResponse
     */
    public function eventsInMedia(Request $request, Response $response) {

        $limit = (int) $request->input("limit", 10);

        if ($limit > 500 || $limit <= 0) {
            $limit = 10;
        }

        // texttv
        $media = $request->input('media');

        $callback = $request->input('callback');

        $events = CrimeEvent::whereHas('newsarticles', function ($query) use ($media) {
            $query->where('url', 'like', "%{$media}%");
        })->with('newsarticles')->orderBy("created_at", "desc")->paginate($limit);

        $events->appends([
            "limit" => $limit,
        ]);

        $json = [
            "links" => [],
            "data" => [],
        ];

        // convert to array so we can modify data before returning to client
        $eventsAsArray = $events->toArray();

        $json["links"] = $eventsAsArray;
        unset($json["links"]["data"]);

        // create array with data is a format more suited for app and web
        foreach ($events->items() as $item) {
            /*
            {
            id: 2056,
            created_at: "2016-10-12 21:39:14",
            updated_at: "2016-10-12 21:39:21",
            title: "2016-10-12 21:33, Trafikolycka, Lund",
            description: "Personbil och cyklist kolliderar, Dalbyvägen / Tornavägen.",
            permalink: "http://polisen.se/Stockholms_lan/Aktuellt/Handelser/Skane/2016-10-12-2133-Trafikolycka-Lund/",
            pubdate: "1476301051",
            pubdate_iso8601: "2016-10-12T21:37:31+0200",
            md5: "aa77027ca1f82eb675a6425fd41b23b7",
            parsed_date: "2016-10-12 21:33:00",
            parsed_title_location: "Lund",
            parsed_content: "Larm kommer om en cyklist och personbil som kolliderat på Dalbyvägen / Tornavägen. Polis, räddningstjänst och ambulans åker till platsen. Polisen Skåne",
            location_lng: "13.2087799",
            location_lat: "55.7068088",
            parsed_title: "Trafikolycka",
            parsed_teaser: "Personbil och cyklist kolliderar, Dalbyvägen / Tornavägen.",
            scanned_for_locations: 1,
            geocoded: 1,
            location_geometry_type: "GEOMETRIC_CENTER",
            }
             */

            // Keep only some keys from the articles array.
            $newsArticles = $item->newsarticles->toArray();
            $keysToKeep = array_flip(['title', 'shortdesc', 'url']);
            $newsArticles = array_map(function ($itemArticle) use ($keysToKeep) {
                return array_intersect_key($itemArticle, $keysToKeep);
            }, $newsArticles);

            $event = [
                "id" => $item->id,
                "pubdate_iso8601" => $item->pubdate_iso8601,
                "pubdate_unix" => $item->pubdate,
                "title_type" => $item->parsed_title,
                "title_location" => $item->parsed_title_location,
                "description" => $item->description,
                "content" => $item->parsed_content,
                "content_formatted" => $item->getParsedContent(),
                "content_teaser" => $item->getParsedContentTeaser(),
                //"locations" => $item->locations,
                "location_string" => $item->getLocationString(),
                "date_human" => $item->getParsedDateFormattedForHumans(),
                "lat" => (float) $item->location_lat,
                "lng" => (float) $item->location_lng,
                "viewport_northeast_lat" => $item->viewport_northeast_lat,
                "viewport_northeast_lng" => $item->viewport_northeast_lng,
                "viewport_southwest_lat" => $item->viewport_southwest_lat,
                "viewport_southwest_lng" => $item->viewport_southwest_lng,
                "administrative_area_level_1" => $item->administrative_area_level_1,
                "administrative_area_level_2" => $item->administrative_area_level_2,
                "image" => $item->getStaticImageSrc(640, 320, 1),
                "external_source_link" => $item->permalink,
                "permalink" => $item->getPermalink(true),
                "newsArticles" => $newsArticles
            ];

            $json["data"][] = $event;
        }

        // return json or jsonp if ?callback is set
        return response()->json($json)->withCallback($callback);
    }

    /**
     * Ger de mest nyligen besökta händelsena.
     * /api/mostViewedRecently
     */
    public function mostViewedRecently(Request $request, Response $response) {
        $events = Helper::getMostViewedEventsRecently($request->input('minutes', 10), $request->input('limit', 10));

        $events = $events->map(function ($data) {
            /** @var CrimeEvent $item */
            $item = $data->crimeEvent;

            return [
                "id" => $item->id,
                "views" => $data->views,
                "pubdate_iso8601" => $item->pubdate_iso8601,
                "pubdate_unix" => $item->pubdate,
                "parsed_date_hm" => $item->getParsedDateInFormat('%H:%M'),
                "title_type" => $item->parsed_title,
                "title_location" => $item->parsed_title_location,
                "headline" => $item->getHeadline(),
                "description" => $item->description,
                "content" => $item->parsed_content,
                "content_formatted" => $item->getParsedContent(),
                "content_teaser" => $item->getParsedContentTeaser(),
                "location_string" => $item->getLocationString(),
                "date_human" => $item->getParsedDateFormattedForHumans(),
                "lat" => (float) $item->location_lat,
                "lng" => (float) $item->location_lng,
                "administrative_area_level_1" => $item->administrative_area_level_1,
                "administrative_area_level_2" => $item->administrative_area_level_2,
                "image" => $item->getStaticImageSrc(640, 320, 1),
                "image_far" => $item->getStaticImageSrcFar(640, 320, 2),
                "permalink" => $item->getPermalink(true),
            ];
        });

        $json = [
            'items' => $events
        ];

        return response()->json($json)->withCallback($request->input('callback'));
    }

    /**
     * Hämta data för eventsMap-komponenten.
     */
    public function eventsMap() {
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

        // create array with data is a format more suited for app and web
        foreach ($events as $item) {
            $event = [
                "id" => $item->id,
                'time' => $item->getParsedDateInFormat('%H:%M'),
                'time_human' => $item->getParsedDateFormattedForHumans(),
                'headline' => $item->getHeadline(),
                "type" => $item->parsed_title,
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
