<?php

namespace App\Http\Controllers;

use App\CrimeEvent;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;

/**
 * Controller för pixel.
 */
class PixelController extends Controller
{
    /**
     * Tracka saker via pixel.
     *
     * @param Request $request Request.
     *
     * @return $response Response.
     */
    public function pixel(Request $req)
    {
        // path: /stockholms-lan/trafikolycka-taby-taby-kyrkby-37653
        $path = $req->input('path');
        $path = urldecode($path);

        $data = [];

        // Om path slutar med siffror är det ett brott/händelse.
        if (preg_match('/-(\d+)$/', $path, $matches)) {
            $eventId = intval($matches[1]);
            $crimeEvent = CrimeEvent::find($eventId);
            $data['eventId'] = $eventId;
            // $data['event'] = $crimeEvent;
        }

        return $data;
    }
}
