<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of the routes that are handled
| by your application. Just tell Laravel the URIs it should respond
| to using a Closure or controller method. Build something great!
|
*/

use App\Http\Controllers\FeedController;
use App\CrimeEvent;
use Illuminate\Http\Request;
use App\Http\Requests;
use Carbon\Carbon;

Carbon::setLocale('sv');
setlocale(LC_ALL, 'sv_SE', 'sv_SE.utf8');

/**
 * startpage: show latest events
 */
Route::get('/', function () {

    $data = [];

    $data["events"] = CrimeEvent::orderBy("created_at", "desc")->paginate(10);
    $data["showLanSwitcher"] = true;

    $breadcrumbs = new Creitive\Breadcrumbs\Breadcrumbs;
    $breadcrumbs->addCrumb('Hem', '/');
    $breadcrumbs->addCrumb('Alla län', route("lanOverview"));

    $data["breadcrumbs"] = $breadcrumbs;

    return view('start', $data);

})->name("start");

/**
 * startpage: show latest events
 */
Route::get('/nara', function (Request $request) {

    $data = [];
    $events = null;

    $lat = (float) $request->input("lat");
    $lng = (float) $request->input("lng");
    $error = (bool) $request->input("error");

    $lat = round($lat, 5);
    $lng = round($lng, 5);

    if ($lat && $lng && ! $error ) {
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

        $events = CrimeEvent::getEventsNearLocation($lat, $lng, $nearbyCount, $nearbyInKm);
        $numTries++;

        // we want to show at least 5 events
        // if less than 5 events is found then increase the range by nn km, until a hit is found
        while ($events->count() < 5) {

            $nearbyInKm = $nearbyInKm + 10;
            $events = CrimeEvent::getEventsNearLocation($lat, $lng, $nearbyCount, $nearbyInKm);
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
    #$data["events"] = CrimeEvent::orderBy("created_at", "desc")->paginate(10);
    // $data["showLanSwitcher"] = true;

    $breadcrumbs = new Creitive\Breadcrumbs\Breadcrumbs;
    $breadcrumbs->addCrumb('Hem', '/');
    $breadcrumbs->addCrumb('Nära dig', route("geo"));

    $data["breadcrumbs"] = $breadcrumbs;

    return view('geo', $data);

})->name("geo");

/**
 * Admin-sida
 */
Route::group(['prefix' => 'admin'], function () {

    Route::get('', function ()    {

        return redirect()->route("adminDashboard");

    });

    // /admin/dashboard
    Route::get('dashboard', function (FeedController $feedController, Request $request )    {

        $data = [];

        // if parseItem is set and integer then parse that item
        $parseItemID = (int) $request->input("parseItem");
        if ($parseItemID) {
            $feedController->parseItem($parseItemID);
        }

        // $data["feedsUpdateResult"] = $feedController->updateFeedsFromPolisen();

        $data["events"] = CrimeEvent::orderBy("created_at", "desc")->paginate(100);

        return view('admin.dashboard', $data);

    })->name("adminDashboard");

});

/**
 * Alla län översikt
 */
Route::get('/lan/', function (Request $request) {

    $data = [];

    // some old pages are indexed by google like this
    // "brottsplatskartan.se/lan?lan=/lan/orebro-lan
    $old_lan_query = $request->input("lan");

    if ($old_lan_query) {
        // /lan/orebro-lan
        $old_lan_query = str_replace('/lan/', '', $old_lan_query);
        $redirect_to = "lan/{$old_lan_query}";
        return redirect($redirect_to, 301);
    }

    // Hämta alla län, grupperat på län och antal
    $data["lan"] = DB::table('crime_events')
                ->select("administrative_area_level_1")
                ->groupBy('administrative_area_level_1')
                ->orderBy('administrative_area_level_1', 'asc')
                ->where('administrative_area_level_1', "!=", "")
                ->get();

    $breadcrumbs = new Creitive\Breadcrumbs\Breadcrumbs;
    $breadcrumbs->addCrumb('Hem', '/');
    $breadcrumbs->addCrumb('Län', route("lanOverview"));

    $data["breadcrumbs"] = $breadcrumbs;

    return view('overview-lan', $data);

})->name("lanOverview");

/**
 * Alla orter översikt
 */
Route::get('/plats/', function () {

    $data = [];

    $data["orter"] = DB::table('crime_events')
                ->select("parsed_title_location")
                ->where('parsed_title_location', "!=", "")
                ->orderBy('parsed_title_location', 'asc')
                ->distinct()
                ->get();

    $breadcrumbs = new Creitive\Breadcrumbs\Breadcrumbs;
    $breadcrumbs->addCrumb('Hem', '/');
    $breadcrumbs->addCrumb('Platser', route("platserOverview"));

    $data["breadcrumbs"] = $breadcrumbs;

    return view('overview-platser', $data);

})->name("platserOverview");

/**
 * Översikt brottstyp/händelsetyp
 */
Route::get('/typ/', function () {

    $data = [];

    $data["types"] = DB::table('crime_events')
                ->select("parsed_title")
                ->where('parsed_title', "!=", "")
                ->orderBy('parsed_title', 'asc')
                ->distinct()
                ->get();

    $breadcrumbs = new Creitive\Breadcrumbs\Breadcrumbs;
    $breadcrumbs->addCrumb('Hem', '/');
    $breadcrumbs->addCrumb('Brottstyper', route("typeOverview"));

    $data["breadcrumbs"] = $breadcrumbs;

    return view('overview-typer', $data);

})->name("typeOverview");


/**
 * En typ
 */
Route::get('/typ/{typ}', function ($typ) {

    $data = [
        "type" => $typ
    ];

    $data["events"] = CrimeEvent::orderBy("created_at", "desc")
                                ->where("parsed_title", $typ)
                                ->paginate(10);

    if (!$data["events"]->count()) {
        abort(404);
    }

    $breadcrumbs = new Creitive\Breadcrumbs\Breadcrumbs;
    $breadcrumbs->addCrumb('Hem', '/');
    $breadcrumbs->addCrumb('Brottstyper', route("typeOverview"));
    $breadcrumbs->addCrumb(e($typ), route("typeSingle", ["typ" => $typ]));

    $data["breadcrumbs"] = $breadcrumbs;

    return view('single-typ', $data);

})
->name("typeSingle")
->where('typ', '(.*)');


/**
 * En ort
 */
Route::get('/plats/{plats}', function ($plats) {

    $data = [
        "plats" => $plats
    ];

    // Hämta events där plats är från huvudtabellen
    $events = CrimeEvent::orderBy("created_at", "desc")
                                ->where("parsed_title_location", $plats)
                                ->orWhere("administrative_area_level_2", $plats)
                                ->orWhereHas('locations', function($query) use($plats) {
                                        $query->where('name', '=', $plats);
                                })
                                ->paginate(10);

    // Leta också i locations-tabellen
    /*
    $events2 = CrimeEvent::whereHas('locations', function($query) use($plats) {
        $query->where('name', '=', $plats);
    })->paginate();
    */

    #echo "<br>events count: " . $events->count();
    #echo "<br>events2 count: " . $events2->count();

    #$events = $events->merge($events2);
    #echo "<br>joined events count: " . $events->count();

    $data["events"] = $events;

    if (!$data["events"]->count()) {
        abort(404);
    }

    $breadcrumbs = new Creitive\Breadcrumbs\Breadcrumbs;
    $breadcrumbs->addCrumb('Hem', '/');
    $breadcrumbs->addCrumb('Platser', route("platserOverview"));
    $breadcrumbs->addCrumb(e(title_case($plats)));

    $data["breadcrumbs"] = $breadcrumbs;

    return view('single-plats', $data);

})->name("platsSingle");


/**
 * Sida, med text typ, t.ex. "om brottsplatskartan" eller "api"
*/
Route::get('/sida/{pagename}', function ($pagename = null) {

    $data = [
        "pagename" => $pagename,
        "pageTitle" => "Sidan $pagename"
    ];

    return view('page', $data);

})->name("page");


/**
 * Ett län
 */
Route::get('/lan/{lan}', function ($lan) {

    $data = [
        "lan" => $lan
    ];

    $data["events"] = CrimeEvent::orderBy("created_at", "desc")
                                ->where("administrative_area_level_1", $lan)
                                ->paginate(10);

    if (!$data["events"]->count()) {
        abort(404);
    }

    $breadcrumbs = new Creitive\Breadcrumbs\Breadcrumbs;
    $breadcrumbs->addCrumb('Hem', '/');
    $breadcrumbs->addCrumb('Län', route("lanOverview"));
    $breadcrumbs->addCrumb(e($lan), e($lan));

    $data["breadcrumbs"] = $breadcrumbs;
    $data["showLanSwitcher"] = true;

    return view('single-lan', $data);

})->name("lanSingle");


/**
 * single event page/en händelse/ett crimeevent
 * ca. såhär:
 *
 * http://brottsplatskartan.se/vastra-gotalands-lan/rattfylleri-2331
 *
 */
Route::get('/{lan}/{eventName}', function ($lan,  $eventName, Request $request) {

    preg_match('!\d+!', $eventName, $matches);
    if (!isset($matches[0])) {
        abort(404);
    }

    $eventID = $matches[0];
    $event = CrimeEvent::findOrFail($eventID);

    $breadcrumbs = new Creitive\Breadcrumbs\Breadcrumbs;
    $breadcrumbs->addCrumb('Hem', '/');
    $breadcrumbs->addCrumb('Län', route("lanOverview"));

    if ($event->administrative_area_level_1) {
        $breadcrumbs->addCrumb(
            e($event->administrative_area_level_1),
            route("lanSingle", ["lan" => $event->administrative_area_level_1], true)
        );
    }

    $breadcrumbs->addCrumb(e($event->parsed_title));

    // optional debug
    $debugData = CrimeEvent::maybeAddDebugData($request, $event);

    $data = [
        "lan" => $lan,
        "eventID" => $eventID,
        "event" => $event,
        "breadcrumbs" => $breadcrumbs,
        "debugData" => $debugData
    ];

    return view('single-event', $data);

})->name("singleEvent");




/**
 * sök
 * sökstartsida + sökresultatsida = samma sida
 */
Route::get('/sok/', function (Request $request) {

    $minSearchLength = 2;

    $s = $request->input("s");
    $events = null;

    $breadcrumbs = new Creitive\Breadcrumbs\Breadcrumbs;
    $breadcrumbs->addCrumb('Hem', '/');
    $breadcrumbs->addCrumb('Sök', route("search"));

    if ( $s && mb_strlen($s) >= $minSearchLength ) {

        $breadcrumbs->addCrumb(e($s));

        $events = CrimeEvent::where(function($query) use ($s) {

            $query->where("description", "LIKE","%$s%")
            ->orWhere("parsed_title_location", "LIKE", "%$s%")
            ->orWhere("parsed_content", "LIKE", "%$s%")
            ->orWhere("parsed_title", "LIKE", "%$s%");

        })->orderBy("created_at", "desc")->paginate(10);

    }

    $data = [
        "s" => $s,
        "events" => $events,
        "breadcrumbs" => $breadcrumbs
    ];

    return view('search', $data);

})->name("search");


/**
 * Skicka med data till 404-sidan
 */
\View::composer('errors/404', function($view) {

    $data = [];

    $data["events"] = CrimeEvent::orderBy("created_at", "desc")->paginate(10);

    // Hämta alla län, grupperat på län och antal
    $data["lan"] = DB::table('crime_events')
                ->select("administrative_area_level_1")
                ->groupBy('administrative_area_level_1')
                ->orderBy('administrative_area_level_1', 'asc')
                ->where('administrative_area_level_1', "!=", "")
                ->get();


    $view->with($data);

});
