<?php

namespace App;

use DB;
use Illuminate\Support\Facades\Cache;

class Helper
{

    /**
     * Get chart image src for lan stats
     */
    public static function getStatsImageChartUrl($lan)
    {

        if ($lan == "home") {
            $stats = self::getHomeStats($lan);
        } else {
            $stats = self::getLanStats($lan);
        }

        $chartImgUrl = 'https://chart.googleapis.com/chart?';
        // Visible Axes chxt https://developers.google.com/chart/image/docs/gallery/bar_charts#axis_type
        $chartImgUrl .= 'chxt=x,y';
        // Chart Types (cht). bvs = Vertical bar chart with stacked bars.
        $chartImgUrl .= '&cht=bvg';
        // bar color
        $chartImgUrl .= '&chco=76A4FB';
        // size
        $chartImgUrl .= '&chs=400x150';
        // Data for almost all charts is sent using the chd parameter. 0-100 when using t:n,n,n
        // https://developers.google.com/chart/image/docs/data_formats#text
        // comma separated list of values, %1$s
        $chartImgUrl .= '&chd=t:%1$s';
        // Custom Axis Labels chxl
        // https://developers.google.com/chart/image/docs/chart_params#axis_labels
        // piped | separated values, like "|Jan|Feb|Mar|Apr|May" as %2$s
        $chartImgUrl .= '&chxl=0:|%2$s';
        // min, max values
        $chartImgUrl .= '&chds=%3$s,%4$s';
        // chxr, custom numeric range, other wise 0- 100
        $chartImgUrl .= '&chxr=1,%3$s,%4$s';
        // Bar Width and Spacing chbh
        // https://developers.google.com/chart/image/docs/gallery/bar_charts#chbh
        $chartImgUrl .= '&chbh=a';
        // transparent background
        $chartImgUrl .= '&chf=bg,s,FFFFFF00';

        $chd = "";
        $chxl = "";
        $minValue = 0;
        $maxValue = 0;

        foreach ($stats["numEventsPerDay"] as $statRow) {
            $date = strtotime($statRow->YMD);
            $date = strftime("%d", $date);

            $chd .= $statRow->count . ",";
            $chxl .= $date . "|";
            $maxValue = max($maxValue, $statRow->count);
        }

        $chd = trim($chd, ',');
        $chxl = trim($chxl, '|');

        $chartImgUrl = sprintf($chartImgUrl, $chd, $chxl, $minValue, $maxValue);

        return $chartImgUrl;
    }

    /**
     * Get stats for a lan
     */
    public static function getLanStats($lan)
    {
        $stats = [];

        $stats["numEventsPerDay"] = DB::table('crime_events')
                       ->select(DB::raw('date_format(created_at, "%Y-%m-%d") as YMD'), DB::raw('count(*) AS count'))
                       ->where('administrative_area_level_1', $lan)
                       ->groupBy('YMD')
                       ->orderBy('YMD', 'desc')
                       ->limit(14)
                       ->get();
        return $stats;
    }

    /**
     * Get stats for all lans
     */
    public static function getHomeStats($lan)
    {
        $stats = [];

        $stats["numEventsPerDay"] = DB::table('crime_events')
                       ->select(DB::raw('date_format(created_at, "%Y-%m-%d") as YMD'), DB::raw('count(*) AS count'))
                       // ->where('administrative_area_level_1', $lan)
                       ->groupBy('YMD')
                       ->orderBy('YMD', 'desc')
                       ->limit(14)
                       ->get();
        return $stats;
    }

    public static function getAllLan()
    {

        $minutes = 10;

        $lan = Cache::remember('getAllLan', $minutes, function () {
            $lan = DB::table('crime_events')
                ->select("administrative_area_level_1")
                ->groupBy('administrative_area_level_1')
                ->orderBy('administrative_area_level_1', 'asc')
                ->where('administrative_area_level_1', "!=", "")
                ->get();

            return $lan;
        });

        return $lan;
    }

    /**
     * @param string $string
     * @param string|null $allowable_tags
     * @return string
     */
    public static function stripTagsWithWhitespace($string, $allowable_tags = null)
    {
        $string = str_replace('<', ' <', $string);
        $string = strip_tags($string, $allowable_tags);
        $string = str_replace('  ', ' ', $string);
        $string = trim($string);

        return $string;
    }

    // from http://cubiq.org/the-perfect-php-clean-url-generator
    // @TODO: put in global helper
    public static function toAscii($str, $replace = array(), $delimiter = '-')
    {
        if (! empty($replace)) {
            $str = str_replace((array)$replace, ' ', $str);
        }

        $clean = iconv('UTF-8', 'ASCII//TRANSLIT', $str);
        $clean = preg_replace("![^a-zA-Z0-9/_|+ -]!", '', $clean);
        $clean = strtolower(trim($clean, '-'));
        $clean = preg_replace("![/_|+ -]+!", $delimiter, $clean);

        return $clean;
    }

}
