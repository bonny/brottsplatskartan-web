<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Http\Requests;
use App\CrimeEvent;

use App\Http\Controllers\FeedParserController;

class FeedController extends Controller
{

    protected $apiUrl;
    protected $feedParser;

    public function __construct(FeedParserController $feedParser)
    {
        // Polisens officiella JSON-API över händelser. Migrerade hit från
        // RSS-flödet 2026-04-29 (todo #48 fas 1) — ger stabilt id, separat
        // type-fält och grov location.gps för viewport-bias i fas 2.
        $this->apiUrl = 'https://polisen.se/api/events';
        $this->apiUrl = \App\Helper::makeUrlUsePolisenDomain($this->apiUrl);

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

        // &address=snapparp,+,+Halmstad

        $strLocationURLPart = "";

        foreach ( $itemLocations as $location ) {
            if (!$location) {
                continue;
            }

            $strLocationURLPart .= ", " . $location->name;
        }

        // append main location, from title
        if ($item->parsed_title_location) {
            $strLocationURLPart .= ", " . $item->parsed_title_location;
        }
        
        // Erätt "snapparp, , Halmstad " så det inte blir dubbla komman i anrop till Google = blir zero results.
        $strLocationURLPart = str_replace(', ,', ',', $strLocationURLPart);
        $strLocationURLPart = trim($strLocationURLPart, ", ");

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

        $geometry_type = null;
        $geometry_viewport = null;
        $administrative_area_level_1 = null;
        $administrative_area_level_2 = null;
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
            'error' => false,
            'geocodeUrl' => $apiUrl
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
        $geometry_type = null;
        $geometry_viewport = null;
        $administrative_area_level_1 = null;
        $administrative_area_level_2 = null;

        foreach ( $result_results as $one_result ) {

            $geometry_location = $one_result->geometry->location;
            $geometry_location_lat = $geometry_location->lat;
            $geometry_location_lng = $geometry_location->lng;

            // location_type stores additional data about the specified location.
            $geometry_type = $one_result->geometry->location_type;

            // viewport contains the recommended viewport for displaying the returned result, specified as two latitude,longitude values defining the southwest and northeast corner of the viewport bounding box. Generally the viewport is used to frame a result when displaying it to a user.
            $geometry_viewport = $one_result->geometry->viewport;

            $geometry_address_components = $one_result->address_components;

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
     * @return CrimeEvent Crime event with locations added.
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

        return $item;
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
        $this->parseItemContentAndUpdateIfChanges($itemID);

        // Find and save locations in teaser and content
        $item = $this->parseItemForLocations($itemID);

        return true;
    }

    /**
     * Hämtar händelser från Polisens JSON-API och lägger till nya i DB.
     *
     * Tidigare hämtades RSS via SimplePie. JSON-API:t ger stabilt `id`,
     * separat `type`-fält och grov `location.gps` (län-/kommun-mittpunkt)
     * som senare används för viewport-bias i Google-geokoderingen.
     */
    public function updateFeedsFromPolisen()
    {
        // 75s cache → max 48 anrop/h, väl under Polisens tak (60/h, 1440/dygn,
        // min 10s mellan anrop). Endast lyckade svar cachas så att transienta
        // fel inte tystar importen i en hel cacheperiod.
        $cacheKey = 'polisen_api_events';
        $items = Cache::get($cacheKey);

        if ($items === null) {
            $response = Http::timeout(15)
                ->acceptJson()
                ->withHeaders(['User-Agent' => 'Brottsplatskartan/1.0 (+https://brottsplatskartan.se)'])
                ->retry(2, 500)
                ->get($this->apiUrl);

            if (! $response->successful()) {
                Log::warning('Polisens JSON-API gav icke-OK svar', [
                    'status' => $response->status(),
                    'url' => $this->apiUrl,
                ]);
                $items = [];
            } else {
                $items = $response->json() ?: [];
                if (! empty($items)) {
                    Cache::put($cacheKey, $items, 75);
                }
            }
        }

        $data = [
            "numItemsAdded" => 0,
            "numItemsAlreadyAdded" => 0,
            "itemsAdded" => []
        ];

        foreach ($items as $item) {
            if (! is_array($item) || empty($item['id']) || empty($item['url'])) {
                continue;
            }

            $polisenId = (int) $item['id'];
            $permalink = $this->buildPermalink($item['url']);
            $itemMd5Permalink = md5($permalink);

            // Dedup: nya importer har polisen_id; gamla rader har bara md5.
            // withoutGlobalScopes() — annars missar vi events som markerats
            // is_public=false av ContentFilterService och re-importerar dem.
            $existing = CrimeEvent::withoutGlobalScopes()
                ->where(function ($q) use ($polisenId, $itemMd5Permalink) {
                    $q->where('polisen_id', $polisenId)
                        ->orWhere('md5', $itemMd5Permalink);
                })
                ->exists();

            if ($existing) {
                $data["numItemsAlreadyAdded"]++;
                continue;
            }

            [$gpsLat, $gpsLng] = $this->parseGps($item['location']['gps'] ?? null);

            $datetime = ! empty($item['datetime']) ? strtotime($item['datetime']) : null;
            $isoDatetime = $datetime ? date(\DateTime::ATOM, $datetime) : null;

            $title = $item['name'] ?? '';
            $summary = $item['summary'] ?? '';

            $event = CrimeEvent::create([
                'title' => $title,
                'description' => html_entity_decode($summary),
                'permalink' => $permalink,
                'pubdate' => $datetime,
                'pubdate_iso8601' => $isoDatetime,
                'md5' => $itemMd5Permalink,
                'polisen_id' => $polisenId,
                'polisen_gps_lat' => $gpsLat,
                'polisen_gps_lng' => $gpsLng,
            ]);

            $data["numItemsAdded"]++;
            $data["itemsAdded"][] = $event;
        }

        return $data;
    }

    /**
     * Polisens API levererar relativa URL:er (t.ex. "/aktuellt/handelser/...").
     * Vår domän-helper byter ev. ut polisen.se mot test-domän i lokal dev.
     */
    private function buildPermalink(string $relativeOrAbsolute): string
    {
        if (str_starts_with($relativeOrAbsolute, 'http')) {
            return \App\Helper::makeUrlUsePolisenDomain($relativeOrAbsolute);
        }

        return \App\Helper::makeUrlUsePolisenDomain('https://polisen.se' . $relativeOrAbsolute);
    }

    /**
     * Polisens `location.gps` är en sträng "lat,lng". Vid saknad eller
     * felformaterad input returneras [null, null].
     */
    private function parseGps(?string $gps): array
    {
        if (empty($gps) || ! str_contains($gps, ',')) {
            return [null, null];
        }

        [$lat, $lng] = array_map('trim', explode(',', $gps, 2));
        if (! is_numeric($lat) || ! is_numeric($lng)) {
            return [null, null];
        }

        return [(float) $lat, (float) $lng];
    }
}
