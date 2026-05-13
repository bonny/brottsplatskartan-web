<?php

namespace App\View\Components;

use App\CrimeEvent;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\Component;

/**
 * "Vad händer nu"-ruta — kompakt live-feed för startsidan.
 *
 * Feature-flag-gated: visas bara när `?show_live=1` finns i URL.
 * Spatie Response Cache key:ar på query string, så flag-on och
 * flag-off cachas separat utan att störa varandra. Pilot-läge.
 */
class VadHanderNu extends Component
{
    public function shouldRender(): bool
    {
        return request()->query('show_live') === '1';
    }

    public function render(): View|Closure|string
    {
        // 3 senaste publicerade events inom 120 min.
        // Använder pubdate (när Polisen publicerade) snarare än
        // parsed_date (när händelsen inträffade) — pubdate ger
        // 2x volym och speglar bättre "live på sajten"-känslan.
        // Median pub-parsed-gap är 22 min, p90 = 3.4 h, så ett
        // parsed_date-filter missar nyligen publicerade events.
        //
        // Filtrerar bort sammanfattnings-poster ("ett urval av polisens
        // arbete i trafiken") — de torpederar både innehåll (lågvärde)
        // och layout (10+ orter i location-strängen).
        //
        // isLive=true om senaste event < 30 min → stark puls.
        // isLive=false → dämpad statisk prick (puls vore lögn vid 109min).
        $events = Cache::remember(
            'vadHanderNu:latestLiveEvents:pubdate120min:v2',
            60,
            function () {
                $now = now();
                return CrimeEvent::whereBetween('pubdate', [
                        $now->copy()->subMinutes(120)->timestamp,
                        $now->timestamp,
                    ])
                    ->where('parsed_title', 'NOT LIKE', '%ett urval av polisens arbete%')
                    ->orderBy('pubdate', 'desc')
                    ->with('locations')
                    ->limit(3)
                    ->get();
            }
        );

        $isLive = $events->isNotEmpty()
            && $events->first()->pubdate >= now()->subMinutes(30)->timestamp;

        return view('components.vad-hander-nu', [
            'events' => $events,
            'isLive' => $isLive,
        ]);
    }

    /**
     * Kompakt location-sträng för live-feed: kommun + län, dedup mot
     * headline. `getLocationString()` ger hela prio-1/2-kedjan + parsed
     * title location — för rikt för en kompakt liverad och dubblerar
     * ofta orten som redan står i rubriken.
     */
    public static function compactLocation(CrimeEvent $event, string $headline): string
    {
        $parts = [];

        $kommun = trim((string) ($event->parsed_title_location ?? ''));
        if ($kommun !== '' && stripos($headline, $kommun) === false) {
            $parts[] = $kommun;
        }

        $lan = trim((string) ($event->administrative_area_level_1 ?? ''));
        if ($lan !== '' && $lan !== $kommun) {
            $parts[] = $lan;
        }

        return implode(', ', $parts);
    }
}
