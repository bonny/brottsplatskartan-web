<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;

/**
 * https://brottsplatskartan.localhost/polisstationer
 */
class PolisstationerController extends Controller
{
    public function index(Request $request)
    {
        $locationsByPlace = \App\Helper::getPoliceStationsCached();

        return view(
            'polisstationer',
            [
                'locationsByPlace' => $locationsByPlace
            ]
        );
    }
}
