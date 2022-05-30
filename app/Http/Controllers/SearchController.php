<?php

namespace App\Http\Controllers;

use Creitive\Breadcrumbs\Breadcrumbs;
use App\CrimeEvent;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;

/**
 * Söksidan
 */
class SearchController extends Controller
{
    public function index(Request $request)
    {
        $s = $request->input('s');

        $events = null;

        $breadcrumbs = new Breadcrumbs();
        $breadcrumbs->addCrumb('Hem', '/');
        $breadcrumbs->addCrumb('Sök', route("search"));

        $events = \App\Helper::getLatestEvents(5);
        $eventsByDay = $events->groupBy(function ($item, $key) {
            return date('Y-m-d', strtotime($item->created_at));
        });

        $data = [
            's' => $s,
            'events' => $events,
            'eventsByDay' => $eventsByDay,
            'hideMapImage' => true,
            "locations" => null,
            "breadcrumbs" => $breadcrumbs
        ];

        return view('search', $data);
    }

    /**
     * Sida att mellanlanda på innan man skickas till Google.
     * Använder mellanlandningssida pga vill få in site search data
     * till Google Analytics.
     *
     * Exempel på URL hit:
     * https://brottsplatskartan.localhost/sokresultat?s=test&tbs=qdr%3Am
     */
    public function searchperform(Request $request)
    {
        $s = $request->input('s');
        $tbs = $request->input('tbs', 'qdr:m');
        $redirectToUrl =
            'https://www.google.se/search?q=site%3Abrottsplatskartan.se+-proxy+' .
            urlencode($s) .
            "&tbs={$tbs}";

        $data = [
            "s" => $s,
            'redirectToUrl' => $redirectToUrl
        ];

        // Redirect to Google search because Laravel search no good at the moment.
        // Allow empty search because maybe user wants to get all in the last hour.
        // return redirect($url);

        return view('search-perform', $data);
    }

    public function adsenseSearch(Request $request) {
        return view(
            'adsenseSearch',
            [
                'pageTitle' => 'Sök blåljushändelser',
                'canonicalLink' => route('adsenseSearch')
            ]
        );
    }
}
