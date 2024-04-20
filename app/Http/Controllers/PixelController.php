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
class PixelController extends Controller {
    /**
     * Tracka sökfrågor via pixel.
     *
     * @param Request $req Request.
     *
     * @return array
     */
    public function pixelSok(Request $req) {
        $query = urldecode($req->input('query'));
        $query = str($query)->trim()->lower()->limit(100)->toString();

        // Om show-setting finns så visa sökningar.
        if ($req->has('show-setting')) {
            $searches = \Setting::get('searches', []);
            return $searches;
        }

        // Bail on query är tom.
        if (empty($query)) {
            return response()->json(['error' => 'No query'], 400);
        }

        // Hämta och spara setting.
        // Ändra antal för varje sökning
        // Ta bort de äldsta när de är för många.
        $searches = \Setting::get('searches', []);

        if (isset($searches[$query])) {
            $searches[$query]++;
        } else {
            $searches[$query] = 1;
        }

        // Spara setting.
        \Setting::set('searches', $searches);

        $data = [
            'query' => $query,
            'setting' => $searches
        ];

        return $data;
    }

    /**
     * Tracka saker via pixel.
     *
     * @param Request $req Request.
     *
     * @return array
     */
    public function pixel(Request $req) {
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
                $view = new CrimeView;
                $view->crime_event_id = $eventId;
                $view->save();
            }
        }

        return $data;
    }
}
