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
    /**
     * Översikt, lista alla platser/orter
     */
    public function overview(Request $request)
    {
        $data = [];

        $data["orter"] = \App\Helper::getOrter();

        $breadcrumbs = new \Creitive\Breadcrumbs\Breadcrumbs;
        $breadcrumbs->addCrumb('Hem', '/');
        $breadcrumbs->addCrumb('Platser', route("platserOverview"));

        $data["breadcrumbs"] = $breadcrumbs;

        return view('overview-platser', $data);
    }

    /**
     * Enskild plats/ort
     */
    public function day(Request $request, $plats, $date = null)
    {

        $date = \App\Helper::getdateFromDateSlug($date);

        if (!$date) {
            abort(500, 'Knas med datum hörru');
        }

        $platsOriginalFromSlug = $plats;
        $data = [];
        $dateYMD = $date['date']->format('Y-m-d');

        // Om $plats slutar med namnet på ett län, t.ex. "örebro län", "gävleborgs län" osv
        // så ska platser i det länet med platsen $plats minus länets namn visas
        $allLans = \App\Helper::getAllLan();
        $allLansNames = $allLans->pluck("administrative_area_level_1");
        $foundMatchingLan = false;
        $matchingLanName = null;
        $platsWithoutLan = null;
        $platsSluggified = \App\Helper::toAscii($plats);

        // Kolla om platsen $plats även inkluderar ett län
        // T.ex. om URL är # så ska vi hitta "stockholms län"
        foreach ($allLansNames as $oneLanName) {
            $lanSlug = \App\Helper::toAscii($oneLanName);

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

        if ($foundMatchingLan) {
            // Hämta events där vi vet både plats och län
            // t.ex. "Stockholm" i "Stockholms län"
            $events = $this->getEventsInPlatsWithLan($platsWithoutLan, $oneLanName, $dateYMD);

            // Hämta mest vanligt förekommande händelsetyperna
            $mostCommonCrimeTypes = $this->getMostCommonCrimeTypesInPlatsWithLan($platsWithoutLan, $oneLanName, $dateYMD);

            $canonicalLink = $plats;

            // Skapa fint namn av platsen och länet, blir t.ex. "Orminge i Stockholms Län"
            $plats = sprintf(
                '%1$s i %2$s',
                title_case($platsWithoutLan),
                title_case($oneLanName)
            );
        } else {
            // Hämta events där plats är från huvudtabellen
            // Används när $plats är bara en plats, typ "insjön",
            // "östersunds centrum", "östra karup", "kungsgatan" osv.
            $events = $this->getEventsInPlats($plats, $dateYMD);
            $canonicalLink = $plats;
            $plats = title_case($plats);

            // Hämta mest vanligt förekommande händelsetyperna
            $mostCommonCrimeTypes = $this->getMostCommonCrimeTypesInPlats($plats, $dateYMD);

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

        $breadcrumbs = new \Creitive\Breadcrumbs\Breadcrumbs;
        $breadcrumbs->addCrumb('Hem', '/');
        $breadcrumbs->addCrumb('Platser', route("platserOverview"));
        $breadcrumbs->addCrumb(e($plats));

        $data["breadcrumbs"] = $breadcrumbs;

        // Hämta statistik för platsen
        // $data["chartImgUrl"] = \App\Helper::getStatsImageChartUrl("Stockholms län");
        $introtext_key = "introtext-plats-$plats";

        $introtext = null;
        if ($page == 1) {
            $introtext = \Markdown::parse(\Setting::get($introtext_key));
        }

        $data["introtext"] = $introtext;

        return view('single-plats', $data);
    }

    /**
     * https://brottsplatskartan.localhost/plats/orminge-stockholms-län/handelser/2017-02-01
     */
    // public function day(Request $request, $plats, $date)
    // {
    //     $date = \App\Helper::getdateFromDateSlug($date);
    //     if (!$date) {
    //         abort(500, 'Knas med datum hörru');
    //     }

    //     dd('yo', $date);
    // }

    public function getEventsInPlatsWithLan($platsWithoutLan, $oneLanName, $dateYMD)
    {
        $events = CrimeEvent::orderBy("created_at", "desc")
                ->whereDate('created_at', $dateYMD)
                ->where("administrative_area_level_1", $oneLanName) // måste vara med
                // gruppera dessa
                ->where(function ($query) use ($oneLanName, $platsWithoutLan) {
                    $query->where("parsed_title_location", $platsWithoutLan);
                    $query->orWhereExists(function ($query) use ($platsWithoutLan) {
                        $query->select(\DB::raw(1))
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

        return $events;
    }

    public function getMostCommonCrimeTypesInPlatsWithLan($platsWithoutLan, $oneLanName, $dateYMD)
    {
        $mostCommonCrimeTypes = CrimeEvent::selectRaw('parsed_title, count(id) as antal')
            ->whereDate('created_at', $dateYMD)
            ->where("administrative_area_level_1", $oneLanName) // måste vara med
                    ->where(function ($query) use ($oneLanName, $platsWithoutLan) {
                        $query->where("parsed_title_location", $platsWithoutLan);
                        $query->orWhereExists(function ($query) use ($platsWithoutLan) {
                            $query->select(\DB::raw(1))
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

        return $mostCommonCrimeTypes;
    }

    public function getEventsInPlats($plats, $dateYMD)
    {
        // dd($dateYMD);
        $events = CrimeEvent::orderBy("created_at", "desc")
                    ->where(function ($query) use ($dateYMD) {
                        $query->whereDate('created_at', $dateYMD);
                    })
                    ->where(function ($query) use ($plats) {
                        $query->where("parsed_title_location", $plats);
                        $query->orWhere("administrative_area_level_2", $plats);
                        $query->orWhereHas('locations', function ($query) use ($plats) {
                            $query->where('name', '=', $plats);
                        });
                    })
                    ->with('locations')
                    ->paginate(10);

        return $events;
    }

    public function getMostCommonCrimeTypesInPlats($plats, $dateYMD)
    {
        $mostCommonCrimeTypes = CrimeEvent::selectRaw('parsed_title, count(id) as antal')
            ->whereDate('created_at', $dateYMD)
            ->where("parsed_title_location", $plats)
            ->orWhere("administrative_area_level_2", $plats)
            ->orWhereHas('locations', function ($query) use ($plats) {
                $query->where('name', '=', $plats);
            })
            ->groupBy('parsed_title')
            ->orderByRaw('antal DESC')
            ->limit(5)
            ->get();

        return $mostCommonCrimeTypes;
    }
}

/*
->whereDate('created_at', $date['date']->format('Y-m-d'))
*/
