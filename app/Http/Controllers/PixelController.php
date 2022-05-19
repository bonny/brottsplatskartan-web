<?php

namespace App\Http\Controllers;

use App\CrimeEvent;
use App\Models\CrimeView;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
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
     * @param Request $req Request.
     *
     * @return array
     */
    public function pixel(Request $req)
    {
        // path: /stockholms-lan/trafikolycka-taby-taby-kyrkby-37653
        $path = $req->input('path');
        $path = urldecode($path);

        $data = [];

        // Om path slutar med siffror är det kanske ett brott/händelse.
        if (preg_match('/-(\d+)$/', $path, $matches)) {
            $eventId = intval($matches[1]);

            // URL slutar med ID, dock kan det vara t.ex. året om URL är
            // "/lan/Västmanlands län/handelser/18-juli-2018"
            // så vi fortsätter bara om siffran inte är 4 siffror, pga
            // alla händelser har numera högre värden än så. Fult. Men borde funka.
            if (strlen((string) $eventId) === 4) {
                // Verkar vara år.
            } else {
                // Inte år, förhoppningsvis event. Spara.
                $data['eventId'] = $eventId;
                // $crimeEvent = CrimeEvent::find($eventId);
                // $data['event'] = $crimeEvent;
                $view = new CrimeView;
                $view->crime_event_id = $eventId;
                $view->save();
            }
        }

        return $data;
    }
}
