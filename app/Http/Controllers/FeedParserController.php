<?php

namespace App\Http\Controllers;

use App\CrimeEvent;
use App\highways_added;
use App\highways_ignored;
use App\Http\Requests;
use Carbon\Carbon;
use Feeds;
use Goutte\Client;
use HTMLPurifier;
use HTMLPurifier_Config;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Klass för att parsa polisens RSS-flöden.
 */
class FeedParserController extends Controller
{

    // The loaded highway items
    public $highwayItems = null;

    // The loaded cities items
    public $citiesItems = null;

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

    Sedan 22 Feb 2018 är formatet:
    - 22 februari 21.15, Rattfylleri, Sundsvall

    Ibland uppdateras händelserna och då är formatet:
    - Uppdaterad 02 april 16:24: 02 april 14:22, Polisinsats/kommendering, Varberg, id 51174

    Undantag:
    Uppsala hade en gång en artikel med följande titel, som gjorde att parsed date blev null
    - Uppdatering: Misshandel på Uppsalaskola
    */
    public function parseTitle($title)
    {
        /*
        array:3 [
            0 => "Uppdaterad 23 januari 15.12: 23 januari 13.17"
            1 => " Brand"
            2 => " Linköping"
        ]
        */
        $arrTitleParts = explode(",", $title);

        $returnParts = [
            "parsed_date" => null,
            "parsed_updated_date" => null, // Finns inte alltid.
            "parsed_title" => null,
            "parsed_title_location" => null
        ];

        // Bail om för få delar.
        if (count($arrTitleParts) < 3) {
            return $returnParts;
        }

        $returnParts["parsed_date"] = array_shift($arrTitleParts);
        $returnParts["parsed_title_location"] = array_pop($arrTitleParts);
        $returnParts["parsed_title"] = implode(", ", $arrTitleParts);

        // Kolla om datum innehåller uppdaterad-del också.
        if (preg_match('/^Uppdaterad /', $returnParts["parsed_date"])) {
            $parsedDateParts = explode(': ', $returnParts["parsed_date"]);
            /*
            array:2 [
                0 => "Uppdaterad 23 januari 15.12"
                1 => "23 januari 13.17"
            ]
            */
            if (sizeof($parsedDateParts) === 2) {
                $parsedDateParts[0] = str_replace('Uppdaterad ', '', $parsedDateParts[0]);
                $returnParts["parsed_date"] = $parsedDateParts[1];
                $returnParts["parsed_updated_date"] = $parsedDateParts[0];
            }
        }

        $returnParts = array_map("trim", $returnParts);

        // parsed_date = 22 februari 21.15
        $date = $returnParts["parsed_date"];
        $date = \App\Helper::convertSwedishYearsToEnglish($date);

        // Add comma ',' before time
        // @TODO: detta fungerar inte med datum/titlar som är uppdaterade, t.ex.:
        // "Uppdaterad 13 februari 09:11: 13 februari 08.07, Trafikolycka, Örebro, id 50155"

        #echo "\n-----";
        #echo "\ntitle: $title";
        #echo "\ndate: $date";
        // Detta gör så att "13 february 10.32" -> "13 february, 10.32"
        // Runt 14 Feb 2019 så verkar formatet på titlarna ha ändrats så de är
        // "13 february 10:32", dvs kolon istället för punk. Så ta hänsyn till det också.
        $date = preg_replace('/ \d{2}[\.:]\d{2}/', ', ${0}', $date);
        #echo "\ndate after: $date"; // 13 february 10:32
        // die(__METHOD__);
        $date = Carbon::parse($date);
        # echo $date->format('Y-m-d H:i:s');
        $returnParts["parsed_date"] = $date;
        #>format('Y-m-d H:i:s');
        #var_dump($returnParts["parsed_date"]);exit;

        #$returnParts["parsed_date"] = date("Y-m-d H:i:s", strtotime($returnParts["parsed_date"]));

        return $returnParts;
    }

    /**
     * Get info from remote, i.e. from the details page at polisen.se
     * Info exists in div with id #column2-3
     *
     * We find "parsed_teaser" and "parsed_content" here
     *
     * @param string $contentURL URL to fetch, is like "https://polisen.se/aktuellt/handelser/2018/februari/22/22-februari-11.45-stold-ringa-karlstad/"
     *
     * @return mixed Array with parsed teaser and content if sucess
     *               Bool false if error occured, for example if page returned 404
     */
    public function parseContent($contentURL)
    {
        $returnParts = [
            'parsed_teaser' => '',
            'parsed_content' => ''
        ];

        $cacheKey = md5($contentURL . "_cachebust3");
        $html = Cache::get($cacheKey, false);

        // contentURL: https://polisen.se/aktuellt/handelser/2018/februari/22/22-februari-11.45-stold-ringa-karlstad/

        if (! $html || gettype($html) != "string") {
            $client = new Client();
            // $contentURL .= '-make-it-a-404-to-test';
            $crawler = $client->request(
                'GET',
                \App\Helper::makeUrlUsePolisenDomain($contentURL)
            );

            // Only continue if valid response code, i.e. 20x
            // Redirect from http to https with code 200
            // Redirect from old url to overview page using HTTP/1.1 301 Moved Permanently
            $response = $client->getResponse();
            $responseStatusCode = $response->getStatusCode();

            if ($responseStatusCode !== 200) {
                return false;
            }

            // get content inside #column2-3
            #echo '<pre>';
            #echo sizeof($client->getHistory());
            #print_r($client->getHistory());

            // https://polisen.se/aktuellt/polisens-nyheter/
           # print_r($client->getHistory()->current()->getUri());
            // $url = $client->getHistory()->current()->getUri();
            #echo "\n$contentURL\n";
            #print_r($response);
            #print_r($responseStatusCode);
            #exit;

            #$crawler = $crawler->filter('#column2-3');
            $crawler = $crawler->filter('.event-page.editorial-content');
            if ($crawler->count()) {
                $html = $crawler->html();
            } else {
                // no elements found, maybe a 404 page
                // did happen once and then polisen had removed the permalink for that page
                $html = '';
            }

            // Store page in cache for nn minutes
            Cache::put($cacheKey, $html, 5 * 60);
        } else {
            // echo "<br>got cached html";
        }

        // Parse HTML using hQuery
        // https://github.com/duzun/hQuery.php
        $hQueryDoc = \duzun\hQuery::fromHTML($html);


        $returnParts = [
            'parsed_teaser' => '',
            'parsed_content' => ''
        ];
        /*
        Hämta de delar i HTML:en vi behöver.
        Nytt format på polisen.se 22 Feb 2018:

            - h1: datum + sammanfattning, t.ex "22 februari 19.02, Bråk, Karlstad"
            - .preamble: ingress/sammanfattning, t.ex. "Flera personer slåss på Fredsgatan i Karlstad."
            - .text-body.editorial-html: mer text/detaljer
            - .meta-data-container: textförfattare, datum publicerat, datum uppdaterat
            */

        $docPreamble = $hQueryDoc->find('.preamble');
        if (!empty($docPreamble)) {
            if ($docPreamble->count() === 1) {
                $returnParts['parsed_teaser'] = trim($docPreamble[0]->html());
            }
        }

        $docTextBody = $hQueryDoc->find('.text-body');
        if (!empty($docTextBody)) {
            if ($docTextBody->count() === 1) {
                $returnParts['parsed_content'] = trim($docTextBody[0]->html());
            }
        }

        // If we got nothing the fail, so we don't remove the existing html
        if (empty($returnParts['parsed_teaser']) && empty($returnParts['parsed_content'])) {
            return false;
        }

        // Remove tags, but keep for example links and lists.
        $tagsToKeep = "<a><br><strong><ol><ul><li><p>";
        $returnParts["parsed_teaser"] = strip_tags($returnParts["parsed_teaser"], $tagsToKeep);
        $returnParts["parsed_content"] = strip_tags($returnParts["parsed_content"], $tagsToKeep);

        // Ibland kan sån här html förekomma hos Polisen.se:
        // <br style="mso-special-character: line-break"><br style="mso-special-character: line-break"></p><p>Polisen Stockholms län</p>
        // Så använd HTMLPurifier för att ta bort all style
        $config = HTMLPurifier_Config::createDefault();
        $config->set('CSS.AllowedProperties', array());
        $purifier = new HTMLPurifier($config);

        $returnParts["parsed_teaser"] = trim($purifier->purify($returnParts["parsed_teaser"]));
        $returnParts["parsed_content"] = trim($purifier->purify($returnParts["parsed_content"]));

        // Ta bort lite stuff som t.ex.
        // <p> </p>
        $returnParts["parsed_content"] = str_replace('<p> </p>', '', $returnParts["parsed_content"]);
        $returnParts["parsed_content"] = str_replace('<br /></p>', '</p>', $returnParts["parsed_content"]);

        #echo "\n----------\n$contentURL\n";
        #print_r($returnParts);
        #exit;

        return $returnParts;
    } // function


    private function loadHighways()
    {

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
        // like what? it removed "ösmo" which was bad
        $highwayItems = array_filter($highwayItems, function ($val) {
            // return (mb_strlen($val) > 4);
            // 21 May 2017 changed from 4 to 3
            return (mb_strlen($val) > 3);
        });

        // ta bort lite för vanliga ord från highwayitems, t.ex. "träd" och "vägen" känns lite för generalla
        $highwaysStopWords = $this->getHighwaysStopwords();
        $highwayItems = array_where($highwayItems, function ($val, $key) use ($highwaysStopWords) {
            return ! in_array($val, $highwaysStopWords);
        });

        // Add some manual roads that I've found missing
        // Add this last we we can override stopwords and to short ones
        $highwayItems = array_merge($highwayItems, $this->getHighwaysAddedManually());

        $timetaken = microtime(true) - $starttime;

        Log::info('Loaded highwayitems, after clean and stop words removed', ["count", count($highwayItems)]);
        Log::info('highwayitems, load done', ["time in s", $timetaken]);

        $this->highwayItems = $highwayItems;

        return $highwayItems;
    }

    public function getHighwaysAddedManually()
    {
        $added = highways_added::all()->pluck("name")->toArray();

        $added = array_map("trim", $added);
        $added = array_map("mb_strtolower", $added);

        return $added;
    }

    /**
     * Get streenames etc that are not valid streetnames
     */
    public function getHighwaysStopwords()
    {
        $ignoredHighways = highways_ignored::all()->pluck("name")->toArray();

        $ignoredHighways = array_map("trim", $ignoredHighways);
        $ignoredHighways = array_map("mb_strtolower", $ignoredHighways);

        return $ignoredHighways;
    }

    private function loadCities()
    {

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
    public function findLocations($item)
    {
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
        $arr_description_words = array_map("mb_strtolower", $arr_description_words);

        // Länet, t.ex. "Stockholms län"
        // Användbart för att grovt avgränsa plats för händelsen.
        // Borttaget efter omgörning av polisen.se 22 Feb 2018,
        // dock borde vi kunna ta det från URL istället?
        // Nej... varje län har dock en egen RSS-feed, så hämta via det på nåt vis istället då..
        $police_lan = "";

        // Remove "Polisen Värmland" etc that's the last line in the content words
        // $parsed_content_lines = explode("\n", $item_parsed_content);
        // if (false !== strpos($parsed_content_lines[count($parsed_content_lines)-1], "Polisen ")) {
        //     #echo "\nfound polisen at last line";
        //     $police_lan = array_pop($parsed_content_lines);
        //     $police_lan = str_replace("Polisen ", "", $police_lan);
        //     #echo "\ntext before removal: $item_parsed_content";
        //     $item_parsed_content = implode($parsed_content_lines);
        //     #echo "\ntext after removal: $item_parsed_content";
        //     #echo "\n\n";
        // }
        #exit;

        #print_r($parsed_content_lines);exit;

        // Split content into words
        $item_parsed_content = strip_tags($item_parsed_content);
        preg_match_all('/\pL+/u', $item_parsed_content, $matches);
        $arr_content_words = $matches[0];

        $arr_content_words = array_map("mb_strtolower", $arr_content_words);

        $matchingHighwayItemsInDescription = [];
        $matchingHighwayItemsInContent = [];

        // Check if each word[n] + word[n+1] matches a location in $highwayItems
        // we check both n and n + (n+1) so we match street names with 2 words
        // hm, check one more because "T-bana Skanstull"
        for ($i = 0; $i < count($arr_description_words); $i++) {
            $word1 = $arr_description_words[$i];
            if (in_array($word1, $highwayItems)) {
                #echo "\nword 1 matched: $word1\n";
                $matchingHighwayItemsInDescription[] = $word1;
            }

            if ($i < count($arr_description_words) - 1) {
                $word2 = $arr_description_words[$i] . " " . $arr_description_words[$i+1];
                if (in_array($word2, $highwayItems)) {
                    #echo "\nword 2 matched: $word2\n";
                    $matchingHighwayItemsInDescription[] = $word2;
                }
            }

            //*
            if ($i < count($arr_description_words) - 2) {
                $word3 = $arr_description_words[$i] . " " . $arr_description_words[$i+1] . " " . $arr_description_words[$i+2];
                #echo "<br>word3: $word3";
                if (in_array($word3, $highwayItems)) {
                    #echo "\nword 3 matched: $word3\n";
                    $matchingHighwayItemsInDescription[] = $word3;
                }
            }
            //*/
        }

        // Find in parsed content
        for ($i = 0; $i < count($arr_content_words); $i++) {
            $word1 = $arr_content_words[$i];
            if (in_array($word1, $highwayItems)) {
                #echo "\nword content 1 matched: $word1\n";
                $matchingHighwayItemsInContent[] = $word1;
            }

            if ($i < count($arr_content_words) - 1) {
                $word2 = $arr_content_words[$i] . " " . $arr_content_words[$i+1];
                if (in_array($word2, $highwayItems)) {
                    #echo "\nword content 2 matched: $word2\n";
                    $matchingHighwayItemsInContent[] = $word2;
                }
            }

            // Aktivera pga vi hittade inte "Elsa Borgs Gata"
            if ($i < count($arr_content_words) - 2) {
                $word3 = $arr_content_words[$i] . " " . $arr_content_words[$i+1] . " " . $arr_content_words[$i+2];
                if (in_array($word3, $highwayItems)) {
                    #echo "\nword content 3 matched: $word3\n";
                    $matchingHighwayItemsInContent[] = $word3;
                }
            }
        } // for each $arr_content_words

        // remove hits that is the same as the main location found in the title, often city names
        $title_location = mb_strtolower($item->parsed_title_location);
        #echo "\n checking if title_location '$title_location' exists ";

        $matchingHighwayItemsInDescription = array_filter($matchingHighwayItemsInDescription, function ($val) use ($title_location) {
            return ($val !== $title_location);
        });

        $matchingHighwayItemsInContent = array_filter($matchingHighwayItemsInContent, function ($val) use ($title_location) {
            return ($val !== $title_location);
        });

        // remove duplicates
        $matchingHighwayItemsInDescription = array_unique($matchingHighwayItemsInDescription);
        $matchingHighwayItemsInContent = array_unique($matchingHighwayItemsInContent);

        // remove locations thats exists as part of other locations
        // for example if both "eriksgatan" and "sankt eriksgatan" are found
        // then remove "eriksgatan" because "sankt eriksgatan" is a better, more precise hit
        $matchingHighwayItemsInContent = array_filter($matchingHighwayItemsInContent, function ($val) use ($matchingHighwayItemsInContent) {

            foreach ($matchingHighwayItemsInContent as $arrVal) {
                // dont't check current val
                if ($arrVal === $val) {
                    continue;
                }

                if (strpos(" " . $arrVal, $val) !== false || strpos($arrVal . " ", $val) !== false) {
                    // string was found, so remove
                    return false;
                }
            }

            return true;
        });

        $matchingHighwayItemsInDescription = array_filter($matchingHighwayItemsInDescription, function ($val) use ($matchingHighwayItemsInDescription) {

            foreach ($matchingHighwayItemsInDescription as $arrVal) {
                // dont't check current val
                if ($arrVal === $val) {
                    continue;
                }

                if (strpos(" " . $arrVal, $val) !== false || strpos($arrVal . " ", $val) !== false) {
                    // string was found, so remove
                    return false;
                }
            }

            return true;
        });

        $timetaken = microtime(true) - $starttime;
        Log::info('find locations done', ["time in s", $timetaken]);

        $returnArr = [
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

        #print_r($returnArr);exit;

        return $returnArr;
    }
}
