<?php

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\CrimeEvent;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

/*
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:api');
*/

Route::get('/areas', function (Request $request, Response $response) {

    $data = [
        "data" => []
    ];

    // Hämta alla län, grupperat på län och antal
    $data["data"]["areas"] = DB::table('crime_events')
                ->select("administrative_area_level_1", DB::Raw("count(administrative_area_level_1) as numEvents"))
                ->groupBy('administrative_area_level_1')
                ->orderBy('administrative_area_level_1', 'asc')
                ->where('administrative_area_level_1', "!=", "")
                ->get();


    return response()->json($data)->withCallback($request->input('callback'));


});

Route::get('/event/{eventID}', function (Request $request, Response $response, $eventID) {

    $event = CrimeEvent::findOrFail($eventID);

    $eventArray = $event->toArray();

    $eventArray = array_only($eventArray, [
        "id",
        #"created_at",
        #"updated_at",
        #"title",
        #"geocoded",
        #"scanned_for_locations",
        "description",
        "permalink",
        #"pubdate",
        #"pubdate_iso8601",
        #"md5",
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
        #"tweeted",
    ]);

    $json = [
        "data" => $eventArray
    ];

    return response()->json($json)->withCallback($request->input('callback'));

});


Route::get('/eventsNearby', function (Request $request, Response $response) {

    // The number of events to get. Max 50. Default 10.
    $lat = (float) $request->input("lat");
    $lng = (float) $request->input("lng");

    if (empty($lat) || empty($lng)) {
        abort(404);
    }

    $lat = (float) $request->input("lat");
    $lng = (float) $request->input("lng");
    $error = (bool) $request->input("error");

    $lat = round($lat, 5);
    $lng = round($lng, 5);

    $numTries = 0;
    $nearbyCount = 25;
    $nearbyInKm = 5;

    $events = CrimeEvent::getEventsNearLocation($lat, $lng, $nearbyCount, $nearbyInKm);
    $numTries++;

    // we want to show at least 5 events
    // if less than 5 events is found then increase the range by nn km, until a hit is found
    while ($events->count() < 5) {

        $nearbyInKm = $nearbyInKm + 10;
        $events = CrimeEvent::getEventsNearLocation($lat, $lng, $nearbyCount, $nearbyInKm);
        $numTries++;

    }

    $json = [
        "links" => [],
        "meta" => [
            "nearbyInKm" => $nearbyInKm,
            "nearbyCount" => $nearbyCount,
            "numTries" => $numTries,
        ],
    ];

    // convert to array so we can modify data before returning to client
    $eventsAsArray = $events->toArray();

    //$json["links"] = $eventsAsArray;
    //unset($json["links"]["data"]);

    // create array with data is a format more suited for app and web
    foreach ($events as $item) {

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
            "image" => $item->getStaticImageSrc(320, 320, 2)
        ];

        $json["data"][] = $event;

    }

    // return json or jsonp if ?callback is set
    return response()->json($json)->withCallback($request->input('callback'));

});

Route::get('/events', function (Request $request, Response $response) {

    // The number of events to get. Max 50. Default 10.
    $limit = (int) $request->input("limit", 10);
    if ($limit > 50) $limit = 50;
    if ($limit <= 0) $limit = 10;

    // ?page=n, picked up by Laravel automatically

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
        $events = $events->whereHas("locations", function($query) use ($location) {
            $query->where('name', 'like', $location);
        });
    }

    if ($type) {
        $events = $events->where("parsed_title", $type);
    }

    $events = $events->paginate($limit);

    $json = [
        "links" => [],
        "data" => []
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
        $event = [
            "id" => $item->id,
            "pubdate_iso8601" => $item->pubdate_iso8601,
            "pubdate_unix" => $item->pubdate,
            "title_type" => $item->parsed_title,
            "title_location" => $item->parsed_title_location,
            "description" => $item->description,
            "content" => $item->parsed_content,
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
            "external_source_link" => $item->permalink
        ];

        $json["data"][] = $event;

    }

    // return json or jsonp if ?callback is set
    return response()->json($json)->withCallback($request->input('callback'));

});

#Route::get('/updateFromFeed', "FeedController@update");
