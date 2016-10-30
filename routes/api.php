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

    $data = [];

    // Hämta alla län, grupperat på län och antal
    $data["lan"] = DB::table('crime_events')
                ->select("administrative_area_level_1", DB::Raw("count(administrative_area_level_1) as numEvents"))
                ->groupBy('administrative_area_level_1')
                ->orderBy('administrative_area_level_1', 'asc')
                ->where('administrative_area_level_1', "!=", "")
                ->get();


    return response()->json($data)->withCallback($request->input('callback'));


});

Route::get('/events', function (Request $request, Response $response) {

    // get collection with events
    $events = CrimeEvent::orderBy("created_at", "desc")->paginate(5);

    // clear the events a bit before returning
    #print_r($events);
    #if ($events->items instanceof Illuminate\Database\Eloquent\Collection) {
    #    echo 1;
    #} else {
#        echo 2;#
    #}
    #exit;

    // convert to array so we can modify data before returning to client
    $eventsAsArray = $events->toArray();
    // create array with data is a format more suited for app and web
    $eventsAsArray["events"] = [];
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
            "locations" => $item->locations,
            "lat" => (float) $item->location_lat,
            "lng" => (float) $item->location_lng,
            "viewport_northeast_lat" => $item->viewport_northeast_lat,
            "viewport_northeast_lng" => $item->viewport_northeast_lng,
            "viewport_southwest_lat" => $item->viewport_southwest_lat,
            "viewport_southwest_lng" => $item->viewport_southwest_lng,
            "image" => $item->getStaticImageSrc(320, 320, 2)
        ];


        $eventsAsArray["events"][] = $event;
    }

    unset($eventsAsArray["data"]);

    // return json or jsonp if ?callback is set
    return response()->json($eventsAsArray)->withCallback($request->input('callback'));

});

#Route::get('/updateFromFeed', "FeedController@update");
