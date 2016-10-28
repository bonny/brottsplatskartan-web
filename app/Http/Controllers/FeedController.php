<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests;
use Feeds;
use App\CrimeEvent;

use App\Http\Controllers\FeedParserController;

class FeedController extends Controller
{

    protected $RssURL = 'https://polisen.se/Stockholms_lan/Aktuellt/Handelser/Handelser-i-hela-landet/?feed=rss';
    protected $feedParser;

    public function __construct(FeedParserController $feedParser) {

        $this->feedParser = $feedParser;

    }

    public function getGeocodeURL($itemID) {

        $item = CrimeEvent::findOrFail($itemID);
        $itemLocations = $item->locations;

        $apiUrlTemplate = 'https://maps.googleapis.com/maps/api/geocode/json?key=AIzaSyBNGngVsHlVCo4D26UnHyp3nqcgFa-HEew&language=sv';
        $apiUrlTemplate .= '&components=country:SE';
        $apiUrlTemplate .= '&address=%1$s';

        $strLocationURLPart = "";
        foreach ( $itemLocations as $location ) {
            $strLocationURLPart .= ", " . $location->name;
        }

        // append main location
        $strLocationURLPart .= ", " . $item->parsed_title_location;
        $strLocationURLPart = trim($strLocationURLPart, ", ");

        $strLocationURLPartBeforeUrlEncode = $strLocationURLPart;

        $apiUrl = sprintf($apiUrlTemplate, urlencode($strLocationURLPart));

        return $apiUrl;

    }

    public function geocodeItem($itemID) {

        $apiUrl = $this->getGeocodeURL($itemID);

        #echo "\ngeocoding item with title " . $item->title;
        #echo "\naddress is $strLocationURLPartBeforeUrlEncode";
        #echo "\napiURL:\n$apiUrl\n";

        $result_data = json_decode( file_get_contents($apiUrl) );
        $result_status = $result_data->status;
        $result_results = $result_data->results;
        #print_r($result_results);
        // echo "\nstatus code: $result_status";
        if ($result_results === "OK") {
            return false;
        }

        $geometry_location_lat = null;
        $geometry_location_lng = null;

        foreach ( $result_results as $one_result ) {

            $geometry_location = $one_result->geometry->location;
            $geometry_location_lat = $geometry_location->lat;
            $geometry_location_lng = $geometry_location->lng;

            // location_type stores additional data about the specified location.
            $geometry_type = $one_result->geometry->location_type;

            // viewport contains the recommended viewport for displaying the returned result, specified as two latitude,longitude values defining the southwest and northeast corner of the viewport bounding box. Generally the viewport is used to frame a result when displaying it to a user.
            $geometry_viewport = $one_result->geometry->viewport;

            $geometry_address_components = $one_result->address_components;

            $administrative_area_level_1 = null;
            $administrative_area_level_2 = null;

            foreach ($geometry_address_components as $key => $val) {
                if ( in_array("administrative_area_level_1", $val->types) ) {
                    $administrative_area_level_1 = $val->long_name;
                    break;
                }
            }

            foreach ($geometry_address_components as $key => $val) {
                if ( in_array("administrative_area_level_2", $val->types) ) {
                    $administrative_area_level_2 = $val->long_name;
                    break;
                }
            }

            #echo "\ngeometry_location: " . print_r($geometry_address_components, 1);exit;
            #echo "\ngeometry_type: " . print_r($geometry_type, 1);
            #echo "\ngeometry_viewport: " . print_r($geometry_viewport, 1);
            #exit;
            #echo "\nlat: $geometry_location_lat";
            #echo "\nlng: $geometry_location_lng";

            // only return first
            break;

        }
        #print_r($result_results);

        if ($geometry_location_lat) {
            $item->location_lat = $geometry_location_lat;
            $item->location_lng = $geometry_location_lng;
            $item->location_geometry_type = $geometry_type;
            $item->viewport_northeast_lat = $geometry_viewport->northeast->lat;
            $item->viewport_northeast_lng = $geometry_viewport->northeast->lng;
            $item->viewport_southwest_lat = $geometry_viewport->southwest->lat;
            $item->viewport_southwest_lng = $geometry_viewport->southwest->lng;
            #= json_encode($geometry_viewport, JSON_PRETTY_PRINT);
            $item->administrative_area_level_1 = $administrative_area_level_1;
            $item->administrative_area_level_2 = $administrative_area_level_2;
            $item->geocoded = true;
            $item->save();
            #echo "\nadded location for item";
        }

        #exit;

    }

    /**
     * Parse an item
     * Fetches remote info
     * and finds locations/street names in the text
     */
    public function parseItem($itemID) {

        $item = CrimeEvent::findOrFail($itemID);

        // Parse title
        $parsed_title_items = $this->feedParser->parseTitle($item->title);
        $item->fill($parsed_title_items);
        $item->save();

        // Parse permalink, i.e. get info from remote and store
        $parsed_content_items = $this->feedParser->parseContent($item->permalink);
        $item->fill($parsed_content_items);
        $item->save();

        // Find possible locations in teaser and content
        $locationsByPrio = $this->feedParser->findLocations($item);

        foreach ( $locationsByPrio as $locations) {

            foreach ($locations["locations"] as $locationName) {

                // Add location of not already added
                #$item->fresh(['locations']);
                if ( $item->locations->contains("name", $locationName) ) {

                    // echo "\nskipping, location already added $locationName";

                } else {

                    // echo "\nadding location $locationName";

                    $locationModel = new \App\Locations([
                        "name" => $locationName,
                        "prio" => $locations["prio"],
                    ]);

                    $item->locations()->save($locationModel);

                    // we must reload locations so ->contains() will work in the next loop
                    $item->load('locations');
                }

            }

        }

        $item->scanned_for_locations = true;
        $item->save();

    }

    /*
    Feeds use https://github.com/willvincent/feeds
    */
    public function updateFeedsFromPolisen()
    {

        $feed = \Feeds::make($this->RssURL);
        $feed_items = $feed->get_items();

        $data = [
            "numItemsAdded" => 0,
            "numItemsAlreadyAdded" => 0,
            "itemsAdded" => []
        ];

        foreach ($feed_items as $item) {

            $item_md5 = md5($item->get_id());

            $item_data = [
                "title" => $item->get_title(),
                "description" => html_entity_decode( $item->get_description() ),
                "permalink" => $item->get_permalink(),
                "pubdate" => $item->get_date("U"),
                "pubdate_iso8601" => $item->get_date(\DateTime::ISO8601),
                "md5" => $item_md5,
            ];

            // Store items not already stored
            $existingItem = CrimeEvent::where("md5", $item_md5)->get();

            if ($existingItem->count()) {
                $data["numItemsAlreadyAdded"]++;
                continue;
            }

            // $data["itemsAdded"][] = $item_data;
            $data["numItemsAdded"]++;

            $event = CrimeEvent::create($item_data);
            $data["itemsAdded"][] = $event;

        }

        return $data;

    }

}
