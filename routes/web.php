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
setlocale(LC_ALL, 'sv_SE');

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

    return view('overview-lan', $data);

})->name("lanOverview");

/**
 * Ett län
 */
Route::get('/lan/{lan}', function ($lan) {

    $data = [
        "lan" => $lan
    ];

    $data["events"] = CrimeEvent::orderBy("created_at", "desc")
                                ->where("administrative_area_level_1", $lan)
                                ->paginate(5);

    if (!$data["events"]->count()) {
        abort(404);
    }

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

    $data = [
        "lan" => $lan,
        "eventID" => $eventID,
        "event" => $event
    ];

    return view('single-event', $data);

})->name("singleEvent");

/**
 * Skicka med data till 404-sidan
 */
\View::composer('errors/404', function($view) {

    $data = [];

    $data["events"] = CrimeEvent::orderBy("created_at", "desc")->paginate(5);

    // Hämta alla län, grupperat på län och antal
    $data["lan"] = DB::table('crime_events')
                ->select("administrative_area_level_1")
                ->groupBy('administrative_area_level_1')
                ->orderBy('administrative_area_level_1', 'asc')
                ->where('administrative_area_level_1', "!=", "")
                ->get();


    $view->with($data);

});
