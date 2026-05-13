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
        // 5 senaste events inom 60 min — speglar "live"-känslan.
        // Om 0 events finns < 60 min, dölj rutan helt (per todo:
        // hellre osynlig än uppenbart inaktuell).
        $events = Cache::remember(
            'vadHanderNu:latestLiveEvents:60min',
            60, // 1 min — kortare än response-cache så känns färskt
            function () {
                $now = now();
                return CrimeEvent::whereBetween('parsed_date', [$now->copy()->subHour(), $now])
                    ->orderBy('parsed_date', 'desc')
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
