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

        // Find locations in content
        $highwaysFile = resource_path() . "/openstreetmap/highways_sorted_unique.txt";
        $citiesFile   = resource_path() . "/openstreetmap/swedish-cities-sorted-unique.txt";

        #$highwayItems = explode("\n", file_get_contents($highwaysFile));
        #$citiesItems  = explode("\n", file_get_contents($citiesFile));
        $highwayItems = file($highwaysFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $citiesItems  = file($citiesFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        echo "<br>laddade in " . count($highwayItems) . " gator";
        echo "<br>laddade in " . count($citiesItems) . " orter";

        // trim items and make lowercase
        $highwayItems = array_map("trim", $highwayItems);
        $highwayItems = array_map("mb_strtolower", $highwayItems);

        $citiesItems = array_map("trim", $citiesItems);
        $citiesItems = array_map("mb_strtolower", $citiesItems);

        // remove short items
        $highwayItems = array_filter($highwayItems, function($val) {
            return (mb_strlen($val) > 4);
        });

        // ta bort lite för vanliga ord från highwayitems, t.ex. "träd" och "vägen" var lite för generalla
        $highwaysStopWords = [
            "träd",
            "vägen",
            "polisen",
            "polis",
            "platsen",
            "västra",
            "kommer",
            "något",
            "ringa",
            "polisstationen",
        ];
        $highwayItems = array_where($highwayItems, function($val, $key) use ($highwaysStopWords) {
            return ! in_array($val, $highwaysStopWords);
        });

        echo "<br>laddade in " . count($highwayItems) . " gator efter tagit bort stop words";
        echo "<br>laddade in " . count($citiesItems) . " orter efter tagit bort stop words";

        // gå igenom alla gator och leta efter träff i original description + parsed_content
        $matchingHighwayItems = array_where($highwayItems, function($val, $key) use ($item) {

            // matcha hela ord bara
            // utan \b matchar t.ex. "Vale" -> "Valeborgsvägen" men med \b så blir det inte match
            // dock blir det fortfarande träff på "Södra" -> "Södra Särövägen"
            // /i = PCRE_CASELESS
            // /u = PCRE_UTF8, fixade så att \b inte gav träff "Sö" för "Södra", utan att åäö blev del av ord
            // \m = PCRE_MULTILINE, hittade inte på annat än rad 1 annars
            $regexp = '/\b' . preg_quote($val, '/') . '\b/ium';

            return preg_match($regexp, $item->description) || preg_match($regexp, $item->parsed_content);

        });

        /*
        Nu har vi förhoppningsvis hittat minst 1 träff
        Finns flera träffar så beror det på att den träffar på delar av namn

            Array
            (
                [119677] => Särö <- ska bort!
                [120541] => Södra
                [121237] => Södra Särövägen
                [131899] => Valebergsvägen
            )

        */
        echo "<br>hittade " . count($matchingHighwayItems) . " orter som matchade";
        echo "<pre>" . print_r($matchingHighwayItems, 1) . "</pre>";
        /*
        $array = [100, '200', 300, '400', 500];

        $array = array_where($array, function ($value, $key) {
            return is_string($value);
        });
        */

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

            $data["itemsAdded"][] = $item_data;
            $data["numItemsAdded"]++;

            $event = CrimeEvent::create($item_data);

        }

        return $data;

    }

}
