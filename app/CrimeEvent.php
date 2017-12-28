<?php

namespace App;

use Carbon\Carbon;
use App\Http\Controllers\FeedParserController;
use App\Http\Controllers\FeedController;
// use Sofa\Eloquence\Eloquence;
use Illuminate\Database\Eloquent\Model;

class CrimeEvent extends Model
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

    // return src for an image
    public function getStaticImageSrc($width = 320, $height = 320, $scale = 1) {

        $google_api_key = env("GOOGLE_API_KEY");

        $image_src = "https://maps.googleapis.com/maps/api/staticmap?";
        $image_src .= "key=$google_api_key";
        $image_src .= "&size={$width}x{$height}";
        $image_src .= "&scale={$scale}";
        $image_src .= "&language=sv";

        // if viewport info exists use that and skip manual zoom level
        if ($this->viewport_northeast_lat) {

            $image_src .= "&path=";
            $image_src .= "color:0x00000000|weight:2|fillcolor:0xFF660044";

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

            $image_src .= "|{$this->viewport_northeast_lat},{$this->viewport_northeast_lng}";
            $image_src .= "|{$this->viewport_southwest_lat},{$this->viewport_northeast_lng}";

            $image_src .= "|{$this->viewport_southwest_lat},{$this->viewport_southwest_lng}";
            $image_src .= "|{$this->viewport_northeast_lat},{$this->viewport_southwest_lng}";

        } else if ($this->location_lat) {

            // no viewport but location_lat, fallback to center
            $image_src .= "&center={$this->location_lat},{$this->location_lng}";
            $image_src .= "&zoom=14";
        } else {

            // @TODO: return fallback iamge
            return "";

        }

        #echo "image: <img src='$image_src'>";
        #exit;

        // src="https://maps.googleapis.com/maps/api/staticmap?center={{ $event->location_lat }},{{ $event->location_lng }}&zoom=14&size=600x400&key=...&markers={{ $event->location_lat }},{{ $event->location_lng }}"

        $image_src = Helper::signUrl($image_src);

        return $image_src;

    }

    // get image far away, like whole sweden or something
    public function getStaticImageSrcFar($width = 320, $height = 320, $scale = 1) {

        $google_api_key = env("GOOGLE_API_KEY");

        $image_src = "https://maps.googleapis.com/maps/api/staticmap?";
        $image_src .= "key=$google_api_key";
        $image_src .= "&size={$width}x{$height}";
        $image_src .= "&scale={$scale}";
        $image_src .= "&language=sv";

        // if viewport info exists use that and skip manual zoom level
        if ($this->location_lat) {

            // no viewport but location_lat, fallback to center
            $image_src .= "&center={$this->location_lat},{$this->location_lng}";
            $image_src .= "&zoom=5";

            $image_src .= "&markers=color:red|{$this->location_lat},{$this->location_lng}";

        } else {

            return "";

        }

        $image_src = Helper::signUrl($image_src);

        return $image_src;

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
        return Carbon::createFromTimestamp($this->pubdate)->formatLocalized($format);
    }

    public function getPubDateFormattedForHumans() {

        return Carbon::createFromTimestamp($this->pubdate)->diffForHumans();

    }

    /**
     * Parsed date = the date that is written as text in each crime
     * Is often much earlier than the date in the RSS data
     */
    function getParsedDateFormattedForHumans() {

        $date = $this->parsed_date;
        if (empty($date)) {
            $date = $this->pubdate_iso8601;
        }

        return Carbon::createFromTimestamp(strtotime($date))->diffForHumans();

    }

    function getParsedDateDiffInSeconds() {
        $date = $this->parsed_date;
        if (empty($date)) {
            $date = $this->pubdate_iso8601;
        }

        return Carbon::createFromTimestamp(strtotime($date))->diffInSeconds();
    }


    // ...but fallbacks to pubdate if parsed_date is null
    public function getParsedDateISO8601() {

        $date = $this->parsed_date;
        if (empty($date)) {
            $date = $this->pubdate_iso8601;
        }

        return Carbon::createFromTimestamp(strtotime($date))->toIso8601String();

    }

    public function getParsedDateYMD() {
        return Carbon::createFromTimestamp(strtotime($this->parsed_date))->formatLocalized('%A %d %B %Y %H:%M');
    }


    /**
     * Returns a nice permalink to the page
     *
     * Example return value:
     * /varmlands-lan/rattfylleri-2440
     */
    public function getPermalink($absolute = false) {

        $slugParts = [];

        if ( ! empty($this->administrative_area_level_1) ) {
            $lan = $this->toAscii($this->administrative_area_level_1);
        } else {
            $lan = "sverige";
        }

        // "Stöld/inbrott" and so on
        $slugParts[] = $this->parsed_title;

        if (! empty($this->parsed_title_location)) {
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

        $permalink = route("singleEvent", [
            "lan" => $lan,
            "eventName" => $eventName
        ], $absolute);

        return $permalink;
    }

    /**
     * Hämta eventets platser i en rimligt fint formaterad sträng
     * typ såhär: Borås, nnn län
     */
    public function getLocationString($includePrioLocations = true, $includeParsedTitleLocation = true, $inclueAdministrativeAreaLevel1Locations = true) {

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
            if ($this->administrative_area_level_1 && $this->administrative_area_level_1 !== $this->parsed_title_location) {
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
    public function getLocationStringWithLinks()
    {
        $locations = [];
        $prioOneLocations = $this->locations->whereIn("prio", [1, 2]);

        // Stockholms län, Västmanlands län
        $lan = $this->administrative_area_level_1;
        //echo "<br>level1: " . $this->administrative_area_level_1;
        //echo "<br>level2: " . $this->administrative_area_level_2;

        // Locations = Blandat; "Rönninge", "Götaland", "Hästhovsvägen", "Kungsgatan", osv.
        if ($prioOneLocations->count()) {
            foreach ($prioOneLocations as $oneLocation) {
                $lanNoSpace = preg_replace("![/_|+ -]+!", "-", $lan);

                // $oneLocation->name = "västra kattarpsvägenVästra"
                $locationNameNoSpace = preg_replace("![/_|+ -]+!", "-", $oneLocation->name);

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
                route("platsSingle", ["plats" => mb_strtolower($this->parsed_title_location)])
            );
        }

        // Add administrative_area_level_1 only if not already added
        $someLogic = $lan && $lan !== $this->parsed_title_location;
        if ($someLogic) {
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
    public function getMetaDescription($length = 200)
    {

        $text = "";

        $text .= trim($this->description) . " " . trim($this->parsed_content);

        $text = strip_tags(str_replace('<', ' <', $text));
        $text = str_limit($text, $length);

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

    public static function getEventsNearLocation($lat, $lng, $nearbyCount = 10, $nearbyInKm = 25)
    {
        $events = CrimeEvent::selectRaw('*, ( 6371 * acos( cos( radians(?) ) * cos( radians( location_lat ) ) * cos( radians( location_lng ) - radians(?) ) + sin( radians(?) ) * sin( radians( location_lat ) ) ) ) AS distance', [ $lat, $lng, $lat ])
        ->having("distance", "<=", $nearbyInKm) // välj de som är rimligt nära, värdet är i km
        ->orderBy("parsed_date", "DESC")
        ->orderBy("distance", "ASC")
        ->limit($nearbyCount)
        ->with('locations')
        ->get();

        return $events;
    }

    public function getViewportSize()
    {

        $viewportSize = ($this->viewport_northeast_lat - $this->viewport_southwest_lat) + ($this->viewport_northeast_lng - $this->viewport_southwest_lng);

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
    protected function maybeAddDebugData(\Illuminate\Http\Request $request, CrimeEvent $event)
    {

        $data = [];

        $debugActions = (array) $request->input("debugActions");

        if (!$debugActions) {
            return $data;
        }

        if (in_array("getLocations", $debugActions)) {
            // re-get location data from event, i.e find street names again and retun some debug
            $FeedParserController = new FeedParserController;
            $FeedController = new FeedController($FeedParserController);

            $itemFoundLocations = $FeedParserController->findLocations($event);
            $data["itemFoundLocations"] = $itemFoundLocations;

            // get the url that is sent to google to geocode this item
            $data["itemGeocodeURL"] = $FeedController->getGeocodeURL($event->getKey());
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
        $titleParts = [];

        $titleParts[] = $this->parsed_title;
        $titleParts[] = $this->getDescriptionAsPlainText();

        $prioOneLocations = $this->locations->where("prio", 1);

        foreach ($prioOneLocations as $oneLocation) {
            $titleParts[] = title_case($oneLocation->name);
        }

        $titleParts[] = $this->parsed_title_location;
        $titleParts[] = $this->getPubDateFormatted('%d %b %Y');

        return implode(", ", $titleParts);
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
     */
    public function maybeClearLocationData(\Illuminate\Http\Request $request)
    {
        if (!\Auth::check()) {
            return;
        }

        $debugActions = (array) $request->input("debugActions");

        if (in_array("clearLocation", $debugActions)) {
            $FeedParserController = new FeedParserController;
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

            $locationIgnore = mb_strtolower(trim($request->input('locationIgnore', '')));
            if ($locationIgnore) {
                // echo "<br>locationIgnore: $locationIgnore";
                \App\highways_ignored::firstOrCreate(['name' => $locationIgnore]);
            }

            $FeedController->parseItem($this->getKey());
            $FeedController->geocodeItem($this->getKey());

            $itemFoundLocations = $FeedParserController->findLocations($this);
            unset($itemFoundLocations[0]["debug"]);
            $data["itemFoundLocations"] = $itemFoundLocations;

            // get the url that is sent to google to geocode this item
            $data["itemGeocodeURL"] = $FeedController->getGeocodeURL($this->getKey());

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
        return  $pubDatePlusSomeTime->timestamp > time();
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

        $array = array_filter($array, function ($val, $key) use ($arrKeysToRemove) {
            return !in_array($key, $arrKeysToRemove);
        }, ARRAY_FILTER_USE_BOTH);

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
}
