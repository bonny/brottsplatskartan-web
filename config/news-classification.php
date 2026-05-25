<?php

/**
 * Konfiguration för per-plats nyhetsaggregering (todo #64).
 *
 * Klassifikations-pass markerar artiklar i `news_articles` som blåljus-
 * relaterade och kopplar dem till `places` via stripos-matching.
 */
return [
    /*
     * Används av regex-passet (`app:news:classify`) — en artikel klassas
     * som blåljus om något ord matchar med Unicode-ordgränser. Listan är
     * medvetet bred eftersom plats-matchningen agerar som extra filter.
     *
     * OBS: detta är INTE prefiltret för AI-passet. Det styrs av nycklarna
     * `prefilter_prefix_stems`, `prefilter_suffix_terms`,
     * `prefilter_foreign_places` och `prefilter_swedish_markers` nedan.
     */
    'blaljus_terms' => [
        'polis', 'polisen', 'polisens', 'poliser', 'poliserna', 'polismän', 'polisinsats', 'polisman', 'gripen',
        'gripande', 'anhållen', 'häktad', 'häktas', 'misstänkt', 'misstänkts',
        'åtal', 'åtalad', 'åtalas', 'åtalade', 'dömd', 'döms', 'dömdes',
        'brott', 'brottet', 'brottslig', 'brand', 'branden', 'bränder', 'brinner', 'brand-', 'brinnande',
        'mordbrand', 'pyroman', 'eldsvåda', 'rökutveckling', 'räddningstjänst', 'räddningstjänsten',
        'räddningsinsats', 'utryckning', 'larm', 'blåljus',
        'rån', 'rånad', 'rånet', 'rånare', 'inbrott', 'inbrottet',
        'stulen', 'stöld', 'tillgripen', 'tillgrepp', 'snattade',
        'bedrägeri', 'bedrägerier', 'bluffannons', 'bluffmejl',
        'mord', 'mordet', 'dråp', 'dödad', 'dödligt', 'avliden', 'omkommen', 'omkom', 'död',
        'misshandel', 'misshandlad', 'misshandlade',
        'skottlossning', 'skotten', 'skjuten', 'skjutning', 'skjutningar',
        'sprängning', 'sprängdåd', 'bombdåd', 'attentat', 'detonation', 'explosion', 'explosioner',
        'granat', 'granater', 'raket', 'raketer',
        'kniv', 'knivhot', 'knivattack', 'knivskuren', 'knivskars', 'knivöverfall',
        'olycka', 'olyckan', 'olyckor', 'trafikolycka', 'krock', 'krockade',
        'frontalkrock', 'kollision', 'kolliderade', 'kollidera', 'singelolycka',
        'påkörd', 'påkörda', 'omkullkörd',
        'försvunnen', 'efterlyst', 'efterlyses', 'försvann',
        'evakuerad', 'evakuering', 'avspärrat', 'avspärrad', 'avspärrning',
        'narkotika', 'drog', 'droger', 'narkotikabrott',
        'drunkning', 'drunknad', 'drunknat',
        'ras', 'översvämning', 'översvämmad', 'översvämningar',
        'gänget', 'gängbråk', 'skadeskjuten', 'skottskadad',
        'skadegörelse', 'klotter', 'vandalism',
    ],

    /*
     * Prefilter för AI-passet (`app:news:ai-classify`, todo #81 fas 2).
     *
     * Mätt 2026-05-25 mot 7d prod-data: 66 % av AI-anropen var brus
     * (AI-NEJ). Nuvarande prefilter (mb_strpos, brett) saknar ordgränser
     * och matchar "rån" i "från", "Iran"; "ras" i "krasch", "rasism";
     * "raket" i "raketbolag". Ny prefilter använder fyra mekanismer:
     *
     * 1. PREFIX_STEMS — ord-prefix (matchar polisens, polismannen via
     *    stammen "polis"). Implementerat som regex `(?<![\p{L}])stam\p{L}*`.
     * 2. SUFFIX_OK_TERMS — sammansättnings-suffix (matchar "villabrand",
     *    "Rönningemordet", "dödsolyckan" via `\p{L}(term)\p{L}*`).
     *    BARA termer som är substring-säkra ("rån" är medvetet uteslutet
     *    pga "från"; använd PREFIX_STEMS för rån istället).
     * 3. FOREIGN_PLACES + SWEDISH_MARKERS — om titeln innehåller en
     *    utländsk plats (som hela ord) OCH ingen svensk markör finns i
     *    titel + summary, vetas artikeln. Skydd mot Mexiko-skjutningar
     *    och Ukraina-explosioner som AI klassar som blåljus men inte
     *    tillhör svenska plats-sidor.
     *
     * Simulering 2026-05-25 mot 3 686 prod-artiklar: 98.8 % recall,
     * 45.8 % skip-rate (vs nuvarande 100 % recall, 0 % skip). Förväntad
     * besparing: ~$27/mån på NewsClassifier ($59 → $32).
     */
    'prefilter_prefix_stems' => [
        // Polis/myndighet/dom
        'polis', 'gripen', 'gripit', 'gripits', 'anhåll',
        'häkt', 'misstänk', 'åtal', 'döm',
        // Brott (generellt + sammansättningar)
        'brott', 'inbrott', 'brottsling', 'brottslig',
        // Brand-stam + vanliga sammansättningar (suffix-fallet
        // täcks separat via prefilter_suffix_terms, men vissa
        // vanliga sammansättningar listas explicit för säkerhet)
        'brand', 'brände', 'brann', 'brinn', 'bränn',
        'eldsvåda', 'mordbrand', 'pyroman', 'rökutveckl',
        'villabrand', 'lägenhetsbrand', 'lägenhetsbränder',
        'skogsbrand', 'skogsbränder',
        'bilbrand', 'bilbränder', 'läktarbrand', 'läktarbränder',
        'soptunnebrand', 'soptunnebränder', 'soptunnabrand',
        'fordonsbrand', 'fordonsbränder', 'bussbrand', 'bussbränder',
        'containerbrand', 'containerbränder',
        'gräsbrand', 'gräsbränder', 'skolbrand', 'skolbränder',
        'fastighetsbrand', 'fastighetsbränder',
        'restaurangbrand', 'restaurangbränder',
        // Räddning
        'räddningstjänst', 'räddningsinsats', 'räddningsledare', 'räddningsstyrka',
        'utryckning', 'blåljus',
        // Larm (kärna)
        'larm', 'larmade', 'larmas', 'larmats', 'larmat', 'larmet', 'larmen',
        // Tillgrepp
        'rån', 'stöld', 'stulen', 'stulet', 'stulna',
        'tillgrip', 'tillgrep', 'snatt', 'snattade', 'snatteri',
        // Bedrägeri/bluff
        'bedräger', 'bluffannons', 'bluffmejl', 'bluffsamtal', 'bluffmail',
        // Våld/dödsfall
        'mord', 'mörd', 'dråp',
        'död', 'döda', 'dödar', 'dödat', 'dödade', 'dödlig', 'dödligt', 'dödliga',
        'avliden', 'avlidet', 'avlidne', 'avlidna',
        'omkom', 'omkommen', 'omkomna', 'omkommet',
        'dog', 'döende',
        'misshandel', 'misshandlad', 'misshandlade', 'misshandlas', 'misshandlats',
        // Skott/skjut
        'skottlossning', 'skottlossningar', 'skotten',
        'skjut', 'beskjut', 'skadeskjut', 'skottskad',
        // Sprängning/explosion
        'sprängning', 'sprängningar', 'sprängdåd',
        'sprängde', 'sprängdes', 'sprängt',
        'bomb', 'attentat', 'detonation', 'detonationer',
        'explod', 'explosion', 'explosioner', 'explosivt',
        'granat', 'granater',
        // Vapen/kniv
        'kniv', 'knivhot', 'knivattack', 'knivskuren', 'knivskars',
        'knivöverfall', 'knivskärning', 'knivvåld',
        // Trafik/olycka + sammansättningar
        'olycka', 'olyckan', 'olyckor', 'olyckorna',
        'trafikolycka', 'trafikolyckor', 'trafikolyckan',
        'singelolycka', 'singelolyckan',
        'arbetsplatsolycka', 'arbetsolycka',
        'fallolycka', 'fallolyckor',
        'cykelolycka', 'mopedolycka', 'motorcykelolycka',
        'drunkningsolycka', 'drunkningsolyckor',
        'krock', 'krockade', 'krockat', 'frontalkrock', 'sidokrock',
        'kollision', 'kolliderade', 'kollidera',
        'krasch', 'kraschen', 'kraschade', 'kraschat',
        'bilkrasch', 'bilkraschen',
        'påkörd', 'påkörda', 'påkört', 'omkullkörd', 'omkullkörda',
        // Försvinnande
        'försvunn', 'försvann', 'efterlys',
        // Evakuering/avspärrning
        'evakuera', 'evakuering', 'evakuerad', 'evakuerade', 'evakuerats',
        'avspärr',
        // Narkotika
        'narkotika', 'narkotikabrott',
        // Vatten
        'drunkn',
        // Natur (sammansättningar för ras — inte bara "ras" som
        // kolliderar med rasism/krasch)
        'jordbävning', 'jordbävningar',
        'översvämn',
        'jordras', 'gruvras', 'stenras', 'snöras', 'bergras', 'takras',
        'jordskred', 'skred',
        // "ras" som specifika ord (matchar inte "rasism", "krasch")
        'raset', 'rasen', 'rasade', 'rasat', 'rasar',
        // Gäng
        'gängbråk', 'gängskjutning', 'gängkrim',
        // Skadegörelse
        'skadegörelse', 'klotter', 'vandalis',
        // Sexualbrott
        'våldtäkt', 'våldtagen', 'våldtog',
        'sexualbrott', 'sexuellt',
        // Hot/bomb-skämt
        'bombhot', 'bombskämt',
        // Brottsutredning
        'spaning', 'tillslag', 'razzia',
    ],

    /*
     * Termer som accepteras även som suffix i sammansättningar.
     * Matchas via `\p{L}(term)\p{L}*` — kräver minst ETT ordtecken före,
     * dvs sammansättning. Välj BARA substring-säkra ord — "rån" är
     * uteslutet (matchar "från").
     */
    'prefilter_suffix_terms' => [
        'mord', 'mordet', 'morden',
        'sprängning', 'sprängningen', 'sprängningar',
        'olycka', 'olyckan', 'olyckor', 'olyckorna',
        'krock', 'krocken',
        'krasch', 'kraschen',
        'kollision',
        'skjutning', 'skjutningar', 'skjut', 'skjuten', 'skjutna',
        'brand', 'branden', 'bränder',
        'beslag',
        'jakt',
        'rånet', 'rånen', 'rånad',
    ],

    /*
     * Utländska land/region-namn som triggar foreign-veto. Matchas som
     * hela ord (Unicode-ordgränser) — så "Iran" matchar inte
     * "Iranexperten". Vetas BARA om finns i titeln OCH ingen svensk
     * markör finns i hela texten.
     */
    'prefilter_foreign_places' => [
        'gaza', 'israel', 'palestin',
        'ukraina', 'ukrainsk', 'kiev', 'kyiv', 'kharkiv', 'mariupol',
        'ryssland', 'rysk', 'moskva', 'putin', 'kreml',
        'iran', 'iransk', 'teheran',
        'turkiet', 'turkisk',
        'mexiko', 'mexikansk',
        'usa', 'amerikansk', 'washington',
        'tyskland', 'tysk', 'berlin', 'münchen',
        'frankrike', 'fransk', 'paris',
        'polen', 'polsk', 'warszawa',
        'estland', 'estnisk',
        'lettland', 'lettisk',
        'litauen', 'litauisk',
        'spanien', 'spansk', 'madrid', 'barcelona',
        'italien', 'italiensk', 'rom', 'milano',
        'grekland', 'grekisk',
        'storbritannien', 'brittisk', 'london',
        'belgien', 'belgisk', 'bryssel',
        'nederländerna', 'amsterdam',
        'schweiz', 'schweizisk',
        'österrike', 'österrikisk',
        'serbien', 'kroatien', 'bosnien',
        'kina', 'kinesisk', 'peking',
        'japan', 'japansk', 'tokyo',
        'indien', 'indisk',
        'pakistan',
        'afghanistan', 'taliban',
        'syrien', 'syrisk',
        'libanon', 'libanesisk',
        'jemen',
        'sudan',
        'nigeria',
        'venezuela',
        'colombia', 'colombiansk',
        'brasilien',
        'argentina',
        'australien', 'australisk',
        'kanada', 'kanadensisk',
        'sydkorea', 'nordkorea',
        'taiwan',
        'maldiverna',
        'thailand', 'thai',
        'vietnam',
        'indonesien',
        'mali', 'somalia', 'libyen', 'irak', 'kongo', 'etiopien',
        'krim', 'donbas',
    ],

    /*
     * Svenska markörer som skyddar mot foreign-veto. Om någon av dessa
     * matchar (som hela ord) i titel + summary, släpps artikeln igenom
     * även om titeln nämner en utländsk plats. Innehåller stora städer,
     * län, och svenska kontext-ord ("kommun", "tingsrätt" m.fl.).
     */
    'prefilter_swedish_markers' => [
        'sverige', 'svensk',
        'stockholm', 'göteborg', 'malmö', 'uppsala', 'västerås', 'örebro',
        'linköping', 'helsingborg', 'jönköping', 'norrköping', 'lund',
        'umeå', 'gävle', 'borås', 'eskilstuna', 'sundsvall', 'halmstad',
        'karlstad', 'växjö', 'kalmar', 'luleå', 'östersund', 'falun',
        'kristianstad', 'skellefteå', 'visby', 'karlskrona', 'trollhättan',
        'södertälje', 'haninge', 'huddinge', 'nacka', 'solna', 'sollentuna',
        'södermanland', 'östergötland', 'småland', 'skåne', 'blekinge',
        'dalarna', 'gästrikland', 'hälsingland', 'jämtland', 'lappland',
        'norrbotten', 'västerbotten', 'värmland', 'dalsland', 'bohuslän',
        'halland', 'närke', 'västmanland', 'uppland', 'gotland', 'öland',
        // Svenska kontext-ord
        'kommun', 'län', 'tingsrätt', 'hovrätt', 'polisregion',
        'försvarsmakten', 'säpo',
    ],

    /*
     * Plats-namn kortare än denna längd ignoreras helt. "Vå", "Bo" och
     * liknande korta kommun-namn ger för många falska träffar i artikel-
     * text annars.
     */
    'min_place_name_length' => 4,

    /*
     * Kommunnamn som kolliderar med vanliga svenska ord. Matchas
     * case-sensitive (kräver stor bokstav) — annars triggar verbet
     * "vara" träff på kommunen Vara. Stickprov 2026-05-25 visade att
     * Vara stod för 80 % av plats-FPs i regex-passet.
     *
     * Lägg bara in namn med dokumenterad FP-volym — varje rad här
     * gör visningen striktare och kan missa korrekt skrivna omnämnanden
     * i sällsynta fall (t.ex. utterst gemener-skriven artikel).
     */
    'ambiguous_place_names' => [
        'Vara',
    ],

    /*
     * Aggregator-källor där `description` ofta listar andra artiklar
     * (t.ex. Google News-summary). För dessa matchar vi bara mot `title`
     * — annars triggar listor av relaterade artiklar falska plats-
     * träffar (Stockholm-artikel hamnade på Uppsala-sidan i smoke-test
     * 2026-05-01).
     */
    'title_only_sources' => [
        'google-news-se',
    ],

    /*
     * Källa → tillåtna län. Lokala redaktioners RSS-feeds rapporterar
     * primärt om sitt eget län — om en svt-jamtland-artikel matchar
     * "Stockholm" är det nästan alltid kontextuellt brus (turné, SM,
     * olympiad), inte en blåljus-händelse i Stockholm.
     *
     * Filtrerar bort plats-träffar i andra län vid klassifikation.
     * Källor som inte listas här (t.ex. `dn`, `aftonbladet`, `svt`,
     * `svt-texttv`, `google-news-se`) har ingen geografisk scope och
     * matchar mot alla län.
     *
     * Värde kan vara string (ett län) eller array (flera län — t.ex.
     * SVT Småland täcker Kronoberg + Kalmar + Jönköping).
     */
    'source_to_lan' => [
        'svt-blekinge' => 'Blekinge län',
        'svt-dalarna' => 'Dalarnas län',
        'svt-gavleborg' => 'Gävleborgs län',
        'svt-halland' => 'Hallands län',
        'svt-helsingborg' => 'Skåne län',
        'svt-jamtland' => 'Jämtlands län',
        'svt-jonkoping' => 'Jönköpings län',
        'svt-norrbotten' => 'Norrbottens län',
        'svt-skane' => 'Skåne län',
        'svt-smaland' => ['Kronobergs län', 'Kalmar län', 'Jönköpings län'],
        'svt-stockholm' => 'Stockholms län',
        'svt-sormland' => 'Södermanlands län',
        'svt-uppsala' => 'Uppsala län',
        'svt-varmland' => 'Värmlands län',
        'svt-vast' => 'Västra Götalands län',
        'svt-vasterbotten' => 'Västerbottens län',
        'svt-vasternorrland' => 'Västernorrlands län',
        'svt-vastmanland' => 'Västmanlands län',
        'svt-orebro' => 'Örebro län',
        'svt-ost' => 'Östergötlands län',
        'expressen-gt' => 'Västra Götalands län',
        'expressen-kvp' => 'Skåne län',
        'dn-sthlm' => 'Stockholms län',
    ],

    /*
     * Källa → primär plats (name + lan). Används som fallback när en
     * artikel klassats som blåljus men ingen plats matchat i texten.
     * Stora städer (särskilt Stockholm) rapporteras ofta utifrån
     * stadsdelar (Bromma, Hornsgatan, Rålambshovsparken) som inte
     * finns i `places`. Då faller vi tillbaka till källans primära
     * plats så att artikeln ändå syns på t.ex. /stockholm.
     *
     * Värdet är [name, lan] — slås upp i `places` vid command-start.
     * Källor utan post här får ingen fallback (ingen plats → ingen
     * koppling, som tidigare).
     */
    'source_to_primary_place' => [
        'svt-stockholm' => ['Stockholm', 'Stockholms län'],
        'svt-skane' => ['Malmö', 'Skåne län'],
        'svt-helsingborg' => ['Helsingborg', 'Skåne län'],
        'svt-vast' => ['Göteborg', 'Västra Götalands län'],
        'svt-uppsala' => ['Uppsala', 'Uppsala län'],
        'svt-orebro' => ['Örebro', 'Örebro län'],
        'svt-vasternorrland' => ['Sundsvall', 'Västernorrlands län'],
        'svt-norrbotten' => ['Luleå', 'Norrbottens län'],
        'svt-vasterbotten' => ['Umeå', 'Västerbottens län'],
        'svt-ost' => ['Linköping', 'Östergötlands län'],
        'svt-sormland' => ['Eskilstuna', 'Södermanlands län'],
        'svt-vastmanland' => ['Västerås', 'Västmanlands län'],
        'svt-dalarna' => ['Falun', 'Dalarnas län'],
        'svt-gavleborg' => ['Gävle', 'Gävleborgs län'],
        'svt-jamtland' => ['Östersund', 'Jämtlands län'],
        'svt-varmland' => ['Karlstad', 'Värmlands län'],
        'svt-jonkoping' => ['Jönköping', 'Jönköpings län'],
        // svt-smaland: borttagen 2026-05-25 — regionen spänner 3 län
        // (Kronoberg/Kalmar/Jönköping) så fallback till en enda stad ger
        // fel kommun-koppling oftare än rätt. Källan får istället koppling
        // bara när text explicit nämner en kommun i scope.
        'svt-halland' => ['Halmstad', 'Hallands län'],
        'svt-blekinge' => ['Karlskrona', 'Blekinge län'],
        'expressen-gt' => ['Göteborg', 'Västra Götalands län'],
        'expressen-kvp' => ['Malmö', 'Skåne län'],
        'dn-sthlm' => ['Stockholm', 'Stockholms län'],
    ],

    /*
     * Hur många artiklar per körning. Det är säkrare att kör fler korta
     * pass än ett långt — RSS-fetchen kör var 15:e min så vi bör hinna
     * med inflödet (~880 art/körning för alla 29 källor).
     */
    'batch_size' => 2000,

    /*
     * Hur långt fönster som visas per plats (timmar). 72h är default
     * för "färska blåljusnyheter".
     */
    'display_window_hours' => 72,

    /*
     * Max antal artiklar i den synliga widget-listan per plats.
     */
    'display_limit' => 8,

    /*
     * Inklusive de extra artiklar som visas bakom <details>-toggle
     * ("Visa fler"). Helpern hämtar upp till detta antal i en query;
     * blade slice:ar i visible (display_limit) + hidden (resten).
     */
    'display_limit_expanded' => 23,
];
