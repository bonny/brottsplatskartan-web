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
        $dateOriginalFromArg = $date;
        $platsOriginalFromSlug = $plats;

        $date = \App\Helper::getdateFromDateSlug($date);
        if (!$date) {
            abort(500, 'Knas med datum hörru');
        }

        // Om page finns så är det en gammal URL,
        // skriv om till ny (eller hänvisa canonical iaf och använd dagens datum)
        $page = (int) $request->input("page", 0);
        if ($page) {
            $page = 0;
            $date = \App\Helper::getdateFromDateSlug(null);
        }

        $dateYMD = $date['date']->format('Y-m-d');
        $isToday = $date['date']->isToday();
        $isYesterday = $date['date']->isYesterday();
        $isCurrentYear = $date['date']->isCurrentYear();

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

        // Om en plats är i "sverige" snarare än ett specifikt län så blir plats-url fel:
        // https://brottsplatskartan.localhost/plats/basvägen-
        // Ta bort '-' och redirecta till platsen.
        if (ends_with($plats, '-')) {
            $plats = trim($plats, '-');
            return redirect()->route('platsSingle', ['plats' => $plats]);
        }

        if ($foundMatchingLan) {
            // Hämta events där vi vet både plats och län
            // t.ex. "Stockholm" i "Stockholms län"
            $events = $this->getEventsInPlatsWithLan($platsWithoutLan, $oneLanName, $date, 7, $isToday);

            // Hämta mest vanligt förekommande händelsetyperna
            $mostCommonCrimeTypes = $this->getMostCommonCrimeTypesInPlatsWithLan($platsWithoutLan, $oneLanName, $dateYMD);

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
            $events = $this->getEventsInPlats($plats, $date, 14, $isToday);
            $plats = title_case($plats);
            // dd($plats, $dateYMD, $events);

            // Hämta mest vanligt förekommande händelsetyperna
            $mostCommonCrimeTypes = $this->getMostCommonCrimeTypesInPlats($plats, $dateYMD);

            // Debugbar::info('Hämta events där vi bara vet platsnamn');
            // Indexera inte denna sida om det är en gata, men indexera om det är en ort osv.
            // Får avvakta med denna pga vet inte exakt vad en plats är för en..eh..plats.
            // $data['robotsNoindex'] = true;
        }

        // Group events by day
        $eventsByDay = $events->groupBy(function ($item, $key) {
            return date('Y-m-d', strtotime($item->created_at));
        });

        $mostCommonCrimeTypesMetaDescString = '';
        foreach ($mostCommonCrimeTypes as $oneCrimeType) {
            $mostCommonCrimeTypesMetaDescString .= $oneCrimeType->parsed_title . ', ';
        }
        $mostCommonCrimeTypesMetaDescString = trim($mostCommonCrimeTypesMetaDescString, ', ');

        $metaDescription = "Se senaste brotten som skett i och omkring $plats. $mostCommonCrimeTypesMetaDescString är vanliga händelser nära $plats. Informationen hämtas direkt från Polisen.";

        $linkRelPrev = null;
        $linkRelNext = null;

        $breadcrumbs = new \Creitive\Breadcrumbs\Breadcrumbs;
        $breadcrumbs->addCrumb('Hem', '/');
        $breadcrumbs->addCrumb('Platser', route("platserOverview"));
        $breadcrumbs->addCrumb(e($plats));

        // Hämta statistik för platsen
        // $data["chartImgUrl"] = \App\Helper::getStatsImageChartUrl("Stockholms län");
        $introtext_key = "introtext-plats-$plats";
        $introtext = null;

        if ($page == 1) {
            $introtext = \Markdown::parse(\Setting::get($introtext_key));
        }

        // Start daynav
        if ($foundMatchingLan) {
            $prevDaysNavInfo = $this->getPlatsPrevDaysNavInfo($date['date'], 5, $platsWithoutLan, $oneLanName);
            $nextDaysNavInfo = $this->getPlatsNextDaysNavInfo($date['date'], 5, $platsWithoutLan, $oneLanName);
        } else {
            $prevDaysNavInfo = $this->getPlatsPrevDaysNavInfo($date['date'], 5, $plats);
            $nextDaysNavInfo = $this->getPlatsNextDaysNavInfo($date['date'], 5, $plats);
        }

        $prevDayLink = null;
        if ($prevDaysNavInfo->count()) {
            $firstDay = $prevDaysNavInfo->first();
            $firstDayDate = Carbon::parse($firstDay['dateYMD']);
            $formattedDate = trim(str::lower($firstDayDate->formatLocalized('%e-%B-%Y')));
            $formattedDateFortitle = trim($firstDayDate->formatLocalized('%A %e %B %Y'));
            $prevDayLink = [
                'title' => sprintf('‹ %1$s', $formattedDateFortitle),
                'link' => route("platsDatum", ['plats' => $platsOriginalFromSlug, 'date' => $formattedDate])
            ];
        }

        $nextDayLink = null;
        if ($nextDaysNavInfo->count()) {
            $firstDay = $nextDaysNavInfo->first();
            $firstDayDate = Carbon::parse($firstDay['dateYMD']);
            $formattedDate = trim(str::lower($firstDayDate->formatLocalized('%e-%B-%Y')));
            $formattedDateFortitle = trim($firstDayDate->formatLocalized('%A %e %B %Y'));
            $nextDayLink = [
                'title' => sprintf('%1$s ›', $formattedDateFortitle),
                'link' => route("platsDatum", ['plats' => $platsOriginalFromSlug, 'date' => $formattedDate])
            ];
        }

        // Inkludera inte datum i canonical url om det är idag vi tittar på
        if ($dateOriginalFromArg) {
            // There was a date included
            $canonicalLink = route('platsDatum', [
                'plats' => mb_strtolower($platsOriginalFromSlug),
                'date' => trim(str::lower($date['date']->formatLocalized('%e-%B-%Y')))
                ]
            );
        } else {
            $canonicalLink = route('platsSingle', [
                'plats' => mb_strtolower($platsOriginalFromSlug)
                ]
            );
        }

        $data = [
            'plats' => $plats,
            'events' => $events,
            'eventsByDay' => $eventsByDay,
            'mostCommonCrimeTypes' => $mostCommonCrimeTypes,
            'metaDescription' => $metaDescription,
            "linkRelPrev" => $linkRelPrev,
            "linkRelNext" => $linkRelNext,
            "page" => $page,
            "breadcrumbs" => $breadcrumbs,
            "introtext" => $introtext,
            'isToday' => $isToday,
            'isYesterday' => $isYesterday,
            'isCurrentYear' => $isCurrentYear,
            "canonicalLink" => $canonicalLink,
            'prevDayLink' => $prevDayLink,
            'nextDayLink' => $nextDayLink,
            'dateForTitle' => $date['date']->formatLocalized('%e %B %Y'),
        ];

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

    /**
     * Hämta händelser för en plats som inkluderar län.
     * URL är t.ex.
     * https://brottsplatskartan.localhost/plats/fru%C3%A4ngen-stockholms-l%C3%A4n
     *
     * @param [type] $platsWithoutLan
     * @param [type] $oneLanName
     * @param [type] $date
     * @param integer $numDaysBack
     * @param boolean $isToday
     * @return void
     */
    public function getEventsInPlatsWithLan($platsWithoutLan, $oneLanName, $date, $numDaysBack = 7, $isToday = false)
    {
        $events = CrimeEvent::orderBy("created_at", "desc")
            ->where(function ($query) use ($date, $numDaysBack, $isToday) {
                if ($isToday) {
                    $query->whereDate('created_at', '<=', $date['date']->format('Y-m-d'));
                    $query->whereDate('created_at', '>=', $date['date']->copy()->subDays($numDaysBack)->format('Y-m-d'));
                } else {
                    $query->whereDate('created_at', $date['date']->format('Y-m-d'));
                }
            })
            ->where("administrative_area_level_1", $oneLanName)
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
            ->get();

        return $events;
    }

    public function getMostCommonCrimeTypesInPlatsWithLan($platsWithoutLan, $oneLanName, $dateYMD)
    {
        $mostCommonCrimeTypes = CrimeEvent::selectRaw('parsed_title, count(id) as antal')
            ->whereDate('created_at', $dateYMD)
            ->where("administrative_area_level_1", $oneLanName)
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

    /**
     * Get events.
     *
     * @param string $plats For example "tierp"
     * @param string $dateYMD Date in YMD format
     */
    public function getEventsInPlats($plats, $date, $numDaysBack = 7, $isToday = false)
    {
        /*
        */
        $events = CrimeEvent::orderBy("created_at", "desc")
                    ->where(function ($query) use ($date, $numDaysBack, $isToday) {
                        if ($isToday) {
                            $query->whereDate('created_at', '<=', $date['date']->format('Y-m-d'));
                            $query->whereDate('created_at', '>=', $date['date']->copy()->subDays($numDaysBack)->format('Y-m-d'));
                        } else {
                            $query->whereDate('created_at', $date['date']->format('Y-m-d'));
                        }
                    })
                    ->where(function ($query) use ($plats) {
                        $query->where("parsed_title_location", $plats);
                        $query->orWhere("administrative_area_level_2", $plats);
                        $query->orWhereHas('locations', function ($query) use ($plats) {
                            $query->where('name', '=', $plats);
                        });
                    })
                    ->with('locations')
                    ->get();
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

    // Om plats med län, skicka med plats + län-namnet ($platsWithoutLan, $oneLanName)
    // Om inte plats med län: skicka bara plats ($plats)
    public static function getPlatsPrevDaysNavInfo($date = null, $numDays = 5, $platsWithoutLan = null, $oneLanName = null)
    {
        if ($platsWithoutLan && $oneLanName) {
            $prevDayEvents = CrimeEvent::
                selectRaw('date(created_at) as dateYMD, count(*) as dateCount')
                ->whereDate('created_at', '<', $date->format('Y-m-d'))
                ->where("administrative_area_level_1", $oneLanName)
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
                ->groupBy(\DB::raw('dateYMD'))
                ->orderBy("created_at", "desc")
                ->limit($numDays)
                ->get();
        } else {
            $prevDayEvents = CrimeEvent::
                selectRaw('date(created_at) as dateYMD, count(*) as dateCount')
                ->whereDate('created_at', '<', $date->format('Y-m-d'))
                ->where(function ($query) use ($platsWithoutLan) {
                    $query->where("parsed_title_location", $platsWithoutLan);
                    $query->orWhere("administrative_area_level_2", $platsWithoutLan);
                    $query->orWhereHas('locations', function ($query) use ($platsWithoutLan) {
                        $query->where('name', '=', $platsWithoutLan);
                    });
                })
                ->groupBy(\DB::raw('dateYMD'))
                ->orderBy("created_at", "desc")
                ->limit($numDays)
                ->get();
        }

        return $prevDayEvents;
    }

    public static function getPlatsNextDaysNavInfo($date = null, $numDays = 5, $platsWithoutLan = null, $oneLanName = null)
    {
        if ($platsWithoutLan && $oneLanName) {
            $prevDayEvents = CrimeEvent::
                selectRaw('date(created_at) as dateYMD, count(*) as dateCount')
                ->whereDate('created_at', '>', $date->format('Y-m-d'))
                ->where("administrative_area_level_1", $oneLanName)
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
                ->groupBy(\DB::raw('dateYMD'))
                ->orderBy("created_at", "desc")
                ->limit($numDays)
                ->get();
        } else {
            $prevDayEvents = CrimeEvent::
                selectRaw('date(created_at) as dateYMD, count(*) as dateCount')
                ->whereDate('created_at', '>', $date->format('Y-m-d'))
                ->where(function ($query) use ($platsWithoutLan) {
                    $query->where("parsed_title_location", $platsWithoutLan);
                    $query->orWhere("administrative_area_level_2", $platsWithoutLan);
                    $query->orWhereHas('locations', function ($query) use ($platsWithoutLan) {
                        $query->where('name', '=', $platsWithoutLan);
                    });
                })
                ->groupBy(\DB::raw('dateYMD'))
                ->orderBy("created_at", "desc")
                ->limit($numDays)
                ->get();
        }

        return $prevDayEvents;
    }
}

/*
->whereDate('created_at', $date['date']->format('Y-m-d'))
*/
