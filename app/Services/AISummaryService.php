<?php

namespace App\Services;

use App\Ai\Agents\DailySummaryAgent;
use App\CrimeEvent;
use App\Models\DailySummary;
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
        $query = CrimeEvent::whereDate('date_created_at', $date->format('Y-m-d'));

        if (strtolower($area) === 'stockholm') {
            $query->where(function ($q) {
                $q->where('administrative_area_level_2', 'like', '%Stockholm%')
                  ->orWhere('administrative_area_level_1', 'like', '%Stockholm%')
                  ->orWhere('parsed_content', 'like', '%Stockholm%');
            });
        } else {
            $query->where('administrative_area_level_2', 'like', "%{$area}%");
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
}