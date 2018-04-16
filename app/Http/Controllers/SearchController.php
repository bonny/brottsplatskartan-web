<?php

namespace App\Http\Controllers;

use Creitive\Breadcrumbs\Breadcrumbs;
use App\CrimeEvent;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Söksidan
 */
class SearchController extends Controller
{
    public function index(Request $request)
    {
        $minSearchLength = 2;

        $s = $request->input('s');
        $tbs = $request->input('tbs', 'qdr:m');
        $events = null;

        // Redirect to Google search because Laravel search no good at the moment.
        // Allow empty search beacuse maybe user wants to get all in the last hour.
        if ($s || array_key_exists('s', $_GET)) {
            $url = 'https://www.google.se/search?q=site%3Abrottsplatskartan.se+' . urlencode($s) . "&tbs={$tbs}";
            return redirect($url);
        }

        $breadcrumbs = new Breadcrumbs;
        $breadcrumbs->addCrumb('Hem', '/');
        $breadcrumbs->addCrumb('Sök', route("search"));

        // Get latest events
        $events = CrimeEvent::
            orderBy("created_at", "desc")
            ->with('locations')
            ->limit(20)
            ->get();

        $eventsByDay = $events->groupBy(function ($item, $key) {
            return date('Y-m-d', strtotime($item->created_at));
        });

        $data = [
            "s" => $s,
            'eventsByDay' => $eventsByDay,
            'hideMapImage' => true,
            "locations" => isset($locations) ? $locations : null,
            "breadcrumbs" => $breadcrumbs
        ];

        return view('search', $data);
    }
}
