<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use App\CrimeEvent;

class CoyardsController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return View|array
     */
    public function index(Request $request)
    {
        $data = [];
        $events = null;

        $lat = (float) $request->input("lat");
        $lng = (float) $request->input("lng");
        $nearbyInKm = (float) $request->input("distance", 5);
        $nearbyCount = (int) $request->input("count", 25);
        $error = (bool) $request->input("error");

        $lat = round($lat, 5);
        $lng = round($lng, 5);

        $data["lat"] = $lat;
        $data["lng"] = $lng;

        if ($lat && $lng && ! $error) {
            $numTries = 0;

            // Start by showing $nearbyInKm
            // If no hits then move out until we have hits
            $events = CrimeEvent::getEventsNearLocation($lat, $lng, $nearbyCount, $nearbyInKm);
            $numTries++;

            // we want to show at least 5 events
            // if less than 5 events is found then increase the range by nn km, until a hit is found
            while ($events->count() < 5) {
                $nearbyInKm = $nearbyInKm + 5;
                $events = CrimeEvent::getEventsNearLocation($lat, $lng, $nearbyCount, $nearbyInKm);
                $numTries++;
            }

            $data["nearbyInKm"] = $nearbyInKm;
            $data["nearbyCount"] = $nearbyCount;
            $data["numTries"] = $numTries;
        } else {
            $data["error"] = true;
        }

        $data["events"] = $events;

        $format = $request->input('format', 'json');

        switch ($format) {
            case 'html':
                return view('coyards', $data);
            case 'json':
            default:
                // Rensa i svaret lite.
                $keepKeys = [
                    'events',
                ];

                if ($request->input('debug')) {
                    $keepKeys = array_merge(
                        $keepKeys,
                        [
                            'numTries',
                            'nearbyInKm',
                            'nearbyCount'
                        ]
                    );
                }

                $data = array_intersect_key(
                    $data,
                    array_flip($keepKeys)
                );

                if (isset($data['events'])) {
                    $data['events'] = $data['events']->map([$this, 'cleanupEventsData']);
                }

                return $data;
        }
    }

    public function cleanupEventsData($event) {

        $link = $event->getPermalink();
        $link = $link . "?utm_source=coyards&a=b";

        // Gör länk absolut.
        $link = url($link);

        $returnArray = [
            'title' => $event->parsed_title . 'a',
            'location' => $event->getLocationString(),
            'description' => $event->getDescriptionAsPlainText() . 'a',
            'date' => $event->getPubDateISO8601(),
            'date_human' => $event->getParsedDateYMD(),
            'image' => $event->getStaticImageSrc(640,320) . '&a=b',
            'link' => $link,
        ];

        return $returnArray;
    }
}
