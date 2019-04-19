<?php

namespace App\Http\Controllers;

use App\CrimeEvent;
use App\CrimeView;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use DB;

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
        } elseif ($what == 'MestVisade') {
            $mostViewed = \App\Helper::getMostViewedEvents(Carbon::now(), 10);
            return $mostViewed->all();
        } elseif ($what == 'MestVisadeNyligen') {
            $mostViewed = \App\Helper::getMostViewedEventsRecently(25, 10);
            return $mostViewed->all();
        } elseif ($what == 'date') {

            $format = 'Y-m-d H:i';
            echo "<br><br>date($format):<br>" . date($format);

            $format = '%A %d %B %Y %H:%M';
            $carbonDate = Carbon::now();
            $carbonDateFormatted = $carbonDate->formatLocalized($format);
            echo "<br><br>carbon::formatLocalized($format):<br>$carbonDateFormatted";

            $strftimestr = strftime($format);
            echo "<br><br>strftime($format):<br>$strftimestr";

            $currentLocal = setlocale(LC_ALL, 0);
            echo "<br><br>setlocale(LC_ALL, 0):<br>";
            var_dump($currentLocal);

            // "Locale" fanns inte på DO/Dokku
            // $currentLocal = \Locale::getDefault();
            // echo "<br><br>$currentLocal:<br>$currentLocal";
        } elseif ($what == 'urls') {

            echo "
            <head>
                <meta charset='utf-8'>
            </head>
            ";

            $delimiter = '-';
            $str = '/händelser/stockholms län/vägen gränden 123/ABCÅÄÖ';

            if ($request->get('url')) {
                $str = $request->get('url');
            }

            echo "<br><br>str innan: $str";
            $clean = $str;

            $clean = mb_strtolower($clean);

            setlocale(LC_ALL, 'en_US');

            $clean = iconv('UTF-8', 'ASCII//TRANSLIT', $clean);
            echo "<br><br>str efter iconv:<br>$clean";

            $clean = preg_replace("![^a-zA-Z0-9/_|+ -]!", '', $clean);
            echo "<br><br>str efter preg_replace:<br>$clean";

            #$clean = strtolower(trim($clean, '-'));
            #echo "<br><br>str efter strtolower:<br>$clean";

            $clean = preg_replace("![/_|+ -]+!", $delimiter, $clean);
            echo "<br><br>str efter preg_replace:<br>$clean";
            setlocale(LC_ALL, 'sv_SE', 'sv_SE.utf8');
        } elseif ($what == 'cache') {
            $date = \App\Helper::getdateFromDateSlug(null);

            $numTimesToTest = 10;

            for ($i = 0; $i < $numTimesToTest; $i++) {
                \Debugbar::startMeasure('cacheTest', 'Hämta händelser, utan cache');
                $events = $this->getEventsForTodayMaybeCached($date, 3, false);
                \Debugbar::stopMeasure('cacheTest');
            }

            // \Debugbar::startMeasure('cacheTest', 'Hämta händelser, med cache');
            for ($i = 0; $i < $numTimesToTest; $i++) {
                \Debugbar::startMeasure('cacheTest', 'Hämta händelser, med cache');
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
