<?php

/**
 * Konfiguration för per-plats nyhetsaggregering (todo #64).
 *
 * Klassifikations-pass markerar artiklar i `news_articles` som blåljus-
 * relaterade och kopplar dem till `places` via stripos-matching.
 */
return [
    /*
     * En artikel räknas som blåljus om den matchar minst ett ord ur denna
     * lista (case-insensitive, multibyte-säker, ord-gränsen baseras på
     * Unicode-bokstäver). Listan är medvetet bred — risken för falska
     * negativa är värre än falska positiva eftersom plats-matchningen
     * agerar som extra filter.
     */
    'blaljus_terms' => [
        'polis', 'polisen', 'polisens', 'polismän', 'polisinsats', 'polisman', 'gripen',
        'gripande', 'anhållen', 'häktad', 'misstänkt', 'misstänkts',
        'brott', 'brand', 'branden', 'bränder', 'brinner', 'brand-', 'brinnande',
        'eldsvåda', 'rökutveckling', 'räddningstjänst', 'räddningstjänsten',
        'räddningsinsats', 'utryckning', 'larm', 'blåljus',
        'rån', 'rånad', 'rånet', 'inbrott', 'inbrottet',
        'stulen', 'stöld', 'tillgripen', 'tillgrepp',
        'mord', 'dråp', 'dödad', 'avliden', 'omkommen', 'omkom', 'död',
        'misshandel', 'misshandlad', 'misshandlade',
        'skottlossning', 'skotten', 'skjuten', 'skjutning', 'skjutningar',
        'sprängning', 'detonation', 'explosion', 'explosioner',
        'granat', 'granater', 'raket', 'raketer',
        'kniv', 'knivhot', 'knivskuren', 'knivskars', 'knivöverfall',
        'olycka', 'olyckan', 'olyckor', 'trafikolycka', 'krock', 'krockade',
        'frontalkrock', 'kollision', 'kolliderade', 'kollidera', 'singelolycka',
        'påkörd', 'påkörda',
        'försvunnen', 'efterlyst', 'efterlyses', 'försvann',
        'evakuerad', 'evakuering', 'avspärrat', 'avspärrad', 'avspärrning',
        'narkotika', 'drog', 'droger', 'narkotikabrott',
        'drunkning', 'drunknad', 'drunknat',
        'ras', 'översvämning', 'översvämmad', 'översvämningar',
        'gänget', 'gängbråk', 'skadeskjuten', 'skottskadad',
    ],

    /*
     * Plats-namn kortare än denna längd ignoreras helt. "Vå", "Bo" och
     * liknande korta kommun-namn ger för många falska träffar i artikel-
     * text annars.
     */
    'min_place_name_length' => 4,

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
        'svt-smaland' => ['Växjö', 'Kronobergs län'],
        'svt-halland' => ['Halmstad', 'Hallands län'],
        'svt-blekinge' => ['Karlskrona', 'Blekinge län'],
        'expressen-gt' => ['Göteborg', 'Västra Götalands län'],
        'expressen-kvp' => ['Malmö', 'Skåne län'],
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
