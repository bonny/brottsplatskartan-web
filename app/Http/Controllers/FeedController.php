<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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
        $this->apiUrl = \App\Helper::makeUrlUsePolisenDomain('https://polisen.se/api/events');
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
        return $this->geocodeUrlFor(CrimeEvent::findOrFail($itemID));
    }

    /**
     * Bygger Google-frågan för en händelse. Utbruten från getGeocodeURL() så
     * att den kan testas utan databas (tests/Unit/GeocodeUrlSnapshotTest).
     *
     * @param array<string, string>|null $kommunTillLan Gemener kommun → länets
     *   kortnamn ("malmö" → "Skåne"). null = läs scb_kommuner. Testet skickar in
     *   listan så att det klarar sig utan databas.
     * @param array<string, list<string>>|null $tatortTillLan Gemener tätort →
     *   länens kortnamn där en tätort med det namnet finns. null = scb_tatorter.
     */
    public function geocodeUrlFor(CrimeEvent $item, ?array $kommunTillLan = null, ?array $tatortTillLan = null): string
    {
        $itemLocations = $this->platserForGoogle(
            $item,
            $kommunTillLan ?? $this->kommunTillLan(),
            $tatortTillLan ?? $this->tatortTillLan()
        );
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

        // parsed_title_location är ibland stad, ibland län — komplettera med
        // Polisens säkra län-namn för disambiguering, men bara om det inte
        // redan står där.
        if (
            ! empty($item->polisen_location_name)
            && stripos($strLocationURLPart, $item->polisen_location_name) === false
        ) {
            $strLocationURLPart .= ", " . $item->polisen_location_name;
        }

        // Erätt "snapparp, , Halmstad " så det inte blir dubbla komman i anrop till Google = blir zero results.
        $strLocationURLPart = str_replace(', ,', ',', $strLocationURLPart);
        $strLocationURLPart = trim($strLocationURLPart, ", ");

        $apiUrl = sprintf(
            $apiUrlTemplate,
            urlencode($strLocationURLPart) // 1
        );

        // Viewport-bias (~50 km bbox) från Polisens grova GPS — biasar mot
        // rätt län för tvetydiga ortnamn ("Partille") utan att utesluta
        // träffar utanför boxen.
        if (! empty($item->polisen_gps_lat) && ! empty($item->polisen_gps_lng)) {
            $bounds = $this->buildBoundsBox(
                (float) $item->polisen_gps_lat,
                (float) $item->polisen_gps_lng
            );
            $apiUrl .= '&bounds=' . urlencode($bounds);
        }

        return $apiUrl;
    }

    /**
     * Platserna ur texten som ska med i Google-frågan (#109.1).
     *
     * Texten nämner ibland orter i andra län ("i riktning mot Stockholm",
     * bedrägerivarningar som räknar upp halva Sverige), och Google valde då
     * fel ort (510987 Gotland → Stockholm). Nu tas länsnamn bort (Polisens
     * län läggs till sist ändå) och kommuner i andra län än händelsens.
     * Kommuner i samma län behålls: Polisens titelkommun är inte alltid där
     * det hände (511151 "Vimmerby" hände i Hultsfred), och en eval mot
     * Google på 30 dagars händelser visade att det gav fler fel att ta bort
     * dem. Gator, stadsdelar och byar behålls. Underlag: todo #109.
     *
     * Undantag: finns en tätort med samma namn i händelsens län (Berg i
     * Östergötland, Lund i Gävleborg) behålls namnet — det är troligen byn,
     * inte kommunen i ett annat län. Saknas Polisens län filtreras inga
     * kommuner bort.
     *
     * @param array<string, string> $kommunTillLan
     * @param array<string, list<string>> $tatortTillLan
     * @return \Illuminate\Support\Collection<int, \App\Locations>
     */
    private function platserForGoogle(CrimeEvent $item, array $kommunTillLan, array $tatortTillLan)
    {
        $lanKortnamn = array_map('mb_strtolower', array_unique(array_values($kommunTillLan)));
        $titel = mb_strtolower(trim((string) $item->parsed_title_location));
        $handelsensLan = $this->utanSuffix(mb_strtolower((string) $item->polisen_location_name), 'län', $lanKortnamn);

        return $item->locations->filter(function ($location) use ($kommunTillLan, $tatortTillLan, $lanKortnamn, $titel, $handelsensLan) {
            $namn = mb_strtolower(trim((string) $location->name));

            // "östersunds kommun" → "östersund", "västerås kommun" → "västerås".
            $kommun = $this->utanSuffix($namn, 'kommun', array_keys($kommunTillLan));
            if ($namn === $titel || $kommun === $titel) {
                return true;
            }

            // "skåne", "stockholms län" — Polisens län läggs till sist ändå.
            // "stockholm" och "uppsala" är också kommuner och hanteras nedan.
            $lanForm = $this->utanSuffix($namn, 'län', $lanKortnamn);
            if (in_array($lanForm, $lanKortnamn, true) && ! isset($kommunTillLan[$namn])) {
                return false;
            }

            if (isset($kommunTillLan[$kommun]) && $handelsensLan !== '') {
                return mb_strtolower($kommunTillLan[$kommun]) === $handelsensLan
                    || in_array($handelsensLan, $tatortTillLan[$namn] ?? [], true);
            }

            return true;
        })->values();
    }

    /**
     * Tar bort " kommun"/" län" och ett eventuellt genitiv-s, men bara om
     * resultatet finns i $kanda: "västerås kommun" → "västerås" (inte
     * "västerå"), "stockholms län" → "stockholm".
     *
     * @param list<string> $kanda
     */
    private function utanSuffix(string $namn, string $suffix, array $kanda): string
    {
        if (! str_ends_with($namn, ' ' . $suffix)) {
            return $namn;
        }

        $bas = mb_substr($namn, 0, -mb_strlen(' ' . $suffix));
        if (in_array($bas, $kanda, true) || ! str_ends_with($bas, 's')) {
            return $bas;
        }

        return mb_substr($bas, 0, -1);
    }

    /** @var array<string, string>|null */
    private static ?array $kommunTillLanCache = null;

    /** @var array<string, list<string>>|null */
    private static ?array $tatortTillLanCache = null;

    /**
     * @return array<string, list<string>> Gemener tätort → länens gemena kortnamn ur scb_tatorter.
     */
    private function tatortTillLan(): array
    {
        return self::$tatortTillLanCache ??= DB::table('scb_tatorter')
            ->get(['tatort', 'lan_namn'])
            ->groupBy(fn ($r) => mb_strtolower($r->tatort))
            ->map(fn ($rader) => $rader->pluck('lan_namn')->map(fn ($l) => mb_strtolower($l))->unique()->values()->all())
            ->all();
    }

    /**
     * @return array<string, string> Gemener kommun → länets kortnamn ur scb_kommuner.
     */
    private function kommunTillLan(): array
    {
        return self::$kommunTillLanCache ??= DB::table('scb_kommuner')
            ->pluck('lan_namn', 'kommun_namn')
            ->mapWithKeys(fn ($lan, $kommun) => [mb_strtolower($kommun) => $lan])
            ->all();
    }

    /**
     * Bygger en ~50 km bounding box runt en punkt för Googles `bounds`-param.
     * Format: "sw_lat,sw_lng|ne_lat,ne_lng".
     *
     * 0.45° lat ≈ 50 km. Lng-graden krymper med cos(lat) på höga breddgrader
     * — runt 60°N är 0.9° ≈ 50 km. För Sverige (55°–69°N) räcker fast 0.9°
     * som approximation; biasen är icke-restriktiv så exakthet spelar mindre roll.
     */
    private function buildBoundsBox(float $lat, float $lng): string
    {
        $latDelta = 0.45;
        $lngDelta = 0.9;

        $sw = sprintf('%.6f,%.6f', $lat - $latDelta, $lng - $lngDelta);
        $ne = sprintf('%.6f,%.6f', $lat + $latDelta, $lng + $lngDelta);

        return $sw . '|' . $ne;
    }

    /**
     * Geocode an crime event
     *
     * @param int $itemID ID of crime event to geovode.
     * @return array with info if, key [error] = false is ok, [error] = true if error, [message] with error message.
     *   [status] är Googles status (OK, ZERO_RESULTS, OVER_QUERY_LIMIT …), så
     *   anroparen kan skilja tillfälliga fel från bestående (#109.4).
     */
    public function geocodeItem($itemID) {

        $item = CrimeEvent::findOrFail($itemID);
        $apiUrl = $this->getGeocodeURL($itemID);

        $result_data = json_decode(file_get_contents($apiUrl));
        $result_status = $result_data->status;
        $result_results = $result_data->results;

        if ($result_status !== "OK") {
            // Ingen träff alls är oftast en överspecificerad fråga — prova
            // den snålare reservfrågan (tidigare kördes den bara vid träff på
            // landsnivå, inte här).
            if ($result_status === "ZERO_RESULTS" && $this->provaReservgeokodning($item)) {
                return [
                    'error' => false,
                    'status' => 'OK',
                    'reserv' => true,
                    'geocodeUrl' => $apiUrl,
                ];
            }

            return [
                'error' => true,
                'status' => $result_status,
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
        $partial_match = null;

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
            $partial_match = ! empty($one_result->partial_match);

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
            $item->google_types = $types;
            $item->google_partial_match = $partial_match;

            $item->viewport_northeast_lat = $geometry_viewport->northeast->lat;
            $item->viewport_northeast_lng = $geometry_viewport->northeast->lng;
            $item->viewport_southwest_lat = $geometry_viewport->southwest->lat;
            $item->viewport_southwest_lng = $geometry_viewport->southwest->lng;

            $item->administrative_area_level_1 = $administrative_area_level_1;
            $item->administrative_area_level_2 = $administrative_area_level_2;

            $item->geocoded = true;

            $item->save();

        } else {
            $this->provaReservgeokodning($item);
        }

        return [
            'error' => false,
            'status' => $result_status,
            'geocodeUrl' => $apiUrl
        ];
    }

    /**
     * Träffen var för grov (hela landet) eller saknas: försök igen med bara
     * titelns ort och Polisens län. Tidigare hängde detta på en prio
     * 3-location som alltid var tom (länet togs bort ur polisens text 2018),
     * så frågan blev "Umeå, " utan län (#109.3).
     */
    private function provaReservgeokodning(CrimeEvent $item): bool
    {
        $fallbackLocation = collect([$item->parsed_title_location, $item->polisen_location_name])
            ->filter()
            ->unique()
            ->implode(', ');

        return $fallbackLocation !== ''
            && $this->geocodeItemFallbackVersion($item->getKey(), $fallbackLocation);
    }

    public function geocodeItemFallbackVersion($itemID, $fallbackLocation) {

        $item = CrimeEvent::findOrFail($itemID);

        // Samma nyckel som huvudvägen. GOOGLE_API_KEY är en annan nyckel på
        // prod och används inte längre någonstans.
        $apiUrlTemplate = 'https://maps.googleapis.com/maps/api/geocode/json?key=' . getenv('GEOCODE_GOOGLE_APIKEY') . '&language=sv';
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

        // Jämförde tidigare $result_results (en array) med "OK", så ett
        // fel från Google (OVER_QUERY_LIMIT, REQUEST_DENIED …) passerade
        // tyst och händelsen förblev ogeokodad utan spår i loggen.
        if ($result_status !== "OK") {
            Log::warning('Reservgeokodning misslyckades', [
                'crime_event_id' => $itemID,
                'status' => $result_status,
            ]);
            return false;
        }

        $geometry_location_lat = null;
        $geometry_location_lng = null;
        $geometry_type = null;
        $geometry_viewport = null;
        $administrative_area_level_1 = null;
        $administrative_area_level_2 = null;
        $types = null;
        $partial_match = null;

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
            $partial_match = ! empty($one_result->partial_match);

            // only return first matching place
            break;

        }

        // Non ok is if types contains "Country" because then we have a really zoomed out location.
        // if bad location then fallback to only using
        // Även reserven ska avvisa träffar på landsnivå (mitt i Sverige).
        $valid_good_location = ! empty($geometry_location_lat) && ! in_array("country", (array) $types);

        // If ok location then add
        if ($valid_good_location) {

            $item->location_lat = $geometry_location_lat;
            $item->location_lng = $geometry_location_lng;

            $item->location_geometry_type = $geometry_type;
            $item->google_types = $types;
            $item->google_partial_match = $partial_match;

            $item->viewport_northeast_lat = $geometry_viewport->northeast->lat;
            $item->viewport_northeast_lng = $geometry_viewport->northeast->lng;
            $item->viewport_southwest_lat = $geometry_viewport->southwest->lat;
            $item->viewport_southwest_lng = $geometry_viewport->southwest->lng;

            $item->administrative_area_level_1 = $administrative_area_level_1;
            $item->administrative_area_level_2 = $administrative_area_level_2;

            $item->geocoded = true;

            $item->save();

            return true;
        }

        // Ingen ytterligare reserv — det här *är* reserven.
        return false;
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
        // AI-omskriven titel/text bygger på den gamla brödtexten och visas
        // före Polisens. Töm dem så att uppdateringen syns (#109.2).
        $item->title_alt_1 = null;
        $item->description_alt_1 = null;
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
                if ($locationName === '') {
                    continue;
                }

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
        // Samma lås som tolkaOmEfterAndring(): checkForUpdates kan ta en
        // nyss importerad händelse medan fetch fortfarande parsar den.
        Cache::lock("tolka-om:{$itemID}", 120)->block(30, function () use ($itemID) {
            $this->tolkaTitel($itemID);

            // Parse permalink, i.e. get info from remote and store
            // This can be called a bit later to check if item has remote updates
            $this->parseItemContentAndUpdateIfChanges($itemID);

            // Find and save locations in teaser and content
            $this->parseItemForLocations($itemID);
        });

        return true;
    }

    /**
     * Tolka titeln ("DD månad HH.MM, Typ, Plats") till parsed_title,
     * parsed_title_location och parsed_date, och spara.
     */
    private function tolkaTitel(int $itemID): void
    {
        $item = CrimeEvent::findOrFail($itemID);

        // Parse title
        $parsed_title_items = $this->feedParser->parseTitle($item->title);

        // parsed_date sätts från titelns "DD månad HH.MM" — Carbon defaultar
        // till innevarande år vilket ger fel datum när titeln "29 april 22:00"
        // publiceras kl 06:58 samma morgon (eventet var igår), eller "31 december"
        // publiceras 1 januari (eventet var föregående år). Fixa bara när
        // parsed_date hamnar i faktisk framtid — small-skew på några minuter
        // mellan titel-tid och pubdate-tid är normal data och ska inte röras.
        if (! empty($parsed_title_items['parsed_date'])) {
            $parsedDate = $parsed_title_items['parsed_date'];
            if ($parsedDate->isFuture()) {
                $pubdate = ! empty($item->pubdate)
                    ? \Carbon\Carbon::createFromTimestamp($item->pubdate)
                    : \Carbon\Carbon::now();
                $isYearBoundary = $parsedDate->month === 12 && $pubdate->month === 1;
                $parsed_title_items['parsed_date'] = $isYearBoundary
                    ? $parsedDate->subYear()
                    : $parsedDate->subDay();
            }
        }

        $item->fill($parsed_title_items);
        $item->save();
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
            "itemsAdded" => [],
            // Befintliga händelser där Polisen ändrat titel eller
            // sammanfattning (#109.6): [crime_event_id => API-objekt].
            "itemsChanged" => [],
        ];

        // Batcha dedup-uppslagen — en query för hela listan istället för
        // 500 exists()-anrop. Bygg först alla nycklar, fråga DB en gång,
        // håll resultatet i minnet under loopen.
        $candidatePolisenIds = [];
        $candidateMd5s = [];
        $itemRows = [];
        foreach ($items as $item) {
            if (! is_array($item) || empty($item['id']) || empty($item['url'])) {
                continue;
            }
            $permalink = $this->buildPermalink($item['url']);
            $polisenId = (int) $item['id'];
            $md5 = md5($permalink);
            $candidatePolisenIds[] = $polisenId;
            $candidateMd5s[] = $md5;
            $itemRows[] = [$item, $polisenId, $permalink, $md5];
        }

        // withoutGlobalScopes() — annars missar vi events som markerats
        // is_public=false av ContentFilterService och re-importerar dem.
        $existingQuery = CrimeEvent::withoutGlobalScopes();
        if (! empty($candidatePolisenIds)) {
            $existingQuery->whereIn('polisen_id', $candidatePolisenIds);
        }
        if (! empty($candidateMd5s)) {
            $existingQuery->orWhereIn('md5', $candidateMd5s);
        }
        $existingRows = $existingQuery->get(['id', 'polisen_id', 'md5', 'title', 'description', 'is_public']);
        $existingByPolisenId = $existingRows->keyBy('polisen_id');
        $existingPolisenIds = $existingRows->pluck('polisen_id')->filter()->all();
        $existingMd5s = $existingRows->pluck('md5')->filter()->all();
        $existingPolisenIdSet = array_flip($existingPolisenIds);
        $existingMd5Set = array_flip($existingMd5s);

        foreach ($itemRows as [$item, $polisenId, $permalink, $itemMd5Permalink]) {
            if (isset($existingPolisenIdSet[$polisenId]) || isset($existingMd5Set[$itemMd5Permalink])) {
                $data["numItemsAlreadyAdded"]++;

                // Polisen ändrar ofta titel eller sammanfattning efteråt
                // ("Knivlagen" → "Mord/dråp, försök", kommun → län,
                // "Försvunnen man anträffad"). Tidigare lästes det aldrig in.
                // Mätt 2026-10-10: 14 av 500 skilde sig, inga falska
                // skillnader av formatering. Bara publika — icke-publika
                // visas inte och findOrFail() i parsningen hittar dem inte.
                $existing = $existingByPolisenId[$polisenId] ?? null;
                if (
                    $existing
                    && $existing->is_public
                    && (
                        $existing->title !== ($item['name'] ?? '')
                        || $existing->description !== html_entity_decode($item['summary'] ?? '')
                    )
                ) {
                    $data["itemsChanged"][$existing->id] = $item;
                }

                continue;
            }

            [$gpsLat, $gpsLng] = $this->parseGps($item['location']['gps'] ?? null);
            $locationName = $item['location']['name'] ?? null;

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
                'polisen_location_name' => $locationName,
                // Kolumnen är VARCHAR(80); ett för långt värde skulle annars
                // kasta och stoppa resten av importen.
                'polisen_type' => isset($item['type']) ? mb_substr($item['type'], 0, 80) : null,
            ]);

            $data["numItemsAdded"]++;
            $data["itemsAdded"][] = $event;
        }

        return $data;
    }

    /**
     * Läs in Polisens ändrade titel/sammanfattning för en befintlig händelse
     * och tolka om den (#109.6): titel, datum, brödtext, platser, geokodning.
     *
     * @param array<string, mixed> $apiItem Händelsen ur Polisens API
     */
    public function uppdateraFranApi(int $itemID, array $apiItem): void
    {
        $item = CrimeEvent::findOrFail($itemID);
        $gammalUrl = $this->geocodeUrlFor($item);
        $gammalt = $item->only(['title', 'description', 'polisen_type']);

        $item->title = $apiItem['name'] ?? $item->title;
        $item->description = html_entity_decode($apiItem['summary'] ?? '');
        $item->polisen_type = isset($apiItem['type']) ? mb_substr((string) $apiItem['type'], 0, 80) : $item->polisen_type;
        // AI-omskriven titel/text bygger på den gamla versionen och visas
        // före Polisens (display_title). Töm dem så att ändringen syns;
        // create-summaries skriver om dem med den nya texten.
        $item->title_alt_1 = null;
        $item->description_alt_1 = null;
        $item->save();

        try {
            // Titel och datum tolkas om och detaljsidan hämtas igen. Platserna
            // tolkas bara i tolkaOmEfterAndring() — parseItem() skulle lägga
            // till dem additivt först.
            $this->tolkaTitel($itemID);
            $this->parseItemContentAndUpdateIfChanges($itemID);
            $this->tolkaOmEfterAndring($itemID, $gammalUrl);
        } catch (\Throwable $e) {
            // Ändringen upptäcks genom att titel/sammanfattning skiljer sig
            // från API:t. Återställ dem, annars görs omtolkningen aldrig om.
            CrimeEvent::whereKey($itemID)->update($gammalt);
            throw $e;
        }
    }

    /**
     * Tolka om platserna efter att Polisen ändrat en händelse (#109.2).
     *
     * Tidigare geokodades händelsen bara om med samma platser: gator som
     * tillkom i en uppdatering ("Rättelse: Brottsplats är Bondegatan")
     * plockades aldrig upp, gamla platser låg kvar, och varje omgeokodning
     * var ett Google-anrop med exakt samma fråga. Nu töms platserna och
     * texten tolkas om; Google anropas bara om frågan faktiskt ändrats.
     * Flyttade punkter loggas — ett mått på hur ofta första geokodningen
     * var fel.
     *
     * @param string|null $gammalUrl Frågan före ändringen, om den redan räknats ut
     */
    public function tolkaOmEfterAndring(int $itemID, ?string $gammalUrl = null): void
    {
        // crimeevents:fetch (*/12) och checkForUpdates (*/33) kan träffa samma
        // händelse samtidigt (båda startar :00) — utan lås kan platserna
        // dubbleras.
        Cache::lock("tolka-om:{$itemID}", 120)->block(30, function () use ($itemID, $gammalUrl) {
            $item = CrimeEvent::findOrFail($itemID);
            $gammalUrl ??= $this->geocodeUrlFor($item);
            $fore = [$item->location_lat, $item->location_lng];

            // Räkna ut de nya platserna innan något skrivs, så att ett fel
            // inte lämnar händelsen utan platser.
            $nya = $this->nyaPlatser($item);

            // När Polisen avslutar en händelse ersätts texten ofta med en kort
            // rad ("Försvunnen man anträffad") och gatan försvinner. Hittas
            // inga platser i den nya texten behålls de gamla — annars hamnar
            // punkten i ortens mitt (sågs på prod 2026-10-10: 511089, 511023,
            // 510857). En rättelse ("Brottsplats är Bondegatan") ersätter.
            if ($nya !== []) {
                DB::transaction(function () use ($item, $nya) {
                    $item->locations()->delete();
                    $item->locations()->createMany($nya);
                });
            }

            $nyUrl = $this->getGeocodeURL($itemID);
            if ($nyUrl === $gammalUrl) {
                return;
            }

            $resultat = $this->geocodeItem($itemID);
            $efter = CrimeEvent::findOrFail($itemID);
            if ($resultat['error']) {
                Log::warning('Omgeokodning efter ändring hos Polisen misslyckades', [
                    'crime_event_id' => $itemID,
                    'status' => $resultat['status'] ?? null,
                ]);
                return;
            }

            $adress = function (string $url): string {
                parse_str((string) parse_url($url, PHP_URL_QUERY), $q);
                return (string) ($q['address'] ?? '');
            };
            Log::info('Omgeokodad efter ändring hos Polisen', [
                'crime_event_id' => $itemID,
                'fraga_fore' => $adress($gammalUrl),
                'fraga_efter' => $adress($nyUrl),
                'punkt_fore' => $fore,
                'punkt_efter' => [$efter->location_lat, $efter->location_lng],
            ]);
        });
    }

    /**
     * Platserna ur händelsens nuvarande text, i samma form och ordning som
     * parseItemForLocations() sparar dem (unika namn, prio 1 före 2).
     *
     * @return list<array{name: string, prio: int}>
     */
    private function nyaPlatser(CrimeEvent $item): array
    {
        $nya = [];
        foreach ($this->feedParser->findLocations($item) as $grupp) {
            foreach ($grupp['locations'] as $namn) {
                if ($namn !== '' && ! in_array($namn, array_column($nya, 'name'), true)) {
                    $nya[] = ['name' => $namn, 'prio' => (int) $grupp['prio']];
                }
            }
        }

        return $nya;
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
