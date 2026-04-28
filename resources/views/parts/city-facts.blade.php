{{--
    Intro-paragraf för Tier 1-städer (todo #27 Lager 2).
    Kombinerar Wikidata (description, grundat-år, yta) med
    SCB-befolkning per kommun. Renderas bara om vi har något
    meningsfullt — annars helt utelämnat.

    Vi undviker att skriva ut "{kommun_namn} kommun" för att slippa
    genitiv-fallgropen ("Göteborgs" vs "Göteborg"). I stället använder
    vi "Kommunen har..." — Wikidata-description nämner redan kommunen.

    Förutsätter:
        $cityName    — kortnamn ("Uppsala", inte "Uppsala och Uppsala län")
        $cityFacts   — array från WikidataService::getCityFacts() eller null
        $kommunInfo  — array från BraStatistik::kommunInfo() eller null
        $city        — full $cities-array (för wikidataQid)
--}}
@php
    $_desc = $cityFacts['description'] ?? null;
    $_inception = $cityFacts['inception_year'] ?? null;
    $_area = $cityFacts['area_km2'] ?? null;
    $_pop = $kommunInfo['befolkning'] ?? null;

    $_hasContent = $_desc || $_pop || $_area;
@endphp

@if ($_hasContent)
    <p class="CityFacts">
        @if ($_desc)
            <strong>{{ $cityName }}</strong> är {{ $_desc }}.
        @endif

        @if ($_pop)
            Kommunen har omkring
            <strong>{{ \App\Helper::number($_pop) }}</strong>
            invånare.
        @endif

        @if ($_area && $_inception)
            Stadens yta är <strong>{{ \App\Helper::number($_area) }}&nbsp;km²</strong>
            och det första kända omnämnandet är från år
            <strong>{{ $_inception }}</strong>.
        @elseif ($_area)
            Stadens yta är <strong>{{ \App\Helper::number($_area) }}&nbsp;km²</strong>.
        @elseif ($_inception)
            Det första kända omnämnandet av staden är från år
            <strong>{{ $_inception }}</strong>.
        @endif

        <span class="CityFacts__source">
            Källor:
            <a href="https://www.wikidata.org/wiki/{{ $city['wikidataQid'] }}"
               rel="external noopener"
               target="_blank">Wikidata</a>
            @if ($_pop)
                ·
                <a href="https://www.scb.se/hitta-statistik/sverige-i-siffror/kommuner-i-siffror/"
                   rel="external noopener"
                   target="_blank">SCB</a>
            @endif
        </span>
    </p>
@endif
