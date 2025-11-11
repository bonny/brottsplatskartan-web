<?php

namespace App\CacheProfiles;

use DateTime;
use Illuminate\Http\Request;
use Spatie\ResponseCache\CacheProfiles\CacheAllSuccessfulGetRequests;

class BrottsplatskartanCacheProfile extends CacheAllSuccessfulGetRequests
{
    /**
     * Bestäm cache-livstid baserat på URL/route
     *
     * Parent-klassen hanterar: enabled(), shouldCacheResponse()
     */
    public function cacheRequestUntil(Request $request): DateTime
    {
        // Startsida: kort cache.
        if ($request->is('/') || $request->is('')) {
            return now()->addMinutes(2);
        }

        // VMA alerts: mycket kort cache.
        if ($request->is('vma') || $request->is('api/vma')) {
            return now()->addMinutes(2);
        }

        // Historiska datum-sidor: mycket lång cache (7 dagar)
        // Gammal data ändras aldrig, så kan cachas länge
        if ($request->is('handelser/*')) {
            $date = $this->extractDateFromUrl($request->path());
            if ($date && $date->diffInDays(now()) > 7) {
                return now()->addDays(7);
            }
        }

        // API endpoints: varierad cache
        if ($request->is('api/events')) {
            return now()->addMinutes(10);
        }

        // Standard: 30 minuter
        return now()->addMinutes(30);
    }

    /**
     * Hjälpmetod för att extrahera datum från URL
     *
     * Används för att ge historiska datum-sidor längre cache-tid
     */
    private function extractDateFromUrl(string $path): ?\Carbon\Carbon
    {
        // Extrahera datum från URL som "handelser/15-januari-2024"
        if (preg_match('/(\d{1,2})-([a-zåäö]+)-(\d{4})/i', $path, $matches)) {
            try {
                $day = $matches[1];
                $month = $matches[2];
                $year = $matches[3];

                // Konvertera svensk månad till Carbon
                $monthMap = [
                    'januari' => 'January',
                    'februari' => 'February',
                    'mars' => 'March',
                    'april' => 'April',
                    'maj' => 'May',
                    'juni' => 'June',
                    'juli' => 'July',
                    'augusti' => 'August',
                    'september' => 'September',
                    'oktober' => 'October',
                    'november' => 'November',
                    'december' => 'December',
                ];

                $englishMonth = $monthMap[strtolower($month)] ?? $month;
                return \Carbon\Carbon::parse("$day $englishMonth $year");
            } catch (\Exception $e) {
                return null;
            }
        }

        return null;
    }
}
