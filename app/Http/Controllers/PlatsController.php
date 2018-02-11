<?php

namespace App\Http\Controllers;

use App\CrimeEvent;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Http\Request;

/**
 * Controller för plats, översikt och detalj
 */
class PlatsController extends Controller
{
    public function overview(Request $request)
    {
        $data = [];

        $data["orter"] = \DB::table('crime_events')
                    ->select("parsed_title_location")
                    ->where('parsed_title_location', "!=", "")
                    ->orderBy('parsed_title_location', 'asc')
                    ->distinct()
                    ->get();

        $breadcrumbs = new \Creitive\Breadcrumbs\Breadcrumbs;
        $breadcrumbs->addCrumb('Hem', '/');
        $breadcrumbs->addCrumb('Platser', route("platserOverview"));

        $data["breadcrumbs"] = $breadcrumbs;

        return view('overview-platser', $data);
    }

    public function single($plats, Request $request)
    {
        $platsOriginalFromSlug = $plats;
        $data = [];

        // Om $plats slutar med namnet på ett län, t.ex. "örebro län", "gävleborgs län" osv
        // så ska platser i det länet med platsen $plats minus länets namn visas
        $allLans = App\Helper::getAllLan();
        $allLansNames = $allLans->pluck("administrative_area_level_1");
        $foundMatchingLan = false;
        $matchingLanName = null;
        $platsWithoutLan = null;
        $platsSluggified = App\Helper::toAscii($plats);

        // yttre-ringvägen-skåne-län
        // hittar inte: plats: Årsta i Stockholms Län
        #echo "<br>plats: $plats";

        // platsSluggified: arsta-i-stockholms-lan
        // echo "<br>platsSluggified: $platsSluggified";

        // yttre-ringvagen-skane-lan
        #echo "<br>platsSluggified: $platsSluggified";

        foreach ($allLansNames as $oneLanName) {
            // Skåne län
            // echo "<br>oneLanName: $oneLanName";

            // skane-lan
            $lanSlug = App\Helper::toAscii($oneLanName);
            #echo "<br>lanSlug: $lanSlug";

            // echo "<br> $plats - $oneLanName - $lanSlug - $platsSluggified";
            if (ends_with($platsSluggified, "-" . $lanSlug)) {
                $foundMatchingLan = true;
                $matchingLanName = $oneLanName;

                $lanStrLen = mb_strlen($oneLanName);
                $platsStrLen = mb_strlen($plats);
                $platsWithoutLan = mb_substr($plats, 0, $platsStrLen - $lanStrLen);
                $platsWithoutLan = str_replace("-", " ", $platsWithoutLan);
                $platsWithoutLan = trim($platsWithoutLan);
                break;
            }
        }

        #dd($platsWithoutLan);
        #dd($foundMatchingLan);

        if ($foundMatchingLan) {
            // Hämta events där vi vet både plats och län
            // t.ex. "Stockholm" i "Stockholms län"
            // Query blir ca såhär
            // select * from `crime_events` where `administrative_area_level_1` = ?
            // and (
            //     `parsed_title_location` = ?
            //      or exists (select 1 from `locations` where locations.name = ? AND locations.crime_event_id = crime_events.id)
            // )
            // order by `created_at` desc limit 10 offset 0
            #DB::enableQueryLog();
            $events = CrimeEvent::orderBy("created_at", "desc")
                        ->where("administrative_area_level_1", $oneLanName) // måste vara med
                        // gruppera dessa
                        ->where(function ($query) use ($oneLanName, $platsWithoutLan) {
                            $query->where("parsed_title_location", $platsWithoutLan);
                            $query->orWhereExists(function ($query) use ($platsWithoutLan) {
                                $query->select(DB::raw(1))
                                        ->from('locations')
                                        ->whereRaw(
                                            'locations.name = ?
                                            AND locations.crime_event_id = crime_events.id',
                                            [$platsWithoutLan]
                                        );
                            });
                        })
                        ->with('locations')
                        ->paginate(10);

            #dd(DB::getQueryLog());

            // Hämta mest vanligt förekommande händelsetyperna
            $mostCommonCrimeTypes = CrimeEvent::selectRaw('parsed_title, count(id) as antal')
                ->where("administrative_area_level_1", $oneLanName) // måste vara med
                        ->where(function ($query) use ($oneLanName, $platsWithoutLan) {
                            $query->where("parsed_title_location", $platsWithoutLan);
                            $query->orWhereExists(function ($query) use ($platsWithoutLan) {
                                $query->select(DB::raw(1))
                                        ->from('locations')
                                        ->whereRaw(
                                            'locations.name = ?
                                            AND locations.crime_event_id = crime_events.id',
                                            [$platsWithoutLan]
                                        );
                            });
                        })
                ->groupBy('parsed_title')
                ->orderByRaw('antal DESC')
                ->limit(5)
                ->get();

            $canonicalLink = $plats;

            // Rensa uppp plats lite
            $plats = sprintf(
                '%1$s i %2$s',
                title_case($platsWithoutLan),
                title_case($oneLanName)
            );

            // Debugbar::info('Hämta events där vi vet både platsnamn och län');
        } else {
            // Hämta events där plats är från huvudtabellen
            // Används när $plats är bara en plats, typ "insjön",
            // "östersunds centrum", "östra karup", "kungsgatan" osv.
            $events = CrimeEvent::orderBy("created_at", "desc")
                                        ->where("parsed_title_location", $plats)
                                        ->orWhere("administrative_area_level_2", $plats)
                                        ->orWhereHas('locations', function ($query) use ($plats) {
                                            $query->where('name', '=', $plats);
                                        })
                                        ->with('locations')
                                        ->paginate(10);
            $canonicalLink = $plats;
            $plats = title_case($plats);

            // Hämta mest vanligt förekommande händelsetyperna
            $mostCommonCrimeTypes = CrimeEvent::selectRaw('parsed_title, count(id) as antal')
                ->where("parsed_title_location", $plats)
                ->orWhere("administrative_area_level_2", $plats)
                ->orWhereHas('locations', function ($query) use ($plats) {
                    $query->where('name', '=', $plats);
                })
                ->groupBy('parsed_title')
                ->orderByRaw('antal DESC')
                ->limit(5)
                ->get();

            // Debugbar::info('Hämta events där vi bara vet platsnamn');
            // Indexera inte denna sida om det är en gata, men indexera om det är en ort osv.
            // Får avvakta med denna pga vet inte exakt vad en plats är för en..eh..plats.
            // $data['robotsNoindex'] = true;
        }

        $data['plats'] = $plats;
        $data['events'] = $events;
        $data['mostCommonCrimeTypes'] = $mostCommonCrimeTypes;

        $mostCommonCrimeTypesMetaDescString = '';
        foreach ($mostCommonCrimeTypes as $oneCrimeType) {
            $mostCommonCrimeTypesMetaDescString .= $oneCrimeType->parsed_title . ', ';
        }
        $mostCommonCrimeTypesMetaDescString = trim($mostCommonCrimeTypesMetaDescString, ', ');

        $metaDescription = "Se senaste brotten som skett i och omkring $plats. $mostCommonCrimeTypesMetaDescString är vanliga händelser nära $plats. Informationen hämtas direkt från Polisen.";
        $data['metaDescription'] = $metaDescription;

        $page = (int) $request->input("page", 1);

        if (!$page) {
            $page = 1;
        }

        $linkRelPrev = null;
        $linkRelNext = null;

        if ($page > 1) {
            $linkRelPrev = route('platsSingle', [
                'plats' => $platsOriginalFromSlug,
                'page' => $page - 1
            ]);
        }

        if ($page < $events->lastpage()) {
            $linkRelNext = route('platsSingle', [
                'plats' => $platsOriginalFromSlug,
                'page' => $page + 1
            ]);
        }

        $data["linkRelPrev"] = $linkRelPrev;
        $data["linkRelNext"] = $linkRelNext;

        if ($page == 1) {
            $canonicalLink = route('platsSingle', ['plats' => mb_strtolower($platsOriginalFromSlug)]);
        } else {
            $canonicalLink = route('platsSingle', ['plats' => mb_strtolower($platsOriginalFromSlug), 'page' => $page]);
        }

        $data["canonicalLink"] = $canonicalLink;
        $data["page"] = $page;

        if (!$data["events"]->count()) {
            abort(404);
        }

        $breadcrumbs = new Creitive\Breadcrumbs\Breadcrumbs;
        $breadcrumbs->addCrumb('Hem', '/');
        $breadcrumbs->addCrumb('Platser', route("platserOverview"));
        $breadcrumbs->addCrumb(e($plats));

        $data["breadcrumbs"] = $breadcrumbs;

        // Hämta statistik för platsen
        // $data["chartImgUrl"] = App\Helper::getStatsImageChartUrl("Stockholms län");
        $introtext_key = "introtext-plats-$plats";

        $introtext = null;
        if ($page == 1) {
            $introtext = Markdown::parse(Setting::get($introtext_key));
        }

        $data["introtext"] = $introtext;

        return view('single-plats', $data);
    }

}
