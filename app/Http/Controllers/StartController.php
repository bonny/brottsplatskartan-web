<?php

namespace App\Http\Controllers;

use DB;
use App\Helper;
use App\CrimeEvent;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;

/**
 * Startsidan
 */
class StartController extends Controller
{

    /**
     * Startpage: visa senaste händelserna, datum/dag-versionen.
     *
     * URL är som
     * https://brottsplatskartan.se/handelser/15-januari-2018
     * eller som startsida, då blir datum dagens datum
     * https://brottsplatskartan.se/
     *
     * @param Request $request Request-object.
     * @param Carbon  $date    Year in format "december-2017".
     */
    public function start(Request $request)
    {
        // Om startsida så hämta för flera dagar,
        // så vi inte står där utan händelser.
        $daysBack = 3;

        // Dagens datum.
        $date = ['date' =>Carbon::now()];

        $mostCommonCrimeTypes = $this->getMostCommonCrimeTypesForToday(
            $date,
            $daysBack
        );
        
        // Senaste händelserna
        $eventsRecent = $this->getEventsForToday($date, $daysBack);
        
        // Behåll bara de n senaste
        $eventsRecent = $eventsRecent->take(20);

        // Mest lästa senaste nn minuterna.
        $eventsMostViewedRecently = Helper::getMostViewedEventsRecently(20, 20);
        // dd($eventsMostViewedRecently);

        // Mest lästa idag.
        $eventsMostViewedToday = Helper::getMostViewedEvents(Carbon::now(), 10);

        $introtext = null;
        $introtext_key = "introtext-start";
        $introtext = \Markdown::parse(\Setting::get($introtext_key));

        $title = 'Händelser från Polisen';

        $canonicalLink = route('start');

        $pageTitle = 'Polisens händelser – brott, nyheter, händelser från polisen';
        $pageMetaDescription =
            'Läs de senaste händelserna & brotten som Polisen rapporterat. Se polishändelser ✔ nära dig ✔ i din ort ✔ i ditt län. Händelserna hämtas direkt från Polisens webbplats.';

        // Mest lästa är crimeViews, ändra så vi behåller crimeEvents.
        $eventsMostViewedRecentlyCrimeEvents = $eventsMostViewedRecently->map(function ($item) {
            return $item['crimeEvent'];
        });

        $data = [
            'eventsMostViewedRecentlyCrimeEvents' => $eventsMostViewedRecentlyCrimeEvents,
            'eventsRecent' => $eventsRecent,
            'chartImgUrl' => \App\Helper::getStatsImageChartUrl("home"),
            'title' => $title,
            'introtext' => $introtext,
            'canonicalLink' => $canonicalLink,
            'ogUrl' => $canonicalLink,
            'pageTitle' => $pageTitle,
            'pageMetaDescription' => $pageMetaDescription,
            'mostCommonCrimeTypes' => $mostCommonCrimeTypes,
            'dateFormattedForMostCommonCrimeTypes' => trim(
                $date['date']->formatLocalized('%e %B')
            ),
        ];

        return view('start', $data);
    }


    /**
     * Händelser: visa senaste händelserna för en viss dag/datum.
     *
     * URL är som
     * https://brottsplatskartan.se/handelser/
     * https://brottsplatskartan.se/handelser/15-januari-2018
     *
     * @param Request $request Request-object.
     * @param Carbon  $date    Year in format "december-2017".
     */
    public function day(Request $request, $date = null)
    {
        $date = \App\Helper::getdateFromDateSlug($date);

        if (!$date) {
            abort(500, 'Knas med datum hörru');
        }

        $isToday = $date['date']->isToday();
        $isYesterday = $date['date']->isYesterday();
        $isCurrentYear = $date['date']->isCurrentYear();

        // Hämta events från vald dag
        if ($isToday) {
            // Om startsida så hämta för flera dagar,
            // så vi inte står där utan händelser.
            $daysBack = 3;

            $events = $this->getEventsForToday($date, $daysBack);
            $mostCommonCrimeTypes = $this->getMostCommonCrimeTypesForToday(
                $date,
                $daysBack
            );            
        } else {
            // Om inte idag.
            $beforeDate = $date['date']
                ->copy()
                ->addDays(1)
                ->format('Y-m-d');
            $afterDate = $date['date']->format('Y-m-d');

            $events = CrimeEvent::where('created_at', '<', $beforeDate)
                ->where('created_at', '>', $afterDate)
                ->orderBy("created_at", "desc")
                ->with('locations')
                ->get();

            $mostCommonCrimeTypes = CrimeEvent::selectRaw(
                'parsed_title, count(id) as antal'
            )
                ->where('created_at', '<', $beforeDate)
                ->where('created_at', '>', $afterDate)
                ->groupBy('parsed_title')
                ->orderByRaw('antal DESC')
                ->limit(5)
                ->get();
        }

        // Group events by day
        $eventsByDay = $events->groupBy(function ($item, $key) {
            return date('Y-m-d', strtotime($item->created_at));
        });

        // Om idag så behåll bara idag, om events finns, pga blir så sjukt många annars
        // alltså flera hundra = tar långt tid att ladda sidan.
        if ($isToday) {
            $eventsByDay = $eventsByDay->splice(0, 1);
        }

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
            $formattedDate = trim(
                str::lower($firstDayDate->formatLocalized('%e-%B-%Y'))
            );
            $formattedDateFortitle = trim(
                $firstDayDate->formatLocalized('%A %e %B %Y')
            );
            $prevDayLink = [
                'title' => sprintf('‹ %1$s', $formattedDateFortitle),
                'link' => route("startDatum", ['date' => $formattedDate]),
            ];
        }

        $nextDayLink = null;
        if ($nextDaysNavInfo->count()) {
            $firstDay = $nextDaysNavInfo->first();
            $firstDayDate = Carbon::parse($firstDay['dateYMD']);
            $formattedDate = trim(
                str::lower($firstDayDate->formatLocalized('%e-%B-%Y'))
            );
            $formattedDateFortitle = trim(
                $firstDayDate->formatLocalized('%A %e %B %Y')
            );
            $nextDayLink = [
                'title' => sprintf('%1$s ›', $formattedDateFortitle),
                'link' => route("startDatum", ['date' => $formattedDate]),
            ];
        }

        // Add breadcrumbs for dates before today
        if (!$isToday) {
            // $breadcrumbs = new \Creitive\Breadcrumbs\Breadcrumbs;
            // $breadcrumbs->setDivider('›');
            // $breadcrumbs->addCrumb('Hem', '/');
            // $breadcrumbs->addCrumb('Datum', route("start"));
            // $breadcrumbs->addCrumb('Län', route("lanOverview"));
            // $breadcrumbs->addCrumb('Alla län', route("lanOverview"));
        }

        $introtext = null;
        $introtext_key = "introtext-handelser";
        if ($isToday) {
            $introtext = \Markdown::parse(\Setting::get($introtext_key));
        }

        if ($isCurrentYear) {
            // Skriv inte ut datum om det är nuvarande år
            $dateLocalized = trim($date['date']->formatLocalized('%A %e %B'));
        } else {
            $dateLocalized = trim(
                $date['date']->formatLocalized('%A %e %B %Y')
            );
        }

        // Skapa fin titel.
        if ($isToday) {
            $title = sprintf(
                '
                    Händelser från Polisen idag
                ',
                $dateLocalized
            );
        } elseif ($isYesterday) {
            $title = sprintf(
                '
                    Händelser från Polisen
                    <br><strong>Igår %1$s</strong>
                ',
                $dateLocalized
            );
        } else {
            $title = sprintf(
                '
                    Händelser från Polisen
                    <br><strong>%1$s</strong>
                ',
                $dateLocalized
            );
        }

        if ($isToday) {
            $canonicalLink = route('start');
        } else {
            $canonicalLink = route('startDatum', [
                'date' => trim(
                    str::lower($date['date']->formatLocalized('%e-%B-%Y'))
                ),
            ]);
        }

        $pageTitle = '';
        $pageMetaDescription = '';

        if ($isToday) {
            $pageTitle =
                'Senaste händelserna från Polisen';
            $pageMetaDescription =
                'Läs de senaste händelserna & brotten som Polisen rapporterat. Se polishändelser ✔ nära dig ✔ i din ort ✔ i ditt län. Händelserna hämtas direkt från Polisens webbplats.';
        } else {
            $pageTitle = sprintf(
                'Händelser från Polisen %2$s - %1$d händelser',
                $events->count(),
                trim($date['date']->formatLocalized('%A %e %B %Y'))
            );
        }

        $data = [
            'events' => $events,
            'eventsByDay' => $eventsByDay,
            'showLanSwitcher' => true,
            'breadcrumbs' => isset($breadcrumbs) ? $breadcrumbs : null,
            'chartImgUrl' => \App\Helper::getStatsImageChartUrl("home"),
            'title' => $title,
            'nextDayLink' => $nextDayLink,
            'prevDayLink' => $prevDayLink,
            'linkRelPrev' => !empty($prevDayLink) ? $prevDayLink['link'] : null,
            'linkRelNext' => !empty($nextDayLink) ? $nextDayLink['link'] : null,
            'numEvents' => $events->count(),
            'isToday' => $isToday,
            'introtext' => $introtext,
            'canonicalLink' => $canonicalLink,
            'ogUrl' => $canonicalLink,
            'pageTitle' => $pageTitle,
            'pageMetaDescription' => $pageMetaDescription,
            'mostCommonCrimeTypes' => $mostCommonCrimeTypes,
            'dateFormattedForMostCommonCrimeTypes' => trim(
                $date['date']->formatLocalized('%e %B')
            )
        ];

        return view('handelser', $data);
    }

    /**
     * Hämta händelser till startsidan för idag.
     *
     * @param Carbon  $date Dagens datum.
     * @param integer $daysBack Antal dagar tillbaka att hämta för
     *
     * @return Collection Händelser.
     */
    function getEventsForToday($date, $daysBack = 3)
    {
        $cacheKey =
            'getEventsForToday:date:' .
            $date['date']->format('Y-m-d') .
            ':daysback:' .
            $daysBack;

        $events = Cache::remember($cacheKey, 2 * 60, function () use (
            $date,
            $daysBack
        ) {
            $beforeDate = $date['date']
                ->copy()
                ->addDays(1)
                ->format('Y-m-d');
            $afterDate = $date['date']
                ->copy()
                ->subDays($daysBack)
                ->format('Y-m-d');

            $events = CrimeEvent::where('created_at', '<', $beforeDate)
                ->where('created_at', '>', $afterDate)
                ->orderBy("created_at", "desc")
                ->with('locations')
                ->limit(300)
                ->get();

            return $events;
        });

        return $events;
    }

    /**
     * Hämta händelsetyper till startsidan för idag.
     *
     * @param Carbon  $date Dagens datum.
     * @param integer $daysBack Antal dagar tillbaka att hämta för
     *
     * @return Collection Händelser.
     */

    function getMostCommonCrimeTypesForToday($date, $daysBack)
    {
        $cacheKey =
            'getMostCommonCrimeTypesForToday:date:' .
            $date['date']->format('Y-m-d') .
            ':daysback:' .
            $daysBack;

        $mostCommonCrimeTypes = Cache::remember(
            $cacheKey,
            10 * 60,
            function () use ($date, $daysBack) {
                $mostCommonCrimeTypes = CrimeEvent::selectRaw(
                    'parsed_title, count(id) as antal'
                )
                    ->where('created_at', '<=', $date['date']->format('Y-m-d'))
                    ->where(
                        'created_at',
                        '>=',
                        $date['date']
                            ->copy()
                            ->subDays($daysBack)
                            ->format('Y-m-d')
                    )
                    ->groupBy('parsed_title')
                    ->orderByRaw('antal DESC')
                    ->limit(5)
                    ->get();

                return $mostCommonCrimeTypes;
            }
        );

        return $mostCommonCrimeTypes;
    }
}
