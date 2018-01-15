<?php

namespace App\Http\Controllers;

use App\CrimeEvent;
use Carbon\Carbon;
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
    public function day($date, Request $request)
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
        $prevDayEvents = CrimeEvent::
            selectRaw('date(created_at) as dateYMD, count(*) as dateCount')
            ->whereDate('created_at', '<', $date['date']->addDay())
            ->groupBy(\DB::raw('DATE(created_at)'))
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        $nextDayEvents = CrimeEvent::
            selectRaw('date(created_at) as dateYMD, count(*) as dateCount')
            ->whereDate('created_at', '>', $date['date']->addDay())
            ->groupBy(\DB::raw('DATE(created_at)'))
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();


        $nextDay = [
            'title' => ''
        ];

        $datePrev = [
        ];

        dd($prevDayEvents->toArray(), $nextDayEvents->toArray());

        $data = [
            'events' => $events,
            'showLanSwitcher' => true,
            'breadcrumbs' => $breadcrumbs,
            'chartImgUrl' => \App\Helper::getStatsImageChartUrl("home"),
            'title' => "Händelser  " . $date['date']->formatLocalized('%A %d %B %Y')
        ];

        return view('start', $data);
    }
}
