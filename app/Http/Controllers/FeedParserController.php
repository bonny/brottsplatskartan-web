<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests;
use Feeds;
use App\CrimeEvent;
use Goutte\Client;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\highways_ignored;

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

    Undantag:
    Uppsala hade en gång en artikel med följande titel, som gjorde att parsed date blev null
    - Uppdatering: Misshandel på Uppsalaskola
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

        $cacheKey = md5( $contentURL . "_cachebust3");
        $html = Cache::get($cacheKey, false);

        if (! $html || gettype($html) != "string") {

            #echo "<br>store in cache";

            $client = new Client();
            $crawler = $client->request('GET', $contentURL);
            //$crawler = $client->request('GET', "http://polisen.se/fourohfour");

            // get content inside #column2-3
            $crawler = $crawler->filter('#column2-3');
            if ($crawler->count()) {
                $html = $crawler->html();
            } else {
                // no elements found, maybe a 404 page
                // did happen once and then polisen had removed the permalink for that page
                $html = '';
            }
            
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
        // fix one or multiple <p>&nbsp;</p> that causes long "line breaks"
        // @TODO: get it to work...

        return $returnParts;

    } // function


    private function loadHighways() {

        if (isset($this->highwayItems)) {
            return $this->highwayItems;
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

        // add some manual roads that i've found missing
        $highwayItems = array_merge($highwayItems, $this->getHighwaysAddedManually());

        $timetaken = microtime(true) - $starttime;

        Log::info('Loaded highwayitems, after clean and stop words removed', ["count", count($highwayItems)]);
        Log::info('highwayitems, load done', ["time in s", $timetaken]);

        $this->highwayItems = $highwayItems;

        return $highwayItems;

    }

    function getHighwaysAddedManually() {

        return [
            "brantholmsgränd",
            "sahlgrenska sjukhuset"
        ];

    }


    /**
     * Get streenames etc that are not valid streetnames
     */
    public function getHighwaysStopwords() {

        $ignored_highways = highways_ignored::all()->pluck("name")->toArray();

        return $ignored_highways;

    }

    private function loadCities() {

        if (isset($this->citiesItems)) {
            return $this->citiesItems;
        }

        // Find locations in content
        $citiesFile   = resource_path() . "/openstreetmap/swedish-cities-sorted-unique.txt";
        $citiesItems  = file($citiesFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        $citiesItems = array_map("trim", $citiesItems);
        $citiesItems = array_map("mb_strtolower", $citiesItems);

        $this->citiesItems = $citiesItems;

        return $citiesItems;

    }


    /**
     * Check if item teaser or content contains street names
     */
    public function findLocations($item) {

        // all street names as a huge array
        $highwayItems = $this->loadHighways();
        #$citiesItems = $this->loadCities();

        $starttime = microtime(true);

        // gå igenom alla gator och leta efter träff i original description + parsed_content
        // beskrivning av regex:
        // matcha hela ord bara
        // utan \b matchar t.ex. "Vale" -> "Valeborgsvägen" men med \b så blir det inte match
        // dock blir det fortfarande träff på "Södra" -> "Södra Särövägen"
        // /i = PCRE_CASELESS
        // /u = PCRE_UTF8, fixade så att \b inte gav träff "Sö" för "Södra", utan att åäö blev del av ord
        // \m = PCRE_MULTILINE, hittade inte på annat än rad 1 annars

        $item_description = $item->description;
        $item_parsed_content = $item->parsed_content;

        // Split item description into words

        // this one, with utf8_decode, works on on local, breaks whole words with åäö inside
        #$arr_description_words = str_word_count( utf8_decode($item_description), 1, "0123456789åäöÅÄÖ");
        // this was the solution that works BOTH on dev and live:
        // http://stackoverflow.com/questions/8109997/supporting-special-characters-with-str-word-count
        preg_match_all('/\pL+/u', $item_description, $matches);
        $arr_description_words = $matches[0];

        #$arr_description_words = array_map("utf8_encode", $arr_description_words);
        $arr_description_words = array_map("mb_strtolower", $arr_description_words);

        // Remove "Polisen Värmland" etc that's the last line in the content words
        $police_lan = "";
        $parsed_content_lines = explode("\n", $item_parsed_content);
        if ( false !== strpos($parsed_content_lines[count($parsed_content_lines)-1], "Polisen ") ) {
            #echo "\nfound polisen at last line";
            $police_lan = array_pop($parsed_content_lines);
            $police_lan = str_replace("Polisen ", "", $police_lan);
            #echo "\ntext before removal: $item_parsed_content";
            $item_parsed_content = implode($parsed_content_lines);
            #echo "\ntext after removal: $item_parsed_content";
            #echo "\n\n";
        }
        #exit;
        #print_r($parsed_content_lines);exit;

        // Split content into words
        #$arr_content_words = str_word_count( utf8_decode($item_parsed_content), 1, "0123456789");
        preg_match_all('/\pL+/u', $item_parsed_content, $matches);
        $arr_content_words = $matches[0];

        #$arr_content_words = array_map("utf8_encode", $arr_content_words);
        $arr_content_words = array_map("mb_strtolower", $arr_content_words);

        $matchingHighwayItemsInDescription = [];
        $matchingHighwayItemsInContent = [];

        // Check if each word[n] + word[n+1] matches a location in $highwayItems
        // we check both n and n + (n+1) so we match street names with 2 words
        for ($i = 0; $i < count($arr_description_words); $i++) {

            $word1 = $arr_description_words[$i];
            if ( in_array($word1, $highwayItems) ) {
                #echo "\nword 1 matched: $word1\n";
                $matchingHighwayItemsInDescription[] = $word1;
            }

            if ( $i < count($arr_description_words) - 1 ) {
                $word2 = $arr_description_words[$i] . " " . $arr_description_words[$i+1];
                if ( in_array($word2, $highwayItems) ) {
                    #echo "\nword 2 matched: $word2\n";
                    $matchingHighwayItemsInDescription[] = $word2;
                }
            }

            /*
            if ( $i < count($arr_description_words) - 2 ) {
                $word3 = $arr_description_words[$i] . " " . $arr_description_words[$i+1] . " " . $arr_description_words[$i+2];
                if ( in_array($word3, $highwayItems) ) {
                    #echo "\nword 3 matched: $word3\n";
                    $matchingHighwayItemsInDescription[] = $word3;
                }
            }
            */

        }

        #if ($matchingHighwayItemsInDescription) {
        #    print_r($matchingHighwayItemsInDescription);
        #}


        for ($i = 0; $i < count($arr_content_words); $i++) {

            $word1 = $arr_content_words[$i];
            if ( in_array($word1, $highwayItems) ) {
                #echo "\nword content 1 matched: $word1\n";
                $matchingHighwayItemsInContent[] = $word1;
            }

            if ( $i < count($arr_content_words) - 1 ) {
                $word2 = $arr_content_words[$i] . " " . $arr_content_words[$i+1];
                if ( in_array($word2, $highwayItems) ) {
                    #echo "\nword content 2 matched: $word2\n";
                    $matchingHighwayItemsInContent[] = $word2;
                }
            }

            /*
            if ( $i < count($arr_content_words) - 2 ) {
                $word3 = $arr_content_words[$i] . " " . $arr_content_words[$i+1] . " " . $arr_content_words[$i+2];
                if ( in_array($word3, $highwayItems) ) {
                    #echo "\nword content 3 matched: $word3\n";
                    $matchingHighwayItemsInContent[] = $word3;
                }
            }
            */

        }

        // remove hits that is the same as the main location found in the title, often city names
        $title_location = mb_strtolower($item->parsed_title_location);
        #echo "\n checking if title_location '$title_location' exists ";

        $matchingHighwayItemsInDescription = array_filter($matchingHighwayItemsInDescription, function($val) use ($title_location) {
            return ($val !== $title_location);
        });

        $matchingHighwayItemsInContent = array_filter($matchingHighwayItemsInContent, function($val) use ($title_location) {
            return ($val !== $title_location);
        });

        // remove duplicates
        $matchingHighwayItemsInDescription = array_unique($matchingHighwayItemsInDescription);
        $matchingHighwayItemsInContent = array_unique($matchingHighwayItemsInContent);


        // remove locations thats exists as part of other locations
        // for example if both "eriksgatan" and "sankt eriksgatan" are found
        // then remove "eriksgatan" because "sankt eriksgatan" is a better, more precise hit
        $matchingHighwayItemsInContent = array_filter($matchingHighwayItemsInContent, function($val) use ($matchingHighwayItemsInContent) {

            foreach ($matchingHighwayItemsInContent as $arrVal) {

                // dont't check current val
                if ($arrVal === $val) {
                    continue;
                }

                if (strpos(" " . $arrVal, $val) !== false || strpos($arrVal . " " , $val) !== false) {
                    // string was found, so remove
                    return false;
                }

            }

            return true;

        });

        $matchingHighwayItemsInDescription = array_filter($matchingHighwayItemsInDescription, function($val) use ($matchingHighwayItemsInDescription) {

            foreach ($matchingHighwayItemsInDescription as $arrVal) {

                // dont't check current val
                if ($arrVal === $val) {
                    continue;
                }

                if (strpos(" " . $arrVal, $val) !== false || strpos($arrVal . " " , $val) !== false) {
                    // string was found, so remove
                    return false;
                }

            }

            return true;

        });

        $timetaken = microtime(true) - $starttime;
        Log::info('find locations done', ["time in s", $timetaken]);

        return [
            [
                "prio" => 1,
                "locations" => $matchingHighwayItemsInDescription,
                "debug" => [
                    "mb_internal_encoding" => mb_internal_encoding(),
                    "iconv_get_encoding" => iconv_get_encoding(),
                    #"iconv" => iconv("UTF-8", "ISO-8859-1//TRANSLIT", $item_description),
                    "item_description" => $item_description,
                    "arr_description_words" => $arr_description_words,
                    "arr_content_words" => $arr_content_words,
                ],
                #"item_description_words" => str_word_count($item_description, 1),
                #"item_description_words_2" => str_word_count( utf8_decode( $item_description ), 1),
                #"item_description_words_3" => str_word_count( utf8_encode( $item_description ), 1),
            ],
            [
                "prio" => 2,
                "locations" => $matchingHighwayItemsInContent
            ],
            [
                "prio" => 3,
                "locations" => [$police_lan]
            ]
        ];


    }

}
