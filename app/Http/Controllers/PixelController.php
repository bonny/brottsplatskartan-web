<?php

namespace App\Http\Controllers;

use App\Models\CrimeView;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller för pixel.
 */
class PixelController extends Controller {
    /**
     * Tracka sökfrågor via pixel.
     *
     * @param Request $req Request.
     *
     * @return JsonResponse
     */
    public function pixelSok(Request $req): JsonResponse {
        $query = urldecode((string) $req->input('q'));
        $query = str($query)->trim()->lower()->stripTags()->limit(100)->toString();

        // Antal träffar. 10 = 10 eller fler pga paginering.
        $results_count = (int) urldecode((string) $req->input('c'));

        $settingsKey = 'searches3';

        // Om show-setting finns så visa sökningar.
        // Exempel: POST https://brottsplatskartan.se/pixel-sok body: show-setting=1
        if ($req->has('show-setting')) {
            $searches = \Setting::get($settingsKey, []);
            return $this->noindexJson(is_array($searches) ? $searches : []);
        }

        // Bail on query är tom.
        if (empty($query)) {
            return $this->noindexJson(['error' => 'No query'], 400);
        }

        // Hämta och spara setting.
        // Ändra antal för varje sökning
        // Ta bort de äldsta när de är för många.
        $searches = \Setting::get($settingsKey, []);

        // Lägg till key med aktuell sökning om den inte redan finns.
        if (!isset($searches[$query]) || !is_array($searches[$query])) {
            $searches[$query] = [
                'hits' => $results_count,
                'count' => 0,
            ];
        }

        $searches[$query]['count']++;

        // Key "last" innehåller datumet då sökningen senast gjordes.
        $searches[$query]['last'] = Carbon::now()->toIso8601String();

        // För att undvika att settings-fältet blir för stort
        // så tar vi bort gamla sökningar innan vi sparar.
        $numDaysBackToKeep = 3;
        $searches = array_filter($searches, function ($search) use ($numDaysBackToKeep) {
            $last = Carbon::parse($search['last']);
            return $last->diffInDays(Carbon::now()) <= $numDaysBackToKeep;
        });

        // Spara setting.
        \Setting::set($settingsKey, $searches);

        return $this->noindexJson([
            'query' => $query,
            'setting' => $searches,
        ]);
    }

    /**
     * Tracka saker via pixel.
     *
     * @param Request $req Request.
     *
     * @return JsonResponse
     */
    public function pixel(Request $req): JsonResponse {
        // path: /stockholms-lan/trafikolycka-taby-taby-kyrkby-37653
        $path = urldecode((string) $req->input('path'));

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

        return $this->noindexJson($data);
    }

    /**
     * JsonResponse med X-Robots-Tag: noindex så Google droppar URL:en
     * om den ändå skulle hitta den.
     *
     * @param array<mixed> $data
     */
    private function noindexJson(array $data, int $status = 200): JsonResponse {
        return response()->json($data, $status)
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
