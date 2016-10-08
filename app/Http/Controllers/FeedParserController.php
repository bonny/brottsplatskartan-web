<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests;
use Feeds;
use App\CrimeEvent;
use Goutte\Client;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class FeedParserController extends Controller
{

    // The loaded highway items
    var $highwayItems = null;

    // The loaded cities items
    var $citiesItems = null;

    /*

    Title

    Verkar vara uppbyggd på samma sätt överallt
    - hämta datum
    - hämta typ av händelse
    - hämta plats (område)

    Exempel:
    - 2016-10-03 21:07, Rån, Göteborg
    - 2016-10-03 17:01, Rattfylleri, Boden
    - 2016-10-03 16:51, Trafikolycka, Sölvesborg
    - 2016-10-03 17:03, Trafikolycka, personskada, Skara
    - 2016-10-03 20:04, Rattfylleri, Boxholm

    */
    public function parseTitle( $title ) {

        $arrTitleParts = explode("," , $title);

        $returnParts = [
            "parsed_date" => null,
            "parsed_title" => null,
            "parsed_title_location" => null
        ];

        if ( count($arrTitleParts) < 3 ) {
            return $returnParts;
        }

        $returnParts["parsed_date"] = array_shift($arrTitleParts);
        $returnParts["parsed_title_location"] = array_pop($arrTitleParts);
        $returnParts["parsed_title"] = implode(", ", $arrTitleParts);

        $returnParts = array_map("trim", $returnParts);

        $returnParts["parsed_date"] = date("Y-m-d H:i:s", strtotime($returnParts["parsed_date"]));

        return $returnParts;

    }

    /**
     * Get info from remote, i.e. from the details page at polisen.se
     * Info exists in div with id #column2-3
     *
     * We find "parsed_teaser" and "parsed_content" here
     *
     * @return array
     */
    public function parseContent( $contentURL ) {

        #echo "<br>contentURL: $contentURL";
        $returnParts = [
            "parsed_teaser" => "",
            "parsed_content" => ""
        ];

        $cacheKey = md5( $contentURL . "_cachebust1");
        $html = Cache::get($cacheKey, false);

        if (! $html || gettype($html) != "string") {

            #echo "<br>store in cache";

            $client = new Client();
            $crawler = $client->request('GET', $contentURL);

            // get content inside #column2-3
            $crawler = $crawler->filter('#column2-3');
            $html = $crawler->html();
            Cache::put($cacheKey, $html, 30);

        } else {
            #echo "<br>get cached";
        }

        // the content we want starts after
        // <!--googleon: all-->
        $arrHtml = explode("<!--googleon: all-->", $html);
        $html = array_pop($arrHtml);

        // find .ingress
        // fins everything afer .ingress until #pagefooter
        libxml_use_internal_errors(true);
        $htmlDoc = new \DOMDocument();
        $htmlDoc->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();

        $ingress = $htmlDoc->getElementsByTagName("*");
        $div = $htmlDoc->getElementsByTagName("div");

        foreach ($ingress as $item) {
            if ( "ingress" == $item->getAttribute("class") ) {
                $returnParts["parsed_teaser"] = trim($item->nodeValue);
                break;
            }
        }

        foreach ($div as $item) {
            if ( "pagefooter" == $item->getAttribute("id") ) {
                // skip footer
            } else {
                $returnParts["parsed_content"] .= $htmlDoc->saveHTML($item);
            }
        }

        $returnParts["parsed_content"] = strip_tags($returnParts["parsed_content"], "<br><strong>");
        $returnParts["parsed_content"] = trim($returnParts["parsed_content"]);

        return $returnParts;

    } // function


    private function loadHighways() {

        if (isset($this->highwayItems)) {
            return $highwayItems;
        }

        $starttime = microtime(true);

        $highwaysFile = resource_path() . "/openstreetmap/highways_sorted_unique.txt";
        $highwayItems = file($highwaysFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        Log::info('Loaded highwayitems', ["count", count($highwayItems)]);

        // trim items and make lowercase
        $highwayItems = array_map("trim", $highwayItems);
        $highwayItems = array_map("mb_strtolower", $highwayItems);

        // remove short items
        $highwayItems = array_filter($highwayItems, function($val) {
            return (mb_strlen($val) > 4);
        });

        // ta bort lite för vanliga ord från highwayitems, t.ex. "träd" och "vägen" känns lite för generalla
        $highwaysStopWords = $this->getHighwaysStopwords();
        $highwayItems = array_where($highwayItems, function($val, $key) use ($highwaysStopWords) {
            return ! in_array($val, $highwaysStopWords);
        });

        $timetaken = microtime(true) - $starttime;

        Log::info('Loaded highwayitems, after clean and stop words removed', ["count", count($highwayItems)]);
        Log::info('highwayitems, load done', ["time in s", $timetaken]);

        $this->highwayItems = $highwayItems;

        return $highwayItems;

    }

    private function getHighwaysStopwords() {

        return [
            "träd", "butik", "brottet", "skolan", "anslutning", "gripen",
            "vägen", "istället", "plattformen",
            "polisen",
            "polis",
            "platsen",
            "västra",
            "kommer",
            "något",
            "ringa",
            "polisstationen",
            "räddningstjänsten",
            "under", "fordon", "patrullen", "information"
        ];

    }

    private function loadCities() {

        if (isset($this->citiesItems)) {
            return $citiesItems;
        }

        // Find locations in content
        $citiesFile   = resource_path() . "/openstreetmap/swedish-cities-sorted-unique.txt";
        $citiesItems  = file($citiesFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        $citiesItems = array_map("trim", $citiesItems);
        $citiesItems = array_map("mb_strtolower", $citiesItems);

        $this->citiesItems = $citiesItems;

        return $citiesItems;

    }



    public function findLocations($item) {

        $highwayItems = $this->loadHighways();
        $citiesItems = $this->loadCities();

        $starttime = microtime(true);

        // gå igenom alla gator och leta efter träff i original description + parsed_content
        // beskrivning av regex:
        // matcha hela ord bara
        // utan \b matchar t.ex. "Vale" -> "Valeborgsvägen" men med \b så blir det inte match
        // dock blir det fortfarande träff på "Södra" -> "Södra Särövägen"
        // /i = PCRE_CASELESS
        // /u = PCRE_UTF8, fixade så att \b inte gav träff "Sö" för "Södra", utan att åäö blev del av ord
        // \m = PCRE_MULTILINE, hittade inte på annat än rad 1 annars

        $matchingHighwayItemsInDescription = array_where($highwayItems, function($val, $key) use ($item) {
            $highwaysRegex = '/\b' . preg_quote($val, '/') . '\b/ium';
            return preg_match($highwaysRegex, $item->description);
        });

        $matchingHighwayItemsInContent = array_where($highwayItems, function($val, $key) use ($item) {
            $highwaysRegex = '/\b' . preg_quote($val, '/') . '\b/ium';
            return preg_match($highwaysRegex, $item->parsed_content);
        });

         // || preg_match($highwaysRegex, $item->parsed_content);

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
        #echo "<br>hittade " . count($matchingHighwayItems) . " orter som matchade";
        #echo "<pre>matcher i description/teaser (prio 1)\n" . print_r($matchingHighwayItemsInDescription, 1) . "</pre>";
        #echo "<pre>matcher i content (prio 2)\n" . print_r($matchingHighwayItemsInContent, 1) . "</pre>";

        $timetaken = microtime(true) - $starttime;
        Log::info('find locations done', ["time in s", $timetaken]);

        return [
            [
                "prio" => 1,
                "locations" => $matchingHighwayItemsInDescription
            ],
            [
                "prio" => 2,
                "locations" => $matchingHighwayItemsInContent
            ]
        ];

        /*
        $array = [100, '200', 300, '400', 500];

        $array = array_where($array, function ($value, $key) {
            return is_string($value);
        });
        */

    }

}
