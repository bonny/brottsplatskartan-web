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
 * Startsidan
 */
class MestLastController extends Controller
{

    public function index(Request $request)
    {
        $datesBack = 7;
        $numEventsToGet = 10;
        $days = [];
        for ($i = 0; $i < $datesBack; $i++) {
            $date = Carbon::now()->subDays($i);
            $days[] = [
                'date' => $date,
                'title' => sprintf('Mest lästa händelserna %1$s', $date->formatLocalized('%d %B %Y')),
                'events' => Helper::getMostViewedEvents($date, $numEventsToGet),
            ];
        }

        // Make days array into collection.
        $days = collect($days);

        // Ta bort tomma dagar.
        $days = $days->reject(function ($value) {
            return $value['events']->isEmpty();
        });

        return view(
            'mestLasta',
            [
                'mestLastaNyligen' => [
                    'title' => 'Mest lästa nyligen',
                    'events' => Helper::getMostViewedEventsRecently(20, $numEventsToGet)
                ],
                'mestLasta' => $days
            ]
        );
    }
}
