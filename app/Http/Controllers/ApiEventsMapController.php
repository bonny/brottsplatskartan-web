<?php

namespace App\Http\Controllers;

use App\CrimeEvent;
use Cache;
use Illuminate\Http\Request;

class ApiEventsMapController extends Controller {
    /**
     * Hämta data för eventsMap-komponenten.
     *
     * Stödjer optional ?city= eller ?lan= för att begränsa till en
     * specifik tier 1-stad eller län. Utan filter: senaste 3 dagarna,
     * limit 500 (Sverigekartan / startsidan / event-detalj). Med filter:
     * 30 dagar lookback så små städer faktiskt får markers att visa.
     */
    public function index(Request $request) {
        $city = $request->query('city');
        $lan = $request->query('lan');

        $hasFilter = is_string($city) && $city !== '' || is_string($lan) && $lan !== '';
        $cacheSeconds = 5 * 60;
        $daysBack = $hasFilter ? 30 : 3;

        $citySlug = is_string($city) ? strtolower($city) : '';
        $cityForDb = $citySlug !== '' ? CityController::tier1DisplayName($citySlug) : '';
        $lanFilter = is_string($lan) ? trim($lan) : '';

        $cacheKey = sprintf(
            '%s_d%d_t%d_city:%s_lan:%s',
            __METHOD__,
            $daysBack,
            $cacheSeconds,
            $citySlug,
            $lanFilter
        );

        // Cacha både query OCH transformation för att undvika tunga metodanrop vid varje request
        $transformedEvents = Cache::remember($cacheKey, $cacheSeconds, function () use ($daysBack, $citySlug, $cityForDb, $lanFilter) {
            $query = CrimeEvent::orderBy("created_at", "desc")
                ->where('created_at', '>=', now()->subDays($daysBack))
                ->with('locations'); // Eager load för att undvika N+1 query problem

            if ($citySlug !== '') {
                // Speglar AISummaryService::getEventsForDate / getMonthlyEvents:
                // matcha display-form (åäö) i parsed_title_location, adm_2 och
                // locations.name. Slug-form behålls som fallback för städer
                // utan åäö.
                $query->where(function ($q) use ($citySlug, $cityForDb) {
                    $q->where('parsed_title_location', $cityForDb)
                      ->orWhere('administrative_area_level_2', $cityForDb);

                    if ($citySlug !== $cityForDb) {
                        $q->orWhere('parsed_title_location', $citySlug)
                          ->orWhere('administrative_area_level_2', $citySlug);
                    }

                    $q->orWhereHas('locations', function ($q2) use ($cityForDb) {
                        $q2->where('name', '=', $cityForDb);
                    });
                });
            } elseif ($lanFilter !== '') {
                // Län matchas direkt mot adm_1 ("Stockholms län" etc.).
                $query->where('administrative_area_level_1', $lanFilter);
            }

            $events = $query->limit(500)->get();

            // Transformera data INNE i cachen så metodanrop (getHeadline, getLocationString, etc.)
            // bara körs en gång per cache-period istället för vid varje request
            return $events->map(function ($item) {
                return [
                    "id" => $item->id,
                    'time' => $item->getParsedDateInFormat('HH:mm'), // Carbon isoFormat, inte strftime
                    'time_human' => $item->getParsedDateFormattedForHumans(),
                    'headline' => $item->getHeadline(),
                    "type" => $item->parsed_title,
                    "locations" => $item->getLocationString(includeAdministrativeAreaLevel1Locations: false),
                    "lat" => (float) $item->location_lat,
                    "lng" => (float) $item->location_lng,
                    "image" => $item->getStaticImageSrc(320, 320, 2),
                    "permalink" => $item->getPermalink(true),
                ];
            })->toArray();
        });

        $json = [
            "data" => $transformedEvents,
        ];

        // return json or jsonp if ?callback is set
        return $json;
    }
}
