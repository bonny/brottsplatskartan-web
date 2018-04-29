<?php

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

Route::get('/areas', 'ApiController@areas');

Route::get('/event/{eventID}', 'ApiController@event');

Route::get('/eventsNearby', 'ApiController@eventsNearby');

Route::get('/events', 'ApiController@events');
