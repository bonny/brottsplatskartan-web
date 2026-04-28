{{--
    Wikidata-fakta för Tier 1-städer (todo #27 Lager 2):
    grundat-år + yta. Kompakt rad under h1 — sparar vertikal plats
    för primary content (events).

    Förutsätter:
        $cityFacts — array från WikidataService::getCityFacts()
                     eller null om ingen data
        $city['wikidataQid'] — för källa-länk
--}}
@php
    $_inception = $cityFacts['inception_year'] ?? null;
    $_area = $cityFacts['area_km2'] ?? null;
    $_hasFacts = $_inception || $_area;
@endphp

@if ($_hasFacts)
    <p class="CityFacts">
        @if ($_inception)
            <span class="CityFacts__item">
                Grundad <strong>{{ $_inception }}</strong>
            </span>
        @endif
        @if ($_area)
            <span class="CityFacts__item">
                Yta <strong>{{ \App\Helper::number($_area, 0) }}&nbsp;km²</strong>
            </span>
        @endif
        <span class="CityFacts__source">
            Källa:
            <a href="https://www.wikidata.org/wiki/{{ $city['wikidataQid'] }}"
               rel="external noopener"
               target="_blank">Wikidata</a>
        </span>
    </p>
@endif
