@extends('layouts.web')

@section('title', $city['pageTitle'])
@section('metaDescription', $city['description'])

@section('metaContent')
    @include('parts.place-jsonld', [
        'placeType' => 'City',
        'placeName' => $city['name'],
        'placeLat' => $city['lat'] ?? null,
        'placeLng' => $city['lng'] ?? null,
        'placeAddressRegion' => $lan,
        'placeContainedIn' => $lan,
        'placeUrl' => url()->current(),
        'placeWikidataQid' => $city['wikidataQid'] ?? null,
    ])
@endsection

@section('content')

    <div class="container">
        <div class="row">
            <div class="col-12">

                {{-- Introtext. --}}
                <h1>
                    <strong>{{ $city['name'] }}</strong>
                    <span class="u-block text-2xl mt-4">{{ $city['title'] }}</span>
                </h1>

                {{-- Wikidata-fakta (todo #27 Lager 2): grundat + yta.
                     Visas bara om data finns. --}}
                @include('parts.city-facts')

                {{-- Karta med händelser. location-filter + 30d lookback i
                     API:t (todo #44) — annars hamnar bara 0–2 markers i bbox
                     när Sverigekartan-feeden är dominerad av Stockholm. --}}
                <x-events-map
                    :show-map-title="false"
                    :lat-lng="$mapStartLatLng"
                    :map-zoom="$mapZoom"
                    :location-filter="$citySlug"
                    location-type="city"
                />

                {{-- AI-sammanfattningar --}}
                @if($todaysSummary)
                    <x-daily-summary
                        :summary="$todaysSummary"
                        title="🤖 Sammanfattning av dagens händelser"
                    />
                @endif

                {{-- Månadssammanfattning visas INTE här — startsidan är "live"
                     (idag/igår). Förra månadens AI-sammanfattning hör hemma
                     på månadsvyn /<stad>/handelser/{år}/{månad}. --}}

                {{-- Händelselista. --}}
                <div class="widget">
                    <div class="widget__listItems widget__listItems--city u-margin-top">
                        @foreach ($events as $event)
                            <x-crimeevent.list-item
                                :event="$event"
                                detailed
                                map-distance="near"
                            />
                        @endforeach
                    </div>
                </div>

                {{-- Trend-sparkline över egen data (todo #27 Lager 1).
                     Inline SVG, 0 KB JS. Ärlig om att det är publicerade
                     händelser, inte officiell statistik. --}}
                <x-trend-sparkline :counts="$trendCounts" :days="90" />

                {{-- Topp brottstyper + mest lästa events (todo #27 Lager 1).
                     Server-rendered med charts-css för bar-grafen, vanlig
                     HTML-lista för mest lästa. 0 KB extra JS. --}}
                @include('parts.city-context')

                {{-- BRÅ:s officiella anmälda brott per kommun (todo #38). Visas
                     EFTER händelselistan — händelser är primary content, BRÅ
                     är fördjupande kontext. Mobil-mätning visade att sektionen
                     ovanför händelser tryckte ner primary content för mycket. --}}
                @include('parts.bra-statistik')

                @include('parts.mcf-statistik')

                {{-- Trafik-aggregat per län (todo #50 Fas 2). Bara Tier 1 —
                     Tier 2/3 ligger kvar med noindex. För Sthlm/Uppsala når
                     användarna lansidan via /stad-redirecten i
                     CityRedirectMiddleware. --}}
                @php
                    $trafikLanSlug = \App\Http\Controllers\TrafikController::tier1LanSlug($lan);
                @endphp
                @if ($trafikLanSlug)
                    <p class="u-margin-top">
                        Se även <a href="{{ route('trafikLan', ['lan' => $trafikLanSlug]) }}">aktuella trafikhändelser
                            i {{ $lan }}</a> — vägarbeten, störningar och olyckor live från Trafikverket och Polisen.
                    </p>
                @endif

                {{-- Senaste blåljus-nyheter per plats (todo #64). Aggregerade
                     från RSS-feeds via news_articles + place_news. Visar
                     bara om matchningar finns — pageweight 0 för platser
                     utan träffar. --}}
                @include('parts.place-news', [
                    'placeName' => $city['displayName'] ?? $cityName,
                    'placeLan' => $lan,
                ])

                {{-- Bakåtnavigering via månadsvyer (ersätter ?page=-paginering, todo #25/#33).
                     CityController hämtar bara senaste 25 — för äldre händelser
                     länkar vi till föregående månads kalender-vy. Sidopanelen
                     har det fulla månads-arkivet. --}}
                @php
                    $prevMonth = \Carbon\Carbon::now()->startOfMonth()->subMonth();
                    $cityRouteSlug = request()->route('city');
                @endphp
                <nav class="MonthNav MonthNav--bottom" aria-label="Bläddra äldre händelser">
                    <a href="{{ route('cityMonth', [
                            'city' => $cityRouteSlug,
                            'year' => $prevMonth->format('Y'),
                            'month' => $prevMonth->format('m'),
                        ]) }}"
                       class="MonthNav__link MonthNav__link--prev">
                        ‹ Tidigare händelser från {{ title_case($prevMonth->isoFormat('MMMM YYYY')) }}
                    </a>
                </nav>
            </div>
        </div>
    </div>

@endsection

@section('sidebar')

    <div class="widget" id="brottsstatistik">
        <h2 class="widget__title"><a href="{{ route('statistik') }}">Brottsstatistik</a></h2>
        <div class="widget__listItem__text">
            <p>
                Se alla händelser per dag, topp 10 brottstyper och länstopplistan på
                <a href="{{ route('statistik') }}">statistik-sidan</a>.
            </p>
        </div>
    </div>

    @include('parts.sokruta')
    @include('parts.month-archive', [
        'monthArchiveType' => 'plats',
        'monthArchiveSlug' => mb_strtolower(request()->route('city')),
    ])
    @include('parts.lan-and-cities')
    @include('parts.widget-blog-entries')
    @include('parts.lan-policestations')
@endsection
