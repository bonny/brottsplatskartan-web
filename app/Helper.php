<?php

namespace App;

use App\Models\CrimeView;
use Carbon\Carbon;
use DB;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use App\Models\VMAAlert;
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
            
            // Endast dag.
            $dateObj = new \DateTime($statRow->YMD);
            $date = $dateObj->format('d');

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
        $stats = Cache::remember('getLanStats', MINUTE_IN_SECONDS * 10, function() use ($lan) {
            $stats = [];
            
            $stats["numEventsPerDay"] = DB::table('crime_events')
                ->select(
                    DB::raw('date_format(created_at, "%Y-%m-%d") as YMD'),
                    DB::raw('count(*) AS count')
                )
                ->where('administrative_area_level_1', $lan)
                ->groupBy('YMD')
                ->orderBy('YMD', 'desc')
                ->limit(14)
                ->get();

            return $stats;
        });

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
            "numEventsPerDay" => null
        ];

        $cacheKey = "lan-homestats-" . $lan;
        $cacheTTL = 120 * 60;
        $dateDaysBack = Carbon::now()
            ->subDays(13)
            ->format('Y-m-d');

        $stats["numEventsPerDay"] = Cache::remember(
            $cacheKey,
            $cacheTTL,
            function () use ($dateDaysBack) {
                $numEventsPerDay = DB::table('crime_events')
                    ->select(
                        DB::raw('date_format(created_at, "%Y-%m-%d") as YMD'),
                        DB::raw('count(*) AS count')
                    )
                    ->where('created_at', '>', $dateDaysBack)
                    ->groupBy('YMD')
                    ->orderBy('YMD', 'desc')
                    ->get();

                return $numEventsPerDay;
            }
        );

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
        $lan = $lan->map(function ($item, $key) {
            // DB::enableQueryLog();

            $cacheKey = "lan-stats-today-" . $item->administrative_area_level_1;
            $numEventsToday = Cache::remember(
                $cacheKey,
                10 * 60,
                function () use ($item) {
                    $numEventsToday = DB::table('crime_events')
                        ->where(
                            'administrative_area_level_1',
                            "=",
                            $item->administrative_area_level_1
                        )
                        ->where('created_at', '>', Carbon::now()->subDays(1))
                        ->count();

                    return $numEventsToday;
                }
            );

            $cacheKey = "lan-stats-7days-" . $item->administrative_area_level_1;
            $numEvents7Days = Cache::remember(
                $cacheKey,
                30 * 60,
                function () use ($item) {
                    $numEvents7Days = DB::table('crime_events')
                        ->where(
                            'administrative_area_level_1',
                            "=",
                            $item->administrative_area_level_1
                        )
                        ->where('created_at', '>', Carbon::now()->subDays(7))
                        ->count();

                    return $numEvents7Days;
                }
            );

            $cacheKey =
                "lan-stats-30days-" . $item->administrative_area_level_1;
            $numEvents30Days = Cache::remember(
                $cacheKey,
                70 * 60,
                function () use ($item) {
                    $numEvents30Days = DB::table('crime_events')
                        ->where(
                            'administrative_area_level_1',
                            "=",
                            $item->administrative_area_level_1
                        )
                        ->where('created_at', '>', Carbon::now()->subDays(30))
                        ->count();

                    return $numEvents30Days;
                }
            );

            $item->numEvents = [
                "today" => $numEventsToday,
                "last7days" => $numEvents7Days,
                "last30days" => $numEvents30Days
            ];

            return $item;
        });

        return $lan;
    }

    public static function getAllLan()
    {
        $minutes = 10 * 60;

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
    public static function stripTagsWithWhitespace(
        $string,
        $allowable_tags = null
    ) {
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
        return str_replace(
            array('+', '/'),
            array('-', '_'),
            base64_encode($value)
        );
    }

    // Decode a string from URL-safe base64
    public static function decodeBase64UrlSafe($value)
    {
        return base64_decode(
            str_replace(array('-', '_'), array('+', '/'), $value)
        );
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
        $search = [
            'januari',
            'februari',
            'mars',
            'april',
            'maj',
            'juni',
            'juli',
            'augusti',
            'september',
            'oktober',
            'november',
            'december'
        ];
        $replace = [
            'january',
            'february',
            'march',
            'april',
            'may',
            'june',
            'july',
            'august',
            'september',
            'october',
            'november',
            'december'
        ];

        $str = str_replace($search, $replace, $str);

        return $str;
    }

    /**
     * Return some date info from a string.
     *
     * @param string|null $monthAndYear Like "15-januari-2018"
     *
     * @return mixed array on success, false on error
     */
    public static function getdateFromDateSlug($monthAndYear)
    {
        $monthAndYear = strtolower($monthAndYear);
        $monthAndYear = str_replace('-', ' ', $monthAndYear);

        $search = [
            'januari',
            'februari',
            'mars',
            'april',
            'maj',
            'juni',
            'juli',
            'augusti',
            'september',
            'oktober',
            'november',
            'december'
        ];
        $replace = [
            'january',
            'february',
            'march',
            'april',
            'may',
            'june',
            'july',
            'august',
            'september',
            'october',
            'november',
            'december'
        ];

        // Translate swedish months to english months, so we can parse
        $monthAndYearInEnglish = self::convertSwedishYearsToEnglish(
            $monthAndYear
        );

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
            'day' => $day
        ];
    }

    /**
     * Hämta info om antal händelser per dag tidigare än valt datum.
     *
     * @param object $date Carbon Date Object.
     * @param int    $numDays Antal dagar att hämta info för.
     *
     * @return Collection Array med lite info.
     */
    public static function getPrevDaysNavInfo($date = null, $numDays = 5)
    {
        $dateYmd = $date->format('Y-m-d');
        $cacheKey = "getPrevDaysNavInfo:date:{$dateYmd}:numDays:$numDays";
        $cacheTTL = 15 * 60;

        $prevDayEvents = Cache::remember($cacheKey, $cacheTTL, function () use (
            $dateYmd,
            $numDays
        ) {
            $prevDayEvents = CrimeEvent::selectRaw(
                'date_created_at as dateYMD, count(*) as dateCount'
            )
                ->where('created_at', '<', $dateYmd)
                ->groupBy(\DB::raw('dateYMD'))
                ->orderBy('dateYMD', 'desc')
                ->limit($numDays)
                ->get();

            return $prevDayEvents;
        });

        return $prevDayEvents;
    }

    /**
     * Hämta info om antal händelser per dag senare än idag.
     *
     * @param object $date Carbon Date Object.
     * @param int    $numDays Antal dagar att hämta info för.
     *
     * @return Collection Array med lite info.
     */

    public static function getNextDaysNavInfo($date = null, $numDays = 5)
    {
        $dateYmd = $date->format('Y-m-d');
        $dateYmdPlusOneDay = $date
            ->copy()
            ->addDays(1)
            ->format('Y-m-d');
        $cacheKey = "getNextDaysNavInfo:date:{$dateYmd}:numDays:$numDays";
        $cacheTTL = 16 * 60;

        $nextDayEvents = Cache::remember($cacheKey, $cacheTTL, function () use (
            $dateYmdPlusOneDay,
            $numDays
        ) {
            $nextDayEvents = CrimeEvent::selectRaw(
                'date_created_at as dateYMD, count(*) as dateCount'
            )
                ->where('created_at', '>', $dateYmdPlusOneDay)
                ->groupBy(\DB::raw('dateYMD'))
                ->orderBy('dateYMD', 'asc')
                ->limit($numDays)
                ->get();

            return $nextDayEvents;
        });

        return $nextDayEvents;
    }

    public static function getLanPrevDaysNavInfo(
        $date = null,
        $lan = '',
        $numDays = 5
    ) {
        $dateYmd = $date->format('Y-m-d');
        $cacheKey = "getLanPrevDaysNavInfo:date{$dateYmd}:lan:{$lan}:numDays:{$numDays}";
        $cacheTTL = 14 * 60;

        $prevDayEvents = Cache::remember($cacheKey, $cacheTTL, function () use (
            $date,
            $lan,
            $numDays
        ) {
            return self::getLanPrevDaysNavInfoUncached($date, $lan, $numDays);
        });

        return $prevDayEvents;
    }

    public static function getLanPrevDaysNavInfoUncached(
        $date = null,
        $lan = '',
        $numDays = 5
    ) {
        $prevDayEvents = CrimeEvent::selectRaw(
            'date_created_at as dateYMD, count(*) as dateCount'
        )
            ->where('created_at', '<', $date->format('Y-m-d'))
            ->where("administrative_area_level_1", $lan)
            ->groupBy(\DB::raw('dateYMD'))
            ->orderBy('dateYMD', 'desc')
            ->limit($numDays)
            ->get();

        return $prevDayEvents;
    }

    public static function getLanNextDaysNavInfo(
        $date = null,
        $lan = null,
        $numDays = 5
    ) {
        $dateYmdPlusOneDay = $date
            ->copy()
            ->addDays(1)
            ->format('Y-m-d');

        $dateYmd = $date->format('Y-m-d');
        $cacheKey = "getLanNextDaysNavInfo:date{$dateYmd}:lan:{$lan}:numDays:{$numDays}";
        $cacheTTL = 15 * 60;

        $nextDayEvents = Cache::remember($cacheKey, $cacheTTL, function () use (
            $date,
            $lan,
            $numDays
        ) {
            return self::getLanNextDaysNavInfoUncached($date, $lan, $numDays);
        });

        return $nextDayEvents;
    }

    public static function getLanNextDaysNavInfoUncached(
        $date = null,
        $lan = null,
        $numDays = 5
    ) {
        $dateYmdPlusOneDay = $date
            ->copy()
            ->addDays(1)
            ->format('Y-m-d');

        $nextDayEvents = CrimeEvent::selectRaw(
            'date_created_at as dateYMD, count(*) as dateCount, 1 as ppp'
        )
            ->where('created_at', '>', $dateYmdPlusOneDay)
            ->where("administrative_area_level_1", $lan)
            ->groupBy(\DB::raw('dateYMD'))
            ->orderBy('dateYMD', 'asc')
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

    /**
     * Hämta län i lite vettig collection-format.
     *
     * @return collection Collection med län.
     */
    public static function getLans()
    {
        $lans = [
            [
                "name" => "Blekinge län",
                "shortName" => "Blekinge"
            ],
            [
                "name" => "Dalarnas län",
                "shortName" => "Dalarna"
            ],
            [
                "name" => "Gävleborgs län",
                "shortName" => "Gävleborg"
            ],
            [
                "name" => "Gotlands län",
                "shortName" => "Gotland"
            ],
            [
                "name" => "Hallands län",
                "shortName" => "Halland"
            ],
            [
                "name" => "Jämtlands län",
                "shortName" => "Jämtland"
            ],
            [
                "name" => "Jönköpings län",
                "shortName" => "Jönköping"
            ],
            [
                "name" => "Kalmar län",
                "shortName" => "Kalmar"
            ],
            [
                "name" => "Kronobergs län",
                "shortName" => "Kronoberg"
            ],
            [
                "name" => "Norrbottens län",
                "shortName" => "Norrbotten"
            ],
            [
                "name" => "Örebro län",
                "shortName" => "Örebro"
            ],
            [
                "name" => "Östergötlands län",
                "shortName" => "Östergötland"
            ],
            [
                "name" => "Skåne län",
                "shortName" => "Skåne"
            ],
            [
                "name" => "Södermanland and Uppland Södermanlands län",
                "shortName" => "Södermanland"
            ],
            [
                "name" => "Stockholms län",
                "shortName" => "Stockholm"
            ],
            [
                "name" => "Uppsala län",
                "shortName" => "Uppsala"
            ],
            [
                "name" => "Värmlands län",
                "shortName" => "Värmland"
            ],
            [
                "name" => "Västerbottens län",
                "shortName" => "Västerbotten"
            ],
            [
                "name" => "Västernorrlands län",
                "shortName" => "Västernorrland"
            ],
            [
                "name" => "Västmanlands län",
                "shortName" => "Västmanland"
            ],
            [
                "name" => "Västra Götalands län",
                "shortName" => "Västra Götaland"
            ]
        ];

        $lans = collect($lans);

        return $lans;
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
            'vastra-gotalands-lan' => 'Västra Götalands län',
            'vastra-gotaland' => 'Västra Götalands län',
            'orebro-lan' => 'Örebro län',
            'ostergotlands-lan' => 'Östergötlands län',
            'ostergotland' => 'Östergötlands län'
        ];

        return $arr;
    }

    /**
     * Konverterar från t.ex.
     * Västra Götalands län -> Västra Götaland
     * Uppsala län -> Uppsla
     * Stockholms län -> Stockholm
     * Skåne län -> Skåne
     *
     * @param string $lan Långt länsnamn, t.ex. "Stockholm län"
     *
     * @return string Kortat länsnamn, t.ex. "Stockhol"
     */
    public static function lanLongNameToShortName($lan)
    {
        $arr = [
            'Blekinge län' => 'Blekinge',
            'Dalarnas län' => 'Dalarna',
            'Gävleborgs län' => 'Gävleborg',
            'Gotlands län' => 'Gotland',
            'Hallands län' => 'Halland',
            'Jämtlands län' => 'Jämtland',
            'Jönköpings län' => 'Jönköping',
            'Kalmar län' => 'Kalmar',
            'Kronobergs län' => 'Kronoberg',
            'Norrbottens län' => 'Norrbotten',
            'Örebro län' => 'Örebro',
            'Östergötlands län' => 'Östergötland',
            'Skåne län' => 'Skåne',
            'Södermanlands län' => 'Södermanland',
            'Stockholms län' => 'Stockholm',
            'Uppsala län' => 'Uppsala',
            'Värmlands län' => 'Värmland',
            'Västerbottens län' => 'Västerbotten',
            'Västernorrlands län' => 'Västernorrland',
            'Västmanlands län' => 'Västmanland',
            'Västra Götalands län' => 'Västra Götaland'
        ];

        if (isset($arr[$lan])) {
            $lan = $arr[$lan];
        }

        return $lan;
    }

    /**
     * Get a center latitude,longitude from an array of like geopoints
     * For Example:
     * $data = array
     * (
     *   0 = > array(45.849382, 76.322333),
     *   1 = > array(45.843543, 75.324143),
     *   2 = > array(45.765744, 76.543223),
     *   3 = > array(45.784234, 74.542335)
     * );
     *
     * From
     * https://stackoverflow.com/questions/6671183/calculate-the-center-point-of-multiple-latitude-longitude-coordinate-pairs
     *
     * @param array $data 2 dimensional array of latitudes and longitudes.
     * @return array
     */
    // public static function getCenterFromDegrees($data)
    // {
    //     if (!is_array($data)) {
    //         return false;
    //     }

    //     $num_coords = count($data);

    //     $X = 0.0;
    //     $Y = 0.0;
    //     $Z = 0.0;

    //     foreach ($data as $coord) {
    //         $lat = ($coord[0] * pi()) / 180;
    //         $lon = ($coord[1] * pi()) / 180;

    //         $a = cos($lat) * cos($lon);
    //         $b = cos($lat) * sin($lon);
    //         $c = sin($lat);

    //         $X += $a;
    //         $Y += $b;
    //         $Z += $c;
    //     }

    //     $X /= $num_coords;
    //     $Y /= $num_coords;
    //     $Z /= $num_coords;

    //     $lon = atan2($Y, $X);
    //     $hyp = sqrt($X * $X + $Y * $Y);
    //     $lat = atan2($Z, $hyp);

    //     return array(($lat * 180) / pi(), ($lon * 180) / pi());
    // }

    /**
     * [getPoliceStations description]
     * @return Collection
     */
    public static function getPoliceStations()
    {
        $APIURL = 'https://polisen.se/api/policestations';
        $APIURL = \App\Helper::makeUrlUsePolisenDomain($APIURL);

        // If polisen.se down then exception is thrown.
        try {
            $locations = json_decode(file_get_contents($APIURL));
        } catch (\Exception $e) {
            $locations = collect();
        }

        $locationsCollection = collect($locations);

        // "blekinge-lan" => "Blekinge län" osv.
        $slugsToNames = \App\Helper::getLanSlugsToNameArray();

        /*

        Alla URLar verkar bestå av län/plats
        Förutom stockholm som har en del till (stockholm-syd)

        gavleborg/bollnas/
        gavleborg/gavle/

        kalmar-lan/borgholm/
        kalmar-lan/emmaboda/

        vastra-gotaland/bollebygd/
        vastra-gotaland/boras/

        stockholms-lan/stockholm-syd/botkyrka/
        stockholms-lan/stockholm-nord/danderyd/
        stockholms-lan/stockholm-nord/ekero/
        stockholms-lan/stockholm-syd/farsta/

        */

        // Skapa ny collection där polisstationerna är grupperade på län.
        $locationsByPlace = $locationsCollection->groupBy(function (
            $item,
            $key
        ) use ($slugsToNames) {
            $place = $item->Url;
            $place = str_replace(
                'https://polisen.se/kontakt/polisstationer/',
                '',
                $place
            );
            $place = trim($place, '/');
            $placeParts = explode('/', $place);
            $placeLan = $placeParts[0];

            if (isset($slugsToNames[$placeLan])) {
                $placeLan = $slugsToNames[$placeLan];
            }

            return $placeLan;
        });

        // Sortera listan efter länsnamn.
        $locationsByPlace = $locationsByPlace->sortKeys();

        // Lägg län en nivå ner i arrayen och platserna ett steg ner + lägg på shortname för län.
        $locationsByPlace = $locationsByPlace->map(function ($item, $key) {
            return [
                'lanName' => $key,
                'lanShortName' => self::lanLongNameToShortName($key),
                'policeStations' => $item
            ];
        });

        return $locationsByPlace;
    }

    /**
     * [getPoliceStationsCached description]
     * @return Collection
     */
    public static function getPoliceStationsCached()
    {
        // return \App\Helper::getPoliceStations();
        $locations = Cache::remember(
            'PoliceStationsLocations2',
            60 * 2 * 60,
            function () {
                return \App\Helper::getPoliceStations();
            }
        );

        return $locations;
    }

    public static function getRelatedLinks($place = null, $lan = null)
    {
        $place = is_string($place) ? mb_strtolower($place) : $place;
        $lan = is_string($lan) ? mb_strtolower($lan) : $lan;

        $relatedLinks = RelatedLinks::where(['place' => $place, 'lan' => $lan])
            ->orderBy('prio', 'desc')
            ->get();

        return $relatedLinks;
    }

    /**
     * Hämta de mest visade händelserna för ett datum och en dag bakåt,
     * dvs typ för en viss dag.
     *
     * Denna funktion visar inte så bra saker som hänt nyligen/är poppis
     * "just nu" för en grej som pågått en hel dag kan ha fått fler totala
     * visningar än en grej som fått 1000 visningar senaste minuten.
     *
     * @param  Carbon  $date  [description]
     * @param  integer $limit [description]
     * @return Collection          [description]
     */
    public static function getMostViewedEvents(
        Carbon $date = null,
        int $limit = 10
    ) {
        if (!$date) {
            $date = Carbon::now();
        }

        $now = $date->copy()->format('Y-m-d');
        $tomorrow = $date
            ->copy()
            ->modify('+1 day')
            ->format('Y-m-d');
        $yesterday = $date
            ->copy()
            ->subDays(1)
            ->format('Y-m-d');

        $cacheKey = "getMostViewedEvents:V1:D{$now}:L{$limit}";
        $cacheTTL = 27 * 60;

        $mostViewed = Cache::remember($cacheKey, $cacheTTL, function () use (
            $tomorrow,
            $yesterday,
            $limit
        ) {
            /**
             * explain before fix: no key, 100000 rows
             * explain after adding index to table
             */
            $mostViewed = CrimeView::select(
                DB::raw('count(*) as views'),
                'crime_event_id',
                DB::raw('DATE_FORMAT(created_at, "%Y-%m-%d") AS createdYMD')
            )
                ->where('created_at', '<', $tomorrow)
                ->where('created_at', '>', $yesterday)
                ->groupBy('createdYMD', 'crime_event_id')
                ->orderBy('views', 'desc')
                ->limit($limit)
                ->with('CrimeEvent', 'CrimeEvent.locations')
                ->get();

            return $mostViewed;
        });

        return $mostViewed;
    }

    /**
     * Hämta de mest visade händelserna för n minuter bakåt.
     * Används för att visa händelser som är populära "just nu".
     *
     * @param  int $minutes Antal minuter bakåt att hämta visningar för.
     * @param  int $limit Max antal händelser att hämta.
     * @return Collection         [description]
     */
    public static function getMostViewedEventsRecently(
        $minutes = 10,
        $limit = 10
    ) {
        $cacheKey = "getMostViewedEventsRecently:v1:M{$minutes}:L{$limit}";
        $cacheTTL = 2 * 60;

        $mostViewed = Cache::remember($cacheKey, $cacheTTL, function () use (
            $minutes,
            $limit
        ) {
            $mostViewed = CrimeView::select(
                DB::raw('count(*) as views'),
                'crime_event_id',
                DB::raw('DATE_FORMAT(created_at, "%Y-%m-%d") AS createdYMD')
            )
                ->whereRaw(
                    'created_at >= DATE_ADD(NOW(), INTERVAL -? MINUTE)',
                    [$minutes]
                )
                ->groupBy('createdYMD', 'crime_event_id')
                ->orderBy('views', 'desc')
                ->limit($limit)
                ->with('CrimeEvent', 'CrimeEvent.locations')
                ->get();

            // Can't get eager loading to work all way...
            $mostViewed->load('CrimeEvent.locations');

            return $mostViewed;
        });

        return $mostViewed;
    }

    /**
     * Hämta de senaste händelserna.
     *
     * @param  integer $count [description]
     * @return Collection         [description]
     */
    public static function getLatestEvents(int $count = 5)
    {
        $cacheKey = __METHOD__ . ":latestEvents";
        $events = Cache::remember($cacheKey, 2 * 60, function () {
            $events = CrimeEvent::orderBy("created_at", "desc")
                ->with('locations')
                ->limit(20)
                ->get();

            return $events;
        });

        return $events;
    }

    /**
     * Hämta navigationsalternativ för inbrott-sidorna.
     *
     * @return array Array med navigationalternativ för inbrott-sidorna.
     */
    public static function getInbrottNavItems()
    {
        // Undersidor och deras titlar.
        $undersidor = [
            'start' => [
                'title' =>
                    'Inbrott - Fakta & information om inbrott i hus & lägenhet',
                'pageTitle' => 'Inbrott',
                'pageSubtitle' =>
                    'Fakta & information om inbrott i hus & lägenhet',
                'url' => '/inbrott/'
            ],
            'fakta' => [
                'title' => "Fakta om inbrott",
                'pageTitle' => "Fakta om inbrott",
                'pageSubtitle' =>
                    "Över 60 bostadsinbrott sker varje dag. (Men hur många klaras upp?)"
            ],
            'drabbad' => [
                'title' =>
                    'Drabbad av inbrott - det här ska du göra om du haft inbrott',
                'pageTitle' => 'Drabbad av inbrott',
                'pageSubtitle' =>
                    'Det här ska du göra om du haft inbrott i din villa eller lägenhet.'
            ],
            'skydda-dig' => [
                'title' =>
                    'Skydda dig mot inbrott - skydda dig & ditt hem från inbrott med hjälp av tips & larm',
                'pageTitle' => 'Skydda dig mot inbrott',
                'pageSubtitle' =>
                    'Skydda ditt hem från inbrott med hjälp av tips & larm.'
            ],
            'grannsamverkan' => [
                'title' => 'Grannsamverkan mot brott',
                'pageTitle' => 'Grannsamverkan mot brott',
                'pageSubtitle' =>
                    'Förebygg kriminalitet såsom inbrott genom att gå samman med grannarna i ditt närområde. Ett effektivt sätt att minska brottrisken i ditt område!'
            ],
            'senaste-inbrotten' => [
                'title' => 'Senaste inbrotten',
                'pageTitle' => 'Inbrott som hänt nyligen',
                'pageSubtitle' => 'Lista med de senaste inbrotten från Polisen.'
            ]
        ];

        array_walk($undersidor, function (&$val, $key) {
            if (empty($val['url'])) {
                $val['url'] = "/inbrott/{$key}";
            }
        });

        return $undersidor;
    }

    /**
     * Hämta navigationsalternativ för inbrott-sidorna.
     *
     * @return array Array med navigationalternativ för inbrott-sidorna.
     */
    public static function getBrandNavItems()
    {
        // Undersidor och deras titlar.
        $undersidor = [
            'start' => [
                'title' =>
                    'Senaste nytt om bränder och brandrealterade händelser från Polisen',
                'pageTitle' => 'Brand',
                'pageSubtitle' =>
                    'Senaste nytt om bränder och brandrealterade händelser från Polisen',
                'url' => '/brand/'
            ]
        ];

        array_walk($undersidor, function (&$val, $key) {
            if (empty($val['url'])) {
                $val['url'] = "/brand/{$key}";
            }
        });

        return $undersidor;
    }

    /**
     * Hämta meddelanden.
     *
     * @return Collection
     */
    public static function getVMAAlerts() {
        return Cache::remember('shared_vma_alerts', MINUTE_IN_SECONDS, function() {
            $alerts = VMAAlert::where('status', 'Actual')
                    ->where('msgType', 'Alert')
                    ->orderByDesc('sent')
                    ->get();
            return $alerts;
        });
    }

    /**
     * Hämta meddelanden som uppdaterats senaste 6o minuterna = rimligt aktuella.
     *
     * @return Collection
     */
    public static function getCurrentAlerts() {
        return VMAAlert::where('status', 'Actual')
                ->where('msgType', 'Alert')
                ->where('updated_at', ">=", Carbon::now()->subMinutes(60))
                ->orderByDesc('sent')
                ->get();
    }

}
