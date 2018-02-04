<?php

namespace App\Http\Controllers;

use App\CrimeEvent;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Http\Request;

/**
 * Controller för län, översikt och detalj
 */
class LanController extends Controller
{
    /**
     * Ett län, t.ex. Stockholms län
     *
     * URL är t.ex.
     * https://brottsplatskartan.localhost/lan/Stockholms%20l%C3%A4n
     *
     * Med paginering är URL t.ex.
     * https://brottsplatskartan.localhost/lan/Stockholms%20l%C3%A4n?page=2
     *
     * Ändra från paginering till
     * https://brottsplatskartan.localhost/lan/Stockholms%20l%C3%A4n/handelser/03-februari-2018
     *
     * @param string $lan Namn på län, t.ex. "Stockholms län". Kan även vara "stockholms-län" (med minusstreck)
     */
    public function lan($lan, Request $request)
    {

        // Om län innehåller minustecken ersätter vi det med mellanslag, pga lagrar länen icke-slug'ade
        $lan = str_replace('-', ' ', $lan);

        $page = (int) $request->input("page", 1);

        if (!$page) {
            $page = 1;
        }

        $events = CrimeEvent::orderBy("created_at", "desc")
                                    ->where("administrative_area_level_1", $lan)
                                    ->with('locations')
                                    ->paginate(10);

        // Hämta mest vanligt förekommande brotten
        $mostCommonCrimeTypes = CrimeEvent::selectRaw('parsed_title, count(id) as antal')
            ->where("administrative_area_level_1", $lan)
            ->groupBy('parsed_title')
            ->orderByRaw('antal DESC')
            ->limit(5)
            ->get();

        $linkRelPrev = null;
        $linkRelNext = null;

        if ($page > 1) {
            $linkRelPrev = route('lanSingle', [
                'lan' => $lan,
                'page' => $page - 1
            ]);
        }

        if ($page < $events->lastpage()) {
            $linkRelNext = route('lanSingle', [
                'lan' => $lan,
                'page' => $page + 1
            ]);
        }

        if ($page == 1) {
            $canonicalLink = route('lanSingle', ['lan' => $lan]);
        } else {
            $canonicalLink = route('lanSingle', ['lan' => $lan, 'page' => $page]);
        }

        $data = [
            'events' => $events,
            'lan' => $lan,
            'page' => $page,
            'linkRelPrev' => $linkRelPrev,
            'linkRelNext' => $linkRelNext,
            'canonicalLink' => $canonicalLink,
            'mostCommonCrimeTypes' => $mostCommonCrimeTypes
        ];

        if (!$data["events"]->count()) {
            abort(404);
        }

        $breadcrumbs = new \Creitive\Breadcrumbs\Breadcrumbs;
        $breadcrumbs->addCrumb('Hem', '/');
        $breadcrumbs->addCrumb('Län', route("lanOverview"));
        $breadcrumbs->addCrumb(e($lan), e($lan));

        $data["breadcrumbs"] = $breadcrumbs;
        $data["showLanSwitcher"] = true;

        // Kolla om förklarande text för län finns
        // key = like "introtext-lan-Stockholms län"
        $introtext_key = "introtext-lan-$lan";
        $data["introtext"] = \Markdown::parse(\Setting::get($introtext_key));

        // Hämta statistik för ett län
        $data["lanChartImgUrl"] = \App\Helper::getStatsImageChartUrl($lan);

        $data["lanInfo"] = \App\Helper::getSingleLanWithStats($lan);


        $mostCommonCrimeTypesMetaDescString = '';
        foreach ($mostCommonCrimeTypes as $oneCrimeType) {
            $mostCommonCrimeTypesMetaDescString .= $oneCrimeType->parsed_title . ', ';
        }
        $mostCommonCrimeTypesMetaDescString = trim($mostCommonCrimeTypesMetaDescString, ', ');

        $metaDescription = "Se var brott sker i närheten av {$lan}. Vanliga händelser i {$lan} är: {$mostCommonCrimeTypesMetaDescString}. Informationen kommer direkt från Polisen till vår karta.";

        $data['metaDescription'] = $metaDescription;

        return view('single-lan', $data);
    }

    /**
     * Lista alla län
     *
     * URL är
     * https://brottsplatskartan.se/lan/
     */
    public function listLan(Request $request)
    {
        $data = [];

        // some old pages are indexed by google like this
        // "brottsplatskartan.se/lan?lan=/lan/orebro-lan
        $old_lan_query = $request->input("lan");

        if ($old_lan_query) {
            // /lan/orebro-lan
            $old_lan_query = str_replace('/lan/', '', $old_lan_query);
            $redirect_to = "lan/{$old_lan_query}";
            return redirect($redirect_to, 301);
        }

        $lan = \App\Helper::getAllLanWithStats();
        $data["lan"] = $lan;

        $breadcrumbs = new \Creitive\Breadcrumbs\Breadcrumbs;
        $breadcrumbs->addCrumb('Hem', '/');
        $breadcrumbs->addCrumb('Län', route("lanOverview"));

        $data["breadcrumbs"] = $breadcrumbs;

        return view('overview-lan', $data);
    }
}
