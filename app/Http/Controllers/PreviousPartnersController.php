<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use App\CrimeEvent;

class PreviousPartnersController extends Controller
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

        $randompost = collect([
            [
                'image' => 'https://brottsplatskartan.ams3.digitaloceanspaces.com/blog/2022-textbild-fyra-nakna-man.png',
                'link' => 'https://brottsplatskartan.se/blogg/2021/fyra-nakna-man-pa-museeum-och-andra-knasiga-handelser-fran-polisen-2021',
                'description' => "Fyra nakna män!?",
            ],
            [
                'image' => 'https://brottsplatskartan.ams3.digitaloceanspaces.com/blog/2022-textbild-grannsamverkan.png',
                'link' => 'https://brottsplatskartan.se/inbrott/grannsamverkan?',
                'description' => 'Carehood, Safeland, eller SSF Grannsamverkan. Vilken app väljer du?',
            ],
            [
                'image' => 'https://brottsplatskartan.ams3.digitaloceanspaces.com/blog/2022-textbild-text-tv.png',
                'link' => 'https://texttv.nu/?',
                'description' => 'Korta, snabba nyheter med Text TV-appen från TextTV.nu',
            ],
            [
                'image' => 'https://brottsplatskartan.ams3.digitaloceanspaces.com/blog/2022-textbild-business-referral-1.jpg',
                'link' => str_rot13("uggcf://cbeauho.pbz/?erspbqr=uwX-de-12"),
                'description' => str_rot13('Cbeauho: Serr Cbea Ivqrbf & Frk Zbivrf'),
            ],
        ])->random();       

        $link = $randompost['link'];
        $link = $link . "&utm_source=api-text";

        $image = $randompost['image'];
        $description = $randompost['description'];
        $title = "Sponsrat inlägg ({$event->parsed_title})";

        $returnArray = [
            'title' => $title,
            'location' => $event->getLocationString(),
            'description' => $description,
            'date' => $event->getPubDateISO8601(),
            'date_human' => $event->getParsedDateYMD(),
            'image' => $image,
            'link' => $link,
        ];

        return $returnArray;
    }
}
