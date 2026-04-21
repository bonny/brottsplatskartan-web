<?php

namespace App\CacheProfiles;

use Illuminate\Http\Request;
use Spatie\ResponseCache\CacheProfiles\CacheAllSuccessfulGetRequests;

class BrottsplatskartanCacheProfile extends CacheAllSuccessfulGetRequests
{
    /**
     * Returnera cache-livstid i sekunder baserat på URL/route.
     *
     * Uppdaterad till Spatie Response Cache 8.x: `cacheRequestUntil(): DateTime`
     * ersattes av `cacheLifetimeInSeconds(): int`.
     */
    public function cacheLifetimeInSeconds(Request $request): int
    {
        // Startsida: kort cache.
        if ($request->is('/') || $request->is('')) {
            return 2 * MINUTE_IN_SECONDS;
        }

        // VMA alerts: mycket kort cache.
        if ($request->is('vma') || $request->is('api/vma')) {
            return 2 * MINUTE_IN_SECONDS;
        }

        // Historiska datum-sidor: mycket lång cache (7 dagar).
        // Gammal data ändras aldrig.
        if ($request->is('handelser/*')) {
            $date = $this->extractDateFromUrl($request->path());
            if ($date && $date->diffInDays(now()) > 7) {
                return 7 * DAY_IN_SECONDS;
            }
        }

        // API endpoints: varierad cache
        if ($request->is('api/events')) {
            return 10 * MINUTE_IN_SECONDS;
        }

        // Standard: 30 minuter
        return 30 * MINUTE_IN_SECONDS;
    }

    private function extractDateFromUrl(string $path): ?\Carbon\Carbon
    {
        if (preg_match('/(\d{1,2})-([a-zåäö]+)-(\d{4})/i', $path, $matches)) {
            try {
                $day = $matches[1];
                $month = $matches[2];
                $year = $matches[3];

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
