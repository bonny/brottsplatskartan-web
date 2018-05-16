<?php

namespace App;

use Carbon\Carbon;
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
     * used for graph
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
     * Hämta statistik för alla län,
     * ger antal händelser per dag för alla län.
     *
     * @return array med datum => antal.
     */
    public static function getHomeStats($lan)
    {
        $stats = [
            "numEventsPerDay" => null,
        ];

        $cacheKey = "lan-homestats-" . $lan;
        $cacheTTL = 120;
        $dateDaysBack = Carbon::now()->subDays(13)->format('Y-m-d');

        $stats["numEventsPerDay"] = Cache::remember(
            $cacheKey,
            $cacheTTL,
            function () use ($lan, $dateDaysBack) {
                $numEventsPerDay = DB::table('crime_events')
                    ->select(DB::raw('date_format(created_at, "%Y-%m-%d") as YMD'), DB::raw('count(*) AS count'))
                    ->where('created_at', '>', $dateDaysBack)
                    ->groupBy('YMD')
                    ->orderBy('YMD', 'desc')
                    ->get();

                return $numEventsPerDay;
        });

        return $stats;
    }

    public static function getSingleLanWithStats($lanName = null)
    {
        if (!$lanName) {
            return false;
        }

        $lan = self::getAllLanWithStats();

        foreach ($lan as $oneLan) {
            if ($oneLan->administrative_area_level_1 == $lanName) {
                return $oneLan;
            }
        }

        return false;
    }

    public static function getAllLanWithStats()
    {
        $lan = self::getAllLan();

        // Räkna alla händelser i det här länet för en viss period
        $lan = $lan->map(
            function ($item, $key) {
                // DB::enableQueryLog();

                $cacheKey = "lan-stats-today-" . $item->administrative_area_level_1;
                $numEventsToday = Cache::remember(
                    $cacheKey,
                    10,
                    function () use ($item) {
                        $numEventsToday = DB::table('crime_events')
                            ->where('administrative_area_level_1', "=", $item->administrative_area_level_1)
                            ->where('created_at', '>', Carbon::now()->subDays(1))
                            ->count();

                        return $numEventsToday;
                    }
                );

                $cacheKey = "lan-stats-7days-" . $item->administrative_area_level_1;
                $numEvents7Days = Cache::remember(
                    $cacheKey,
                    30,
                    function () use ($item) {
                        $numEvents7Days = DB::table('crime_events')
                            ->where('administrative_area_level_1', "=", $item->administrative_area_level_1)
                            ->where('created_at', '>', Carbon::now()->subDays(7))
                            ->count();

                        return $numEvents7Days;
                    }
                );

                $cacheKey = "lan-stats-30days-" . $item->administrative_area_level_1;
                $numEvents30Days = Cache::remember(
                    $cacheKey,
                    70,
                    function () use ($item) {
                        $numEvents30Days = DB::table('crime_events')
                            ->where('administrative_area_level_1', "=", $item->administrative_area_level_1)
                            ->where('created_at', '>', Carbon::now()->subDays(30))
                            ->count();

                        return $numEvents30Days;
                    }
                );

                $item->numEvents = [
                    "today" => $numEventsToday,
                    "last7days" => $numEvents7Days,
                    "last30days" => $numEvents30Days,
                ];

                return $item;
            }
        );

        return $lan;
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
    public static function toAscii($str, $replace = array(), $delimiter = '-')
    {
        if (!empty($replace)) {
            $str = str_replace((array) $replace, ' ', $str);
        }

        // Switch locale or iconv will convert "ä" to "ae".
        // If we use english "ä" till be "ä".
        setlocale(LC_ALL, 'en_US');

        $clean = iconv('UTF-8', 'ASCII//TRANSLIT', $str);
        $clean = preg_replace("![^a-zA-Z0-9/_|+ -]!", '', $clean);
        $clean = strtolower(trim($clean, '-'));
        $clean = preg_replace("![/_|+ -]+!", $delimiter, $clean);

        // Switch back locale.
        setlocale(LC_ALL, 'sv_SE', 'sv_SE.utf8');

        return $clean;
    }

    public static function makeUrlUsePolisenDomain($url = null)
    {
        if (empty($url)) {
            return $url;
        }

        $polisenDomain = config('app.polisen_domain');

        $orgDomain = 'https://polisen.se';
        $url = str_replace($orgDomain, $polisenDomain, $url);

        // RSS feed links seems to be non-https
        $orgDomain = 'http://polisen.se';
        $url = str_replace($orgDomain, $polisenDomain, $url);

        return $url;
    }

    // Encode a string to URL-safe base64
    public static function encodeBase64UrlSafe($value)
    {
        return str_replace(array('+', '/'), array('-', '_'),
            base64_encode($value));
    }

    // Decode a string from URL-safe base64
    public static function decodeBase64UrlSafe($value)
    {
        return base64_decode(str_replace(array('-', '_'), array('+', '/'),
            $value));
    }

    // Sign a URL with a given crypto key
    // Note that this URL must be properly URL-encoded
    public static function signUrl($myUrlToSign)
    {
        $privateKey = env("GOOGLE_SIGNING_SECRET");

        // parse the url
        $url = parse_url($myUrlToSign);

        $urlPartToSign = $url['path'] . "?" . $url['query'];

        // Decode the private key into its binary format
        $decodedKey = self::decodeBase64UrlSafe($privateKey);

        // Create a signature using the private key and the URL-encoded
        // string using HMAC SHA1. This signature will be binary.
        $signature = hash_hmac("sha1", $urlPartToSign, $decodedKey, true);

        $encodedSignature = self::encodeBase64UrlSafe($signature);

        return $myUrlToSign . "&signature=" . $encodedSignature;
    }

    // echo signUrl("http://maps.google.com/maps/api/geocode/json?address=New+York&sensor=false&client=clientID", 'vNIXE0xscrmjlyV-12Nj_BvUPaw=');

    public static function convertSwedishYearsToEnglish($str)
    {
        $search = ['januari', 'februari', 'mars', 'april', 'maj', 'juni', 'juli', 'augusti', 'september', 'oktober', 'november', 'december'];
        $replace = ['january', 'february', 'march', 'april', 'may', 'june', 'july', 'august', 'september', 'october', 'november', 'december'];

        $str = str_replace($search, $replace, $str);

        return $str;
    }

    /**
     * Return some date info from a string.
     *
     * @param string $monthAndYear Like "15-januari-2018"
     *
     * @return mixed array on success, false on error
     */
    public static function getdateFromDateSlug($monthAndYear)
    {
        $monthAndYear = strtolower($monthAndYear);
        $monthAndYear = str_replace('-', ' ', $monthAndYear);

        $search = ['januari', 'februari', 'mars', 'april', 'maj', 'juni', 'juli', 'augusti', 'september', 'oktober', 'november', 'december'];
        $replace = ['january', 'february', 'march', 'april', 'may', 'june', 'july', 'august', 'september', 'october', 'november', 'december'];

        // Translate swedish months to english months, so we can parse
        $monthAndYearInEnglish = self::convertSwedishYearsToEnglish($monthAndYear);

        try {
            $date = Carbon::parse($monthAndYearInEnglish);
            $year = $date->format('Y');
            $month = $date->format('m');
            $day = $date->format('d');
        } catch (\Exception $e) {
            return false;
        }

        return [
            'date' => $date,
            'monthAndYear' => $monthAndYear,
            'year' => $year,
            'month' => $month,
            'day' => $day,
        ];
    }

    /**
     * Hämta info om antal händelser per dag tidigare än valt datum.
     *
     * @param object $date Carbon Date Object.
     * @param int    $numDays Antal dagar att hämta info för.
     *
     * @return array Array med lite info.
     */
    public static function getPrevDaysNavInfo($date = null, $numDays = 5)
    {
        $dateYmd = $date->format('Y-m-d');
        $cacheKey = "getPrevDaysNavInfo:date:{$dateYmd}:numDays:$numDays";
        $cacheTTL = 15;

        $prevDayEvents = Cache::remember(
            $cacheKey,
            $cacheTTL,
            function () use ($dateYmd, $numDays) {
                $prevDayEvents = CrimeEvent::
                    selectRaw('date(created_at) as dateYMD, count(*) as dateCount')
                    ->where('created_at', '<', $dateYmd)
                    ->groupBy(\DB::raw('dateYMD'))
                    ->orderBy('created_at', 'desc')
                    ->limit($numDays)
                    ->get();

                    return $prevDayEvents;
            }
        );

        return $prevDayEvents;
    }

    /**
     * Hämta info om antal händelser per dag senare än idag.
     *
     * @param object $date Carbon Date Object.
     * @param int    $numDays Antal dagar att hämta info för.
     *
     * @return array Array med lite info.
     */

    public static function getNextDaysNavInfo($date = null, $numDays = 5)
    {

        $dateYmd = $date->format('Y-m-d');
        $dateYmdPlusOneDay = $date->copy()->addDays(1)->format('Y-m-d');
        $cacheKey = "getNextDaysNavInfo:date:{$dateYmd}:numDays:$numDays";
        $cacheTTL = 16;

        $nextDayEvents = Cache::remember(
            $cacheKey,
            $cacheTTL,
            function () use ($dateYmd, $dateYmdPlusOneDay, $numDays) {
                $nextDayEvents = CrimeEvent::
                    selectRaw('date(created_at) as dateYMD, count(*) as dateCount')
                    ->where('created_at', '>', $dateYmdPlusOneDay)
                    ->groupBy(\DB::raw('dateYMD'))
                    ->orderBy('created_at', 'asc')
                    ->limit($numDays)
                    ->get();

                return $nextDayEvents;
            }
        );

        return $nextDayEvents;
    }

    public static function getLanPrevDaysNavInfo($date = null, $lan, $numDays = 5)
    {
        $dateYmd = $date->format('Y-m-d');
        $cacheKey = "getLanPrevDaysNavInfo:date{$dateYmd}:lan:{$lan}:numDays:{$numDays}";
        $cacheTTL = 14;

        $prevDayEvents = Cache::remember(
            $cacheKey,
            $cacheTTL,
            function () use ($date, $lan, $numDays) {
                return self::getLanPrevDaysNavInfoUncached($date, $lan, $numDays);
            }
        );

        return $prevDayEvents;
    }

    public static function getLanPrevDaysNavInfoUncached($date = null, $lan, $numDays = 5)
    {
        $prevDayEvents = CrimeEvent::
            selectRaw('date(created_at) as dateYMD, count(*) as dateCount')
            ->where('created_at', '<', $date->format('Y-m-d'))
            ->where("administrative_area_level_1", $lan)
            ->groupBy(\DB::raw('dateYMD'))
            ->orderBy('created_at', 'desc')
            ->limit($numDays)
            ->get();

        return $prevDayEvents;
    }


    public static function getLanNextDaysNavInfo($date = null, $lan = null, $numDays = 5)
    {
        $dateYmdPlusOneDay = $date->copy()->addDays(1)->format('Y-m-d');

        $dateYmd = $date->format('Y-m-d');
        $cacheKey = "getLanNextDaysNavInfo:date{$dateYmd}:lan:{$lan}:numDays:{$numDays}";
        $cacheTTL = 15;

        $nextDayEvents = Cache::remember(
            $cacheKey,
            $cacheTTL,
            function () use ($date, $lan, $numDays) {
                return self::getLanNextDaysNavInfoUncached($date, $lan, $numDays);
            }
        );

        return $nextDayEvents;
    }

    public static function getLanNextDaysNavInfoUncached($date = null, $lan = null, $numDays = 5)
    {
        $dateYmdPlusOneDay = $date->copy()->addDays(1)->format('Y-m-d');

        $nextDayEvents = CrimeEvent::
            selectRaw('date(created_at) as dateYMD, count(*) as dateCount, 1 as ppp')
            ->where('created_at', '>', $dateYmdPlusOneDay)
            ->where("administrative_area_level_1", $lan)
            ->groupBy(\DB::raw('dateYMD'))
            ->orderBy('created_at', 'asc')
            ->limit($numDays)
            ->get();

        return $nextDayEvents;
    }

    public static function getOrter()
    {
        $orter = \DB::table('crime_events')
            ->select("parsed_title_location")
            ->where('parsed_title_location', "!=", "")
            ->orderBy('parsed_title_location', 'asc')
            ->distinct()
            ->get();
        return $orter;
    }

    public static function getLanSlugsToNameArray()
    {
        $arr = [
            'blekinge-lan' => 'Blekinge län',
            'blekinge' => 'Blekinge län',
            'dalarnas-lan' => 'Dalarnas län',
            'dalarna' => 'Dalarnas län',
            'gotlands-lan' => 'Gotlands län',
            'gotland' => 'Gotlands län',
            'gavleborgs-lan' => 'Gävleborgs län',
            'gavleborg' => 'Gävleborgs län',
            'hallands-lan' => 'Hallands län',
            'halland' => 'Hallands län',
            'jamtlands-lan' => 'Jämtlands län',
            'jamtland' => 'Jämtlands län',
            'jonkopings-lan' => 'Jönköpings län',
            'kalmar-lan' => 'Kalmar län',
            'kronobergs-lan' => 'Kronobergs län',
            'kronoberg' => 'Kronobergs län',
            'norrbottens-lan' => 'Norrbottens län',
            'norrbotten' => 'Norrbottens län',
            'skane-lan' => 'Skåne län',
            'skane' => 'Skåne län',
            'stockholms-lan' => 'Stockholms län',
            'sodermanlands-lan' => 'Södermanlands län',
            'sodermanland' => 'Södermanlands län',
            'uppsala-lan' => 'Uppsala län',
            'varmlands-lan' => 'Värmlands län',
            'varmland' => 'Värmlands län',
            'vasterbottens-lan' => 'Västerbottens län',
            'vasterbotten' => 'Västerbottens län',
            'vasternorrlands-lan' => 'Västernorrlands län',
            'vasternorrland' => 'Västernorrlands län',
            'vastmanlands-lan' => 'Västmanlands län',
            'vastmanland' => 'Västmanlands län',
            'vastra-gotalands-lan' => 'Västra Götalands län ',
            'vastra-gotaland' => 'Västra Götalands län ',
            'orebro-lan' => 'Örebro län',
            'ostergotlands-lan' => 'Östergötlands län',
            'ostergotland' => 'Östergötlands län',
        ];

        return $arr;
    }
}
