<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

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

    public function getPubDateFormatted() {

        return Carbon::createFromTimestamp($this->pubdate)->formatLocalized('%A %d %B %Y');

    }

    public function getPubDateFormattedForHumans() {

        return Carbon::createFromTimestamp($this->pubdate)->diffForHumans();

    }

    /**
     * Returns a nice permalink to the page
     */
    public function getPermalink($absolute = false) {

        if ( ! empty($this->administrative_area_level_1) ) {
            $lan = $this->toAscii($this->administrative_area_level_1);
        } else {
            $lan = "sverige";
        }
        $eventName = $this->toAscii($this->parsed_title . "-" . $this->getKey());

        $permalink = route("singleEvent", [
            "lan" => $lan,
            "eventName" => $eventName
        ], $absolute);

        return $permalink;

    }

    public function getLocationString() {

        $locations = [];

        if ($this->parsed_title_location) {
            $locations[] = $this->parsed_title_location;
        }

        if ($this->administrative_area_level_1) {
            $locations[] = $this->administrative_area_level_1;
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

    public function getMetaDescription() {

        $desc = "";

        $desc .= $this->description;
        $desc .= $this->parsed_content;

        return $desc;

    }

}
