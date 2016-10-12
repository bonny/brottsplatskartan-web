<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

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
        'parsed_lng',
        'parsed_lat',
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
    public function getStaticImageSrc($width = 200, $height = 100) {

        $google_api_key = env("GOOGLE_API_KEY");

        $image_src = "https://maps.googleapis.com/maps/api/staticmap?";
        $image_src .= "key=$google_api_key";
        $image_src .= "&size={$width}x{$height}";

        // if viewport info exists use that and skip manual zoom level
        if ($this->location_geometry_viewport) {

            $viewport = json_decode($this->location_geometry_viewport);
            $image_src .= "&path=";
            $image_src .= "color:0x00000000|weight:5|fillcolor:0xFF660033";

            /*

            color:
            (optional) specifies a color either as a
            24-bit (example: color=0xFFFFCC) or 32-bit hexadecimal value (example: color=0xFFFFCCFF), or from the set {black, brown, green, purple, yellow, blue, gray, orange, red, white}.

            example from google api:
            path=color:0x00000000|weight:5|fillcolor:0xFFFF0033|8th+Avenue+%26+34th+St,New+York,NY|\
            8th+Avenue+%26+42nd+St,New+York,NY|Park+Ave+%26+42nd+St,New+York,NY,NY|\
            Park+Ave+%26+34th+St,New+York,NY,NY

            */

            $image_src .= "|{$viewport->northeast->lat},{$viewport->northeast->lng}";
            $image_src .= "|{$viewport->southwest->lat},{$viewport->northeast->lng}";

            $image_src .= "|{$viewport->southwest->lat},{$viewport->southwest->lng}";
            $image_src .= "|{$viewport->northeast->lat},{$viewport->southwest->lng}";
        } else {
            // no viewport, fallback to center
            $image_src .= "&center={$this->parsed_lat},{$this->parsed_lng}";
            $image_src .= "&zoom=14";
        }

        #echo "image: <img src='$image_src'>";
        #exit;

        // src="https://maps.googleapis.com/maps/api/staticmap?center={{ $event->parsed_lat }},{{ $event->parsed_lng }}&zoom=14&size=600x400&key=AIzaSyBNGngVsHlVCo4D26UnHyp3nqcgFa-HEew&markers={{ $event->parsed_lat }},{{ $event->parsed_lng }}"
        return $image_src;

    }

}
