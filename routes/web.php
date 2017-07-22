<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of the routes that are handled
| by your application. Just tell Laravel the URIs it should respond
| to using a Closure or controller method. Build something great!
|
*/

use App\Http\Controllers\FeedController;
use App\CrimeEvent;
use App\Locations;
use App\Dictionary;
use Illuminate\Http\Request;
use App\Http\Requests;
use Carbon\Carbon;

Carbon::setLocale('sv');
setlocale(LC_ALL, 'sv_SE', 'sv_SE.utf8');

/**
 * startpage: show latest events
 */
Route::get('/', function (Request $request) {

    $data = [];

    $page = (int) $request->input("page", 1);

    if (!$page) {
        $page = 1;
    }

    $events = CrimeEvent::orderBy("created_at", "desc")->paginate(20);

    if ($page > $events->lastPage()) {
        abort(404);
    }

    $data["showLanSwitcher"] = true;
    $data["events"] = $events;

    $linkRelPrev = null;
    $linkRelNext = null;

    if ($page > 1) {
        $linkRelPrev = route('start', [
            'page' => $page - 1
        ]);
    }

    if ($page < $events->lastpage()) {
        $linkRelNext = route('start', [
            'page' => $page + 1
        ]);
    }

    $data["page"] = $page;
    $data["linkRelPrev"] = $linkRelPrev;
    $data["linkRelNext"] = $linkRelNext;

    $breadcrumbs = new Creitive\Breadcrumbs\Breadcrumbs;
    $breadcrumbs->addCrumb('Hem', '/');
    $breadcrumbs->addCrumb('Län', route("lanOverview"));
    $breadcrumbs->addCrumb('Alla län', route("lanOverview"));

    $data["breadcrumbs"] = $breadcrumbs;

    $introtext_key = "introtext-start";
    if ($page == 1) {
        $data["introtext"] = Markdown::parse(Setting::get($introtext_key));
    }

    // Hämta statistik
    $data["chartImgUrl"] = App\Helper::getStatsImageChartUrl("home");

    // Total antal händelser
    // $data["events"]->total();

    if ($page == 1) {
        $data["canonicalLink"] = route('start');
    } else {
        $data["canonicalLink"] = route('start', ['page' => $page]);
    }

    // Händelser idag
    #$stats = App\Helper::getHomeStats('home');
    #dd($stats);

    $data["numEventsToday"] = DB::table('crime_events')
                    ->where('created_at', '>', Carbon::now()->subDays(1))
                    ->count();

    return view('start', $data);
})->name("start");

/**
 * nära: show latest events close to position
 */
Route::get('/nara', function (Request $request) {

    $data = [];
    $events = null;

    $lat = (float) $request->input("lat");
    $lng = (float) $request->input("lng");
    $error = (bool) $request->input("error");

    $lat = round($lat, 5);
    $lng = round($lng, 5);

    if ($lat && $lng && ! $error) {
        // works, but cant use "having"
        #$events = CrimeEvent::selectRaw('*, ( 6371 * acos( cos( radians(?) ) * cos( radians( location_lat ) ) * cos( radians( location_lng ) - radians(?) ) + sin( radians(?) ) * sin( radians( location_lat ) ) ) ) AS distance', [ $lat, $lng, $lat ])
        #->orderBy("distance", "ASC")
        #->paginate(10);
        $numTries = 0;

        // try 2, works but does not support pagination
        $nearbyCount = 25;

        // Start by showing pretty close, like 5 km
        // If no hits then move out until we have more
        $nearbyInKm = 5;

        $events = CrimeEvent::getEventsNearLocation($lat, $lng, $nearbyCount, $nearbyInKm);
        $numTries++;

        // we want to show at least 5 events
        // if less than 5 events is found then increase the range by nn km, until a hit is found
        while ($events->count() < 5) {
            $nearbyInKm = $nearbyInKm + 10;
            $events = CrimeEvent::getEventsNearLocation($lat, $lng, $nearbyCount, $nearbyInKm);
            $numTries++;
        }

        $data["nearbyInKm"] = $nearbyInKm;
        $data["nearbyCount"] = $nearbyCount;
        $data["numTries"] = $numTries;
    } else {
        $data["error"] = true;
    }

    /*
    Raw sql that seems to work:

    SELECT
    title, ( 6371 * acos( cos( radians(59.316) ) * cos( radians( location_lat ) ) * cos( radians( location_lng ) - radians(18.08) ) + sin( radians(59.316) ) * sin( radians( location_lat ) ) ) ) AS distance
    FROM crime_events
    HAVING distance < 25
    ORDER BY distance LIMIT 0, 50;
    */

    $data["events"] = $events;
    #$data["events"] = CrimeEvent::orderBy("created_at", "desc")->paginate(10);
    // $data["showLanSwitcher"] = true;

    $breadcrumbs = new Creitive\Breadcrumbs\Breadcrumbs;
    $breadcrumbs->addCrumb('Hem', '/');
    $breadcrumbs->addCrumb('Nära dig', route("geo"));

    $data["breadcrumbs"] = $breadcrumbs;

    return view('geo', $data);
})->name("geo");

/**
 * Admin-sida
 */
Route::group(['prefix' => 'admin'], function () {

    Route::get('', function () {
        return redirect()->route("adminDashboard");
    });

    // /admin/dashboard
    Route::get('dashboard', function (FeedController $feedController, Request $request) {
        $data = [];

        // if parseItem is set and integer then parse that item
        $parseItemID = (int) $request->input("parseItem");
        if ($parseItemID) {
            $feedController->parseItem($parseItemID);
        }

        // $data["feedsUpdateResult"] = $feedController->updateFeedsFromPolisen();

        $data["events"] = CrimeEvent::orderBy("created_at", "desc")->paginate(100);

        return view('admin.dashboard', $data);
    })->name("adminDashboard");
});

/**
 * Alla län översikt
 */
Route::get('/lan/', function (Request $request) {

    $data = [];

    // some old pages are indexed by google like this
    // "brottsplatskartan.se/lan?lan=/lan/orebro-lan
    $old_lan_query = $request->input("lan");

    if ($old_lan_query) {
        // /lan/orebro-lan
        $old_lan_query = str_replace('/lan/', '', $old_lan_query);
        $redirect_to = "lan/{$old_lan_query}";
        return redirect($redirect_to, 301);
    }

    $lan = App\Helper::getAllLanWithStats();
    $data["lan"] = $lan;

    $breadcrumbs = new Creitive\Breadcrumbs\Breadcrumbs;
    $breadcrumbs->addCrumb('Hem', '/');
    $breadcrumbs->addCrumb('Län', route("lanOverview"));

    $data["breadcrumbs"] = $breadcrumbs;

    return view('overview-lan', $data);
})->name("lanOverview");

/**
 * Alla orter översikt
 */
Route::get('/plats/', function () {

    $data = [];

    $data["orter"] = DB::table('crime_events')
                ->select("parsed_title_location")
                ->where('parsed_title_location', "!=", "")
                ->orderBy('parsed_title_location', 'asc')
                ->distinct()
                ->get();

    $breadcrumbs = new Creitive\Breadcrumbs\Breadcrumbs;
    $breadcrumbs->addCrumb('Hem', '/');
    $breadcrumbs->addCrumb('Platser', route("platserOverview"));

    $data["breadcrumbs"] = $breadcrumbs;

    return view('overview-platser', $data);
})->name("platserOverview");

/**
 * Url för ort så som den såg ut i Brottsplatskartan 2
 * t.ex.:
 * https://brottsplatskartan.se/orter/Falkenberg
 * https://brottsplatskartan.se/orter/Stockholm
 * redirecta dessa till
 * https://brottsplatskartan.se/plats/<ortnamn>
 */
Route::get('/orter/{ort}', function ($ort = "") {

    return redirect()->route("platsSingle", [ "ort" => $ort ]);
    // dd($ort);
});

/**
 * Översikt brottstyp/händelsetyp
 */
Route::get('/typ/', function () {

    $data = [];

    $data["types"] = DB::table('crime_events')
                ->select("parsed_title")
                ->where('parsed_title', "!=", "")
                ->orderBy('parsed_title', 'asc')
                ->distinct()
                ->get();

    $breadcrumbs = new Creitive\Breadcrumbs\Breadcrumbs;
    $breadcrumbs->addCrumb('Hem', '/');
    $breadcrumbs->addCrumb('Brottstyper', route("typeOverview"));

    $data["breadcrumbs"] = $breadcrumbs;

    return view('overview-typer', $data);
})->name("typeOverview");


/**
 * En typ
 */
Route::get('/typ/{typ}', function ($typ) {

    $data = [
        "type" => $typ
    ];

    $data["events"] = CrimeEvent::orderBy("created_at", "desc")
                                ->where("parsed_title", $typ)
                                ->paginate(10);

    if (!$data["events"]->count()) {
        abort(404);
    }

    $breadcrumbs = new Creitive\Breadcrumbs\Breadcrumbs;
    $breadcrumbs->addCrumb('Hem', '/');
    $breadcrumbs->addCrumb('Brottstyper', route("typeOverview"));
    $breadcrumbs->addCrumb(e($typ), route("typeSingle", ["typ" => $typ]));

    $data["breadcrumbs"] = $breadcrumbs;

    return view('single-typ', $data);
})
->name("typeSingle")
->where('typ', '(.*)');


/**
 * En specifik ort
 *
 * Nuvarande struktur:
 *
 *  /plats/storgatan/
*
 * Ny struktur:
 *
 *  /plats/storgatan-örebro-län/
 *  /plats/storgatan-gävleborgs-län/
 *
 */
Route::get('/plats/{plats}', function ($plats, Request $request) {

    $platsOriginalFromSlug = $plats;
    $data = [];

    // Om $plats slutar med namnet på ett län, t.ex. "örebro län", "gävleborgs län" osv
    // så ska platser i det länet med platsen $plats minus länets namn visas
    $allLans = App\Helper::getAllLan();
    $allLansNames = $allLans->pluck("administrative_area_level_1");
    $foundMatchingLan = false;
    $matchingLanName = null;
    $platsWithoutLan = null;
    $platsSluggified = App\Helper::toAscii($plats);

    // yttre-ringvägen-skåne-län
    // hittar inte: plats: Årsta i Stockholms Län
    #echo "<br>plats: $plats";

    // platsSluggified: arsta-i-stockholms-lan
    // echo "<br>platsSluggified: $platsSluggified";

    // yttre-ringvagen-skane-lan
    #echo "<br>platsSluggified: $platsSluggified";

    foreach ($allLansNames as $oneLanName) {
        // Skåne län
        // echo "<br>oneLanName: $oneLanName";

        // skane-lan
        $lanSlug = App\Helper::toAscii($oneLanName);
        #echo "<br>lanSlug: $lanSlug";

        // echo "<br> $plats - $oneLanName - $lanSlug - $platsSluggified";
        if (ends_with($platsSluggified, "-" . $lanSlug)) {
            $foundMatchingLan = true;
            $matchingLanName = $oneLanName;

            $lanStrLen = mb_strlen($oneLanName);
            $platsStrLen = mb_strlen($plats);
            $platsWithoutLan = mb_substr($plats, 0, $platsStrLen - $lanStrLen);
            $platsWithoutLan = str_replace("-", " ", $platsWithoutLan);
            break;
        }
    }

    if ($foundMatchingLan) {
        #echo "<br><br>Hittade län som matchade, så visa platser som matchar ";
        #echo "'{$platsWithoutLan}' från länet {$oneLanName} ($lanSlug)";

        // Hämta events där plats är från huvudtabellen
        // Används när $plats är bara en plats, typ "insjön",
        // "östersunds centrum", "östra karup", "kungsgatan" osv.
        $events = CrimeEvent::orderBy("created_at", "desc")
                    ->where("administrative_area_level_1", $oneLanName)
                    ->whereExists(function ($query) use ($platsWithoutLan) {
                        $query->select(DB::raw(1))
                                ->from('locations')
                                ->whereRaw(
                                    'locations.name = ?
                                    AND locations.crime_event_id = crime_events.id',
                                    [$platsWithoutLan]
                                );
                    })
                    ->paginate(10);

        $canonicalLink = $plats;

        // Rensa uppp plats lite
        $plats = sprintf(
            '%1$s i %2$s',
            title_case($platsWithoutLan),
            title_case($oneLanName)
        );
    } else {
        // Hämta events där plats är från huvudtabellen
        // Används när $plats är bara en plats, typ "insjön",
        // "östersunds centrum", "östra karup", "kungsgatan" osv.
        $events = CrimeEvent::orderBy("created_at", "desc")
                                    ->where("parsed_title_location", $plats)
                                    ->orWhere("administrative_area_level_2", $plats)
                                    ->orWhereHas('locations', function ($query) use ($plats) {
                                            $query->where('name', '=', $plats);
                                    })
                                    ->paginate(10);
        $canonicalLink = $plats;
        $plats = title_case($plats);
    }

    $data["plats"] = $plats;
    $data["events"] = $events;

    $page = (int) $request->input("page", 1);

    if (!$page) {
        $page = 1;
    }

    $linkRelPrev = null;
    $linkRelNext = null;

    if ($page > 1) {
        $linkRelPrev = route('platsSingle', [
            'plats' => $platsOriginalFromSlug,
            'page' => $page - 1
        ]);
    }

    if ($page < $events->lastpage()) {
        $linkRelNext = route('platsSingle', [
            'plats' => $platsOriginalFromSlug,
            'page' => $page + 1
        ]);
    }

    $data["linkRelPrev"] = $linkRelPrev;
    $data["linkRelNext"] = $linkRelNext;

    if ($page == 1) {
        $canonicalLink = route('platsSingle', ['plats' => $platsOriginalFromSlug]);
    } else {
        $canonicalLink = route('platsSingle', ['plats' => $platsOriginalFromSlug, 'page' => $page]);
    }

    $data["canonicalLink"] = $canonicalLink;
    $data["page"] = $page;

    if (!$data["events"]->count()) {
        abort(404);
    }

    $breadcrumbs = new Creitive\Breadcrumbs\Breadcrumbs;
    $breadcrumbs->addCrumb('Hem', '/');
    $breadcrumbs->addCrumb('Platser', route("platserOverview"));
    $breadcrumbs->addCrumb(e($plats));

    $data["breadcrumbs"] = $breadcrumbs;

    // Hämta statistik för platsen
    // $data["chartImgUrl"] = App\Helper::getStatsImageChartUrl("Stockholms län");
    $introtext_key = "introtext-plats-$plats";

    $introtext = null;
    if ($page == 1) {
         $introtext = Markdown::parse(Setting::get($introtext_key));
    }

    $data["introtext"] = $introtext;

    return view('single-plats', $data);
})->name("platsSingle");

/**
 * Sida, med text typ, t.ex. "om brottsplatskartan" eller "api"
*/
Route::get('/sida/{pagename}', function ($pagename = null) {

    $pagetitle = "Sidan $pagename";

    switch ($pagename) {
        case "om":
            $pagetitle = "Om Brottsplatskartan";
            break;
        case "api":
            $pagetitle = "Brottsplatskartans API för att hämta brott från Polisen";
            break;
        case "appar":
            $pagetitle = "Brottsplatskartans app för Iphone och Android";
            break;
        case "stockholm":
            $pagetitle = "Senaste händelserna från Polisen i Stockholm";
            break;
    }


    $data = [
        "pagename" => $pagename,
        "pageTitle" => $pagetitle
    ];

    return view('page', $data);
})->name("page");

/**
 * Route för översiktssidan för ordlistan
 */
Route::get('/ordlista/{word}', function ($word, Request $request) {

    // Word kan vara "fylleri-lob" så vi ersätter minustecken med /
    $word = str_replace('-', '/', $word);
    // Meeen ord kan också vara "brott i nära relation" och då ska ju - egentligen vara " "
    $wordSpaces = str_replace('/', ' ', $word);

    $wordForQuery = DB::connection()->getPdo()->quote($word);
    $wordSpacesForQuery = DB::connection()->getPdo()->quote($wordSpaces);
    #dd($wordSpacesForQuery);

    // We use COLLATE so a query for "raddningstjanst" also matches "räddningstjänst"
    $word = Dictionary::whereRaw("word IN($wordForQuery, $wordSpacesForQuery COLLATE utf8_general_ci)")->first();

    // This gives collate error, not sure why
    // $word = DB::select('select * from dictionaries where word = ? COLLATE utf8_general_ci', [$wordForQuery]);

    if (empty($word)) {
        abort(404);
    }

    $breadcrumbs = new Creitive\Breadcrumbs\Breadcrumbs;
    $breadcrumbs->addCrumb('Hem', '/');
    $breadcrumbs->addCrumb('Ordlista', route('ordlista'));
    $breadcrumbs->addCrumb($word->word, route('ordlistaOrd', ['word' => $word->word]));

    $data = [
        'word' => $word,
        'breadcrumbs' => $breadcrumbs
    ];

    return view('dictionary-word', $data);
})->name("ordlistaOrd");

/**
 * Route för översiktssidan för ordlistan
 */
Route::get('/ordlista/', function (Request $request) {
    $words = Dictionary::orderBy('word', 'asc')->get();

    $breadcrumbs = new Creitive\Breadcrumbs\Breadcrumbs;
    $breadcrumbs->addCrumb('Hem', '/');
    $breadcrumbs->addCrumb('Ordlista', route("ordlista"));

    $data = [
        'words' => $words,
        'breadcrumbs' => $breadcrumbs
    ];

    return view('dictionary', $data);
})->name("ordlista");

/**
 * Ett län, t.ex. Stockholms län
 * @param string $lan Namn på län, t.ex. "Stockholms län". Kan även vara "stockholms-län" (med minusstreck)
 */
Route::get('/lan/{lan}', function ($lan, Request $request) {

    // Om län innehåller minustecken ersätter vi det med mellanslag, pga lagrar länen icke-slug'ade
    $lan = str_replace('-', ' ', $lan);

    $page = (int) $request->input("page", 1);

    if (!$page) {
        $page = 1;
    }

    $events = CrimeEvent::orderBy("created_at", "desc")
                                ->where("administrative_area_level_1", $lan)
                                ->paginate(10);

    $linkRelPrev = null;
    $linkRelNext = null;

    if ($page > 1) {
        $linkRelPrev = route('lanSingle', [
            'lan' => $lan,
            'page' => $page - 1
        ]);
    }

    if ($page < $events->lastpage()) {
        $linkRelNext = route('lanSingle', [
            'lan' => $lan,
            'page' => $page + 1
        ]);
    }

    if ($page == 1) {
        $canonicalLink = route('lanSingle', ['lan' => $lan]);
    } else {
        $canonicalLink = route('lanSingle', ['lan' => $lan, 'page' => $page]);
    }

    $data = [
        'events' => $events,
        'lan' => $lan,
        'page' => $page,
        'linkRelPrev' => $linkRelPrev,
        'linkRelNext' => $linkRelNext,
        'canonicalLink' => $canonicalLink
    ];

    if (!$data["events"]->count()) {
        abort(404);
    }

    $breadcrumbs = new Creitive\Breadcrumbs\Breadcrumbs;
    $breadcrumbs->addCrumb('Hem', '/');
    $breadcrumbs->addCrumb('Län', route("lanOverview"));
    $breadcrumbs->addCrumb(e($lan), e($lan));

    $data["breadcrumbs"] = $breadcrumbs;
    $data["showLanSwitcher"] = true;

    // Kolla om förklarande text för län finns
    // key = like "introtext-lan-Stockholms län"
    $introtext_key = "introtext-lan-$lan";
    $data["introtext"] = Markdown::parse(Setting::get($introtext_key));

    // Hämta statistik för ett län
    $data["lanChartImgUrl"] = App\Helper::getStatsImageChartUrl($lan);

    $data["lanInfo"] = App\Helper::getSingleLanWithStats($lan);

    return view('single-lan', $data);
})->name("lanSingle");

/**
 * Uppdatera saker kring ett single event
 */
Route::post('/{lan}/{eventName}', function ($lan, $eventName, Request $request) {

    preg_match('!\d+$!', $eventName, $matches);
    $eventID = $matches[0];

    if (!$eventID) {
        abort(404);
    }

    $origin = $request->header('origin');

    \App\Newsarticle::create([
        'crime_event_id' => $eventID,
        'title' => $request->title,
        'shortdesc' => $request->shortdesc,
        'url' => $request->url,
        'source' => ''
    ]);

    return response()
            ->json([
                'saved' => true
            ])
            ->withHeaders([
                'AMP-Access-Control-Allow-Source-Origin' => $origin
            ]);

});

/**
 * single event page/en händelse/ett crimeevent
 * ca. såhär:
 *
 * http://brottsplatskartan.se/vastra-gotalands-lan/rattfylleri-2331
 *
 */
Route::get('/{lan}/{eventName}', function ($lan, $eventName, Request $request) {

    // event måste innehålla siffra sist = crime event id
    preg_match('!\d+$!', $eventName, $matches);
    if (!isset($matches[0])) {
        abort(404);
    }

    // län får inte vara siffra, om det är det så är det en gammal url som besöks (finns träffar kvar i google)
    // https://brottsplatskartan.dev/20034/misshandel-grov-torget-karlskoga-2611-jun-2013
    if (is_numeric($lan)) {
        // dd("old event, abort");
        abort(404);
    }

    $eventID = $matches[0];
    $event = CrimeEvent::findOrFail($eventID);

    $breadcrumbs = new Creitive\Breadcrumbs\Breadcrumbs;
    $breadcrumbs->addCrumb('Hem', '/');
    $breadcrumbs->addCrumb('Län', route("lanOverview"));

    if ($event->administrative_area_level_1) {
        $breadcrumbs->addCrumb(
            e($event->administrative_area_level_1),
            route("lanSingle", ["lan" => $event->administrative_area_level_1], true)
        );
    }

    $breadcrumbs->addCrumb(e($event->parsed_title));

    // optional debug
    $debugData = (array) CrimeEvent::maybeAddDebugData($request, $event);

    // maybe clear locations and re-encode
    $debugData = $debugData + (array) $event->maybeClearLocationData($request);

    // Add nearby events
    $eventsNearby = CrimeEvent::getEventsNearLocation($event->location_lat, $event->location_lng, $nearbyCount = 10, $nearbyInKm = 25);

    // Hämta alla ord i ordlistan, oavsett om de ligger i word eller synonyms
    #dd($eventText);
    $text = $event->getSingleEventTitle() . ' ' . $event->getParsedContentAsPlainText();
    $dictionaryWordsInText = Dictionary::getWordsInText($text);

    if (isset($_GET["debug1"])) {
        dd($dictionaryWordsInText);
    }

    if (isset($_GET["debug2"])) {
        dd($text);
    }

    // Hämta nyhetsartiklar som hör till händelsen
    $newsarticles = $event->newsarticles;

    $data = [
        'lan' => $lan,
        'eventID' => $eventID,
        'event' => $event,
        'eventsNearby' => $eventsNearby,
        'breadcrumbs' => $breadcrumbs,
        'debugData' => $debugData,
        'dictionaryWordsInText' => $dictionaryWordsInText,
        'newsarticles' => $newsarticles
    ];

    return view('single-event', $data);
})->name("singleEvent");




/**
 * sök
 * sökstartsida + sökresultatsida = samma sida
 */
Route::get('/sok/', function (Request $request) {

    $minSearchLength = 2;

    $s = $request->input("s");
    $events = null;

    $breadcrumbs = new Creitive\Breadcrumbs\Breadcrumbs;
    $breadcrumbs->addCrumb('Hem', '/');
    $breadcrumbs->addCrumb('Sök', route("search"));

    if ($s && mb_strlen($s) >= $minSearchLength) {
        $breadcrumbs->addCrumb(e($s));

        /*
        $events = CrimeEvent::where(function ($query) use ($s) {
            $query->where("description", "LIKE", "%$s%")
                ->orWhere("parsed_title_location", "LIKE", "%$s%")
                ->orWhere("parsed_content", "LIKE", "%$s%")
                ->orWhere("parsed_title", "LIKE", "%$s%")
                #->orWhereHas('locations', function ($query) use ($s) {
                #    $query->orWhere('name', 'like', "%$s%");
                #});
                ;
        })->orderBy("created_at", "desc")->paginate(10);
        */

        // Leta locations som matchar sökt fras
        $locations = Locations::search($s, [
            "name" => 20
        ])->get();

        $foundLocations = [];

        // Behåll bara unika platser
        $locations = $locations->filter(function ($location) use (& $foundLocations) {
            $name = ucwords($location->name);

            if (in_array($name, $foundLocations)) {
                return false;
            }

            $foundLocations[] = $name;
            return true;
        });

        // Se till att platserna inte blir för många
        $maxLocationsToShow = 10;
        if (sizeof($locations) > $maxLocationsToShow) {
            $locations = $locations->slice(0, $maxLocationsToShow);
        }

        // Sök med hjälp av Eloquence
        $events = CrimeEvent::search($s, [
            "parsed_title" => 20,
            "parsed_title_location" => 20, // crash when 10, works when 20
            "parsed_teaser" => 10,
            "administrative_area_level_1" => 10,
            "administrative_area_level_2" => 7,
            "description" => 5,
            "parsed_content" => 20
        ])->paginate(10);
    }

    $data = [
        "s" => $s,
        "events" => $events,
        "events2" => isset($events2) ? $events2 : null,
        "locations" => isset($locations) ? $locations : null,
        "breadcrumbs" => $breadcrumbs
    ];

    return view('search', $data);
})->name("search");

/**
 * coyards: sida för samarbete med coyards.se, visas i deras app och hemsida
 * Exempel för Danderyd: 59.407905 | Longitud: 18.019075
 * https://brottsplatskartan.dev/coyards?lat=59.407905&lng=18.019075&distance=5&count=25
 *
 * @param lat$ och lng$ som get-params. anger plats där händelser ska visas nära
 * @param $distance anger inom hur långt avstånd händelser ska hämtas, i km
 * @param $count max number of events to get
 */
Route::get('/coyards', function (Request $request) {

    $data = [];
    $events = null;

    $lat = (float) $request->input("lat");
    $lng = (float) $request->input("lng");
    $nearbyInKm = (float) $request->input("distance", 5);
    $nearbyCount = (int) $request->input("count", 25);
    $error = (bool) $request->input("error");

    $lat = round($lat, 5);
    $lng = round($lng, 5);

    $data["lat"] = $lat;
    $data["lng"] = $lng;

    if ($lat && $lng && ! $error) {
        $numTries = 0;

        // Start by showing $nearbyInKm
        // If no hits then move out until we have hits
        $events = CrimeEvent::getEventsNearLocation($lat, $lng, $nearbyCount, $nearbyInKm);
        $numTries++;

        // we want to show at least 5 events
        // if less than 5 events is found then increase the range by nn km, until a hit is found
        while ($events->count() < 5) {
            $nearbyInKm = $nearbyInKm + 5;
            $events = CrimeEvent::getEventsNearLocation($lat, $lng, $nearbyCount, $nearbyInKm);
            $numTries++;
        }

        $data["nearbyInKm"] = $nearbyInKm;
        $data["nearbyCount"] = $nearbyCount;
        $data["numTries"] = $numTries;
    } else {
        $data["error"] = true;
    }

    $data["events"] = $events;

    return view('coyards', $data);
})->name("coyards");

/**
 * Testsida för design, så vi lätt kan se hur rubriker
 * av olika storlekar och listor och stycken och bilder
 * osv samspelar.
 */
Route::get('/design', function (Request $request) {
    return view('design');
});

/**
 * Skicka med data till 404-sidan
 */
\View::composer('errors/404', function ($view) {

    $data = [];

    $data["events"] = CrimeEvent::orderBy("created_at", "desc")->paginate(10);

    // Hämta alla län, grupperat på län och antal
    $data["lan"] = DB::table('crime_events')
                ->select("administrative_area_level_1")
                ->groupBy('administrative_area_level_1')
                ->orderBy('administrative_area_level_1', 'asc')
                ->where('administrative_area_level_1', "!=", "")
                ->get();


    $view->with($data);
});

/*
Route::get('loggain', function () {
    // Only authenticated users may enter...
    return redirect('/?inloggad=jajjemensan');
})->middleware('auth.basic');
*/

// Added by php artisan make:auth
Auth::routes();
Route::get('/home', 'HomeController@index')->name('home');

Route::get('logout', function () {
    Auth::logout();
    return redirect('/');
});
