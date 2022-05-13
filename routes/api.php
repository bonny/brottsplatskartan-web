<?php

use App\Http\Controllers\ApiController;

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

Route::get('/areas', [ApiController::class, 'areas']);
Route::get('/event/{eventID}', [ApiController::class, 'event']);
Route::get('/eventsNearby', [ApiController::class, 'eventsNearby']);
Route::get('/events', [ApiController::class, 'events']);
Route::get('/eventsInMedia', [ApiController::class, 'eventsInMedia']);
Route::get('/mostViewedRecently', [ApiController::class, 'mostViewedRecently']);
