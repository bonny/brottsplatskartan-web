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

// To disable debugbar temporarily on local development, uncomment the line below.
// \Debugbar::disable();
if ($_GET['debugbar-disable'] ?? false) {
    \Debugbar::disable();
} elseif ($_GET['debugbar-enable'] ?? false) {
    \Debugbar::enable();
} else { }

Route::get('/debug/{what}', 'DebugController@debug')->name('debug');

Route::redirect('/karta/', '/sverigekartan/', 301);
Route::get('/sverigekartan/{location?}', 'FullScreenMapController@index')->name(
    'FullScreenMap'
);

// URL is like
// https://brottsplatskartan.localhost/pixel?path=%2Fstockholms-lan%2Ftrafikolycka-taby-taby-kyrkby-37653&rand=0.1843466328440977
//
Route::get('/pixel', 'PixelController@pixel');

Route::get('/polisstationer', 'PolisstationerController@index')->name(
    'polisstationer'
);

/**
 * startpage: visa senaste händelserna, datum/dag-versionen
 *
 * URL är t.ex:
 * https://brottsplatskartan.se/
 * https://brottsplatskartan.se/handelser/15-januari-2018
 * https://brottsplatskartan.se/handelser/ › https://brottsplatskartan.se/
 *
 * @param string $year Year in format "december-2017"
 */
Route::match(['get', 'post'], '/', 'StartController@day')->name('start');

Route::get('/handelser/{date}', 'StartController@day')->name('startDatum');
Route::redirect('/handelser/', '/');

/**
 * Skicka vidare gamla /datum-urlar till /handelser
 */
Route::redirect('/datum/', '/handelser/');
Route::get('/datum/{date}', function ($date) {
    return redirect()->route('startDatum', ['date' => $date]);
});

/**
 * nära: show latest events close to position
 */
Route::get('/nara', 'GeoController@nara')->name("geo");
Route::redirect('/geo.php', '/nara-hitta-plats');
Route::get('/nara-hitta-plats', 'GeoController@geoDetect')->name("geoDetect");

/**
 * Län
 * - översikt över alla län
 * - listning av händelser i enskild län
 * - listning av händelser i enskild län per datum
 *
 * URL är t.ex.:
 * https://brottsplatskartan.se/lan/Stockholms%20l%C3%A4n
 * https://brottsplatskartan.se/lan/Stockholms%20l%C3%A4n/handelser/9-februari-2018
 * https://brottsplatskartan.se/lan/Stockholms%20l%C3%A4n/handelser/ › länets url
 */
Route::get('/lan/', 'LanController@listLan')->name("lanOverview");
Route::get('/lan/{lan}', 'LanController@day')->name("lanSingle");
Route::get('/lan/{lan}/handelser/{date}', 'LanController@day')->name('lanDate');
Route::get('/lan/{lan}/handelser', function ($lan) {
    return redirect()->route('lanSingle', ['lan' => $lan]);
});

/**
 * Alla orter översikt
 */
Route::get('/plats/', 'PlatsController@overview')->name("platserOverview");

/**
 * Url för ort så som den såg ut i Brottsplatskartan 2.
 * Skickar vidare besökare till nyare routen "ort"
 *
 * t.ex.:
 * https://brottsplatskartan.se/orter/Falkenberg
 * https://brottsplatskartan.se/orter/Stockholm
 * redirecta dessa till
 * https://brottsplatskartan.se/plats/<ortnamn>
 */
Route::get('/orter/{ort}', function ($ort = "") {
    return redirect()->route("platsSingle", ["ort" => $ort]);
});

/**
 * Översikt brottstyp/händelsetyp.
 *
 * Exempel på URL:
 * https://brottsplatskartan.se/typ
 */
Route::get('/typ/', function () {
    $data = [];

    $data["types"] = DB::table('crime_events')
        ->select("parsed_title")
        ->where('parsed_title', "!=", "")
        ->orderBy('parsed_title', 'asc')
        ->distinct()
        ->get();

    $breadcrumbs = new Creitive\Breadcrumbs\Breadcrumbs();
    $breadcrumbs->setDivider('›');
    $breadcrumbs->addCrumb('Hem', '/');
    $breadcrumbs->addCrumb('Brottstyper', route("typeOverview"));

    $data["breadcrumbs"] = $breadcrumbs;

    return view('overview-typer', $data);
})->name("typeOverview");

/**
 * En typ av brott/händelse.
 *
 * Exempel på URL:
 * https://brottsplatskartan.se/typ/Inbrott
 */
Route::get('/typ/{typ}', function ($typ) {
    $data = [
        "type" => $typ
    ];

    // Skicka vidare anrop till inbrotts-relaterade saker till sidan om inbrott.
    $inbrottSlugs = [
        'inbrott',
        'inbrott, försök',
        'inbrott,  försök',
        'larm inbrott',
        'stöld/inbrott',
        'hemfridsbrott'
    ];

    if (in_array(mb_strtolower($typ), $inbrottSlugs)) {
        return redirect()->route('inbrott');
    }

    // Skicka vidare anrop till brand-relaterade saker till sidan om brand.
    $inbrottSlugs = [
        'brand',
        'brand automatlarm',
    ];

    if (in_array(mb_strtolower($typ), $inbrottSlugs)) {
        return redirect()->route('brand');
    }

    $data["events"] = CrimeEvent::orderBy("created_at", "desc")
        ->where("parsed_title", $typ)
        ->paginate(10);

    if (!$data["events"]->count()) {
        abort(404);
    }

    $breadcrumbs = new Creitive\Breadcrumbs\Breadcrumbs();
    $breadcrumbs->setDivider('›');
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
 * Ny struktur, med plats + län pga samma gatunamn finns på flera ställen ibland
 * och även om detta inte är exakt så är det mer nära rätt iaf:
 *
 *  /plats/storgatan-örebro-län/
 *  /plats/storgatan-gävleborgs-län/
 *
 * Gammal struktur, med plats utan län:
 *
 *  /plats/storgatan/

 */
Route::get('/plats/{plats}', 'PlatsController@day')->name("platsSingle");
Route::get('/plats/{plats}/handelser', function ($plats) {
    return redirect()->route('platsSingle', ['plats' => $plats]);
});
Route::get('/plats/{plats}/handelser/{date}', 'PlatsController@day')->name(
    'platsDatum'
);

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
            $pagetitle =
                "Brottsplatskartans API för att hämta brott från Polisen";
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
        "pageTitle" => $pagetitle,
        "canonicalLink" => route('page', [
            'pagename' => mb_strtolower($pagename)
        ])
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

    $wordForQuery = DB::connection()
        ->getPdo()
        ->quote($word);
    $wordSpacesForQuery = DB::connection()
        ->getPdo()
        ->quote($wordSpaces);

    // We use COLLATE so a query for "raddningstjanst" also matches "räddningstjänst"
    $word = Dictionary::whereRaw(
        "word IN($wordForQuery, $wordSpacesForQuery COLLATE utf8mb4_general_ci)"
    )->first();

    // This gives collate error, not sure why
    // $word = DB::select('select * from dictionaries where word = ? COLLATE utf8_general_ci', [$wordForQuery]);

    if (empty($word)) {
        abort(404);
    }

    $breadcrumbs = new Creitive\Breadcrumbs\Breadcrumbs();
    $breadcrumbs->setDivider('›');
    $breadcrumbs->addCrumb('Hem', '/');
    $breadcrumbs->addCrumb('Ordlista', route('ordlista'));
    $breadcrumbs->addCrumb(
        $word->word,
        route('ordlistaOrd', ['word' => $word->word])
    );

    $allWords = Dictionary::pluck('word');

    $data = [
        'word' => $word,
        'allWords' => $allWords,
        'breadcrumbs' => $breadcrumbs
    ];

    return view('dictionary-word', $data);
})->name("ordlistaOrd");

/**
 * Route för översiktssidan för ordlistan
 */
Route::get('/ordlista/', function (Request $request) {
    $words = Dictionary::orderBy('word', 'asc')->get();

    $breadcrumbs = new Creitive\Breadcrumbs\Breadcrumbs();
    $breadcrumbs->setDivider('›');
    $breadcrumbs->addCrumb('Hem', '/');
    $breadcrumbs->addCrumb('Ordlista', route("ordlista"));

    $data = [
        'words' => $words,
        'breadcrumbs' => $breadcrumbs
    ];

    return view('dictionary', $data);
})->name("ordlista");

/**
 * Uppdatera saker kring ett single event
 */
Route::post('/{lan}/{eventName}', function (
    $lan,
    $eventName,
    Request $request
) {
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
 * Routes för blogg
 *
 * Senaste inläggen
 * - brottsplatskartan.se/blogg
 *
 * Inlägg från 2017
 * - brottsplatskartan.se/blogg/2017/
 *
 * Enskild inlägg från 2017
 * - brottsplatskartan.se/blogg/2017/polisen-se-nere
 */
Route::prefix('blogg')->group(function () {
    Route::get('/', function () {
        // Matchar https://brottsplatskartan.localhost/blogg
        $blogItems = App\Blog::orderBy("created_at", "desc")->get(); // ->paginate(10);

        $breadcrumbs = new Creitive\Breadcrumbs\Breadcrumbs();
        $breadcrumbs->setDivider('›');
        $breadcrumbs->addCrumb('Hem', '/');
        $breadcrumbs->addCrumb('Blogg');

        $data = [
            'blogItems' => $blogItems,
            'breadcrumbs' => $breadcrumbs
        ];

        return view('blog-start', $data);
    })->name('blog');

    // https://brottsplatskartan.localhost/blogg/2017/hejsan
    Route::get('{year}', function ($year) {
        // Matchar https://brottsplatskartan.localhost/blogg/2017

        if (!is_numeric($year)) {
            abort(404);
        }

        $blogItems = App\Blog::whereYear('created_at', $year)
            ->orderBy("created_at", "desc")
            ->paginate(10);

        $breadcrumbs = new Creitive\Breadcrumbs\Breadcrumbs();
        $breadcrumbs->setDivider('›');
        $breadcrumbs->addCrumb('Hem', '/');
        $breadcrumbs->addCrumb('Blogg');

        $data = [
            'blogItems' => $blogItems,
            'breadcrumbs' => $breadcrumbs
        ];

        return view('blog-start', $data);
    })->name('blogYear');

    // Enskilt inlägg.
    // Matchar https://brottsplatskartan.localhost/blogg/2017/mitt-blogginlagg.
    Route::get('{year}/{slug}', function ($year, $slug) {
        $blog = App\Blog::where('slug', $slug)->first();

        if (!$blog) {
            // abort(404);
            return view('blog-start', []);
        }

        $breadcrumbs = new Creitive\Breadcrumbs\Breadcrumbs();
        $breadcrumbs->setDivider('›');
        $breadcrumbs->addCrumb('Hem', '/');
        $breadcrumbs->addCrumb('Blogg', route("blog"));
        $breadcrumbs->addCrumb($blog->title);

        $data = [
            'blog' => $blog,
            'breadcrumbs' => $breadcrumbs
        ];

        return view('single-blog-item', $data);
    })->name('blogItem');
});

/**
 * Huvudsida + undersidor för inbrott, grannsamverkan och liknande.
 */
Route::get('/inbrott/{undersida?}', function (
    Request $request,
    $undersida = 'start'
) {
    // Hämta se senaste händelserna som innehåller "inbrott", "larm", "intrång", osv.
    $latestInbrottEvents = CrimeEvent::orderBy("created_at", "desc")
        ->where("parsed_title", 'like', '%inbrott%')
        ->orWhere("parsed_title", 'like', '%larm%')
        ->orWhere("parsed_title", 'like', '%intrång%')
        ->paginate(40);

    $undersidor = \App\Helper::getInbrottNavItems();

    // Bail om undersida inte finns.
    $valdUndersida = $undersidor[$undersida] ?? null;
    if (!$valdUndersida) {
        abort(404);
    }

    // Lägg till breadcrumb.
    $breadcrumbs = new Creitive\Breadcrumbs\Breadcrumbs();
    $breadcrumbs->setDivider('›');
    $breadcrumbs->addCrumb('Hem', '/');
    $breadcrumbs->addCrumb('Inbrott', route("inbrott"));

    if (!empty($valdUndersida) && $undersida !== 'start') {
        $breadcrumbs->addCrumb(
            $valdUndersida['title'],
            route("inbrott", ['undersida' => $undersida])
        );
    }

    $data = [
        'title' => $valdUndersida['title'],
        'pageTitle' => $valdUndersida['pageTitle'],
        'pageSubtitle' => $valdUndersida['pageSubtitle'] ?? null,
        'canonicalLink' => $valdUndersida['url'],
        'breadcrumbs' => $breadcrumbs,
        'latestInbrottEvents' => $latestInbrottEvents,
        'undersidor' => $undersidor,
        'undersida' => $undersida
    ];

    return view('inbrott', $data);
})->name('inbrott');

/**
 * Huvudsida + undersidor för inbrott, grannsamverkan och liknande.
 */
Route::get('/brand/{undersida?}', function (
    Request $request,
    $undersida = 'start'
) {
    // Hämta se senaste händelserna som innehåller "brand" osv.
    $latestBrandEvents = CrimeEvent::orderBy("created_at", "desc")
        ->where("parsed_title", 'like', '%brand%')
        ->orWhere("parsed_title", 'like', '%brand%')
        ->orWhere("parsed_title", 'like', '%mordbrand%')
        ->orWhere("parsed_title", 'like', '%brinner%')
        ->orWhere("parsed_title", 'like', '%brinna%')
        ->orWhere("parsed_title", 'like', '%rökutveckling%')
        ->orWhere("parsed_title", 'like', '%röklukt%')
        ->paginate(40);

    $undersidor = \App\Helper::getBrandNavItems();

    // Bail om undersida inte finns.
    $valdUndersida = $undersidor[$undersida] ?? null;
    if (!$valdUndersida) {
        abort(404);
    }

    // Lägg till breadcrumb.
    $breadcrumbs = new Creitive\Breadcrumbs\Breadcrumbs();
    $breadcrumbs->setDivider('›');
    $breadcrumbs->addCrumb('Hem', '/');
    $breadcrumbs->addCrumb('Brand', route("brand"));

    if (!empty($valdUndersida) && $undersida !== 'start') {
        $breadcrumbs->addCrumb(
            $valdUndersida['title'],
            route("brand", ['undersida' => $undersida])
        );
    }

    $data = [
        'title' => $valdUndersida['title'],
        'pageTitle' => $valdUndersida['pageTitle'],
        'pageSubtitle' => $valdUndersida['pageSubtitle'] ?? null,
        'canonicalLink' => $valdUndersida['url'],
        'breadcrumbs' => $breadcrumbs,
        'latestBrandEvents' => $latestBrandEvents,
        'undersidor' => $undersidor,
        'undersida' => $undersida
    ];

    return view('brand', $data);
})->name('brand');

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

    $cacheKey = "route-lan-event:{$lan}:{$eventName}";
    $event = Cache::remember($cacheKey, 2, function () use ($eventID) {
        $event = CrimeEvent::with(['locations', 'newsarticles'])->findOrFail(
            $eventID
        );
        return $event;
    });

    $breadcrumbs = new Creitive\Breadcrumbs\Breadcrumbs();
    $breadcrumbs->setDivider('›');
    $breadcrumbs->addCrumb('Hem', '/');
    $breadcrumbs->addCrumb('Län', route("lanOverview"));

    if ($event->administrative_area_level_1) {
        $breadcrumbs->addCrumb(
            e($event->administrative_area_level_1),
            route(
                "lanSingle",
                ["lan" => $event->administrative_area_level_1],
                true
            )
        );
    }

    $breadcrumbs->addCrumb(e($event->parsed_title));

    // optional debug
    $debugData = (array)CrimeEvent::maybeAddDebugData($request, $event);

    // maybe clear locations and re-encode
    $debugData = $debugData + (array)$event->maybeClearLocationData($request);

    // Add nearby events
    $eventsNearby = CrimeEvent::getEventsNearLocation(
        $event->location_lat,
        $event->location_lng,
        $nearbyCount = 10,
        $nearbyInKm = 25
    );

    // Hämta alla ord i ordlistan, oavsett om de ligger i word eller synonyms
    $text =
        $event->getSingleEventTitle() .
        ' ' .
        $event->getParsedContentAsPlainText();
    $dictionaryWordsInText = Dictionary::getWordsInTextCached($text);

    if (isset($_GET["debug1"])) {
        dd($dictionaryWordsInText);
    }

    if (isset($_GET["debug2"])) {
        dd($text);
    }

    // Hämta nyhetsartiklar som hör till händelsen.
    $newsarticles = $event->newsarticles;

    $data = [
        'lan' => $lan,
        'eventID' => $eventID,
        'event' => $event,
        'eventsNearby' => $eventsNearby,
        'breadcrumbs' => $breadcrumbs,
        'debugData' => $debugData,
        'dictionaryWordsInText' => $dictionaryWordsInText,
        'newsarticles' => $newsarticles,
        'mostViewed' => \App\Helper::getMostViewedEvents(Carbon::now(), 5)
    ];

    return view('single-event', $data);
})->name("singleEvent");

/**
 * sök
 * sökstartsida + sökresultatsida = samma sida
 */
Route::get('/sok/', 'SearchController@index')->name("search");
Route::get('/sokresultat/', 'SearchController@searchperform')->name(
    "searchperform"
);

/**
 * coyards: sida för samarbete med coyards.se, visas i deras app och hemsida
 * Exempel för Danderyd: 59.407905 | Longitud: 18.019075
 * https://brottsplatskartan.localhost/coyards?lat=59.407905&lng=18.019075&distance=5&count=25
 * To view as (simple) HTML.
 * https://brottsplatskartan.localhost/coyards?lat=59.407905&lng=18.019075&distance=5&count=25&format=html
 *
 * @param lat$ och lng$ som get-params. anger plats där händelser ska visas nära
 * @param $distance anger inom hur långt avstånd händelser ska hämtas, i km
 * @param $count max number of events to get
 */
Route::resource('coyards', 'CoyardsController')->names([
    'index' => 'coyards'
]);

/**
 * Testsida för design, så vi lätt kan se hur rubriker
 * av olika storlekar och listor och stycken och bilder
 * osv samspelar.
 */
Route::get('/design', function (Request $request) {
    // Hämta in senaste brottet/händelsen.
    $event = CrimeEvent::orderBy('created_at', 'desc')->first();

    $data = [
        'event' => $event,
        'eventID' => $event->id
    ];

    return view('design', $data);
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

// Add route for log viewer
// https://github.com/rap2hpoutre/laravel-log-viewer
Route::get(
    'logs',
    '\Rap2hpoutre\LaravelLogViewer\LogViewerController@index'
)->middleware('auth');

// Add routes for RSS feeds.
// https://github.com/spatie/laravel-feed
Route::feeds();
