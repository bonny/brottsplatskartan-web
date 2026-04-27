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

                {{-- Karta med händelser --}}
                <x-events-map :show-map-title="false" :lat-lng=$mapStartLatLng :map-zoom=$mapZoom />

                {{-- AI-sammanfattningar --}}
                @if($todaysSummary)
                    <x-daily-summary 
                        :summary="$todaysSummary" 
                        title="🤖 Sammanfattning av dagens händelser" 
                    />
                @endif

                {{-- @if($yesterdaysSummary)
                    <x-daily-summary 
                        :summary="$yesterdaysSummary" 
                        :title="'Sammanfattning från ' . $yesterdaysSummary->summary_date->locale('sv')->isoFormat('dddd D MMMB')" 
                    />
                @endif --}}

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

    <div class="widget">
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
