<?php

namespace App\Http\Controllers;

use App\CrimeEvent;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Http\Request;

/**
 * Startsidan
 */
class StartController extends Controller
{

    /**
     * startpage: visa senaste händelserna, datum/dag-versionen
     * URL är som
     * https://brottsplatskartan.se/datum/15-januari-2018
     * @param string $year Year in format "december-2017"
     */
    public function day(Request $request, $date = null)
    {
        $date = \App\Helper::getdateFromDateSlug($date);

        if (!$date) {
            abort(500, 'Knas med datum hörru');
        }

        // Hämnta events från denna dag
        #dd($date['date']->format('Y-m-d'));
        $events = CrimeEvent::
            whereDate('created_at', $date['date']->format('Y-m-d'))
            ->orderBy("created_at", "desc")
            ->with('locations')
            ->get();

        $breadcrumbs = new \Creitive\Breadcrumbs\Breadcrumbs;
        $breadcrumbs->addCrumb('Hem', '/');
        $breadcrumbs->addCrumb('Län', route("lanOverview"));
        $breadcrumbs->addCrumb('Alla län', route("lanOverview"));

        // $introtext_key = "introtext-start";
        // if ($page == 1) {
        //     $data["introtext"] = Markdown::parse(Setting::get($introtext_key));
        // }

        // @TODO:

        // nästa dag som har händelser
        // hämta dag + antal händelser

        // aktuellt datum + 1 dag
        // om dag är nyare än dagens datum = false
        // annars: hämta antal händelser
        $prevDaysNavInfo = \App\Helper::getPrevDaysNavInfo($date['date']);
        $nextDaysNavInfo = \App\Helper::getNextDaysNavInfo($date['date']);

        $prevDayLink = null;
        if ($prevDaysNavInfo->count()) {
            $firstDay = $prevDaysNavInfo->first();
            $firstDayDate = Carbon::parse($firstDay['dateYMD']);
            $formattedDate = str::lower($firstDayDate->formatLocalized('%d-%B-%Y'));
            $formattedDateFortitle = $firstDayDate->formatLocalized('%A %d %B %Y');
            $prevDayLink = [
                'title' => sprintf('‹ %1$s', $formattedDateFortitle),
                'link' => route("startDatum", ['date' => $formattedDate])
            ];
        }

        $nextDayLink = null;
        if ($nextDaysNavInfo->count()) {
            $firstDay = $nextDaysNavInfo->first();
            $firstDayDate = Carbon::parse($firstDay['dateYMD']);
            $formattedDate = str::lower($firstDayDate->formatLocalized('%d-%B-%Y'));
            $formattedDateFortitle = $firstDayDate->formatLocalized('%A %d %B %Y');
            $nextDayLink = [
                'title' => sprintf('%1$s ›', $formattedDateFortitle),
                'link' => route("startDatum", ['date' => $formattedDate])
            ];
        }

        $numEventsToday = \DB::table('crime_events')
                    ->whereDate('created_at', $date['date']->format('Y-m-d'))
                    ->count();

        $data = [
            'events' => $events,
            'showLanSwitcher' => true,
            'breadcrumbs' => $breadcrumbs,
            'chartImgUrl' => \App\Helper::getStatsImageChartUrl("home"),
            'title' => sprintf('%1$s', $date['date']->formatLocalized('%A %e %B %Y')),
            'nextDayLink' => $nextDayLink,
            'prevDayLink' => $prevDayLink,
            'numEventsToday' => $numEventsToday
        ];

        return view('start', $data);
    }
}
