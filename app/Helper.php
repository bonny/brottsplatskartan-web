<?php

namespace App;

use DB;

class Helper
{

    public static function getAllLan() {
   	
    	$lan = DB::table('crime_events')
                ->select("administrative_area_level_1")
                ->groupBy('administrative_area_level_1')
                ->orderBy('administrative_area_level_1', 'asc')
                ->where('administrative_area_level_1', "!=", "")
                ->get();

       return $lan;

    }

}