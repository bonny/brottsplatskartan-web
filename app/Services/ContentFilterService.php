<?php

namespace App\Services;

use App\CrimeEvent;
use Illuminate\Support\Collection;

class ContentFilterService
{
    /**
     * Identifierar händelser som inte ska vara publika baserat på innehåll.
     *
     * @param CrimeEvent $event
     * @return bool
     */
    public function shouldBePublic(CrimeEvent $event): bool
    {
        // Om det redan är markerat som icke-publikt, behåll det
        if (!$event->is_public) {
            return false;
        }

        // Kontrollera om det är ett presstalesperson-meddelande
        if ($this->isPressNotice($event)) {
            return false;
        }

        // Lägg till fler filter här i framtiden

        return true;
    }

    /**
     * Identifierar presstalesperson-meddelanden som inte ska visas publikt.
     *
     * @param CrimeEvent $event
     * @return bool
     */
    public function isPressNotice(CrimeEvent $event): bool
    {
        $title = strtolower($event->title ?? '');
        $description = strtolower($event->description ?? '');
        $parsedContent = strtolower($event->parsed_content ?? '');

        // Mönster för presstalesperson-meddelanden
        $pressPatterns = [
            // Huvudmönster
            '/efter klockan \d{1,2}:\d{2} finns ingen presstalesperson i tjänst/i',
            '/frågor från media besvaras av vakthavande befäl i mån av tid/i',
            
            // Variationer
            '/ingen presstalesperson/i',
            '/presstalesperson.*tjänst/i',
            '/media besvaras av vakthavande befäl/i',
            '/vakthavande befäl i mån av tid/i',
        ];

        // Kontrollera alla textfält mot alla mönster
        $textFields = [$title, $description, $parsedContent];
        
        foreach ($textFields as $text) {
            if (empty($text)) continue;
            
            foreach ($pressPatterns as $pattern) {
                if (preg_match($pattern, $text)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Hämtar händelser som ska markeras som icke-publika.
     *
     * @param int $daysBack Antal dagar bakåt att kontrollera
     * @return Collection
     */
    public function getEventsToMarkAsNonPublic(int $daysBack = 30): Collection
    {
        // Använd withoutGlobalScope för att se alla händelser
        $events = CrimeEvent::withoutGlobalScope('public')
            ->where('created_at', '>=', now()->subDays($daysBack))
            ->where('is_public', true) // Bara de som fortfarande är publika
            ->get();

        return $events->filter(function ($event) {
            return !$this->shouldBePublic($event);
        });
    }

    /**
     * Markerar händelser som icke-publika baserat på filter.
     *
     * @param int $daysBack Antal dagar bakåt att kontrollera
     * @return array Med information om vilka som uppdaterades
     */
    public function markEventsAsNonPublic(int $daysBack = 30): array
    {
        $eventsToUpdate = $this->getEventsToMarkAsNonPublic($daysBack);
        
        $updatedCount = 0;
        $updatedEvents = [];

        foreach ($eventsToUpdate as $event) {
            $event->is_public = false;
            $event->save();
            
            $updatedCount++;
            $updatedEvents[] = [
                'id' => $event->id,
                'title' => $event->title,
                'reason' => $this->getFilterReason($event)
            ];
        }

        return [
            'updated_count' => $updatedCount,
            'updated_events' => $updatedEvents
        ];
    }

    /**
     * Returnerar anledning till varför en händelse filtrerades.
     *
     * @param CrimeEvent $event
     * @return string
     */
    private function getFilterReason(CrimeEvent $event): string
    {
        if ($this->isPressNotice($event)) {
            return 'Presstalesperson-meddelande';
        }

        return 'Okänd anledning';
    }
}