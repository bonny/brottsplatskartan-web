<?php

namespace App;

use Illuminate\Http\Request;
use Carbon\Carbon;
use DB;
use Illuminate\Support\Facades\Cache;
use App\Http\Controllers\FeedParserController;
use App\Http\Controllers\FeedController;
use Illuminate\Database\Eloquent\Model;
use Spatie\Feed\Feedable;
use Spatie\Feed\FeedItem;
use App\Http\Controllers\PlatsController;

class CrimeEvent extends Model implements Feedable
{
    // use Searchable;
    // use Eloquence;

    protected $fillable = [
        'title',
        'description',
        'permalink',
        'pubdate',
        'pubdate_iso8601',
        'md5',
        'parsed_date',
        'parsed_title',
        'parsed_title_location',
        'parsed_content_location',
        'parsed_content',
        'location_lng',
        'location_lat',
        'parsed_teaser',
        'scanned_for_locations',
        'geocoded'
    ];

    /**
     * Get the comments for the blog post.
     */
    public function locations()
    {
        return $this->hasMany('App\Locations');
    }

    /**
     * Har händelsen en tillgänglig karta?
     *
     * @return boolean
     */
    public function hasMapImage() {
        return $this->geocoded;
    }

    // return src for an image
    public function getStaticImageSrc($width = 320, $height = 320, $scale = 1)
    {
        // $google_api_key = env("GOOGLE_API_KEY");

        // $image_src = "https://maps.googleapis.com/maps/api/staticmap?";
        // $image_src .= "key=$google_api_key";
        // $image_src .= "&size={$width}x{$height}";
        // $image_src .= "&scale={$scale}";
        // $image_src .= "&language=sv";

        $tileserverUrl = 'https://kartbilder.brottsplatskartan.se/';
        $tileServerQueryArgs = [];
        // http_build_query([1 => 2, 'a' => 'b']);
        // return $tileServerQueryArgs;

        // if viewport info exists use that and skip manual zoom level
        if ($this->viewport_northeast_lat) {
            // $image_src .= "&path=";
            // $image_src .= "color:0x00000000|weight:2|fillcolor:0xFF660044";

            // /styles/klokantech-basic/static/auto/640x340.jpg?path=59.3137,18.0780|59.32,18.0790|59.33,18.0791|59.34,18.0800|59.30,18.0001&latlng=1&fill=rgba(255,0,0,.2)&width=2&stroke=rgba(255,0,0,.2)
            $tileserverUrl .= 'styles/klokantech-basic/static/auto/';
            $tileserverUrl .= "{$width}x{$height}.jpg";
            $tileServerQueryArgs = array_merge($tileServerQueryArgs, [
                "latlng" => 1,
                "fill" => "rgba(255,0,0,.2)",
                "width" => 2,
                "stroke" => "rgba(255,0,0,.2)"
            ]);

            $tileServerPath = "";
            $tileServerPath .= "|{$this->viewport_northeast_lat},{$this->viewport_northeast_lng}";
            $tileServerPath .= "|{$this->viewport_southwest_lat},{$this->viewport_northeast_lng}";

            $tileServerPath .= "|{$this->viewport_southwest_lat},{$this->viewport_southwest_lng}";
            $tileServerPath .= "|{$this->viewport_northeast_lat},{$this->viewport_southwest_lng}";

            $tileServerPath = trim($tileServerPath, '|');

            $tileServerQueryArgs['path'] = $tileServerPath;
            $tileServerQueryArgs['padding'] = "0.4";

            return $tileserverUrl .
                '?' .
                http_build_query($tileServerQueryArgs);

            // path=59.3137,18.0780
            // 59.32,18.0790|59.33,18.0791|59.34,18.0800|59.30,18.0001

            /*

            color:
            (optional) specifies a color either as a
            24-bit (example: color=0xFFFFCC) or
            32-bit hexadecimal value (example: color=0xFFFFCCFF), or from the set {black, brown, green, purple, yellow, blue, gray, orange, red, white}.

            example from google api:
            path=color:0xFFFFCC|weight:5|fillcolor:0xFFFF0033|8th+Avenue+%26+34th+St,New+York,NY|\
            8th+Avenue+%26+42nd+St,New+York,NY|Park+Ave+%26+42nd+St,New+York,NY,NY|\
            Park+Ave+%26+34th+St,New+York,NY,NY

            */

            // $image_src .= "|{$this->viewport_northeast_lat},{$this->viewport_northeast_lng}";
            // $image_src .= "|{$this->viewport_southwest_lat},{$this->viewport_northeast_lng}";

            // $image_src .= "|{$this->viewport_southwest_lat},{$this->viewport_southwest_lng}";
            // $image_src .= "|{$this->viewport_northeast_lat},{$this->viewport_southwest_lng}";
        } elseif ($this->location_lat) {
            // no viewport but location_lat, fallback to center
            // $image_src .= "&center={$this->location_lat},{$this->location_lng}";
            // $image_src .= "&zoom=14";
            return '';
        } else {
            // @TODO: return fallback iamge
            return '';
        }

        // echo "image: <img src='$image_src'>";
        // exit;

        // src="https://maps.googleapis.com/maps/api/staticmap?center={{ $event->location_lat }},{{ $event->location_lng }}&zoom=14&size=600x400&key=...&markers={{ $event->location_lat }},{{ $event->location_lng }}"

        // $image_src = Helper::signUrl($image_src);

        return '';
    }

    /**
     * get image far away, like whole sweden or something
     */
    public function getStaticImageSrcFar(
        $width = 320,
        $height = 320,
        $scale = 1
    ) {
        $tileserverUrl = 'https://kartbilder.brottsplatskartan.se/';
        $tileServerQueryArgs = [];

        // if viewport info exists use that and skip manual zoom level
        if ($this->viewport_northeast_lat) {
            // $image_src .= "&path=";
            // $image_src .= "color:0x00000000|weight:2|fillcolor:0xFF660044";

            // /styles/klokantech-basic/static/auto/640x340.jpg?path=59.3137,18.0780|59.32,18.0790|59.33,18.0791|59.34,18.0800|59.30,18.0001&latlng=1&fill=rgba(255,0,0,.2)&width=2&stroke=rgba(255,0,0,.2)
            $zoomLevel = 5;
            $expandNumber = 0.25;

            $tileserverUrl .= 'styles/klokantech-basic/static/';
            $tileserverUrl .= "{$this->location_lng},{$this->location_lat},{$zoomLevel}";
            $tileserverUrl .= "/{$width}x{$height}.jpg";
            $tileServerQueryArgs = array_merge($tileServerQueryArgs, [
                "latlng" => 1,
                "fill" => "rgba(255,0,0,.2)",
                "width" => 2,
                "stroke" => "rgba(255,0,0,.2)"
            ]);

            // Expand marked region.

            $viewport_northeast_lat_first = number_format(
                $this->viewport_northeast_lat + $expandNumber,
                5,
                '.',
                ''
            );
            $viewport_northeast_lng = number_format(
                $this->viewport_northeast_lng + $expandNumber,
                5,
                '.',
                ''
            );

            $viewport_southwest_lat = number_format(
                $this->viewport_southwest_lat - $expandNumber,
                5,
                '.',
                ''
            );
            $viewport_southwest_lng = number_format(
                $this->viewport_southwest_lng - $expandNumber,
                5,
                '.',
                ''
            );

            $tileServerPath = "";
            $tileServerPath .=
                "|" .
                $viewport_northeast_lat_first .
                "," .
                $viewport_northeast_lng;
            $tileServerPath .=
                "|" . $viewport_southwest_lat . "," . $viewport_northeast_lng;

            $tileServerPath .=
                "|" . $viewport_southwest_lat . "," . $viewport_southwest_lng;
            $tileServerPath .=
                "|" .
                $viewport_northeast_lat_first .
                "," .
                $viewport_southwest_lng;

            $tileServerPath = trim($tileServerPath, '|');

            $tileServerQueryArgs['path'] = $tileServerPath;
            // $tileServerQueryArgs['padding'] = "0.4";

            return $tileserverUrl .
                '?' .
                http_build_query($tileServerQueryArgs);

            // path=59.3137,18.0780
            // 59.32,18.0790|59.33,18.0791|59.34,18.0800|59.30,18.0001

            /*

            color:
            (optional) specifies a color either as a
            24-bit (example: color=0xFFFFCC) or
            32-bit hexadecimal value (example: color=0xFFFFCCFF), or from the set {black, brown, green, purple, yellow, blue, gray, orange, red, white}.

            example from google api:
            path=color:0xFFFFCC|weight:5|fillcolor:0xFFFF0033|8th+Avenue+%26+34th+St,New+York,NY|\
            8th+Avenue+%26+42nd+St,New+York,NY|Park+Ave+%26+42nd+St,New+York,NY,NY|\
            Park+Ave+%26+34th+St,New+York,NY,NY

            */

            // $image_src .= "|{$this->viewport_northeast_lat},{$this->viewport_northeast_lng}";
            // $image_src .= "|{$this->viewport_southwest_lat},{$this->viewport_northeast_lng}";

            // $image_src .= "|{$this->viewport_southwest_lat},{$this->viewport_southwest_lng}";
            // $image_src .= "|{$this->viewport_northeast_lat},{$this->viewport_southwest_lng}";
        } elseif ($this->location_lat) {
            // no viewport but location_lat, fallback to center
            // $image_src .= "&center={$this->location_lat},{$this->location_lng}";
            // $image_src .= "&zoom=14";
            return '';
        } else {
            // @TODO: return fallback iamge
            return '';
        }

        // echo "image: <img src='$image_src'>";
        // exit;

        // src="https://maps.googleapis.com/maps/api/staticmap?center={{ $event->location_lat }},{{ $event->location_lng }}&zoom=14&size=600x400&key=...&markers={{ $event->location_lat }},{{ $event->location_lng }}"

        // $image_src = Helper::signUrl($image_src);

        return '';
    }

    /**
     * The pub date is the date from the RSS-feed,
     * i.e. when the crime is posted by polisen
     * the actual event may have happened much earlier
     */
    public function getPubDateISO8601()
    {
        return Carbon::createFromTimestamp($this->pubdate)->toIso8601String();
    }

    public function getPubDateFormatted($format = '%A %d %B %Y')
    {
        return Carbon::createFromTimestamp($this->pubdate)->formatLocalized(
            $format
        );
    }

    public function getPubDateFormattedForHumans()
    {
        return Carbon::createFromTimestamp($this->pubdate)->diffForHumans();
    }

    /**
     * Parsed date = the date that is written as text in each crime.
     * Is often much earlier than the date in the RSS data.
     */
    public function getParsedDateFormattedForHumans()
    {
        $date = $this->parsed_date;
        if (empty($date)) {
            $date = $this->pubdate_iso8601;
        }

        return Carbon::createFromTimestamp(strtotime($date))->diffForHumans();
    }

    public function getParsedDateDiffInSeconds()
    {
        $date = $this->parsed_date;
        if (empty($date)) {
            $date = $this->pubdate_iso8601;
        }

        return Carbon::createFromTimestamp(strtotime($date))->diffInSeconds();
    }

    // ...but fallbacks to pubdate if parsed_date is null
    public function getParsedDateISO8601()
    {
        $date = $this->parsed_date;
        if (empty($date)) {
            $date = $this->pubdate_iso8601;
        }

        return Carbon::createFromTimestamp(strtotime($date))->toIso8601String();
    }

    /**
     * Returns the date of the crime/event in YMD format.
     *
     * @return string Date in format "Lördag 15 December 2018 13:20"
     */
    public function getParsedDateYMD()
    {
        $date = $this->parsed_date;
        if (empty($date)) {
            $date = $this->pubdate_iso8601;
        }

        $date = Carbon::createFromTimestamp(strtotime($date));

        $formattedDate = '';
        if ($this->isParsedDateThisYear()) {
            // Pågående år, ta inte med år.
            $formattedDate = $date->formatLocalized('%A %d %B %H:%M');
        } else {
            // Inte pågående år, inkludera år.
            $formattedDate = $date->formatLocalized('%A %d %B %Y %H:%M');
        }

        return $formattedDate;
    }

    /**
     * Kolla om året för denna händelse är samma år som just nu pågår.
     * @return boolean True om det är året som pågår, false om inte.
     */
    public function isParsedDateThisYear()
    {
        $eventDateYear = Carbon::createFromTimestamp(
            strtotime($this->parsed_date)
        )->format('Y');
        $currentYear = date('Y');

        return $currentYear === $eventDateYear;
    }

    /**
     * Returns a nice permalink to the page
     *
     * Example return value:
     * /varmlands-lan/rattfylleri-2440
     */
    public function getPermalink($absolute = false)
    {
        $slugParts = [];

        if (!empty($this->administrative_area_level_1)) {
            $lan = $this->toAscii($this->administrative_area_level_1);
        } else {
            $lan = "sverige";
        }

        // "Stöld/inbrott" and so on
        $slugParts[] = $this->parsed_title;

        if (!empty($this->parsed_title_location)) {
            $slugParts[] = $this->parsed_title_location;
        } else {
            #$eventName = "";
        }

        $prio1locations = $this->locations->filter(function ($val, $key) {
            return $val->prio == 1;
        });

        foreach ($prio1locations as $location) {
            $slugParts[] = $location->name;
        }

        $slugParts[] = $this->getKey();

        $eventName = implode("-", $slugParts);
        $eventName = $this->toAscii($eventName);

        $permalink = route(
            "singleEvent",
            [
                "lan" => $lan,
                "eventName" => $eventName
            ],
            $absolute
        );

        return $permalink;
    }

    /**
     * Hämta eventets platser i en rimligt fint formaterad sträng
     * typ såhär: Borås, nnn län
     */
    public function getLocationString(
        $includePrioLocations = true,
        $includeParsedTitleLocation = true,
        $inclueAdministrativeAreaLevel1Locations = true
    ) {
        $locations = [];

        if ($includePrioLocations) {
            $prioLocations = $this->locations->whereIn("prio", [1, 2]);

            if ($prioLocations->count()) {
                foreach ($prioLocations as $oneLocation) {
                    $locations[] = title_case($oneLocation->name);
                }
            }
        }

        if ($includeParsedTitleLocation) {
            if ($this->parsed_title_location) {
                $locations[] = $this->parsed_title_location;
            }
        }

        if ($inclueAdministrativeAreaLevel1Locations) {
            if (
                $this->administrative_area_level_1 &&
                $this->administrative_area_level_1 !==
                    $this->parsed_title_location
            ) {
                $locations[] = $this->administrative_area_level_1;
            }
        }

        $location = implode(", ", $locations);

        return $location;
    }

    /**
     * Som getLocationString
     * men platser kan vara länkade, t.ex. länen
     */
    public function getLocationStringWithLinks($args = [])
    {
        $locations = [];
        $prioOneLocations = $this->locations->whereIn("prio", [1, 2]);

        // Stockholms län, Västmanlands län
        $lan = $this->administrative_area_level_1;

        // Locations = Blandat; "Rönninge", "Götaland", "Hästhovsvägen", "Kungsgatan", osv.
        if ($prioOneLocations->count()) {
            foreach ($prioOneLocations as $oneLocation) {
                $lanNoSpace = preg_replace("![/_|+ -]+!", "-", $lan);

                // $oneLocation->name = "västra kattarpsvägenVästra"
                $locationNameNoSpace = preg_replace(
                    "![/_|+ -]+!",
                    "-",
                    $oneLocation->name
                );

                // kungsgatan-stockholms-län
                $platsUrlSlug = sprintf(
                    '%1$s-%2$s',
                    mb_strtolower($locationNameNoSpace),
                    mb_strtolower($lanNoSpace)
                );

                // $platsUrlSlug = $this->toAscii($platsUrlSlug);

                $locations[] = sprintf(
                    '<a href="%2$s">%1$s</a>',
                    title_case($oneLocation->name),
                    route("platsSingle", ["plats" => $platsUrlSlug])
                );
            }
        }

        // Title location = oftare typ "Malmö", "Våsterås", osv.
        if ($this->parsed_title_location) {
            $locations[] = sprintf(
                '<a href="%2$s">%1$s</a>',
                $this->parsed_title_location,
                route("platsSingle", [
                    "plats" => mb_strtolower($this->parsed_title_location)
                ])
            );
        }

        // Add administrative_area_level_1, if not already added.
        // administrative_area_level_1 = lan = Stockholms län
        $outputLan = $lan && $lan !== $this->parsed_title_location;
        if (isset($args['skipLan']) && $args['skipLan']) {
            $outputLan = false;
        }
        if ($outputLan) {
            $locations[] = sprintf(
                '<a href="%2$s">%1$s</a>',
                $lan,
                route("lanSingle", ["lan" => mb_strtolower($lan)])
            );
        }

        $location = implode(", ", $locations);

        return $location;
    }

    // from http://cubiq.org/the-perfect-php-clean-url-generator
    public function toAscii($str, $replace = array(), $delimiter = '-')
    {
        $text = Helper::toAscii($str, $replace, $delimiter);
        return $text;
    }

    /**
     * @return string
     */
    public function getMetaDescription($length = 250)
    {
        $text = "";

        $text .= trim($this->description) . " " . trim($this->parsed_content);

        $text = strip_tags(str_replace('<', ' <', $text));
        $text = str_limit($text, $length, '');

        return $text;
    }

    // https://laracasts.com/discuss/channels/laravel/search-option-in-laravel-5?page=1
    /*
    public static function scopeSearchByKeyword($query, $keyword) {

        if ($keyword != '') {

            $query->where(function ($query) use ($keyword) {

                $query->where("description", "LIKE","%$keyword%")
                    ->orWhere("parsed_title_location", "LIKE", "%$keyword%")
                    ->orWhere("parsed_content", "LIKE", "%$keyword%")
                    ->orWhere("parsed_title", "LIKE", "%$keyword%");

            });
        }

        return $query;
    }
    */

    /**
     * Get the description (kinda the teaser)
     * replacing new lines with <p>
     */
    public function getDescription()
    {
        $text = $this->description;
        $text = $this->autop($text);

        return $text;
    }

    /**
     * Get description with tags stripped
     */
    public function getDescriptionAsPlainText()
    {
        $text = $this->getDescription();
        $text = Helper::stripTagsWithWhitespace($text);
        $text = trim($text);

        return $text;
    }

    /**
     * Get the description
     *
     * @return string The content
     */
    public function getParsedContent()
    {
        $text = $this->parsed_content;

        $text = $this->autop($text);

        return $text;
    }

    /**
     * Get the description as plaint text, with html tags removed
     *
     * @return string The content
     */
    public function getParsedContentAsPlainText()
    {
        $text = $this->getParsedContent();
        $text = Helper::stripTagsWithWhitespace($text);
        $text = trim($text);

        return $text;
    }

    /**
     * Get the description, for overview pages, where text is cropped after nn chars
     * and styles removed to not interfere in listing
     */
    public function getParsedContentTeaser($length = 160)
    {
        $text = $this->parsed_content;

        // strip tags but make sure there is at least a space where the tag was
        // so text paragraphs don't collapse
        #$text = strip_tags(str_replace('<', ' <', $text));
        $text = Helper::stripTagsWithWhitespace($text);

        $text = str_limit($text, $length);

        // $text = $this->autop($text);

        return $text;
    }

    /**
     * Kinda like wp's autop function
     * replaces newlines with paragraphs, removes duplicate <br>:s and so on
     */
    public function autop($text)
    {
        // replace <br> with new line
        $text = str_replace("<br>", "\n", $text);

        // wrap paragraphs around each line
        $text = '<p>' . preg_replace('/[\r\n]+/', '</p><p>', $text) . '</p>';

        // remove duplicate <br>
        $text = preg_replace('/(<br>)+/', '<br>', $text);

        return $text;
    }

    public static function getEventsNearLocation(
        $lat,
        $lng,
        $nearbyCount = 10,
        $nearbyInKm = 25
    ) {
        $cacheKey = "getEventsNearLocation:lat{$lat}:lng{$lng}:nearby{$nearbyCount}:nearbyKm{$nearbyInKm}";
        $cacheTTL = 9;

        $events = Cache::remember($cacheKey, $cacheTTL, function () use (
            $lat,
            $lng,
            $nearbyCount,
            $nearbyInKm
        ) {
            return self::getEventsNearLocationUncached(
                $lat,
                $lng,
                $nearbyCount,
                $nearbyInKm
            );
        });

        return $events;
    }

    public static function getEventsNearLocationUncached(
        $lat,
        $lng,
        $nearbyCount = 10,
        $nearbyInKm = 25
    ) {
        $someDaysAgoYMD = Carbon::now()
            ->subDays(15)
            ->format('Y-m-d');

        $events = CrimeEvent::selectRaw( // välj de som är rimligt nära, värdet är i km
            '*, ( 6371 * acos( cos( radians(?) ) * cos( radians( location_lat ) ) * cos( radians( location_lng ) - radians(?) ) + sin( radians(?) ) * sin( radians( location_lat ) ) ) ) AS distance',
            [$lat, $lng, $lat]
        )
            ->where('created_at', '>', $someDaysAgoYMD)
            ->having("distance", "<=", $nearbyInKm)
            ->orderBy("parsed_date", "DESC")
            ->orderBy("distance", "ASC")
            ->limit($nearbyCount)
            ->with('locations')
            ->get();

        return $events;
    }

    public function getViewportSize()
    {
        $viewportSize =
            $this->viewport_northeast_lat -
            $this->viewport_southwest_lat +
            ($this->viewport_northeast_lng - $this->viewport_southwest_lng);

        return $viewportSize;
    }

    /**
     * > 26 = hela sverige
     * ca 12 = norrbotten
     * ca 10 = västerbotten
     * ca 8 = jämtland
     * ca 7 = dalarna
     * ca 6 = västernorrland
     * ca 1.5 - 2 = län
     * ca 0.6 = stockholm
     * ca 0.2 = södertälje
     * ca 0.1 = typ kungsholmen
     * ca 0.005 - 0.06 = typ längre gata
     * mindre än det = jäkla nära
     */
    public function getViewPortSizeAsString()
    {
        $size = $this->getViewportSize();

        $sizeAsString = "";

        switch ($size) {
            case $size > 20:
                $sizeAsString = "veryfar";
                break;

            case $size > 6:
                $sizeAsString = "far";
                break;

            case $size > 0.8:
                $sizeAsString = "lan";
                break;

            case $size > 0.1:
                $sizeAsString = "town";
                break;

            case $size > 0.05:
                $sizeAsString = "street";
                break;

            default:
                $sizeAsString = "closest";
        }

        return $sizeAsString;
    }

    /**
     * Return some debug stuff if a query contains some debugActions
     *
     * @return array
     */
    protected function maybeAddDebugData(Request $request, CrimeEvent $event)
    {
        $data = [];

        $debugActions = (array) $request->input("debugActions");

        if (!$debugActions) {
            return $data;
        }

        if (in_array("getLocations", $debugActions)) {
            // re-get location data from event, i.e find street names again and retun some debug
            $FeedParserController = new FeedParserController();
            $FeedController = new FeedController($FeedParserController);

            $itemFoundLocations = $FeedParserController->findLocations($event);
            $data["itemFoundLocations"] = $itemFoundLocations;

            // get the url that is sent to google to geocode this item
            $data["itemGeocodeURL"] = $FeedController->getGeocodeURL(
                $event->getKey()
            );
        }

        return $data;
    }

    /**
     * Get title for an event
     * Result is like:
     * Brand, Bilbrand, Gustav Adolfs gata/Nytorgsbacken, Helsingborg.,
     * Gustav Adolfs Gata, Nytorgsbacken, Helsingborg, 02 nov 2017",
     *
     *
     * @return string
     */
    public function getSingleEventTitle()
    {
        $cacheKey = __METHOD__ . ':' . $this->getKey();
        $seconds = 5;
        $title = Cache::remember($cacheKey, $seconds, function () {
            $titleParts = [];

            $titleParts[] = $this->parsed_title;
            $titleParts[] = $this->getDescriptionAsPlainText();

            $prioOneLocations = $this->locations->where("prio", 1);

            foreach ($prioOneLocations as $oneLocation) {
                $titleParts[] = title_case($oneLocation->name);
            }

            $titleParts[] = $this->parsed_title_location;
            $titleParts[] = $this->getPubDateFormatted('%d %b %Y');

            $title = implode(", ", $titleParts);

            return $title;
        });

        return $title;
    }

    /**
     * Get a slightly shorter version of the title, used for ld+json
     *
     * @return string
     */
    public function getSingleEventTitleShort()
    {
        $titleParts = [];

        $titleParts[] = $this->parsed_title;
        $titleParts[] = $this->getDescriptionAsPlainText();

        // $prioOneLocations = $this->locations->where("prio", 1);

        // foreach ($prioOneLocations as $oneLocation) {
        //     $titleParts[] = title_case($oneLocation->name);
        // }

        // $titleParts[] = $this->parsed_title_location;
        // $titleParts[] = $this->getPubDateFormatted('%d %b %Y');

        return implode(", ", $titleParts);
    }

    /**
     * Clears the location data for an event
     * and then re-parses it
     * useful when locations have been added or removed, so geocode
     * of item may change
     *
     * @param Request $request The request.
     *
     * @return mixed Array with debug data on success. Null if not authed.
     */
    public function maybeClearLocationData(Request $request)
    {
        if (!\Auth::check()) {
            return;
        }

        $debugActions = (array) $request->input("debugActions");

        if (in_array("clearLocation", $debugActions)) {
            $FeedParserController = new FeedParserController();
            $FeedController = new FeedController($FeedParserController);

            $this->locations()->delete();

            $this->fill([
                "scanned_for_locations" => 0,
                "geocoded" => 0
            ]);

            $this->save();

            // Add and remove locatons
            $locationAdd = trim($request->input('locationAdd', ''));
            if ($locationAdd) {
                // echo "<br>locationAdd: $locationAdd";
                \App\highways_added::firstOrCreate(['name' => $locationAdd]);
            }

            $locationIgnore = mb_strtolower(
                trim($request->input('locationIgnore', ''))
            );
            if ($locationIgnore) {
                // echo "<br>locationIgnore: $locationIgnore";
                \App\highways_ignored::firstOrCreate([
                    'name' => $locationIgnore
                ]);
            }

            $FeedController->parseItem($this->getKey());
            $FeedController->geocodeItem($this->getKey());

            $itemFoundLocations = $FeedParserController->findLocations($this);
            unset($itemFoundLocations[0]["debug"]);
            $data["itemFoundLocations"] = $itemFoundLocations;

            // get the url that is sent to google to geocode this item
            $data["itemGeocodeURL"] = $FeedController->getGeocodeURL(
                $this->getKey()
            );

            return $data;
        }
    }

    /**
     * Should a link to the page where we got all the info be shown?
     * Polisen.se removed their page after about a week and then the page is a 404
     *
     * @return bool
     */
    public function shouldShowSourceLink()
    {
        $pubDate = Carbon::createFromTimestamp(strtotime($this->parsed_date));
        $pubDatePlusSomeTime = $pubDate->addWeek();

        // if pubdate + 1 week is more than today then ok to show
        return $pubDatePlusSomeTime->timestamp > time();
    }

    /**
     * Get the indexable data array for the model.
     *
     * @return array
     */
    public function toSearchableArray()
    {
        $array = $this->toArray();

        // Remove some things we don't wanna search
        // Add some things we wanna search
        /*
        array:26 [
          "id" => 15093
          "created_at" => "2017-05-06 09:44:18"
          "updated_at" => "2017-05-06 16:00:19"
          "title" => "2017-05-06 00:11, Brand, Sollentuna"
          "geocoded" => 0
          "scanned_for_locations" => 0
          "description" => "Räddningstjänsten släckte lägenhetsbrand i Helenlund."
          "permalink" => "http://polisen.se/Stockholms_lan/Aktuellt/Handelser/Stockholms-lan/2017-05-06-0011-Brand-Sollentuna/"
          "pubdate" => "1494053478"
          "pubdate_iso8601" => "2017-05-06T08:51:18+0200"
          "md5" => "dbe0978ee2d2b646fcff06df023f8a2d"
          "parsed_date" => "2017-05-06 00:11:00"
          "parsed_title_location" => "Sollentuna"
          "parsed_content" => null
          "location_lng" => null
          "location_lat" => null
          "parsed_title" => "Brand"
          "parsed_teaser" => null
          "location_geometry_type" => null
          "administrative_area_level_1" => null
          "administrative_area_level_2" => null
          "viewport_northeast_lat" => null
          "viewport_northeast_lng" => null
          "viewport_southwest_lat" => null
          "viewport_southwest_lng" => null
          "tweeted" => 0
        ]
        */

        $arrKeysToRemove = [
            "geocoded",
            "scanned_for_locations",
            "permalink",
            "md5",
            "location_geometry_type",
            "tweeted",
            "locations", // is array, gives mb_strtolower() expects parameter 1 to be string, array given
            "viewport_northeast_lat",
            "viewport_northeast_lng",
            "viewport_southwest_lat",
            "viewport_southwest_lng"
        ];

        $array = array_filter(
            $array,
            function ($val, $key) use ($arrKeysToRemove) {
                return !in_array($key, $arrKeysToRemove);
            },
            ARRAY_FILTER_USE_BOTH
        );

        #if (!empty($array["administrative_area_level_1"])) {
        #}

        // Add back locations in better format for sarch
        /*
        $locations = $this->locations;
        $strLocations = "{$this->parsed_title_location}, ";
        foreach ($locations as $location) {
            $strLocations .= "$location->name, ";
        }
        $strLocations = trim($strLocations, ' ,');
        */
        $strLocations = $this->getLocationString();

        $array["locationsString"] = $strLocations;

        #if (!empty($array["administrative_area_level_1"])) {
        #print_r($array);
        #}

        return $array;
    }

    public function newsarticles()
    {
        return $this->hasMany('App\Newsarticle');
    }

    /**
     * Return ld+json for an article
     */
    public function getLdJson()
    {
        $permalink = $this->getPermalink(true);
        $title = $this->getSingleEventTitleShort();
        $image = $this->getStaticImageSrc(696, 420);
        $datePublished = $this->getPubDateISO8601();
        $dateModified = $datePublished;
        $description = $this->getDescriptionAsPlainText();
        $locationLat = $this->location_lat;
        $locationLng = $this->location_lng;

        $str = <<<SQL
            <script type="application/ld+json">
                {
                  "@context": "http://schema.org",
                  "@type": "NewsArticle",
                  "mainEntityOfPage": {
                    "@type": "WebPage",
                    "@id": "{$permalink}"
                  },
                  "headline": "{$title}",
                  "image": [
                    "$image"
                   ],
                  "datePublished": "$datePublished",
                  "dateModified": "$dateModified",
                  "author": {
                    "@type": "Organization",
                    "name": "Brottsplatskartan"
                  },
                   "publisher": {
                    "@type": "Organization",
                    "name": "Brottsplatskartan",
                    "logo": {
                      "@type": "ImageObject",
                      "url": "https://brottsplatskartan.se/img/brottsplatskartan-logotyp.png"
                    }
                  },
                  "description": "$description"
                }
            </script>
SQL;

        /*
              "geo": {
                "@type": "GeoCoordinates",
                "latitude": "$locationLat",
                "longitude": "$locationLng"
              }

        */

        return $str;
    }

    /**
     * Hämta created_at i fint localized format, t.ex. "Onsdag 21 Mars 2018"
     *
     * @return string
     */
    public function getCreatedAtLocalized()
    {
        $createdAtCarbon = Carbon::parse($this->created_at);
        $createdAtLocalized = $createdAtCarbon->formatLocalized('%A %e %B %Y');
        return $createdAtLocalized;
    }

    /**
     * Returnerar sant om en händelse är av typen inbrott.
     *
     * @return boolean
     */
    public function isInbrott()
    {
        $inbrottOrd = ['inbrott', 'larm', 'intrång'];

        $isInbrott =
            str_contains(\mb_strtolower($this->parsed_title), $inbrottOrd) ||
            str_contains(
                \mb_strtolower($this->getDescriptionAsPlainText()),
                $inbrottOrd
            ) ||
            str_contains(
                \mb_strtolower($this->getParsedContent()),
                $inbrottOrd
            );

        return $isInbrott;
    }

    /**
     * Undocumented function
     *
     * @return void
     */
    public function toFeedItem()
    {
        return FeedItem::create()
            ->id($this->id)
            ->title($this->getSingleEventTitle())
            ->updated($this->updated_at)
            ->link($this->getPermalink())
            ->author('Brottsplatskartan.se')
            ->summary($this->getMetaDescription(100) . '…');
    }

    /**
     * Hämta händelser till RSS-flödet.
     *
     * Exempelurl:
     * https://brottsplatskartan.localhost/rss
     *
     * Exempel som visar bara för Stockholms län:
     * https://brottsplatskartan.localhost/rss?lan=stockholms%20l%C3%A4n
     *
     * @return void
     */
    public function getFeedItems(
        Request $request,
        PlatsController $platsController
    ) {
        $limit = 50;
        $lan = $request->input('lan');
        $plats = $request->input('plats');

        if ($lan && $plats) {
            // "15-januari-2018"
            $dateString = Carbon::parse('today')->formatLocalized('%d-%B-%Y');
            $date = \App\Helper::getdateFromDateSlug($dateString);
            $events = $platsController->getEventsInPlatsWithLanUncached(
                $plats,
                $lan,
                $date,
                14,
                false
            );
        } elseif ($lan) {
            $events = CrimeEvent::orderBy("created_at", "desc")
                ->where("administrative_area_level_1", $lan)
                ->with('locations')
                ->limit($limit)
                ->get();
        } else {
            // Oavsett plats eller län.
            $events = CrimeEvent::orderBy("created_at", "desc")
                ->with('locations')
                ->limit($limit)
                ->get();
        }

        return $events;
    }

    public function crimeViews()
    {
        return $this->hasMany('App\CrimeView');
    }
}
