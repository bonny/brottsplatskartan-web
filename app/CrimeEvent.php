<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use App\Http\Controllers\FeedParserController;
use App\Http\Controllers\FeedController;

class CrimeEvent extends Model
{

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
        'parsed_content',
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

        // src="https://maps.googleapis.com/maps/api/staticmap?center={{ $event->location_lat }},{{ $event->location_lng }}&zoom=14&size=600x400&key=AIzaSyBNGngVsHlVCo4D26UnHyp3nqcgFa-HEew&markers={{ $event->location_lat }},{{ $event->location_lng }}"
        return $image_src;

    }

    /**
     * The pub date is the date from the RSS-feed,
     * i.e. when the crime is posted by polisen
     * the actual event may have happened much earlier
     */
    public function getPubDateISO8601() {

        return Carbon::createFromTimestamp($this->pubdate)->toIso8601String();

    }

    public function getPubDateFormatted($format = '%A %d %B %Y') {

        return Carbon::createFromTimestamp($this->pubdate)->formatLocalized($format);

    }

    public function getPubDateFormattedForHumans() {

        return Carbon::createFromTimestamp($this->pubdate)->diffForHumans();

    }

    /**
     * Parsed date = the date that is written as text in each crime
     * Is often much earlier that the RSS data
     */
    function getParsedDateFormattedForHumans() {

        return Carbon::createFromTimestamp(strtotime($this->parsed_date))->diffForHumans();

    }

    public function getParsedDateISO8601() {

        return Carbon::createFromTimestamp(strtotime($this->parsed_date))->toIso8601String();

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

        if ( ! empty($this->parsed_title_location) ) {
            $slugParts[] = $this->parsed_title_location;
        } else {
            #$eventName = "";
        }

        $prio1locations = $this->locations->filter(function($val, $key) {
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
    public function getLocationString() {

        $locations = [];

        $prioOneLocations = $this->locations->where("prio", 1);

        if ($prioOneLocations->count()) {
            foreach ($prioOneLocations as $oneLocation) {
                $locations[] = title_case($oneLocation->name);
            }
        }

        if ($this->parsed_title_location) {
            $locations[] = $this->parsed_title_location;
        }

        if ($this->administrative_area_level_1 && $this->administrative_area_level_1 !== $this->parsed_title_location) {
            $locations[] = $this->administrative_area_level_1;
        }

        $location = implode(", ", $locations);

        return $location;

    }

    /**
     * Som getLocationString
     * men platser kan vara länkade, t.ex. länen
     */
    public function getLocationStringWithLinks() {

        $locations = [];

        $prioOneLocations = $this->locations->where("prio", 1);

        if ($prioOneLocations->count()) {
            foreach ($prioOneLocations as $oneLocation) {

                $locations[] = sprintf(
                    '<a href="%2$s">%1$s</a>',
                    title_case($oneLocation->name),
                    route("platsSingle", ["plats" => $oneLocation->name])
                );

            }
        }

        if ($this->parsed_title_location) {

            //$locations[] = $this->parsed_title_location;
            $locations[] = sprintf(
                '<a href="%2$s">%1$s</a>',
                $this->parsed_title_location,
                route("platsSingle", ["plats" => $this->parsed_title_location])
            );

        }

        if ($this->administrative_area_level_1 && $this->administrative_area_level_1 !== $this->parsed_title_location) {

            $locations[] = sprintf(
                '<a href="%2$s">%1$s</a>',
                $this->administrative_area_level_1,
                route("lanSingle", ["lan" => $this->administrative_area_level_1])
            );

        }

        $location = implode(", ", $locations);

        return $location;

    }

    // from http://cubiq.org/the-perfect-php-clean-url-generator
    // @TODO: put in global helper
    public function toAscii($str, $replace=array(), $delimiter='-') {

    	if( !empty($replace) ) {
    		$str = str_replace((array)$replace, ' ', $str);
    	}

    	$clean = iconv('UTF-8', 'ASCII//TRANSLIT', $str);
    	$clean = preg_replace("![^a-zA-Z0-9/_|+ -]!", '', $clean);
    	$clean = strtolower(trim($clean, '-'));
    	$clean = preg_replace("![/_|+ -]+!", $delimiter, $clean);

    	return $clean;
    }

    /**
     * @TODO: shorten!
     * @return string
     */
    public function getMetaDescription($length = 155) {

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
    public function getDescription() {

        $text = $this->description;

        $text = $this->autop($text);

        return $text;

    }

    /**
     * Get the description
     */
    public function getParsedContent() {

        $text = $this->parsed_content;

        $text = $this->autop($text);

        return $text;

    }

    /**
     * Get the description, for overview pages, where text is cropped after nn chars
     * and styles removed to not interfere in listing
     */
    public function getParsedContentTeaser($length = 160) {

        $text = $this->parsed_content;

        // strip tags but make sure there is at least a space where the tag was
        // so text paragraphs don't collapse
        $text = strip_tags(str_replace('<', ' <', $text));

        $text = str_limit($text, $length);

        // $text = $this->autop($text);

        return $text;

    }

    /**
     * Kinda like wp's autop function
     * replaces newlines with paragraphs, removes duplicate <br>:s and so on
     */
    public function autop($text) {

        // replace <br> with new line
        $text = str_replace("<br>", "\n", $text);

        // wrap paragraphs around each line
        $text = '<p>' . preg_replace('/[\r\n]+/', '</p><p>', $text) . '</p>';

        // remove duplicate <br>
        $text = preg_replace('/(<br>)+/', '<br>', $text);

        return $text;

    }

    public static function getEventsNearLocation($lat, $lng, $nearbyCount, $nearbyInKm) {

        $events = CrimeEvent::selectRaw('*, ( 6371 * acos( cos( radians(?) ) * cos( radians( location_lat ) ) * cos( radians( location_lng ) - radians(?) ) + sin( radians(?) ) * sin( radians( location_lat ) ) ) ) AS distance', [ $lat, $lng, $lat ])
        ->having("distance", "<=", $nearbyInKm) // välj de som är rimligt nära, värdet är i km
        ->orderBy("parsed_date", "DESC")
        ->orderBy("distance", "ASC")
        ->limit($nearbyCount)
        ->get();

        return $events;

    }

    /**
     * Return some debug stuff if a query contains some debugActions
     *
     * @return array
     */
    protected function maybeAddDebugData(\Illuminate\Http\Request $request, CrimeEvent $event) {

        $data = [];

        $debugActions = (array) $request->input("debugActions");

        if (!$debugActions) {
            return $data;
        }

        if ( in_array("getLocations", $debugActions) ) {

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

    public function getSingleEventTitle() {

        $title = "";
        $titleParts = [];


        $titleParts[] = $this->parsed_title;

        $prioOneLocations = $this->locations->where("prio", 1);

        foreach ($prioOneLocations as $oneLocation) {
            $titleParts[] = title_case($oneLocation->name);
        }

        $titleParts[] = $this->parsed_title_location;
        $titleParts[] = $this->getPubDateFormatted('%d %B %Y');

        $title = implode(", ", $titleParts);

        return $title;

    }

}
