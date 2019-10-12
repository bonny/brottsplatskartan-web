<?php

namespace App\Http\Controllers;

use App\CrimeEvent;
use App\Place;
use Carbon\Carbon;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Controller för plats, översikt och detalj
 */
class PlatsController extends Controller
{
    /**
     * Översikt, lista alla platser/orter
     *
     * Exempel på URL:
     * https://brottsplatskartan.localhost/plats
     *
     * URL för att skapa platser som inte finns i plats-db
     * (ofarligt, men lite overhead så därför on demand):
     * https://brottsplatskartan.localhost/plats?skapaPlatser=1
     */
    public function overview(Request $request)
    {
        $data = [];

        $orter = \App\Helper::getOrter();
        $data["orter"] = $orter;
        // \Debugbar::info('$orter', $orter);
        // print_r($orter);

        $breadcrumbs = new \Creitive\Breadcrumbs\Breadcrumbs;
        $breadcrumbs->setDivider('›');
        $breadcrumbs->addCrumb('Hem', '/');
        $breadcrumbs->addCrumb('Platser', route("platserOverview"));

        // Skapa rader i tabellen om inte finns.
        // Kör on demand pga lite overhead.
        // @TODO: Denna borta finnas i import?
        if ($request->get('skapaPlatser')) {
            $orter->each(function ($ort) {
                $ortName = $ort->parsed_title_location;
                $ortName = trim($ortName);
                $place = Place::firstOrCreate([
                    'name' => $ortName
                ]);

                if ($place->wasRecentlyCreated) {
                    \Debugbar::info('$place skapades nyligen, så geocodar och grejjar', $place);
                    $place->geocode();
                } // if recently created
            });
        }

        $data["breadcrumbs"] = $breadcrumbs;

        return view('overview-platser', $data);
    }

    /**
     * Enskild plats/ort.
     * Exempel på URL:
     * https://brottsplatskartan.localhost/plats/stockholm
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
            // Exempel på url:
            // https://brottsplatskartan.localhost/plats/bananskal
            $events = $this->getEventsInPlats($plats, $date, 14, $isToday);

            // Om inga events för vald period, kolla om något finns alls.
            if (!$events->count()) {
                $eventsExists = CrimeEvent::where(function ($query) use ($plats) {
                    $query->where("parsed_title_location", $plats);
                    $query->orWhere("administrative_area_level_2", $plats);
                    $query->orWhereHas('locations', function ($query) use ($plats) {
                        $query->where('name', '=', $plats);
                    });
                })
                ->exists();

                if (!$eventsExists) {
                    abort(404);
                }
            }

            // Gör så att plats blir "Västra Hejsan Hoppsan" och inte "västra hejsan hoppsan".
            $plats = title_case($plats);

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
            $fintFormateratDatum = $firstDayDate->formatLocalized('%A %e %B %Y');
            $formattedDate = trim(str::lower($firstDayDate->formatLocalized('%e-%B-%Y')));
            $formattedDateFortitle = trim($fintFormateratDatum);
            $prevDayLink = [
                'title' => sprintf('‹ %1$s', $formattedDateFortitle),
                'link' => route("platsDatum", ['plats' => $platsOriginalFromSlug, 'date' => $formattedDate]),
            ];
        }

        $nextDayLink = null;
        if ($nextDaysNavInfo->count()) {
            $firstDay = $nextDaysNavInfo->first();
            $firstDayDate = Carbon::parse($firstDay['dateYMD']);
            $fintFormateratDatum = $firstDayDate->formatLocalized('%A %e %B %Y');
            $formattedDate = trim(str::lower($firstDayDate->formatLocalized('%e-%B-%Y')));
            $formattedDateFortitle = trim($fintFormateratDatum);
            $nextDayLink = [
                'title' => sprintf('%1$s ›', $formattedDateFortitle),
                'link' => route("platsDatum", ['plats' => $platsOriginalFromSlug, 'date' => $formattedDate]),
            ];
        }

        // Inkludera inte datum i canonical url om det är idag vi tittar på
        if ($dateOriginalFromArg) {
            // There was a date included
            $canonicalLink = route(
                'platsDatum',
                [
                    'plats' => mb_strtolower($platsOriginalFromSlug),
                    'date' => trim(str::lower($date['date']->formatLocalized('%e-%B-%Y'))),
                ]
            );
        } else {
            $canonicalLink = route(
                'platsSingle',
                [
                    'plats' => mb_strtolower($platsOriginalFromSlug),
                ]
            );
        }

        /*
        $latLngs = [];
        $events->each(function ($elm, $idx) use (& $latLngs) {
            $latLngs[] = [
                $elm->location_lat,
                $elm->location_lng
            ];
        });

        \Debugbar::info('events', $events->toArray());
        \Debugbar::info('$latLngs', $latLngs);
        $latLngsCenter = \App\Helper::getCenterFromDegrees($latLngs);
        \Debugbar::info('$latLngsCenter', $latLngsCenter);
        \Debugbar::info('$latLngsCenter lat,nlng', "{$latLngsCenter[0]},{$latLngsCenter[1]}");
        */

        $place = Place::where('name', $plats)->first();

        // Add breadcrumb.
        $breadcrumbs = new \Creitive\Breadcrumbs\Breadcrumbs;
        $breadcrumbs->setDivider('›');
        $breadcrumbs->addCrumb('Hem', '/');
        $breadcrumbs->addCrumb('Platser', route("platserOverview"));

        if ($place) {
            $breadcrumbs->addCrumb($place->lan, route("lanSingle", ['lan' => $place->lan]));
        }

        #dd($platsOriginalFromSlug);
        $breadcrumbs->addCrumb(
            e($plats), 
            route(
                'platsSingle',
                ['plats' => mb_strtolower($platsOriginalFromSlug)]
            )
        );

        if (!$isToday) {
            $breadcrumbs->addCrumb($fintFormateratDatum);
        }

        // Hämta närmaste polisstation.
        // https://github.com/thephpleague/geotools
        $lanPolicestations = null;
        $relatedLinks = null;

        if ($place) {
            $lanPolicestations = $place->getClosestPolicestations();
        }

        if ($foundMatchingLan) {
            $relatedLinks = \App\Helper::getRelatedLinks($platsWithoutLan, $oneLanName);
        } else {
            $relatedLinks = \App\Helper::getRelatedLinks($plats);
        }

        $data = [
            'plats' => $plats,
            'place' => $place,
            'policeStations' => $lanPolicestations,
            'relatedLinks' => $relatedLinks,
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
        $dateYmd = $date['date']->format('Y-m-d');
        $cacheKey = 'getEventsInPlatsWithLan:' . md5("{$platsWithoutLan}:{$oneLanName}:{$dateYmd}:{$numDaysBack}:{$isToday}");
        $cacheTTL = 1 * 60;

        $events = Cache::Remember(
            $cacheKey,
            $cacheTTL,
            function () use ($platsWithoutLan, $oneLanName, $date, $numDaysBack, $isToday) {
                $events = self::getEventsInPlatsWithLanUncached($platsWithoutLan, $oneLanName, $date, $numDaysBack, $isToday);
                return $events;
            }
        );

        return $events;
    }

    public function getEventsInPlatsWithLanUncached($platsWithoutLan, $oneLanName, $date, $numDaysBack = 7, $isToday = false)
    {
        $dateYmd = $date['date']->format('Y-m-d');
        $dateYmdPlusOneDay = $date['date']->copy()->addDays(1)->format('Y-m-d');
        $dateYmdMinusNumDaysBack = $date['date']->copy()->subDays($numDaysBack)->format('Y-m-d');

        $events = CrimeEvent::orderBy("created_at", "desc")
            ->where(function ($query) use ($date, $dateYmd, $dateYmdPlusOneDay, $dateYmdMinusNumDaysBack, $numDaysBack, $isToday) {
                if ($isToday) {
                    $query->where('created_at', '<', $dateYmdPlusOneDay);
                    $query->where('created_at', '>', $dateYmdMinusNumDaysBack);
                } else {
                    $query->where('created_at', '<', $dateYmdPlusOneDay);
                    $query->where('created_at', '>', $dateYmd);
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

    /**
     * Hämta de mest vanliga brotten för en plats, som inkluderar län i urlen.
     *
     * @param [type] $platsWithoutLan
     * @param [type] $oneLanName
     * @param [type] $dateYMD
     * @return collection Array händelsetyp => antal
     */
    public function getMostCommonCrimeTypesInPlatsWithLan($platsWithoutLan, $oneLanName, $dateYMD)
    {
        $date = new Carbon($dateYMD);
        $dateYmdPlusOneDay = $date->copy()->addDays(1)->format('Y-m-d');
        $cacheKey = "getMostCommonCrimeTypesInPlatsWithLan:$platsWithoutLan:$oneLanName:$dateYMD";
        $cacheTTL = 20 * 60;

        $mostCommonCrimeTypes = Cache::Remember(
            $cacheKey,
            $cacheTTL,
            function () use ($platsWithoutLan, $oneLanName, $dateYMD, $dateYmdPlusOneDay) {
                return self::getMostCommonCrimeTypesInPlatsWithLanUncached($platsWithoutLan, $oneLanName, $dateYMD, $dateYmdPlusOneDay);
            }
        );

        return $mostCommonCrimeTypes;
    }

    public function getMostCommonCrimeTypesInPlatsWithLanUncached($platsWithoutLan, $oneLanName, $dateYMD, $dateYmdPlusOneDay)
    {
        $mostCommonCrimeTypes = DB::table('crime_events')
            ->selectRaw('parsed_title, count(id) as antal')
            ->where('created_at', '>', $dateYMD)
            ->where('created_at', '<', $dateYmdPlusOneDay)
            ->where("administrative_area_level_1", $oneLanName)
            ->where(function ($query) use ($oneLanName, $platsWithoutLan) {
                $query->where("parsed_title_location", $platsWithoutLan);
                $query->orWhereExists(function ($query) use ($platsWithoutLan) {
                    $query->select(\DB::raw(1))
                        ->from('locations')
                        ->whereRaw(
                            'locations.name = ?
                                AND locations.crime_event_id = crime_events.id ',
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
     * Hämta händelser för en plats, utan län. T.ex. "tierp".
     * Exempelurl:
     * https://brottsplatskartan.se/plats/tierp
     *
     * @param string $plats For example "tierp"
     * @param string $dateYMD Date in YMD format
     */
    public function getEventsInPlats($plats, $date, $numDaysBack = 7, $isToday = false)
    {
        $dateYmd = $date['date']->format('Y-m-d');
        $dateYmdPlusOneDay = $date['date']->copy()->addDays(1)->format('Y-m-d');
        $dateYmdMinusNumDaysBack = $date['date']->copy()->subDays($numDaysBack)->format('Y-m-d');

        $cacheKey = "getEventsInPlats:$plats:$dateYmd:$numDaysBack:$isToday";
        $cacheTTL = 1 * 60;

        $events = Cache::Remember(
            $cacheKey,
            $cacheTTL,
            function () use ($dateYmd, $dateYmdPlusOneDay, $dateYmdMinusNumDaysBack, $numDaysBack, $isToday, $plats) {
                return self::getEventsInPlatsUncached($dateYmd, $dateYmdPlusOneDay, $dateYmdMinusNumDaysBack, $numDaysBack, $isToday, $plats);
            }
        );

        return $events;
    }

    public function getEventsInPlatsUncached($dateYmd, $dateYmdPlusOneDay, $dateYmdMinusNumDaysBack, $numDaysBack, $isToday, $plats)
    {
        $events = CrimeEvent::orderBy("created_at", "desc")
            ->where(function ($query) use ($numDaysBack, $isToday, $dateYmd, $dateYmdPlusOneDay, $dateYmdMinusNumDaysBack, $plats) {
                if ($isToday) {
                    $query->where('created_at', '<', $dateYmdPlusOneDay);
                    $query->where('created_at', '>', $dateYmdMinusNumDaysBack);
                } else {
                    $query->where('created_at', '<', $dateYmdPlusOneDay);
                    $query->where('created_at', '>', $dateYmd);
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

    /**
     * Hämta mest vanligt förekommande brottstyperna för en plats utan län.
     *
     * @param [type] $plats
     * @param [type] $dateYMD
     * @return array
     */
    public function getMostCommonCrimeTypesInPlats($plats, $dateYMD)
    {
        $date = Carbon::parse($dateYMD);
        $dateYmdPlusOneDay = $date->copy()->addDays(1)->format('Y-m-d');

        $cacheKey = "getMostCommonCrimeTypesInPlats:$plats:$dateYMD";
        $cacheTTL = 45 * 60;

        $mostCommonCrimeTypes = Cache::Remember(
            $cacheKey,
            $cacheTTL,
            function () use ($plats, $dateYMD, $dateYmdPlusOneDay) {
                return self::getMostCommonCrimeTypesInPlatsUncached($plats, $dateYMD, $dateYmdPlusOneDay);
            }
        );

        return $mostCommonCrimeTypes;
    }

    public function getMostCommonCrimeTypesInPlatsUncached($plats, $dateYMD, $dateYmdPlusOneDay)
    {
        $mostCommonCrimeTypes = CrimeEvent::selectRaw('parsed_title, count(id) as antal')
            ->where('created_at', '<', $dateYmdPlusOneDay)
            ->where('created_at', '>', $dateYMD)
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



    /**
     * Om plats med län, skicka med plats + län-namnet ($platsWithoutLan, $oneLanName)
     * Om inte plats med län: skicka bara plats ($plats)
     *
     * @param [type] $date
     * @param integer $numDays
     * @param [type] $platsWithoutLan
     * @param [type] $oneLanName
     * @return void
     */
    public static function getPlatsPrevDaysNavInfo($date = null, $numDays = 5, $platsWithoutLan = null, $oneLanName = null)
    {
        $dateYmd = $date->format('Y-m-d');

        $cacheKey = "getPlatsPrevDaysNavInfo4:$dateYmd:$numDays:$platsWithoutLan:$oneLanName";
        $cacheTTL = 22 * 60;

        $prevDayEvents = Cache::Remember(
            $cacheKey,
            $cacheTTL,
            function () use ($date, $numDays, $platsWithoutLan, $oneLanName) {
                return self::getPlatsPrevDaysNavInfoUncached($date, $numDays, $platsWithoutLan, $oneLanName);
            }
        );

        return $prevDayEvents;
    }

    public static function getPlatsPrevDaysNavInfoUncached($date = null, $numDays = 5, $platsWithoutLan = null, $oneLanName = null)
    {
        $dateYmd = $date->format('Y-m-d');
        $dateYmdPlusOneDay = $date->copy()->addDays(1)->format('Y-m-d');
        $dateYmdMinusNumDaysBack = $date->copy()->subDays($numDays)->format('Y-m-d');

        // Vi vill ha $numDays dagar tillbaka, men har inget hänt på väldigt långt tid kan
        // det bli många rader som gås igenom, så vi begränsar till typ ett halvt år max.
        $dateYmdMinusManyDaysBack = $date->copy()->subDays(90)->format('Y-m-d');

        if ($platsWithoutLan && $oneLanName) {
            // Både plats och län
            $prevDayEvents = CrimeEvent::
                selectRaw('date(created_at) as dateYMD, count(*) as dateCount, 1 as vvv')
                ->where('created_at', '<', $dateYmd)
                ->where('created_at', '>', $dateYmdMinusManyDaysBack)
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
            // Plats utan län
            #DB::enableQueryLog();

            $prevDayEvents = CrimeEvent::
                selectRaw('date(created_at) as dateYMD, count(*) as dateCount')
                ->where('created_at', '<', $dateYmd)
                ->where('created_at', '>', $dateYmdMinusManyDaysBack)
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

            #dd(DB::getQueryLog());
        }

        return $prevDayEvents;
    }

    public static function getPlatsNextDaysNavInfo($date = null, $numDays = 5, $platsWithoutLan = null, $oneLanName = null)
    {
        $dateYmd = $date->format('Y-m-d');

        $cacheKey = "getPlatsNextDaysNavInfo:$dateYmd:$numDays:$platsWithoutLan:$oneLanName";
        $cacheTTL = 23 * 60;

        $prevDayEvents = Cache::Remember(
            $cacheKey,
            $cacheTTL,
            function () use ($date, $numDays, $platsWithoutLan, $oneLanName) {
                return self::getPlatsNextDaysNavInfoUncached($date, $numDays, $platsWithoutLan, $oneLanName);
            }
        );

        return $prevDayEvents;
    }

    public static function getPlatsNextDaysNavInfoUncached($date = null, $numDays = 5, $platsWithoutLan = null, $oneLanName = null)
    {
        $dateYmd = $date->format('Y-m-d');
        $dateYmdPlusOneDay = $date->copy()->addDays(1)->format('Y-m-d');
        $dateYmdMinusNumDaysBack = $date->copy()->subDays($numDays)->format('Y-m-d');
        $dateYmdPlusManyDaysForward = $date->copy()->addDays(90)->format('Y-m-d');

        if ($platsWithoutLan && $oneLanName) {
            $prevDayEvents = CrimeEvent::
                selectRaw('date(created_at) as dateYMD, count(*) as dateCount')
                ->where('created_at', '>', $dateYmdPlusOneDay)
                ->where('created_at', '<', $dateYmdPlusManyDaysForward)
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
                ->orderBy("created_at", "asc")
                ->limit($numDays)
                ->get();
        } else {
            $prevDayEvents = CrimeEvent::
                selectRaw('date(created_at) as dateYMD, count(*) as dateCount')
                ->where('created_at', '>', $dateYmdPlusOneDay)
                ->where('created_at', '<', $dateYmdPlusManyDaysForward)
                ->where(function ($query) use ($platsWithoutLan) {
                    $query->where("parsed_title_location", $platsWithoutLan);
                    $query->orWhere("administrative_area_level_2", $platsWithoutLan);
                    $query->orWhereHas('locations', function ($query) use ($platsWithoutLan) {
                        $query->where('name', '=', $platsWithoutLan);
                    });
                })
                ->groupBy(\DB::raw('dateYMD'))
                ->orderBy("created_at", "asc")
                ->limit($numDays)
                ->get();
        }

        return $prevDayEvents;
    }

}
