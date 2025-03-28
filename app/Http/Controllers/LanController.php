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
        $breadcrumbs->setDivider('›');
        $breadcrumbs->addCrumb('Hem', '/');
        $breadcrumbs->addCrumb('Län', route("lanOverview"));

        $data["breadcrumbs"] = $breadcrumbs;

        return view('overview-lan', $data);
    }

    /**
     * Visa händelser för ett län ett specifikt datum.
     *
     * URL är t.ex.
     * - https://brottsplatskartan.localhost/lan/Stockholms%20l%C3%A4n
     * - https://brottsplatskartan.se/lan/Stockholms%20l%C3%A4n/handelser/3-februari-2018
     */
    public function day(Request $request, $lan, $date = null)
    {
        $date = \App\Helper::getdateFromDateSlug($date);
        if (!$date) {
            abort(500, 'Knas med datum hörru');
        }

        $isToday = $date['date']->isToday();
        $isYesterday = $date['date']->isYesterday();
        $isCurrentYear = $date['date']->isCurrentYear();

        // Om län innehåller minustecken ersätter vi det med mellanslag, pga lagrar länen icke-slug'ade
        $lan = str_replace('-', ' ', $lan);

        $daysBack = 3;
        if ($isToday) {
            // Hämta händelser för flera dagar pga vi vill inte riskera att få en tom lista.
            $events = CrimeEvent::orderBy("created_at", "desc")
                ->where('created_at', '<', $date['date']->copy()->addDays(1)->format('Y-m-d'))
                ->where('created_at', '>', $date['date']->copy()->subDays($daysBack)->format('Y-m-d'))
                ->where("administrative_area_level_1", $lan)
                ->with('locations')
                ->limit(500)
                ->get();
        } else {
            // Hämta alla händelser för detta datum.
            $events = CrimeEvent::orderBy("created_at", "desc")
                ->where('created_at', '<', $date['date']->copy()->addDays(1)->format('Y-m-d'))
                ->where('created_at', '>', $date['date']->format('Y-m-d'))
                ->where("administrative_area_level_1", $lan)
                ->with('locations')
                ->get();
        }

        // Hämta mest vanligt förekommande brotten
        $mostCommonCrimeTypes = CrimeEvent::selectRaw('parsed_title, count(id) as antal')
            ->where('created_at', '<', $date['date']->copy()->addDays(1)->format('Y-m-d'))
            ->where('created_at', '>', $date['date']->format('Y-m-d'))
            ->where("administrative_area_level_1", $lan)
            ->groupBy('parsed_title')
            ->orderByRaw('antal DESC')
            ->limit(5)
            ->get();

        // Group events by day
        $eventsByDay = $events->groupBy(function ($item, $key) {
            return date('Y-m-d', strtotime($item->created_at));
        });

        $prevDaysNavInfo = \App\Helper::getLanPrevDaysNavInfo($date['date'], $lan);
        $nextDaysNavInfo = \App\Helper::getLanNextDaysNavInfo($date['date'], $lan);

        $prevDayLink = null;
        if ($prevDaysNavInfo->count()) {
            $firstDay = $prevDaysNavInfo->first();
            $firstDayDate = Carbon::parse($firstDay['dateYMD']);
            $formattedDate = trim(str::lower($firstDayDate->isoFormat('D-MMMM-YYYY')));
            $formattedDateFortitle = trim($firstDayDate->isoFormat('dddd D MMMM YYYY'));
            $prevDayLink = [
                'title' => sprintf('‹ %1$s', $formattedDateFortitle),
                'link' => route("lanDate", ['lan' => $lan, 'date' => $formattedDate])
            ];
        }

        $nextDayLink = null;
        if ($nextDaysNavInfo->count()) {
            $firstDay = $nextDaysNavInfo->first();
            $firstDayDate = Carbon::parse($firstDay['dateYMD']);
            $formattedDate = trim(str::lower($firstDayDate->isoFormat('D-MMMM-YYYY')));
            $formattedDateFortitle = trim($firstDayDate->isoFormat('dddd D MMMM YYYY'));
            $nextDayLink = [
                'title' => sprintf('%1$s ›', $formattedDateFortitle),
                'link' => route("lanDate", ['lan' => $lan, 'date' => $formattedDate])
            ];
        }

        if ($isCurrentYear) {
            $dateLocalized = trim($date['date']->isoFormat('dddd D MMMM'));
        } else {
            $dateLocalized = trim($date['date']->isoFormat('dddd D MMMM YYYY'));
        }

        if ($isToday) {
            $title = sprintf(
                '
                    Händelser i %2$s idag
                ',
                $dateLocalized,
                $lan
            );
        } elseif ($isYesterday) {
            $title = sprintf(
                '
                    Händelser i %2$s
                    <br><strong>igår %1$s</strong>
                ',
                $dateLocalized,
                $lan
            );
        } else {
            $title = sprintf(
                '
                    Polisen i %2$s
                    <br>
                    <strong>
                        Händelser
                        %1$s
                    </strong>
                ',
                $dateLocalized,
                $lan
            );
        }

        if ($isToday) {
            $canonicalLink = route('lanSingle', ['lan' => $lan]);
        } else {
            $canonicalLink = route(
                'lanDate',
                [
                    'lan' => $lan,
                    'date' => trim(str::lower($date['date']->formatLocalized('%e-%B-%Y')))
                ]
            );
        }
        
        $fintFormateratDatum = trim($date['date']->formatLocalized('%A %e %B %Y'));

        if ($isToday) {
            $pageTitle = "Brott och händelser från Polisen i $lan";
        } else {
            $pageTitle = sprintf(
                '%2$s: %1$d händelser från Polisen i %3$s',
                $events->count(),
                $fintFormateratDatum,
                $lan
            );
        }


        $data = [
            'title' => $title,
            'pageTitle' => $pageTitle,
            'events' => $events,
            'eventsByDay' => $eventsByDay,
            'lan' => $lan,
            'isLan' => true,
            'linkRelPrev' => !empty($prevDayLink) ? $prevDayLink['link'] : null,
            'linkRelNext' => !empty($nextDayLink) ? $nextDayLink['link'] : null,
            'nextDayLink' => $nextDayLink,
            'prevDayLink' => $prevDayLink,
            'canonicalLink' => $canonicalLink,
            'mostCommonCrimeTypes' => $mostCommonCrimeTypes,
            'dateFormattedForMostCommonCrimeTypes' => $fintFormateratDatum,
            'isToday' => $isToday,
            'isYesterday' => $isYesterday,
            'isCurrentYear' => $isCurrentYear,
            'numEvents' => $events->count()
        ];

        if (!$isToday && !$data["events"]->count()) {
            abort(404);
        }

        $breadcrumbs = new \Creitive\Breadcrumbs\Breadcrumbs;
        $breadcrumbs->setDivider('›');
        $breadcrumbs->addCrumb('Hem', '/');
        $breadcrumbs->addCrumb('Län', route("lanOverview"));
        $breadcrumbs->addCrumb(e($lan), e($lan));

        if (!$isToday) {
            $breadcrumbs->addCrumb($fintFormateratDatum);
        }

        $data["breadcrumbs"] = $breadcrumbs;
        $data["showLanSwitcher"] = true;

        // Kolla om förklarande text för län finns
        // key = like "introtext-lan-Stockholms län"
        $introtext_key = "introtext-lan-$lan";
        $data["introtext"] = Str::markdown(\Setting::get($introtext_key, '') );

        // Hämta statistik för ett län
        $data['chartHtml'] = \App\Helper::getStatsChartHtml($lan);

        $data["lanInfo"] = \App\Helper::getSingleLanWithStats($lan);

        $mostCommonCrimeTypesMetaDescString = '';
        foreach ($mostCommonCrimeTypes as $oneCrimeType) {
            $mostCommonCrimeTypesMetaDescString .= $oneCrimeType->parsed_title . ', ';
        }
        $mostCommonCrimeTypesMetaDescString = trim($mostCommonCrimeTypesMetaDescString, ', ');

        $metaDescription = null;
        if ($isToday) {
            $metaDescription = "Se var brott sker i närheten av {$lan}. Vanliga händelser i {$lan} är: {$mostCommonCrimeTypesMetaDescString}. Informationen kommer direkt från Polisen till vår karta.";
        } else {
            // $metaDescription = '';
        }

        $policeStations = \App\Helper::getPoliceStationsCached()->first(function ($val, $key) use ($lan) {
            // ('lanName', $lan);
            return mb_strtolower($val['lanName']) === mb_strtolower($lan);
        });
        $data['policeStations'] = $policeStations;

        $data['metaDescription'] = $metaDescription;
        $data['mostCommonCrimeTypes'] = $mostCommonCrimeTypes;

        $relatedLinks = \App\Helper::getRelatedLinks(null, $lan);

        $data['relatedLinks'] = $relatedLinks;

        return view('single-lan', $data);
    }
}
