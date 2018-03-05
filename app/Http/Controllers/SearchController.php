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
    public function index (Request $request) {
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

        if ($s && mb_strlen($s) >= $minSearchLength) {
            $breadcrumbs->addCrumb(e($s));

            /*
            $events = CrimeEvent::where(function ($query) use ($s) {
                $query->where("description", "LIKE", "%$s%")
                    ->orWhere("parsed_title_location", "LIKE", "%$s%")
                    ->orWhere("parsed_content", "LIKE", "%$s%")
                    ->orWhere("parsed_title", "LIKE", "%$s%")
                    #->orWhereHas('locations', function ($query) use ($s) {
                    #    $query->orWhere('name', 'like', "%$s%");
                    #});
                    ;
            })->orderBy("created_at", "desc")->paginate(10);
            */

            // Leta locations som matchar sökt fras
            $locations = Locations::search($s, [
                "name" => 20
            ])->get();

            $foundLocations = [];

            // Behåll bara unika platser
            $locations = $locations->filter(function ($location) use (& $foundLocations) {
                $name = ucwords($location->name);

                if (in_array($name, $foundLocations)) {
                    return false;
                }

                $foundLocations[] = $name;
                return true;
            });

            // Se till att platserna inte blir för många
            $maxLocationsToShow = 10;
            if (sizeof($locations) > $maxLocationsToShow) {
                $locations = $locations->slice(0, $maxLocationsToShow);
            }

            // Sök med hjälp av Eloquence
            // @TODO: jag tog bort Eloquence pga save() slutade funka typ
            $events = CrimeEvent::search($s, [
                "parsed_title" => 20,
                "parsed_title_location" => 20, // crash when 10, works when 20
                "parsed_teaser" => 10,
                "administrative_area_level_1" => 10,
                "administrative_area_level_2" => 7,
                "description" => 5,
                "parsed_content" => 20
            ])->paginate(10);
        }

        $events = CrimeEvent::
            orderBy("created_at", "desc")
            ->with('locations')
            ->limit(5)
            ->get();

        $data = [
            "s" => $s,
            "events" => $events,
            "events2" => isset($events2) ? $events2 : null,
            "locations" => isset($locations) ? $locations : null,
            "breadcrumbs" => $breadcrumbs
        ];

        return view('search', $data);
    }
}
