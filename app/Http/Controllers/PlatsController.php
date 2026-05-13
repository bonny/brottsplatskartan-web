<?php

namespace App\Http\Controllers;

use App\BraStatistik;
use App\MCFStatistik;
use App\CrimeEvent;
use App\Place;
use App\Tier1;
use Carbon\Carbon;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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

        $breadcrumbs = new \Creitive\Breadcrumbs\Breadcrumbs;
        $breadcrumbs->setDivider('›');
        $breadcrumbs->addCrumb('Hem', '/');
        $breadcrumbs->addCrumb('Platser', route("platserOverview"));

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

        // Konsolidera SEO-värde: 301:a versaler → gemener.
        // Före: /plats/Malmö och /plats/malmö rankades separat i Google.
        $platsLowercase = mb_strtolower($plats);
        if ($plats !== $platsLowercase) {
            $params = ['plats' => $platsLowercase];
            if ($date !== null) {
                $params['date'] = $date;
                return redirect()->route('platsDatum', $params, 301);
            }
            return redirect()->route('platsSingle', $params, 301);
        }

        $date = \App\Helper::getdateFromDateSlug($date);

        if (!$date) {
            abort(500, 'Knas med datum hörru');
        }

        // todo #25: 301-redirect från dagsvy → månadsvy med dag-anchor.
        // Aktiveras per plats via MONTHLY_VIEWS_PILOT-flaggan. Idag-vyer
        // 301:as inte (de är likvärdiga med plats-startsidan).
        // todo #33: Tier 1-städer 301:as till /{city}/handelser/{year}/{month}.
        if (
            $dateOriginalFromArg
            && !$date['date']->isToday()
            && \App\Helper::isInMonthlyViewsPilot($platsOriginalFromSlug)
        ) {
            $isTier1 = in_array(
                mb_strtolower($platsOriginalFromSlug),
                Tier1::slugs(),
                true
            );
            $monthUrl = route(
                $isTier1 ? 'cityMonth' : 'platsMonth',
                [
                    $isTier1 ? 'city' : 'plats' => $platsOriginalFromSlug,
                    'year' => $date['date']->format('Y'),
                    'month' => $date['date']->format('m'),
                ]
            ) . '#' . $date['date']->format('Y-m-d');
            return redirect($monthUrl, 301);
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
        $allLansNames = \App\Helper::getAllLan();
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
            return redirect()->route('platsSingle', ['plats' => $plats], 301);
        }

        if ($foundMatchingLan) {
            // Hämta events där vi vet både plats och län
            // t.ex. "Stockholm" i "Stockholms län"
            $events = $this->getEventsInPlatsWithLan($platsWithoutLan, $matchingLanName, $date, 7, $isToday);

            // Hämta mest vanligt förekommande händelsetyperna
            $mostCommonCrimeTypes = $this->getMostCommonCrimeTypesInPlatsWithLan($platsWithoutLan, $matchingLanName, $dateYMD);

            // Skapa fint namn av platsen och länet, blir t.ex. "Orminge i Stockholms Län"
            $plats = sprintf(
                '%1$s i %2$s',
                title_case($platsWithoutLan),
                title_case($matchingLanName)
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
            // $mostCommonCrimeTypes = $this->getMostCommonCrimeTypesInPlats($plats, $dateYMD);
            $mostCommonCrimeTypes = collect();

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

        $metaDescription = "Senaste händelserna som skett i och omkring $plats.";

        if ($plats === 'Stockholm') {
            $metaDescription = 'Vad har hänt i Stockholm idag? Se Polisens händelser med kartor som visar var varje händelse skett.';
        }

        $linkRelPrev = null;
        $linkRelNext = null;

        // Hämta statistik för platsen
        $introtext_key = "introtext-plats-$plats";
        $introtext = null;

        // Start daynav
        if ($foundMatchingLan) {
            $prevDaysNavInfo = $this->getPlatsPrevDaysNavInfo($date['date'], 5, $platsWithoutLan, $matchingLanName);
            $nextDaysNavInfo = $this->getPlatsNextDaysNavInfo($date['date'], 5, $platsWithoutLan, $matchingLanName);
        } else {
            $prevDaysNavInfo = $this->getPlatsPrevDaysNavInfo($date['date'], 5, $plats);
            $nextDaysNavInfo = $this->getPlatsNextDaysNavInfo($date['date'], 5, $plats);
        }

        $prevDayLink = null;
        if ($prevDaysNavInfo->count()) {
            $firstDay = $prevDaysNavInfo->first();
            $firstDayDate = Carbon::parse($firstDay['dateYMD']);
            $fintFormateratDatum = $firstDayDate->isoFormat('dddd D MMMM YYYY');
            $formattedDate = trim(str::lower($firstDayDate->isoFormat('D-MMMM-YYYY')));
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
            $fintFormateratDatum = $firstDayDate->isoFormat('dddd D MMMM YYYY');
            $formattedDate = trim(str::lower($firstDayDate->isoFormat('D-MMMM-YYYY')));
            $formattedDateFortitle = trim($fintFormateratDatum);
            $nextDayLink = [
                'title' => sprintf('%1$s ›', $formattedDateFortitle),
                'link' => route("platsDatum", ['plats' => $platsOriginalFromSlug, 'date' => $formattedDate]),
            ];
        }

        // Inkludera inte datum i canonical url om det är idag vi tittar på.
        if ($dateOriginalFromArg) {
            // There was a date included
            $canonicalLink = route(
                'platsDatum',
                [
                    'plats' => mb_strtolower($platsOriginalFromSlug),
                    'date' => trim(str::lower($date['date']->isoFormat('D-MMMM-YYYY'))),
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

        $place = Place::where('name', $plats)->first();

        // Add breadcrumb.
        $breadcrumbs = new \Creitive\Breadcrumbs\Breadcrumbs;
        $breadcrumbs->setDivider('›');
        $breadcrumbs->addCrumb('Hem', '/');
        $breadcrumbs->addCrumb('Platser', route("platserOverview"));

        if ($place) {
            $breadcrumbs->addCrumb($place->lan, route("lanSingle", ['lan' => \App\Helper::lanSlug($place->lan)]));
        }

        $breadcrumbs->addCrumb(
            e($plats), 
            route(
                'platsSingle',
                ['plats' => mb_strtolower($platsOriginalFromSlug)]
            )
        );

        if (!$isToday && !empty($fintFormateratDatum)) {
            $breadcrumbs->addCrumb($fintFormateratDatum);
        }

        // Hämta närmaste polisstation.
        // https://github.com/thephpleague/geotools
        $lanPolicestations = null;
        $relatedLinks = null;

        if ($place) {
            // Detta fungerar ej på PHP 8.0 pga får varning typ
            // "deg2rad(): Argument #1 ($num) must be of type float, string given php 8.0".
            // Aktivera igen när https://github.com/thephpleague/geotools uppdateras
            $lanPolicestations = $place->getClosestPolicestations();
        }

        if ($foundMatchingLan) {
            $relatedLinks = \App\Helper::getRelatedLinks($platsWithoutLan, $matchingLanName);
        } else {
            $relatedLinks = \App\Helper::getRelatedLinks($plats);
        }

        // BRÅ:s officiella anmälda brott (todo #38). Renderar bara om platsen
        // är mappad till en kommun via PlacePopulation (#37). Län-mappade och
        // omappade platser får ingen sektion. Visa bara på huvudsidan
        // (idag-vy) — datumvyer skulle förvirra med årlig statistik bredvid
        // dagsdata.
        $bra = null;
        $braLanGrannar = null;
        $braRikssnitt = null;
        $mcf = null;
        if ($isToday) {
            $bra = BraStatistik::forBpkPlaceName($plats);
            if ($bra) {
                $braLanGrannar = BraStatistik::lanGrannar($bra->kommun_kod, $bra->ar);
                $braRikssnitt = BraStatistik::rikssnitt($bra->ar);
            }
            $mcf = MCFStatistik::forBpkPlaceName($plats);
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
            'dateForTitle' => $date['date']->isoFormat('D MMMM YYYY'),
            'mapDistance' => 'near',
            'robotsNoindex' => \App\Helper::shouldNoindexForDateRoute($dateOriginalFromArg, $date['date']),
            'bra' => $bra,
            'braLanGrannar' => $braLanGrannar,
            'braRikssnitt' => $braRikssnitt,
            'mcf' => $mcf,
        ];

        return view('single-plats', $data);
    }

    /**
     * Månadsvy för plats — todo #25.
     *
     * Ersätter dagsvyer på plats-nivå med en aggregerad månadsvy som
     * innehåller statistik, dag-sektioner med anchors, och
     * Dataset/FAQPage-schema. Tomma månader 301:as till plats-startsidan.
     *
     * URL: /plats/{plats}/handelser/{year}/{month}
     */
    public function month(Request $request, $plats, $year, $month)
    {
        $platsOriginalFromSlug = $plats;

        // 301:a versaler → gemener (samma som day()).
        $platsLowercase = mb_strtolower($plats);
        if ($plats !== $platsLowercase) {
            return redirect()->route('platsMonth', [
                'plats' => $platsLowercase,
                'year' => $year,
                'month' => $month,
            ], 301);
        }

        $monthRange = \App\Helper::getMonthRangeFromYearMonth($year, $month);
        if (!$monthRange) {
            abort(404);
        }

        // Trimma trailing-dash på plats (samma som day()).
        if (ends_with($plats, '-')) {
            $plats = trim($plats, '-');
            return redirect()->route('platsSingle', ['plats' => $plats], 301);
        }

        // Plats med matchande län-suffix.
        $allLansNames = \App\Helper::getAllLan();
        $foundMatchingLan = false;
        $matchingLanName = null;
        $platsWithoutLan = null;
        $platsSluggified = \App\Helper::toAscii($plats);

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
            $events = $this->getEventsInPlatsWithLanForMonth(
                $platsWithoutLan,
                $matchingLanName,
                $monthRange['start'],
                $monthRange['end']
            );
            $platsDisplay = sprintf(
                '%1$s i %2$s',
                title_case($platsWithoutLan),
                title_case($matchingLanName)
            );
        } else {
            $events = $this->getEventsInPlatsForMonth(
                $plats,
                $monthRange['start'],
                $monthRange['end']
            );

            // Om inga events i månaden — kolla om platsen finns alls.
            if (!$events->count()) {
                $eventsExists = CrimeEvent::where(function ($query) use ($plats) {
                    $query->where("parsed_title_location", $plats);
                    $query->orWhere("administrative_area_level_2", $plats);
                    $query->orWhereHas('locations', function ($query) use ($plats) {
                        $query->where('name', '=', $plats);
                    });
                })->exists();

                if (!$eventsExists) {
                    abort(404);
                }
            }

            $platsDisplay = title_case($plats);
        }

        // Tomma månader: 301 till plats-startsidan (#25-policy).
        if ($events->isEmpty()) {
            return redirect()->route('platsSingle', [
                'plats' => $platsOriginalFromSlug,
            ], 301);
        }

        // Magra månader (1–2 events): noindex,follow + ingen AdSense.
        $totalEvents = $events->count();
        $robotsNoindex = $totalEvents < 3;

        $eventsByDay = $events->groupBy(function ($item) {
            return date('Y-m-d', strtotime($item->created_at));
        })->sortKeys();

        // Statistik för "Snabba fakta" + Dataset/FAQPage-schema.
        $crimeTypeCounts = $events->groupBy('parsed_title')
            ->map->count()
            ->sortDesc();
        $mostCommonCrimeType = $crimeTypeCounts->keys()->first();
        $mostCommonCrimeTypeCount = $crimeTypeCounts->first();
        $crimeTypeDistinctCount = $crimeTypeCounts->count();

        // Trend mot föregående månad (samma plats-filter, föregående range).
        $prevMonthStart = (clone $monthRange['start'])->subMonth();
        $prevMonthEnd = (clone $prevMonthStart)->endOfMonth();
        $prevMonthEvents = $foundMatchingLan
            ? $this->getEventsInPlatsWithLanForMonth($platsWithoutLan, $matchingLanName, $prevMonthStart, $prevMonthEnd)
            : $this->getEventsInPlatsForMonth($plats, $prevMonthStart, $prevMonthEnd);
        $prevMonthCount = $prevMonthEvents->count();
        $trendVsPrev = null;
        if ($prevMonthCount > 0) {
            $trendVsPrev = (int) round((($totalEvents - $prevMonthCount) / $prevMonthCount) * 100);
        }

        // todo #33: Tier 1-städer rankar via /{city}/handelser/-namespace
        // (CityController::month). prev/next/canonical pekar på cityMonth
        // istället för platsMonth så URL-equity konsolideras.
        $isTier1 = in_array(
            mb_strtolower($platsOriginalFromSlug),
            Tier1::slugs(),
            true
        );
        $monthRouteName = $isTier1 ? 'cityMonth' : 'platsMonth';
        $monthRouteParam = $isTier1 ? 'city' : 'plats';

        // Prev/next månad-länkar.
        $prevMonth = (clone $monthRange['start'])->subMonth();
        $nextMonth = (clone $monthRange['start'])->addMonth();
        $prevMonthLink = [
            'title' => sprintf('‹ %s', title_case($prevMonth->isoFormat('MMMM YYYY'))),
            'link' => route($monthRouteName, [
                $monthRouteParam => $platsOriginalFromSlug,
                'year' => $prevMonth->format('Y'),
                'month' => $prevMonth->format('m'),
            ]),
        ];
        $nextMonthLink = $nextMonth->isFuture() ? null : [
            'title' => sprintf('%s ›', title_case($nextMonth->isoFormat('MMMM YYYY'))),
            'link' => route($monthRouteName, [
                $monthRouteParam => $platsOriginalFromSlug,
                'year' => $nextMonth->format('Y'),
                'month' => $nextMonth->format('m'),
            ]),
        ];

        $monthYearTitle = title_case($monthRange['start']->isoFormat('MMMM YYYY'));

        $canonicalLink = route($monthRouteName, [
            $monthRouteParam => mb_strtolower($platsOriginalFromSlug),
            'year' => $monthRange['start']->format('Y'),
            'month' => $monthRange['start']->format('m'),
        ]);

        $place = Place::where('name', $platsDisplay)->first();

        $breadcrumbs = new \Creitive\Breadcrumbs\Breadcrumbs;
        $breadcrumbs->setDivider('›');
        $breadcrumbs->addCrumb('Hem', '/');
        $breadcrumbs->addCrumb('Platser', route('platserOverview'));
        if ($place) {
            $breadcrumbs->addCrumb($place->lan, route('lanSingle', ['lan' => \App\Helper::lanSlug($place->lan)]));
        }
        $breadcrumbs->addCrumb(
            e($platsDisplay),
            route('platsSingle', ['plats' => mb_strtolower($platsOriginalFromSlug)])
        );
        $breadcrumbs->addCrumb($monthYearTitle);

        $metaDescription = sprintf(
            'Polishändelser i %s under %s. %d händelser registrerade%s.',
            $platsDisplay,
            $monthYearTitle,
            $totalEvents,
            $mostCommonCrimeType
                ? sprintf(', vanligast: %s', mb_strtolower($mostCommonCrimeType))
                : ''
        );

        $pageTitle = sprintf('Polishändelser i %s, %s', $platsDisplay, $monthYearTitle);

        // MCF släpper datan i mars året efter, så aktuella månader returnerar null.
        $mcfManad = null;
        $kommunKodForPlats = BraStatistik::kommunKodForBpkPlaceName($platsOriginalFromSlug);
        if ($kommunKodForPlats) {
            $mcfManad = MCFStatistik::forKommunManad(
                $kommunKodForPlats,
                (int) $monthRange['start']->format('Y'),
                (int) $monthRange['start']->format('m'),
            );
        }

        // AI-månadssammanfattning för Tier 1-städer (todo #27 Lager 3).
        // Lookup på en redan-genererad rad — ingen on-the-fly AI-generering
        // (det är schedulerns jobb 1:a varje månad). Tomt visas inget.
        $monthlySummary = null;
        if ($isTier1) {
            $monthlySummary = \App\Models\MonthlySummary::where('area', $platsOriginalFromSlug)
                ->where('year', (int) $monthRange['start']->format('Y'))
                ->where('month', (int) $monthRange['start']->format('m'))
                ->first();
        }

        $data = [
            'plats' => $platsDisplay,
            'platsSlug' => $platsOriginalFromSlug,
            'place' => $place,
            'events' => $events,
            'eventsByDay' => $eventsByDay,
            'monthRange' => $monthRange,
            'monthYearTitle' => $monthYearTitle,
            'totalEvents' => $totalEvents,
            'mostCommonCrimeType' => $mostCommonCrimeType,
            'mostCommonCrimeTypeCount' => $mostCommonCrimeTypeCount,
            'crimeTypeDistinctCount' => $crimeTypeDistinctCount,
            'crimeTypeCounts' => $crimeTypeCounts,
            'prevMonthCount' => $prevMonthCount,
            'trendVsPrev' => $trendVsPrev,
            'prevMonthLink' => $prevMonthLink,
            'nextMonthLink' => $nextMonthLink,
            'breadcrumbs' => $breadcrumbs,
            'metaDescription' => $metaDescription,
            'pageTitle' => $pageTitle,
            'canonicalLink' => $canonicalLink,
            'mapDistance' => 'near',
            'robotsNoindex' => $robotsNoindex,
            'showAdSense' => $totalEvents >= 3,
            'monthlySummary' => $monthlySummary,
            'mcfManad' => $mcfManad,
        ];

        return view('single-plats-month', $data);
    }

    protected function dieAfterTryCount() {
        static $tryCount = 0;

        $count = request('dieCount');

        if ($count === null) {
            return;
        }

        if ((int) $count === $tryCount) {
            dd('Died.', debug_backtrace());
        }

        $tryCount++;
    }

    /**
     * Enskild plats/ort, med debuginfo.
     * Exempel på URL:
     * https://brottsplatskartan.localhost/plats/stockholm
     */
    public function dayDebug(Request $request, $plats, $date = null)
    {
        $dateOriginalFromArg = $date;
        $platsOriginalFromSlug = $plats;

        $this->dieAfterTryCount();

        $date = \App\Helper::getdateFromDateSlug($date);
        $this->dieAfterTryCount();
        if (!$date) {
            $this->dieAfterTryCount();
            abort(500, 'Knas med datum hörru');
        }

        // Om page finns så är det en gammal URL,
        // skriv om till ny (eller hänvisa canonical iaf och använd dagens datum)
        $page = (int) $request->input("page", 0);
        if ($page) {
            $this->dieAfterTryCount();
            $page = 0;
            $date = \App\Helper::getdateFromDateSlug(null);
        }
        $this->dieAfterTryCount();
        $dateYMD = $date['date']->format('Y-m-d');
        $isToday = $date['date']->isToday();
        $isYesterday = $date['date']->isYesterday();
        $isCurrentYear = $date['date']->isCurrentYear();
        $this->dieAfterTryCount();
        // Om $plats slutar med namnet på ett län, t.ex. "örebro län", "gävleborgs län" osv
        // så ska platser i det länet med platsen $plats minus länets namn visas
        $allLansNames = \App\Helper::getAllLan();
        $foundMatchingLan = false;
        $matchingLanName = null;
        $platsWithoutLan = null;
        $platsSluggified = \App\Helper::toAscii($plats);
        $this->dieAfterTryCount();
        // Kolla om platsen $plats även inkluderar ett län
        // T.ex. om URL är # så ska vi hitta "stockholms län"
        foreach ($allLansNames as $oneLanName) {
            $lanSlug = \App\Helper::toAscii($oneLanName);
            $this->dieAfterTryCount();
            if (ends_with($platsSluggified, "-" . $lanSlug)) {
                $foundMatchingLan = true;
                $matchingLanName = $oneLanName;
                $this->dieAfterTryCount();
                $lanStrLen = mb_strlen($oneLanName);
                $platsStrLen = mb_strlen($plats);
                $platsWithoutLan = mb_substr($plats, 0, $platsStrLen - $lanStrLen);
                $platsWithoutLan = str_replace("-", " ", $platsWithoutLan);
                $platsWithoutLan = trim($platsWithoutLan);
                break;
            }
        }
        $this->dieAfterTryCount();
        // Om en plats är i "sverige" snarare än ett specifikt län så blir plats-url fel:
        // https://brottsplatskartan.localhost/plats/basvägen-
        // Ta bort '-' och redirecta till platsen.
        if (ends_with($plats, '-')) {
            $plats = trim($plats, '-');
            return redirect()->route('platsSingle', ['plats' => $plats], 301);
        }
        $this->dieAfterTryCount();
        if ($foundMatchingLan) {
            // Hämta events där vi vet både plats och län
            // t.ex. "Stockholm" i "Stockholms län"
            $events = $this->getEventsInPlatsWithLan($platsWithoutLan, $matchingLanName, $date, 7, $isToday);
            $this->dieAfterTryCount();
            // Hämta mest vanligt förekommande händelsetyperna
            $mostCommonCrimeTypes = $this->getMostCommonCrimeTypesInPlatsWithLan($platsWithoutLan, $matchingLanName, $dateYMD);
            $this->dieAfterTryCount();
            // Skapa fint namn av platsen och länet, blir t.ex. "Orminge i Stockholms Län"
            $plats = sprintf(
                '%1$s i %2$s',
                title_case($platsWithoutLan),
                title_case($matchingLanName)
            );
        } else {
            // Hämta events där plats är från huvudtabellen
            // Används när $plats är bara en plats, typ "insjön",
            // "östersunds centrum", "östra karup", "kungsgatan" osv.
            // Exempel på url:
            // https://brottsplatskartan.localhost/plats/bananskal
            $this->dieAfterTryCount();
            $events = $this->getEventsInPlats($plats, $date, 14, $isToday);
            $this->dieAfterTryCount();
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
                $this->dieAfterTryCount();
                if (!$eventsExists) {
                    abort(404);
                }
            }
            $this->dieAfterTryCount();
            // Gör så att plats blir "Västra Hejsan Hoppsan" och inte "västra hejsan hoppsan".
            $plats = title_case($plats);
            $this->dieAfterTryCount();
            // Hämta mest vanligt förekommande händelsetyperna
            // $mostCommonCrimeTypes = $this->getMostCommonCrimeTypesInPlats($plats, $dateYMD);
            $mostCommonCrimeTypes = collect();

            // Debugbar::info('Hämta events där vi bara vet platsnamn');
            // Indexera inte denna sida om det är en gata, men indexera om det är en ort osv.
            // Får avvakta med denna pga vet inte exakt vad en plats är för en..eh..plats.
            // $data['robotsNoindex'] = true;
        }
        $this->dieAfterTryCount();
        // Group events by day
        $eventsByDay = $events->groupBy(function ($item, $key) {
            return date('Y-m-d', strtotime($item->created_at));
        });
        $this->dieAfterTryCount();
        $mostCommonCrimeTypesMetaDescString = '';
        foreach ($mostCommonCrimeTypes as $oneCrimeType) {
            $mostCommonCrimeTypesMetaDescString .= $oneCrimeType->parsed_title . ', ';
        }
        $mostCommonCrimeTypesMetaDescString = trim($mostCommonCrimeTypesMetaDescString, ', ');

        $metaDescription = "Senaste händelserna som skett i och omkring $plats.";

        if ($plats === 'Stockholm') {
            $metaDescription = 'Vad har hänt i Stockholm idag? Se Polisens händelser med kartor som visar var varje händelse skett.';
        }

        $linkRelPrev = null;
        $linkRelNext = null;

        // Hämta statistik för platsen
        $introtext_key = "introtext-plats-$plats";
        $introtext = null;
        $this->dieAfterTryCount();
        // Start daynav
        if ($foundMatchingLan) {
            $prevDaysNavInfo = $this->getPlatsPrevDaysNavInfo($date['date'], 5, $platsWithoutLan, $matchingLanName);
            $nextDaysNavInfo = $this->getPlatsNextDaysNavInfo($date['date'], 5, $platsWithoutLan, $matchingLanName);
        } else {
            $prevDaysNavInfo = $this->getPlatsPrevDaysNavInfo($date['date'], 5, $plats);
            $nextDaysNavInfo = $this->getPlatsNextDaysNavInfo($date['date'], 5, $plats);
        }
        $this->dieAfterTryCount();
        $prevDayLink = null;
        if ($prevDaysNavInfo->count()) {
            $firstDay = $prevDaysNavInfo->first();
            $firstDayDate = Carbon::parse($firstDay['dateYMD']);
            $fintFormateratDatum = $firstDayDate->isoFormat('dddd D MMMM YYYY');
            $formattedDate = trim(str::lower($firstDayDate->isoFormat('D-MMMM-YYYY')));
            $formattedDateFortitle = trim($fintFormateratDatum);
            $prevDayLink = [
                'title' => sprintf('‹ %1$s', $formattedDateFortitle),
                'link' => route("platsDatum", ['plats' => $platsOriginalFromSlug, 'date' => $formattedDate]),
            ];
        }
        $this->dieAfterTryCount();
        $nextDayLink = null;
        if ($nextDaysNavInfo->count()) {
            $firstDay = $nextDaysNavInfo->first();
            $firstDayDate = Carbon::parse($firstDay['dateYMD']);
            $fintFormateratDatum = $firstDayDate->isoFormat('dddd D MMMM YYYY');
            $formattedDate = trim(str::lower($firstDayDate->isoFormat('D-MMMM-YYYY')));
            $formattedDateFortitle = trim($fintFormateratDatum);
            $nextDayLink = [
                'title' => sprintf('%1$s ›', $formattedDateFortitle),
                'link' => route("platsDatum", ['plats' => $platsOriginalFromSlug, 'date' => $formattedDate]),
            ];
        }
        $this->dieAfterTryCount();
        // Inkludera inte datum i canonical url om det är idag vi tittar på.
        if ($dateOriginalFromArg) {
            // There was a date included
            $canonicalLink = route(
                'platsDatum',
                [
                    'plats' => mb_strtolower($platsOriginalFromSlug),
                    'date' => trim(str::lower($date['date']->isoFormat('D-MMMM-YYYY'))),
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

        $place = Place::where('name', $plats)->first();
        $this->dieAfterTryCount();
        // Add breadcrumb.
        $breadcrumbs = new \Creitive\Breadcrumbs\Breadcrumbs;
        $breadcrumbs->setDivider('›');
        $breadcrumbs->addCrumb('Hem', '/');
        $breadcrumbs->addCrumb('Platser', route("platserOverview"));

        if ($place) {
            $breadcrumbs->addCrumb($place->lan, route("lanSingle", ['lan' => \App\Helper::lanSlug($place->lan)]));
        }
        $this->dieAfterTryCount();
        $breadcrumbs->addCrumb(
            e($plats), 
            route(
                'platsSingle',
                ['plats' => mb_strtolower($platsOriginalFromSlug)]
            )
        );

        if (!$isToday && !empty($fintFormateratDatum)) {
            $breadcrumbs->addCrumb($fintFormateratDatum);
        }
        $this->dieAfterTryCount();
        // Hämta närmaste polisstation.
        // https://github.com/thephpleague/geotools
        $lanPolicestations = null;
        $relatedLinks = null;

        if ($place) {
            // Detta fungerar ej på PHP 8.0 pga får varning typ
            // "deg2rad(): Argument #1 ($num) must be of type float, string given php 8.0".
            // Aktivera igen när https://github.com/thephpleague/geotools uppdateras
            $lanPolicestations = $place->getClosestPolicestations();
        }
        $this->dieAfterTryCount();
        if ($foundMatchingLan) {
            $relatedLinks = \App\Helper::getRelatedLinks($platsWithoutLan, $matchingLanName);
        } else {
            $relatedLinks = \App\Helper::getRelatedLinks($plats);
        }
        $this->dieAfterTryCount();
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
            'dateForTitle' => $date['date']->isoFormat('D MMMM YYYY'),
            'mapDistance' => 'near',
        ];

        return view('single-plats', $data);
    }

    /**
     * Hämta händelser för en plats som inkluderar län.
     * URL är t.ex.
     * https://brottsplatskartan.localhost/plats/fru%C3%A4ngen-stockholms-l%C3%A4n
     *
     * @param string $platsWithoutLan
     * @param string $oneLanName
     * @param array<string, Carbon> $date
     * @param integer $numDaysBack
     * @param boolean $isToday
     * @return Collection
     */
    public function getEventsInPlatsWithLan($platsWithoutLan, $oneLanName, $date, $numDaysBack = 7, $isToday = false)
    {
        $dateYmd = $date['date']->format('Y-m-d');
        $cacheKey = 'getEventsInPlatsWithLan:' . md5("{$platsWithoutLan}:{$oneLanName}:{$dateYmd}:{$numDaysBack}:{$isToday}");
        $cacheTTL = 1 * 60;

        $events = Cache::remember(
            $cacheKey,
            $cacheTTL,
            function () use ($platsWithoutLan, $oneLanName, $date, $numDaysBack, $isToday) {
                $events = self::getEventsInPlatsWithLanUncached($platsWithoutLan, $oneLanName, $date, $numDaysBack, $isToday);
                return $events;
            }
        );

        return $events;
    }


    /**
     * @param mixed $platsWithoutLan 
     * @param mixed $oneLanName 
     * @param array<string, Carbon> $date
     * @param int $numDaysBack 
     * @param bool $isToday 
     *
     * @return Collection
     */
    public function getEventsInPlatsWithLanUncached($platsWithoutLan, $oneLanName, $date, $numDaysBack = 7, $isToday = false)
    {
        $dateYmd = $date['date']->format('Y-m-d');
        $dateYmdPlusOneDay = $date['date']->copy()->addDays(1)->format('Y-m-d');
        $dateYmdMinusNumDaysBack = $date['date']->copy()->subDays($numDaysBack)->format('Y-m-d');

        $events = CrimeEvent::orderBy("created_at", "desc")
            ->where(function ($query) use ($dateYmd, $dateYmdPlusOneDay, $dateYmdMinusNumDaysBack, $isToday) {
                if ($isToday) {
                    $query->where('created_at', '<', $dateYmdPlusOneDay);
                    $query->where('created_at', '>', $dateYmdMinusNumDaysBack);
                } else {
                    $query->where('created_at', '<', $dateYmdPlusOneDay);
                    $query->where('created_at', '>', $dateYmd);
                }
            })
            ->where("administrative_area_level_1", $oneLanName)
            ->where(function ($query) use ($platsWithoutLan) {
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
     * @param string $platsWithoutLan
     * @param string $oneLanName
     * @param string $dateYMD
     * @return collection Array händelsetyp => antal
     */
    public function getMostCommonCrimeTypesInPlatsWithLan($platsWithoutLan, $oneLanName, $dateYMD)
    {
        $date = new Carbon($dateYMD);
        $dateYmdPlusOneDay = $date->copy()->addDays(1)->format('Y-m-d');
        $cacheKey = "getMostCommonCrimeTypesInPlatsWithLan:$platsWithoutLan:$oneLanName:$dateYMD";
        $cacheTTL = 20 * 60;

        $mostCommonCrimeTypes = Cache::remember(
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
            ->where(function ($query) use ($platsWithoutLan) {
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
     * @param array<string, Carbon> $date
     * @param int $numDaysBack
     * @param bool $isToday
     */
    public function getEventsInPlats($plats, $date, $numDaysBack = 7, $isToday = false)
    {
        $dateYmd = $date['date']->format('Y-m-d');
        $dateYmdPlusOneDay = $date['date']->copy()->addDays(1)->format('Y-m-d');
        $dateYmdMinusNumDaysBack = $date['date']->copy()->subDays($numDaysBack)->format('Y-m-d');

        $cacheKey = "getEventsInPlats:$plats:$dateYmd:$numDaysBack:$isToday";
        $cacheTTL = 1 * 60;

        $events = Cache::remember(
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
            ->where(function ($query) use ($isToday, $dateYmd, $dateYmdPlusOneDay, $dateYmdMinusNumDaysBack) {
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
     * Hämta events för plats över ett månad-range (todo #25).
     *
     * Range-query mot composite-index `(plats, parsed_date)` — undvik
     * MONTH()/YEAR()-funktioner som triggar full scan.
     */
    public function getEventsInPlatsForMonth($plats, Carbon $start, Carbon $end)
    {
        $cacheKey = sprintf('getEventsInPlatsForMonth:%s:%s', $plats, $start->format('Y-m'));
        $cacheTTL = 30 * 60;

        // Tier 1-städer kommer in som ASCII-slug (malmo/goteborg) men
        // DB-fälten lagrar display-form (Malmö/Göteborg). Översätt så
        // queryn faktiskt träffar något.
        $platsForDb = Tier1::displayName($plats);

        return Cache::remember($cacheKey, $cacheTTL, function () use ($plats, $platsForDb, $start, $end) {
            return CrimeEvent::orderBy('created_at', 'desc')
                ->whereBetween('created_at', [$start, $end])
                ->where(function ($query) use ($plats, $platsForDb) {
                    $query->where('parsed_title_location', $platsForDb);
                    $query->orWhere('administrative_area_level_2', $platsForDb);
                    if ($plats !== $platsForDb) {
                        $query->orWhere('parsed_title_location', $plats);
                        $query->orWhere('administrative_area_level_2', $plats);
                    }
                    $query->orWhereHas('locations', function ($query) use ($platsForDb) {
                        $query->where('name', '=', $platsForDb);
                    });
                })
                ->with('locations')
                ->get();
        });
    }

    /**
     * Hämta events för plats inom ett specifikt län över ett månad-range
     * (todo #25). Range-query, samma cache-strategi som månads-platsen.
     */
    public function getEventsInPlatsWithLanForMonth($platsWithoutLan, $oneLanName, Carbon $start, Carbon $end)
    {
        $cacheKey = sprintf(
            'getEventsInPlatsWithLanForMonth:%s:%s:%s',
            $platsWithoutLan,
            $oneLanName,
            $start->format('Y-m')
        );
        $cacheTTL = 30 * 60;

        return Cache::remember($cacheKey, $cacheTTL, function () use ($platsWithoutLan, $oneLanName, $start, $end) {
            return CrimeEvent::orderBy('created_at', 'desc')
                ->whereBetween('created_at', [$start, $end])
                ->where('administrative_area_level_1', $oneLanName)
                ->where(function ($query) use ($platsWithoutLan) {
                    $query->where('parsed_title_location', $platsWithoutLan);
                    $query->orWhere('administrative_area_level_2', $platsWithoutLan);
                    $query->orWhereHas('locations', function ($query) use ($platsWithoutLan) {
                        $query->where('name', '=', $platsWithoutLan);
                    });
                })
                ->with('locations')
                ->get();
        });
    }

    /**
     * Hämta mest vanligt förekommande brottstyperna för en plats utan län.
     *
     * @param string $plats
     * @param string $dateYMD
     * @return Collection
     */
    public function getMostCommonCrimeTypesInPlats($plats, $dateYMD)
    {
        $date = Carbon::parse($dateYMD);
        $dateYmdPlusOneDay = $date->copy()->addDays(1)->format('Y-m-d');

        $cacheKey = "getMostCommonCrimeTypesInPlats:$plats:$dateYMD";
        $cacheTTL = 45 * 60;

        $mostCommonCrimeTypes = Cache::remember(
            $cacheKey,
            $cacheTTL,
            function () use ($plats, $dateYMD, $dateYmdPlusOneDay) {
                return self::getMostCommonCrimeTypesInPlatsUncached($plats, $dateYMD, $dateYmdPlusOneDay);
            }
        );

        return $mostCommonCrimeTypes;
    }


    /**
     * Hämta mest vanligt förekommande brottstyperna för en plats utan län (ocached version).
     * 
     * @param string $plats 
     * @param string $dateYMD 
     * @param string $dateYmdPlusOneDay 
     * @return Collection 
     */
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
     * @param Carbon $date
     * @param integer $numDays
     * @param string $platsWithoutLan
     * @param string $oneLanName
     * @return Collection 
     */
    public static function getPlatsPrevDaysNavInfo($date = null, $numDays = 5, $platsWithoutLan = null, $oneLanName = null)
    {
        $dateYmd = $date->format('Y-m-d');

        $cacheKey = "getPlatsPrevDaysNavInfo4:$dateYmd:$numDays:$platsWithoutLan:$oneLanName";
        $cacheTTL = 22 * 60;

        $prevDayEvents = Cache::remember(
            $cacheKey,
            $cacheTTL,
            function () use ($date, $numDays, $platsWithoutLan, $oneLanName) {
                return self::getPlatsPrevDaysNavInfoUncached($date, $numDays, $platsWithoutLan, $oneLanName);
            }
        );

        return $prevDayEvents;
    }

    /**
     * @param Carbon $date 
     * @param int $numDays 
     * @param mixed $platsWithoutLan 
     * @param mixed $oneLanName 
     * @return Collection 
     */
    public static function getPlatsPrevDaysNavInfoUncached($date = null, $numDays = 5, $platsWithoutLan = null, $oneLanName = null)
    {
        $dateYmd = $date->format('Y-m-d');

        // Vi vill ha $numDays dagar tillbaka, men har inget hänt på väldigt långt tid kan
        // det bli många rader som gås igenom, så vi begränsar till typ ett halvt år max.
        $dateYmdMinusManyDaysBack = $date->copy()->subDays(90)->format('Y-m-d');

        if ($platsWithoutLan && $oneLanName) {
            // Både plats och län
            $prevDayEvents = CrimeEvent::
                selectRaw('date_created_at as dateYMD, count(*) as dateCount, 1 as vvv')
                ->where('date_created_at', '<', $dateYmd)
                ->where('date_created_at', '>', $dateYmdMinusManyDaysBack)
                ->where("administrative_area_level_1", $oneLanName)
                ->where(function ($query) use ($platsWithoutLan) {
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
                ->orderBy("dateYMD", "desc")
                ->limit($numDays)
                ->get();
        } else {
            // Plats utan län
            $prevDayEvents = CrimeEvent::
                selectRaw('date_created_at as dateYMD, count(*) as dateCount')
                ->where('date_created_at', '<', $dateYmd)
                ->where('date_created_at', '>', $dateYmdMinusManyDaysBack)
                ->where(function ($query) use ($platsWithoutLan) {
                    $query->where("parsed_title_location", $platsWithoutLan);
                    $query->orWhere("administrative_area_level_2", $platsWithoutLan);
                    $query->orWhereHas('locations', function ($query) use ($platsWithoutLan) {
                        $query->where('name', '=', $platsWithoutLan);
                    });
                })
                ->groupBy(\DB::raw('dateYMD'))
                ->orderBy("dateYMD", "desc")
                ->limit($numDays)
                ->get();
        }

        return $prevDayEvents;
    }

    public static function getPlatsNextDaysNavInfo($date = null, $numDays = 5, $platsWithoutLan = null, $oneLanName = null)
    {
        $dateYmd = $date->format('Y-m-d');

        $cacheKey = "getPlatsNextDaysNavInfo:$dateYmd:$numDays:$platsWithoutLan:$oneLanName";
        $cacheTTL = 23 * 60;

        $prevDayEvents = Cache::remember(
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
        $dateYmdPlusOneDay = $date->copy()->addDays(1)->format('Y-m-d');
        $dateYmdPlusManyDaysForward = $date->copy()->addDays(90)->format('Y-m-d');

        if ($platsWithoutLan && $oneLanName) {
            $prevDayEvents = CrimeEvent::
                selectRaw('date_created_at as dateYMD, count(*) as dateCount')
                ->where('date_created_at', '>', $dateYmdPlusOneDay)
                ->where('date_created_at', '<', $dateYmdPlusManyDaysForward)
                ->where("administrative_area_level_1", $oneLanName)
                ->where(function ($query) use ($platsWithoutLan) {
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
                ->orderBy("dateYMD", "asc")
                ->limit($numDays)
                ->get();
        } else {
            $prevDayEvents = CrimeEvent::
                selectRaw('date_created_at as dateYMD, count(*) as dateCount')
                ->where('date_created_at', '>', $dateYmdPlusOneDay)
                ->where('date_created_at', '<', $dateYmdPlusManyDaysForward)
                ->where(function ($query) use ($platsWithoutLan) {
                    $query->where("parsed_title_location", $platsWithoutLan);
                    $query->orWhere("administrative_area_level_2", $platsWithoutLan);
                    $query->orWhereHas('locations', function ($query) use ($platsWithoutLan) {
                        $query->where('name', '=', $platsWithoutLan);
                    });
                })
                ->groupBy(\DB::raw('dateYMD'))
                ->orderBy("dateYMD", "asc")
                ->limit($numDays)
                ->get();
        }

        return $prevDayEvents;
    }

    /**
     * Landingssida för sidan 🚁
     * https://brottsplatskartan.test/helikopter
     */
    public function helicopter(Request $request) {
        $events = CrimeEvent::orderBy("created_at", "desc")
            // ->where(function ($query) {
            //     if ($isToday) {
            //         $query->where('created_at', '<', $dateYmdPlusOneDay);
            //         $query->where('created_at', '>', $dateYmdMinusNumDaysBack);
            //     } else {
            //         $query->where('created_at', '<', $dateYmdPlusOneDay);
            //         $query->where('created_at', '>', $dateYmd);
            //     }
            // })
            #->where(function ($query) {
                // $query->where("parsed_title_location", $plats);
                // $query->orWhere("administrative_area_level_2", $plats);
                // $query->orWhereHas('locations', function ($query) use ($plats) {
                //     $query->where('name', '=', $plats);
                // });
            #})
            ->where('parsed_title', 'LIKE', "%helikopter%")
            ->orWhere('parsed_teaser', 'LIKE', "%helikopter%")
            ->orWhere('parsed_content', 'LIKE', "%helikopter%")
            ->limit(25)
            ->with('locations')
            ->get();
        
        $data = [
            'events' => $events,
        ];
        return view('overview-helicopter', $data);
    }
}
