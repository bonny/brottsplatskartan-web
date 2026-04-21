<?php

namespace App\Http\Controllers;

use App\Helper;
use Creitive\Breadcrumbs\Breadcrumbs;
use Illuminate\Support\Facades\DB;

class StatisticsController extends Controller
{
    public function index()
    {
        $breadcrumbs = new Breadcrumbs();
        $breadcrumbs->setDivider('›');
        $breadcrumbs->addCrumb('Hem', '/');
        $breadcrumbs->addCrumb('Statistik', '/statistik');

        $chart14d = Helper::getStatsChartHtml('home');
        $topCrimeTypes = Helper::getTopCrimeTypes(7, 10);
        $topDays = Helper::getTopDays(5);
        $lanTopList = Helper::getLanTopList(21);
        $totalEvents = \Cache::remember('stats:totalEvents', HOUR_IN_SECONDS, function () {
            return DB::table('crime_events')->count();
        });

        $pageTitle = 'Brottsstatistik för Sverige';
        $canonicalLink = url('/statistik');
        $pageMetaDescription = 'Aktuell brottsstatistik från hela Sverige. Antal polishändelser per dag, topp 10 brottstyper senaste veckan och länstopplistor.';

        return view('statistik', compact(
            'breadcrumbs',
            'chart14d',
            'topCrimeTypes',
            'topDays',
            'lanTopList',
            'totalEvents',
            'pageTitle',
            'canonicalLink',
            'pageMetaDescription',
        ));
    }
}
