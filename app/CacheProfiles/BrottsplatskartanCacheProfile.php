<?php

namespace App\CacheProfiles;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Spatie\ResponseCache\CacheProfiles\CacheAllSuccessfulGetRequests;
use Symfony\Component\HttpFoundation\Response;

class BrottsplatskartanCacheProfile extends CacheAllSuccessfulGetRequests
{
    /**
     * Utökar förälderns filter med application/xml + application/atom+xml
     * så att RSS/Atom-feeds också cachas. Förälderns logik täcker bara
     * text/* och *json.
     */
    public function hasCacheableContentType(Response $response): bool
    {
        $contentType = $response->headers->get('Content-Type', '');
        if (Str::contains($contentType, ['xml'])) {
            return true;
        }
        return parent::hasCacheableContentType($response);
    }
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

        // RSS/Atom-feeds — RSS-läsare poll:ar aggressivt.
        if ($request->is('rss') || $request->is('feed*')) {
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

    /**
     * Grace-period för SWR (stale-while-revalidate).
     *
     * Under grace-fönstret serveras stale-svar omedelbart medan en
     * bakgrundsprocess regenererar. Tumregel: ~4x fresh-tid för hot
     * paths. För data som aldrig ändras (historiska datum) är grace
     * irrelevant. För VMA prioriterar vi färskhet före latens.
     */
    public function graceInSeconds(Request $request): int
    {
        // VMA: prioritera färsk data, ingen SWR.
        if ($request->is('vma') || $request->is('api/vma')) {
            return 0;
        }

        // RSS — kort grace så läsare får färsk feed relativt snabbt.
        if ($request->is('rss') || $request->is('feed*')) {
            return 5 * MINUTE_IN_SECONDS;
        }

        // Startsida: kort fresh -> längre grace för att slippa köbildning.
        if ($request->is('/') || $request->is('')) {
            return 10 * MINUTE_IN_SECONDS;
        }

        // Historiska datum: data ändras aldrig, grace spelar ingen roll.
        if ($request->is('handelser/*')) {
            $date = $this->extractDateFromUrl($request->path());
            if ($date && $date->diffInDays(now()) > 7) {
                return HOUR_IN_SECONDS;
            }
        }

        // API
        if ($request->is('api/events')) {
            return HOUR_IN_SECONDS;
        }

        // Standard: 2h grace
        return 2 * HOUR_IN_SECONDS;
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
