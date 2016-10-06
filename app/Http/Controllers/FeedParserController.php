<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests;
use Feeds;
use App\CrimeEvent;
use Goutte\Client;
use Illuminate\Support\Facades\Cache;

class FeedParserController extends Controller
{

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
        $htmlDoc = new \DOMDocument();
        $htmlDoc->loadHTML('<?xml encoding="UTF-8">' . $html);

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

}
