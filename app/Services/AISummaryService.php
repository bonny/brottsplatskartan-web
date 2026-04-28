<?php

namespace App\Services;

use App\Ai\Agents\DailySummaryAgent;
use App\Ai\Agents\MonthlySummaryAgent;
use App\CrimeEvent;
use App\Models\DailySummary;
use App\Models\MonthlySummary;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class AISummaryService
{

    /**
     * Genererar en AI-sammanfattning av dagens händelser för ett specifikt område
     * Kontrollerar först om händelserna har ändrats sedan senaste körningen
     * 
     * @param string $area Område att summera (t.ex. 'stockholm')
     * @param Carbon $date Datum att summera
     * @return array ['summary' => DailySummary|null, 'ai_generated' => bool]
     */
    public function generateDailySummary(string $area, Carbon $date): array
    {
        $events = $this->getEventsForDate($area, $date);
        
        if ($events->isEmpty()) {
            Log::info("Inga händelser hittades för {$area} på {$date->format('Y-m-d')}");
            return ['summary' => null, 'ai_generated' => false];
        }

        // Kontrollera om sammanfattning redan finns och om händelserna har ändrats
        $existingSummary = DailySummary::where('summary_date', $date->format('Y-m-d'))
            ->where('area', $area)
            ->first();
        
        if ($existingSummary && $this->eventsUnchanged($events, $existingSummary)) {
            Log::info("Händelserna för {$area} på {$date->format('Y-m-d')} har inte ändrats - hoppar över AI-generering");
            return ['summary' => $existingSummary, 'ai_generated' => false];
        }

        Log::info("Händelser har ändrats för {$area} på {$date->format('Y-m-d')} - genererar ny sammanfattning");
        $summary = $this->generateSummaryText($events, $area, $date);
        
        if (!$summary) {
            Log::error("Kunde inte generera sammanfattning för {$area} på {$date->format('Y-m-d')}");
            return ['summary' => null, 'ai_generated' => false];
        }

        $dailySummary = DailySummary::updateOrCreate(
            [
                'summary_date' => $date->format('Y-m-d'),
                'area' => $area
            ],
            [
                'summary' => $summary,
                'events_data' => $events->toArray(),
                'events_count' => $events->count()
            ]
        );
        
        return ['summary' => $dailySummary, 'ai_generated' => true];
    }

    /**
     * Hämtar alla brottshändelser för ett specifikt område och datum
     * 
     * @param string $area Område att söka i
     * @param Carbon $date Datum att söka för
     * @return \Illuminate\Database\Eloquent\Collection Samling av händelser
     */
    private function getEventsForDate(string $area, Carbon $date): \Illuminate\Database\Eloquent\Collection
    {
        $areaSlug = strtolower($area);
        $areaForDb = \App\Http\Controllers\CityController::tier1DisplayName($areaSlug);

        $query = CrimeEvent::whereDate('date_created_at', $date->format('Y-m-d'));

        if ($areaSlug === 'stockholm') {
            // Stockholm-specialfall: bredare match (inkl. län + parsed_content)
            // — matchar historiskt beteende sedan summary-funktionen lanserades.
            $query->where(function ($q) {
                $q->where('administrative_area_level_2', 'like', '%Stockholm%')
                  ->orWhere('administrative_area_level_1', 'like', '%Stockholm%')
                  ->orWhere('parsed_content', 'like', '%Stockholm%');
            });
        } else {
            // Speglar AISummaryService::getMonthlyEvents() — slug→display via
            // tier1DisplayName + locations-relation. Krävs för att Göteborg/
            // Malmö (slug ≠ display med åäö) ska få träffar alls.
            $query->where(function ($q) use ($areaSlug, $areaForDb) {
                $q->where('parsed_title_location', $areaForDb)
                  ->orWhere('administrative_area_level_2', $areaForDb);

                if ($areaSlug !== $areaForDb) {
                    $q->orWhere('parsed_title_location', $areaSlug)
                      ->orWhere('administrative_area_level_2', $areaSlug);
                }

                $q->orWhereHas('locations', function ($q2) use ($areaForDb) {
                    $q2->where('name', '=', $areaForDb);
                });
            });
        }

        return $query->orderBy('date_created_at', 'desc')->get();
    }

    /**
     * Genererar sammanfattningstext via laravel/ai DailySummaryAgent.
     * Modell + max_tokens + temperature definieras som attribut på agenten.
     */
    private function generateSummaryText($events, string $area, Carbon $date): ?string
    {
        $eventsText = $this->formatEventsForAI($events);
        $swedishDate = $date->locale('sv')->isoFormat('dddd D MMMM YYYY');

        $userPrompt = "<task>\n  <area>{$area}</area>\n  <date>{$swedishDate}</date>\n</task>\n\n{$eventsText}";

        try {
            $response = (new DailySummaryAgent)->prompt($userPrompt);
            $text = (string) $response;
            return $text !== '' ? $text : null;
        } catch (\Exception $e) {
            Log::error("DailySummaryAgent fel: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Formaterar händelser till XML-format som är lämplig för AI-prompten
     * 
     * @param $events Samling av händelser
     * @return string Formaterad text med XML-taggar för varje händelse
     */
    private function formatEventsForAI($events): string
    {
        $formatted = ['<events>'];
        
        /** @var CrimeEvent $event */
        foreach ($events as $event) {
            $time = Carbon::parse($event->created_at)->format('H:i');
            $location = $event->administrative_area_level_2 ?: $event->administrative_area_level_1;
            $type = $event->title ?: 'Händelse';
            
            $eventUrl = $event->getPermalink(true);
            
            $formatted[] = '<event>';
            $formatted[] = "  <id>{$event->id}</id>";
            $formatted[] = "  <time>{$time}</time>";
            $formatted[] = "  <type>{$type}</type>";
            $formatted[] = "  <location>{$location}</location>";
            $formatted[] = "  <description>{$event->parsed_content}</description>";
            $formatted[] = "  <url>{$eventUrl}</url>";
            $formatted[] = '</event>';
        }
        
        $formatted[] = '</events>';

        return implode("\n", $formatted);
    }

    /**
     * Kontrollerar om händelserna har ändrats sedan senaste sammanfattning
     *
     * @param \Illuminate\Database\Eloquent\Collection $events Aktuella händelser
     * @param DailySummary $existingSummary Befintlig sammanfattning
     * @return bool True om händelserna är oförändrade
     */
    private function eventsUnchanged($events, DailySummary $existingSummary): bool
    {
        // Jämför antal händelser
        if ($events->count() !== $existingSummary->events_count) {
            return false;
        }

        // Skapa hash av händelse-ID:n för snabb jämförelse
        $currentEventIds = $events->pluck('id')->sort()->values()->toArray();
        $previousEventIds = collect($existingSummary->events_data ?? [])
            ->pluck('id')
            ->sort()
            ->values()
            ->toArray();

        return $currentEventIds === $previousEventIds;
    }

    /**
     * Genererar en AI-månadssammanfattning för ett område (todo #27 Lager 3).
     * Kontrollerar först om månadens events ändrats sedan senaste körningen
     * (cheap path) — bara nya/ändrade events triggar AI-anrop.
     *
     * @return array{summary: MonthlySummary|null, ai_generated: bool}
     */
    public function generateMonthlySummary(string $area, int $year, int $month): array
    {
        $start = Carbon::create($year, $month, 1)->startOfMonth();
        $end = (clone $start)->endOfMonth();
        $label = "{$area} {$year}-" . str_pad((string) $month, 2, '0', STR_PAD_LEFT);

        $events = $this->getMonthlyEvents($area, $start, $end);
        if ($events->isEmpty()) {
            Log::info("Inga månads-events för {$label}");
            return ['summary' => null, 'ai_generated' => false];
        }

        $existing = MonthlySummary::where('area', $area)
            ->where('year', $year)
            ->where('month', $month)
            ->first();

        if ($existing && $this->monthlyEventsUnchanged($events, $existing)) {
            Log::info("Månads-events oförändrade för {$label} — hoppar över AI");
            return ['summary' => $existing, 'ai_generated' => false];
        }

        $prevStart = (clone $start)->subMonth();
        $prevEnd = (clone $prevStart)->endOfMonth();
        $prevMonthCount = $this->getMonthlyEvents($area, $prevStart, $prevEnd)->count();

        $summary = $this->generateMonthlySummaryText($events, $area, $start, $prevMonthCount);
        if (!$summary) {
            Log::error("AI kunde inte generera månadssammanfattning för {$label}");
            return ['summary' => null, 'ai_generated' => false];
        }

        $monthly = MonthlySummary::updateOrCreate(
            ['area' => $area, 'year' => $year, 'month' => $month],
            [
                'summary' => $summary,
                'events_data' => $events->pluck('id')->values()->toArray(),
                'events_count' => $events->count(),
                'prev_month_count' => $prevMonthCount,
            ]
        );

        return ['summary' => $monthly, 'ai_generated' => true];
    }

    /**
     * Hämtar events för en plats över ett månads-range. Speglar
     * PlatsController::getEventsInPlatsForMonth() men utan att binda
     * Service till en Controller. Cachen återanvänds — samma cache-key.
     */
    private function getMonthlyEvents(string $area, Carbon $start, Carbon $end): \Illuminate\Database\Eloquent\Collection
    {
        $cacheKey = sprintf('getEventsInPlatsForMonth:%s:%s', $area, $start->format('Y-m'));

        // Samma slug→display-mappning som PlatsController::getEventsInPlatsForMonth.
        $areaForDb = \App\Http\Controllers\CityController::tier1DisplayName($area);

        return \Illuminate\Support\Facades\Cache::remember($cacheKey, 30 * 60, function () use ($area, $areaForDb, $start, $end) {
            return CrimeEvent::orderBy('created_at', 'desc')
                ->whereBetween('created_at', [$start, $end])
                ->where(function ($query) use ($area, $areaForDb) {
                    $query->where('parsed_title_location', $areaForDb);
                    $query->orWhere('administrative_area_level_2', $areaForDb);
                    if ($area !== $areaForDb) {
                        $query->orWhere('parsed_title_location', $area);
                        $query->orWhere('administrative_area_level_2', $area);
                    }
                    $query->orWhereHas('locations', function ($q) use ($areaForDb) {
                        $q->where('name', '=', $areaForDb);
                    });
                })
                ->with('locations')
                ->get();
        });
    }

    /**
     * Bygger user-prompt + anropar MonthlySummaryAgent.
     */
    private function generateMonthlySummaryText($events, string $area, Carbon $monthStart, int $prevMonthCount): ?string
    {
        $eventsText = $this->formatEventsForAI($events);
        $monthLabel = $monthStart->locale('sv')->isoFormat('MMMM YYYY');

        $userPrompt = "<task>\n"
            . "  <area>{$area}</area>\n"
            . "  <month>{$monthLabel}</month>\n"
            . "  <events_count>{$events->count()}</events_count>\n"
            . "  <prev_month_count>{$prevMonthCount}</prev_month_count>\n"
            . "</task>\n\n{$eventsText}";

        try {
            $response = (new MonthlySummaryAgent)->prompt($userPrompt);
            $text = (string) $response;
            return $text !== '' ? $text : null;
        } catch (\Exception $e) {
            Log::error("MonthlySummaryAgent fel: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Change-detection för månadssammanfattning. Endast id-array sparas
     * (events_data) för effektivitet — månader kan ha 200+ events.
     */
    private function monthlyEventsUnchanged($events, MonthlySummary $existing): bool
    {
        if ($events->count() !== $existing->events_count) {
            return false;
        }

        $currentIds = $events->pluck('id')->sort()->values()->toArray();
        $previousIds = collect($existing->events_data ?? [])
            ->sort()
            ->values()
            ->toArray();

        return $currentIds === $previousIds;
    }
}