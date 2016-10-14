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

Route::get('/', function () {

    $data = [];

    // get latest events
    $data["events"] = CrimeEvent::orderBy("created_at", "desc")->paginate(20);

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
