<?php

namespace App;

use DB;

class Helper
{

    /**
     * Get stats for a lan
     */
    public static function getLanStats($lan) {
        $stats = [];

        $stats["numEventsPerWeek"] = DB::table('crime_events')
                       ->select(DB::raw('date_format(created_at, "%Y-%m-%d") as YMD'), DB::raw('count(*) AS count') )
                       ->where('administrative_area_level_1', $lan)
                       ->groupBy('YMD')
                       ->orderBy('YMD', 'desc')
                       ->limit(7)
                       ->get();
        return $stats;
    }

    public static function getAllLan()
    {

    	$lan = DB::table('crime_events')
                ->select("administrative_area_level_1")
                ->groupBy('administrative_area_level_1')
                ->orderBy('administrative_area_level_1', 'asc')
                ->where('administrative_area_level_1', "!=", "")
                ->get();

       return $lan;

    }

    /**
     * @param string $string
     * @param string|null $allowable_tags
     * @return string
     */
    public static function strip_tags_with_whitespace($string, $allowable_tags = null)
    {
        $string = str_replace('<', ' <', $string);
        $string = strip_tags($string, $allowable_tags);
        $string = str_replace('  ', ' ', $string);
        $string = trim($string);

        return $string;
    }

}
