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

    private function geocodeItem($itemID) {

        $item = CrimeEvent::findOrFail($itemID);
        $itemLocations = $item->locations;

        $apiURL = 'https://maps.googleapis.com/maps/api/geocode/outputFormat?key=AIzaSyBNGngVsHlVCo4D26UnHyp3nqcgFa-HEew&language=sv';
        $apiURL .= '&components=country:SE';
        $apiURL .= '&address=';

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
                if ( $item->locations->contains("name", $locationName) ) {
                    // echo "<br>location already added";
                } else {

                    $locationModel = new \App\Locations([
                        "name" => $locationName,
                        "prio" => $locations["prio"],
                    ]);

                    $item->locations()->save($locationModel);
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
