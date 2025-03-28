@extends('layouts.web')

@section('title', $city['pageTitle'])
@section('metaDescription', $city['description'])

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

                {{-- Händelselista. --}}
                <div class="widget">
                    <div class="widget__listItems widget__listItems--city u-margin-top-double">
                        @foreach ($events as $event)
                            @include('parts.crimeevent-city', [
                                'event' => $event,
                            ])
                        @endforeach
                    </div>
                </div>

                {{ $events->links('vendor.pagination.default') }}
            </div>
        </div>
    </div>

@endsection

@section('sidebar')

    <div class="widget Stats Stats--lan" id="brottsstatistik">
        <h2 class="widget__title Stats__title">Brottsstatistik</h2>
        <div class="widget__listItem__text">
            <p class="pb-6">Antal Polisiära händelser per dag för {{ $lan }}, 14 dagar tillbaka.</p>
            {!! $chartHtml !!}
        </div>
    </div>

    @include('parts.sokruta')
    @include('parts.lan-and-cities')
    @include('parts.widget-blog-entries')
    @include('parts.lan-policestations')
@endsection
