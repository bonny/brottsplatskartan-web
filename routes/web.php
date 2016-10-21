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

    return view('start', $data);

});

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
Route::get('/lan/', function () {

    $data = [];

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

})->name("typeSingle");


/**
 * En ort
 */
Route::get('/plats/{plats}', function ($plats) {

    $data = [
        "plats" => $plats
    ];

    $data["events"] = CrimeEvent::orderBy("created_at", "desc")
                                ->where("parsed_title_location", $plats)
                                ->orWhere("administrative_area_level_2", $plats)
                                ->paginate(10);

    if (!$data["events"]->count()) {
        abort(404);
    }

    $breadcrumbs = new Creitive\Breadcrumbs\Breadcrumbs;
    $breadcrumbs->addCrumb('Hem', '/');
    $breadcrumbs->addCrumb('Platser', route("platserOverview"));
    $breadcrumbs->addCrumb($plats);

    $data["breadcrumbs"] = $breadcrumbs;

    return view('single-plats', $data);

})->name("platsSingle");


/**
 * sida
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

    return view('single-lan', $data);

})->name("lanSingle");


/**
 * single event page
 * ca. såhär:
 *
 * http://brottsplatskartan.se/vastra-gotalands-lan/rattfylleri-2331
 *
 */
Route::get('/{lan}/{eventName}', function ($lan,  $eventName) {

    preg_match('!\d+!', $eventName, $matches);
    if (!isset($matches[0])) {
        abort(404);
    }

    $eventID = $matches[0];
    $event = CrimeEvent::find($eventID);

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

    $data = [
        "lan" => $lan,
        "eventID" => $eventID,
        "event" => $event,
        "breadcrumbs" => $breadcrumbs
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
