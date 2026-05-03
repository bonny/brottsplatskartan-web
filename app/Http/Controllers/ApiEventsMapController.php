<?php

namespace App\Http\Controllers;

use App\CrimeEvent;
use App\Models\Event;
use App\Tier1;
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
     *
     * ?source=polisen (default, bakåtkompat) | trafikverket | all.
     * Trafikverket-source ignorerar ?city (källan har bara län-granularitet).
     */
    public function index(Request $request) {
        $city = $request->query('city');
        $lan = $request->query('lan');
        $source = $request->query('source', 'polisen');
        if (!in_array($source, ['polisen', 'trafikverket', 'all'], true)) {
            $source = 'polisen';
        }

        $hasFilter = is_string($city) && $city !== '' || is_string($lan) && $lan !== '';
        $cacheSeconds = 5 * 60;
        $daysBack = $hasFilter ? 30 : 3;

        $citySlug = is_string($city) ? strtolower($city) : '';
        $cityForDb = $citySlug !== '' ? Tier1::displayName($citySlug) : '';
        $lanFilter = is_string($lan) ? trim($lan) : '';

        $cacheKey = sprintf(
            '%s_d%d_t%d_city:%s_lan:%s_src:%s',
            __METHOD__,
            $daysBack,
            $cacheSeconds,
            $citySlug,
            $lanFilter,
            $source
        );

        $transformedEvents = Cache::remember($cacheKey, $cacheSeconds, function () use ($daysBack, $citySlug, $cityForDb, $lanFilter, $source) {
            $polisenRows = ($source === 'polisen' || $source === 'all')
                ? $this->fetchPolisen($daysBack, $citySlug, $cityForDb, $lanFilter)
                : [];

            // Trafikverket: ?city ignoreras (källan har bara län-granularitet).
            // Vid stadsfråga utan ?lan ges inget Trafikverket-data.
            $trafikRows = ($source === 'trafikverket' || $source === 'all') && $citySlug === ''
                ? $this->fetchTrafikverket($lanFilter)
                : [];

            return array_merge($polisenRows, $trafikRows);
        });

        return [
            'data' => $transformedEvents,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchPolisen(int $daysBack, string $citySlug, string $cityForDb, string $lanFilter): array {
        $query = CrimeEvent::orderBy('created_at', 'desc')
            ->where('created_at', '>=', now()->subDays($daysBack))
            ->with('locations');

        if ($citySlug !== '') {
            // Speglar AISummaryService::getEventsForDate / getMonthlyEvents.
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
            $query->where('administrative_area_level_1', $lanFilter);
        }

        $events = $query->limit(500)->get();

        return $events->map(function ($item) {
            return [
                'source' => 'polisen',
                'id' => $item->id,
                'time' => $item->getParsedDateInFormat('HH:mm'),
                'time_human' => $item->getParsedDateFormattedForHumans(),
                'headline' => $item->getHeadline(),
                'type' => $item->parsed_title,
                'locations' => $item->getLocationString(includeAdministrativeAreaLevel1Locations: false),
                'lat' => (float) $item->location_lat,
                'lng' => (float) $item->location_lng,
                'image' => $item->getStaticImageSrc(320, 320, 2),
                'permalink' => $item->getPermalink(true),
            ];
        })->toArray();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchTrafikverket(string $lanFilter): array {
        $query = Event::active()
            ->forSource('trafikverket')
            ->orderByDesc('start_time');

        if ($lanFilter !== '') {
            $query->where('administrative_area_level_1', $lanFilter);
        }

        return $query->limit(2000)->get()->map(function (Event $item) {
            return [
                'source' => 'trafikverket',
                'id' => $item->id,
                'time' => $item->start_time->format('H:i'),
                'time_human' => $item->start_time->diffForHumans(),
                'headline' => $item->message ?: $item->location_descriptor,
                'type' => $item->message_type,
                'message_code' => $item->message_code,
                'locations' => $item->location_descriptor,
                'road_number' => $item->road_number,
                'lat' => (float) $item->lat,
                'lng' => (float) $item->lng,
                'severity' => $item->severity_code,
                'icon_id' => $item->icon_id,
                'ends_at' => $item->end_time?->toIso8601String(),
                'permalink' => null,
                'source_url' => $item->source_url,
            ];
        })->toArray();
    }
}
