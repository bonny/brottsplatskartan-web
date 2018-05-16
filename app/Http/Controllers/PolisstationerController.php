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
    const APIURL = 'https://polisen.se/api/policestations';

    public function index(Request $request)
    {
        $locations = json_decode(file_get_contents($this::APIURL));
        $locationsCollection = collect($locations);

        // "blekinge-lan" => "Blekinge län" osv.
        $slugsToNames = \App\Helper::getLanSlugsToNameArray();

        /*

        Alla URLar verkar bestå av län/plats
        Förutom stockholm som har en del till (stockholm-syd)

        gavleborg/bollnas/
        gavleborg/gavle/

        kalmar-lan/borgholm/
        kalmar-lan/emmaboda/

        vastra-gotaland/bollebygd/
        vastra-gotaland/boras/

        stockholms-lan/stockholm-syd/botkyrka/
        stockholms-lan/stockholm-nord/danderyd/
        stockholms-lan/stockholm-nord/ekero/
        stockholms-lan/stockholm-syd/farsta/

        */
        $locationsByPlace = $locationsCollection->groupBy(function ($item, $key) use ($slugsToNames) {
            $place = $item->Url;
            $place = str_replace('https://polisen.se/kontakt/polisstationer/', '', $place);
            $place = trim($place, '/');
            $placeParts = explode('/', $place);
            $placeLan = $placeParts[0];

            if (isset($slugsToNames[$placeLan])) {
                $placeLan = $slugsToNames[$placeLan];
            }

            return $placeLan;
        });

        // Sortera listan efter länsnamn.
        $locationsByPlace = $locationsByPlace->sortKeys();

        #dd($locationsByPlace);

        return view(
            'polisstationer',
            [
                'locationsByPlace' => $locationsByPlace
            ]
        );
    }
}
