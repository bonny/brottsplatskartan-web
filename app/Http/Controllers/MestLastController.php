<?php

namespace App\Http\Controllers;

use DB;
use App\CrimeEvent;
use App\Helper;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;

/**
 * Kontroller för sidan med mest lästa händelserna.
 * https://brottsplatskartan.se/mest-last
 */
class MestLastController extends Controller
{
    public function index(Request $request)
    {
        $datesBack = 7;
        $numEventsToGet = 10;

        $cacheKey = "mestLastaControllerIndexView:V1:D{$datesBack}:N{$numEventsToGet}";

        // Kort cachetid, typ en minut så alltid fräsch lista med senaste händelserna.
        $cacheTTL = 1 * 60;

        // $renderedView = Cache::remember($cacheKey, $cacheTTL, function () use (
        //     $datesBack,
        //     $numEventsToGet
        // ) {
            $days = [];
            for ($i = 0; $i < $datesBack; $i++) {
                $date = Carbon::now()->subDays($i);
                $days[] = [
                    'date' => $date,
                    'title' => sprintf(
                        'Mest lästa händelserna %1$s',
                        $date->formatLocalized('%d %B %Y')
                    ),
                    'events' => Helper::getMostViewedEvents(
                        $date,
                        $numEventsToGet
                    )
                ];
            }

            // Make days array into collection.
            $days = collect($days);

            // Ta bort tomma dagar.
            $days = $days->reject(function ($value) {
                return $value['events']->isEmpty();
            });

            $view = view('mestLasta', [
                'mestLastaNyligen' => [
                    'title' => 'Mest lästa nyligen',
                    'events' => Helper::getMostViewedEventsRecently(
                        20,
                        $numEventsToGet
                    )
                ],
                'mestLasta' => $days
            ]);

        //     return $view->render();
        // });

        $renderedView = $view->render();

        return $renderedView;
    }
}
