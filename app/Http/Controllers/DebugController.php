<?php

namespace App\Http\Controllers;

use App\CrimeEvent;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;

/**
 * Debug
 * URL är t.ex.
 * https://brottsplatskartan.localhost/debug/phpinfo
 */
class DebugController extends Controller
{
    /**
     * Debuga lite random saker.
     *
     * @param Request $request Request.
     * @param string  $what    Vad som ska debugas/testas.
     *
     * @return void
     */
    public function debug(Request $request, $what = null)
    {
        if ($what == 'phpinfo') {
            phpinfo();
        } elseif ($what == 'cache') {
            $date = \App\Helper::getdateFromDateSlug(null);

            $numTimesToTest = 10;

            for ($i = 0; $i < $numTimesToTest; $i++) {
                \Debugbar::startMeasure('cacheTest', 'Hämta händelser, utan cache');
                $events = $this->getEventsForTodayMaybeCached($date, 3, false);
                \Debugbar::stopMeasure('cacheTest');
            }

            \Debugbar::startMeasure('cacheTest', 'Hämta händelser, med cache');
            for ($i = 0; $i < $numTimesToTest; $i++) {
                \Debugbar::startMeasure('cacheTest', 'Hämta händelser, utan cache');
                $events = $this->getEventsForTodayMaybeCached($date, 3, true);
                \Debugbar::stopMeasure('cacheTest');
            }

            // \Debugbar::info($events);

        }
    }

    private function getEventsForTodayMaybeCached($date, $daysBack, $useCache)
    {
        $cacheKey = 'getEventsForToday:date:' . $date['date']->format('Y-m-d') . ':daysback:' . $daysBack;

        if ($useCache) {
            $events = Cache::remember(
                $cacheKey,
                5,
                function () use ($date, $daysBack) {
                    echo "<br>get cached";
                    $events = $this->getEventsForToday($date, $daysBack);
                    return $events;
                }
            );
        } else {
            echo "<br>get non cached";
            $events = $this->getEventsForToday($date, $daysBack);
        }

        return $events;
    }

    /**
     * Hämta händelser för idag.
     *
     * @param Carbon  $date     Datum.
     * @param integer $daysBack Dagar.
     *
     * @return Collection       Grejjer.
     */
    private function getEventsForToday($date, $daysBack = 3) {
        $events = CrimeEvent::
            whereDate('created_at', '<=', $date['date']->format('Y-m-d'))
            ->whereDate('created_at', '>=', $date['date']->copy()->subDays($daysBack)->format('Y-m-d'))
            ->orderBy("created_at", "desc")
            ->with('locations')
            ->limit(500)
            ->get();

        return $events;
    }
}
