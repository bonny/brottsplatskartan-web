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
                <ul class="widget__listItems u-margin-top-double">
                    @foreach ($events as $event)
                        @include('parts.crimeevent-small', [
                            'event' => $event,
                            'detailed' => true,
                            'mapDistance' => 'near',
                        ])
                    @endforeach
                </ul>

                {{ $events->links('vendor.pagination.default') }}
            </div>
        </div>
    </div>

@endsection
