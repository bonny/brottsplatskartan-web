<?php

namespace App\Http\Controllers;

use App\CrimeEvent;
use App\Services\GeokodHalsa;
use App\Services\Jobbstatus;
use Carbon\Carbon;
use Creitive\Breadcrumbs\Breadcrumbs;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Statussida (/status): hur importen och datakällorna mår — senaste
 * körningar, händelser per dag, geokodningens kvalitet. Publik men noindex.
 */
class StatusController extends Controller
{
    private const DAGAR = 30;

    public function index(GeokodHalsa $geokodHalsa, Jobbstatus $jobbstatus)
    {
        $breadcrumbs = new Breadcrumbs();
        $breadcrumbs->setDivider('›');
        $breadcrumbs->addCrumb('Hem', '/');
        $breadcrumbs->addCrumb('Status', '/status');

        $data = Cache::remember('statussida:v1', 5 * 60, function () use ($geokodHalsa) {
            $fran = Carbon::today()->subDays(self::DAGAR - 1);

            return [
                'beraknad' => Carbon::now(),
                'handelserPerDag' => DB::table('crime_events')
                    ->where('is_public', 1)
                    ->where('created_at', '>=', $fran)
                    ->selectRaw('DATE(created_at) AS YMD, COUNT(*) AS count')
                    ->groupBy('YMD')
                    ->get(),
                'nyhetskopplingarPerDag' => DB::table('crime_event_news')
                    ->where('is_match', 1)
                    ->where('created_at', '>=', $fran)
                    ->selectRaw('DATE(created_at) AS YMD, COUNT(*) AS count')
                    ->groupBy('YMD')
                    ->get(),
                'geokod' => $geokodHalsa->berakna($fran->toDateString(), Carbon::tomorrow()->toDateString()),
                'ogeokodade' => CrimeEvent::where('geocoded', 0)
                    ->where('scanned_for_locations', 1)
                    ->where('created_at', '>', Carbon::now()->subDays(15))
                    ->count(),
                'kallor' => [
                    ['Senaste polishändelse', DB::table('crime_events')->max('created_at'), 6 * 60],
                    ['Senaste nyhetsartikel', DB::table('news_articles')->max('fetched_at'), 60],
                    ['Senaste trafikhändelse (Trafikverket)', DB::table('events')->max('imported_at'), 60],
                    ['Senaste VMA', DB::table('vma_alerts')->max('created_at'), null],
                ],
            ];
        });

        return response()
            ->view('status', $data + [
                'breadcrumbs' => $breadcrumbs,
                'jobb' => $jobbstatus->lista(),
                'dagar' => self::DAGAR,
                'robotsNoindex' => true,
                'pageTitle' => 'Status för Brottsplatskartan',
                'canonicalLink' => url('/status'),
            ])
            ->header('X-Robots-Tag', 'noindex, follow');
    }
}
