<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests;
use Feeds;
use App\CrimeEvent;

use App\Http\Controllers\FeedParserController;

class FeedController extends Controller
{

    protected $RssURL;
    protected $feedParser;

    public function __construct(FeedParserController $feedParser)
    {
        // URL innan Polisen ändrade sin webbplats
        // 22 Feb 2018
        # $this->RssURL = 'https://polisen.se/Stockholms_lan/Aktuellt/Handelser/Handelser-i-hela-landet/?feed=rss';
        $this->RssURL = 'https://polisen.se/aktuellt/rss/hela-landet/handelser-i-hela-landet/';

        $this->RssURL = \App\Helper::makeUrlUsePolisenDomain($this->RssURL);

        $this->feedParser = $feedParser;
    }

    /**
     * Hämta URL för att geocoda ett feed item.
     *
     * @param int $itemID $itemID Item.
     *
     * @return string URL för geocode.
     */
    public function getGeocodeURL($itemID)
    {
        $item = CrimeEvent::findOrFail($itemID);
        $itemLocations = $item->locations;
        $googleApiKey = getenv('GEOCODE_GOOGLE_APIKEY');

        $apiUrlTemplate = 'https://maps.googleapis.com/maps/api/geocode/json?key=' . $googleApiKey . '&language=sv';
        $apiUrlTemplate .= '&components=country:SE';
        $apiUrlTemplate .= '&address=%1$s';

        $strLocationURLPart = "";

        foreach ( $itemLocations as $location ) {
            $strLocationURLPart .= ", " . $location->name;
        }

        // append main location, from title
        $strLocationURLPart .= ", " . $item->parsed_title_location;
        $strLocationURLPart = trim($strLocationURLPart, ", ");

        $strLocationURLPartBeforeUrlEncode = $strLocationURLPart;

        $apiUrl = sprintf(
            $apiUrlTemplate,
            urlencode($strLocationURLPart) // 1
        );

        return $apiUrl;
    }

    /**
     * Geocode an crime event
     *
     * @param int $itemID ID of crime event to geovode.
     * @return array with info if, key [error] = false is ok, [error] = true if error, [message] with error message
     */
    public function geocodeItem($itemID) {

        $item = CrimeEvent::findOrFail($itemID);
        $apiUrl = $this->getGeocodeURL($itemID);

        $result_data = json_decode(file_get_contents($apiUrl));
        $result_status = $result_data->status;
        $result_results = $result_data->results;

        if ($result_status !== "OK") {
            return [
                'error' => true,
                'error_message' => "itemID: {$itemID}\nstatus: {$result_status}\nurl: {$apiUrl}"
            ];
        }

        $geometry_location_lat = null;
        $geometry_location_lng = null;
        $types = null;

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

            $types = $one_result->types;

            // only return first matching place
            break;

        }

        // Non ok is if types contains "Country" because then we have a really zoomed out location.
        // if bad location then fallback to only using
        $valid_good_location = ! empty($geometry_location_lat) && ! in_array("country", $types);

        // If ok location then add
        if ($valid_good_location) {

            $item->location_lat = $geometry_location_lat;
            $item->location_lng = $geometry_location_lng;

            $item->location_geometry_type = $geometry_type;

            $item->viewport_northeast_lat = $geometry_viewport->northeast->lat;
            $item->viewport_northeast_lng = $geometry_viewport->northeast->lng;
            $item->viewport_southwest_lat = $geometry_viewport->southwest->lat;
            $item->viewport_southwest_lng = $geometry_viewport->southwest->lng;

            $item->administrative_area_level_1 = $administrative_area_level_1;
            $item->administrative_area_level_2 = $administrative_area_level_2;

            $item->geocoded = true;

            $item->save();

        } else {

            // location not so good, fallback to checking prio 3 location = location found in text "Polisen nnn"
            $prioThreeLocation = $item->locations->where("prio", 3)->first();
            if ($prioThreeLocation) {
                $fallbackLocation = $prioThreeLocation->name;
                if ($item->parsed_title_location) {
                    $fallbackLocation = "{$item->parsed_title_location}, $fallbackLocation";
                    // echo $fallbackLocation;exit;
                }
                $this->geocodeItemFallbackVersion($itemID, $fallbackLocation);
            }

        }

        return [
            'error' => false
        ];

    }

    public function geocodeItemFallbackVersion($itemID, $fallbackLocation) {

        $item = CrimeEvent::findOrFail($itemID);

        $apiUrlTemplate = 'https://maps.googleapis.com/maps/api/geocode/json?key=' . getenv('GOOGLE_API_KEY') . '&language=sv';
        $apiUrlTemplate .= '&components=country:SE';
        $apiUrlTemplate .= '&address=%1$s';

        $apiUrl = sprintf(
            $apiUrlTemplate,
            urlencode($fallbackLocation) // 1
        );

        $result_data = json_decode(file_get_contents($apiUrl));
        // echo "in geocodeItemFallbackVersion, apiurl:\n$apiUrl";exit;
        $result_status = $result_data->status;
        $result_results = $result_data->results;

        if ($result_results === "OK") {
            return false;
        }

        $geometry_location_lat = null;
        $geometry_location_lng = null;
        $types = null;

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

            $types = $one_result->types;

            // only return first matching place
            break;

        }

        // Non ok is if types contains "Country" because then we have a really zoomed out location.
        // if bad location then fallback to only using
        $valid_good_location = ! empty($geometry_location_lat);

        // If ok location then add
        if ($valid_good_location) {

            $item->location_lat = $geometry_location_lat;
            $item->location_lng = $geometry_location_lng;

            $item->location_geometry_type = $geometry_type;

            $item->viewport_northeast_lat = $geometry_viewport->northeast->lat;
            $item->viewport_northeast_lng = $geometry_viewport->northeast->lng;
            $item->viewport_southwest_lat = $geometry_viewport->southwest->lat;
            $item->viewport_southwest_lng = $geometry_viewport->southwest->lng;

            $item->administrative_area_level_1 = $administrative_area_level_1;
            $item->administrative_area_level_2 = $administrative_area_level_2;

            $item->geocoded = true;

            $item->save();
        } else {
            // no fallback beacuse this *is* the fallback :)
        }
    }

    /**
     * Get item content from polisen.se and parse it if contents was updated
     * and save changes to crime event.
     *
     * @param int $itemID Crime event id
     * @return string Status NOT_CHANGED, ERROR, CHANGED
     */
    public function parseItemContentAndUpdateIfChanges($itemID)
    {
        $item = CrimeEvent::findOrFail($itemID);

        $parsed_content_items = $this->feedParser->parseContent($item->permalink);

        if ($parsed_content_items === false) {
            return 'ERROR';
        }

        // We got remote contents, but are they new or same as old?
        if ($parsed_content_items['parsed_teaser'] == $item['parsed_teaser'] && $parsed_content_items['parsed_content'] == $item['parsed_content']) {
            return 'NOT_CHANGED';
        }

        $item->fill($parsed_content_items);
        $item->save();

        return 'CHANGED';
    }

    /**
     * Find locations in crime event and save
     *
     * @param int $itemID Crime event ID
     * @return bool
     */
    public function parseItemForLocations($itemID)
    {
        $item = CrimeEvent::findOrFail($itemID);
        $locationsByPrio = $this->feedParser->findLocations($item);

        foreach ($locationsByPrio as $locations) {
            foreach ($locations["locations"] as $locationName) {
                // Add location of not already added
                if ($item->locations->contains("name", $locationName)) {
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

        return true;
    }

    /**
     * Parse an item/event:
     * - Fetches remote info from polisen.se
     * - Finds locations/street names in the text
     *
     * @param int $itemID Crime event id
     * @return Bool true on success, false on fail
     */
    public function parseItem($itemID)
    {
        $item = CrimeEvent::findOrFail($itemID);
        // Parse title
        $parsed_title_items = $this->feedParser->parseTitle($item->title);
        $item->fill($parsed_title_items);
        $item->save();

        // Parse permalink, i.e. get info from remote and store
        // This can be called a bit later to check if item has remote updates
        $itemContentsWasUpdated = $this->parseItemContentAndUpdateIfChanges($itemID);
        // dd('itemContentsWasUpdated', $itemContentsWasUpdated);

        // If contents was not changed bail
        if ($itemContentsWasUpdated == 'NOT_CHANGED' && $itemContentsWasUpdated == 'ERROR') {
            return false;
        }

        // Find and save locations in teaser and content
        $this->parseItemForLocations($itemID);

        return true;
    }

    /**
     * Hämtar RSS-feeden från Polisen
     * och lägger till händelser i DB
     *
     * Uses https://github.com/willvincent/feeds
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
            // Previously we used get_id for md5 but
            // after Polisen relaunched their site sometimes
            // we get duplicates. Try to solve this by
            // using permalink instead.
            $item_md5 = md5($item->get_id());
            $item_md5_permalink = md5($item->get_permalink());

            $item_data = [
                "title" => $item->get_title(),
                "description" => html_entity_decode($item->get_description()),
                "permalink" => $item->get_permalink(),
                "pubdate" => $item->get_date("U"),
                "pubdate_iso8601" => $item->get_date(\DateTime::ISO8601),
                "md5" => $item_md5_permalink,
            ];

            // Store items not already stored
            $existingItem = CrimeEvent::
                where("md5", $item_md5)
                ->orWhere("md5", $item_md5_permalink)
                ->get();

            #dd($existingItem);exit;

            // Continue to next item if event already is in db
            if ($existingItem->count()) {
                $data["numItemsAlreadyAdded"]++;
                continue;
            }

            $data["numItemsAdded"]++;

            $event = CrimeEvent::create($item_data);
            $data["itemsAdded"][] = $event;
        }

        return $data;
    }
}
