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
        // 5 senaste publicerade events inom 120 min.
        // Använder pubdate (när Polisen publicerade) snarare än
        // parsed_date (när händelsen inträffade) — pubdate ger
        // 2x volym och speglar bättre "live på sajten"-känslan.
        // Median pub-parsed-gap är 22 min, p90 = 3.4 h, så ett
        // parsed_date-filter missar nyligen publicerade events.
        // Om 0 events inom fönstret, dölj rutan helt.
        $events = Cache::remember(
            'vadHanderNu:latestLiveEvents:pubdate120min',
            60, // 1 min — kortare än response-cache så känns färskt
            function () {
                $now = now();
                return CrimeEvent::whereBetween('pubdate', [
                        $now->copy()->subMinutes(120)->timestamp,
                        $now->timestamp,
                    ])
                    ->orderBy('pubdate', 'desc')
                    ->with('locations')
                    ->limit(5)
                    ->get();
            }
        );

        return view('components.vad-hander-nu', [
            'events' => $events,
        ]);
    }
}
