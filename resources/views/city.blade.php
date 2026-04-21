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
                @if(request()->query('page', 1) == 1)
                    <x-events-map :show-map-title="false" :lat-lng=$mapStartLatLng :map-zoom=$mapZoom />
                @endif

                {{-- Paginering högst upp om inte sida 1 --}}
                @if(request()->query('page', 1) > 1)
                    {{ $events->links('vendor.pagination.default') }}
                @endif

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
                            @include('parts.crimeevent-small', [
                                'event' => $event,
                                'detailed' => true,
                                'mapDistance' => 'near'
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
    @include('parts.lan-and-cities')
    @include('parts.widget-blog-entries')
    @include('parts.lan-policestations')
@endsection
