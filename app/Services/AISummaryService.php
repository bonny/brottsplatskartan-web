<?php

namespace App\Services;

use App\CrimeEvent;
use App\Models\DailySummary;
use Claude\Claude3Api\Client;
use Claude\Claude3Api\Config;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class AISummaryService
{
    private $claude;

    /**
     * Initialiserar Claude API-klienten med konfiguration
     */
    public function __construct()
    {
        $config = new Config(config('services.claude.api_key'));
        $this->claude = new Client($config);
    }

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
        $startOfDay = $date->copy()->startOfDay();
        $endOfDay = $date->copy()->endOfDay();

        $query = CrimeEvent::whereBetween('created_at', [$startOfDay, $endOfDay]);

        if (strtolower($area) === 'stockholm') {
            $query->where(function ($q) {
                $q->where('administrative_area_level_2', 'like', '%Stockholm%')
                  ->orWhere('administrative_area_level_1', 'like', '%Stockholm%')
                  ->orWhere('parsed_content', 'like', '%Stockholm%');
            });
        } else {
            $query->where('administrative_area_level_2', 'like', "%{$area}%");
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    /**
     * Genererar sammanfattningstext genom att skicka händelser till Claude AI
     * 
     * @param $events Samling av händelser
     * @param string $area Område
     * @param Carbon $date Datum
     * @return string|null AI-genererad sammanfattning eller null vid fel
     */
    private function generateSummaryText($events, string $area, Carbon $date): ?string
    {
        $eventsText = $this->formatEventsForAI($events);
        
        $prompt = $this->buildPrompt($eventsText, $area, $date);

        try {
            $response = $this->claude->chat($prompt);
            return $response->getContent()[0]['text'] ?? null;
        } catch (\Exception $e) {
            Log::error("Claude API fel: " . $e->getMessage());
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
            
            $eventUrl = $event->getPermalink();
            
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
     * Bygger den svenska AI-prompten för att skapa en journalistisk sammanfattning
     * 
     * @param string $eventsText Formaterad text med händelser
     * @param string $area Område
     * @param Carbon $date Datum
     * @return string Komplett prompt för Claude AI
     */
    private function buildPrompt(string $eventsText, string $area, Carbon $date): string
    {
        $swedishDate = $date->locale('sv')->isoFormat('dddd D MMMM YYYY');
        
        return "Du är en svensk nyhetsredaktör som skriver sammanfattningar av brottshändelser för webbplatsen Brottsplatskartan.se.

Skriv en engagerande sammanfattning av dagens polishändelser i {$area} för {$swedishDate}.

INSTRUKTIONER:
- Skriv på svenska
- Börja med de allvarligaste brotten (våld, skottlossning, rån, etc.)
- Använd en journalistisk ton som är informativ men inte sensationalistisk
- Håll sammanfattningen mellan 2-4 stycken (100-200 ord)
- Inkludera specifika platser när det är relevant
- Nämn tidsperioder (t.ex. \"under förmiddagen\", \"sent på kvällen\") 
- Sluta med mindre allvarliga händelser som trafikolyckor
- Undvik att hitta på detaljer som inte finns i källmaterialet
- Gör texten SEO-vänlig för sökningar relaterade till brott i {$area}
- VIKTIGT: Inkludera INGEN rubrik eller titel - skriv bara brödtexten direkt
- VIKTIGT: Alla händelser som nämns i sammanfattningen MÅSTE få en klickbar länk
- Använd MARKDOWN-format för länkar: [beskrivande text](URL_från_XML)
- Exempel: \"Ett [rån skedde på Drottninggatan](URL) under eftermiddagen\"
- Exempel: \"[Trafikolycka på E4](URL) orsakade stora trafikstörningar\"
- Gör länktexten naturlig och beskrivande (inte bara \"händelse\" eller \"brott\")

HÄNDELSER ATT SAMMANFATTA:
Händelserna nedan är strukturerade med XML-taggar. Använd informationen från <id>, <time>, <type>, <location>, <description> och <url> för varje <event>:

{$eventsText}

Skriv sammanfattningen:";
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