<?php

namespace App\Http\Controllers;

use App\CrimeEvent;
use App\CrimeView;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use DB;

class FullScreenMapController extends Controller
{
    /**
     * @param Request $request Request.
     *
     * @return \Illuminate\View\View
     */
    public function index(Request $request, $location = null)
    {
        $data = [
            'lat' => 59,
            'lng' => 18,
            'zoom' => 5
        ];

        // Cleanup location, if set.
        // "@58.388211,18.215332,5z"
        if ($location) {
            // "58.388211,18.215332,5"
            $location = trim($location, ' @z');

            /*
                array:3 [▼
                0 => "58.388211"
                1 => "18.215332"
                2 => "5"
                ]
            */
            $locationParts = explode(',', $location);
            if (sizeof($locationParts) === 3) {
                $data['lat'] = number_format(floatval($locationParts[0]), 5);
                $data['lng'] = number_format(floatval($locationParts[1]), 5);
                $data['zoom'] = intval($locationParts[2]);
            }
        }

        return view('sverigekartan', $data);
    }

    public function iframe(Request $request, $location = null) {

        $data = [
            'lat' => 59,
            'lng' => 18,
            'zoom' => 5
        ];

        // Cleanup location, if set.
        // "@58.388211,18.215332,5z"
        if ($location) {
            // "58.388211,18.215332,5"
            $location = trim($location, ' @z');

            /*
                array:3 [▼
                0 => "58.388211"
                1 => "18.215332"
                2 => "5"
                ]
            */
            $locationParts = explode(',', $location);
            if (sizeof($locationParts) === 3) {
                $data['lat'] = number_format(floatval($locationParts[0]), 5);
                $data['lng'] = number_format(floatval($locationParts[1]), 5);
                $data['zoom'] = intval($locationParts[2]);
            }
        }

        return view('sverigekartan-iframe', $data);
    }
}
