{{--

Template för polisstationer
/polissationer

--}}


@extends('layouts.web')
@section('title', 'Polisstationer')

@section('content')

    <h1>Polisstationer</h1>

    <ul class="PoliceStation-locationsNav">
        @foreach ($locationsByPlace as $place => $placeLocations)
            <li class="PoliceStation-locationsNav-item">
                <a href="#{{str_slug($place)}}">{{$place}}</a>@if (!$loop->last),@endif
            </li>
        @endforeach
    </ul>

    @foreach ($locationsByPlace as $place => $placeLocations)

        <h2 id="{{str_slug($place)}}">{{$place}}</h2>

        @foreach ($placeLocations as $location)

            <h3>{{$location->name}}</h3>

            @isset($location->url)
                {{$location->url}}
            @endisset

            {{$location->location->name}}
            {{-- $location->location->gps --}}

            @if (is_array($location->services))
                <p>Tjänster:</p>
                <ul class="PoliceStation-services">
                    @foreach ($location->services as $service)
                        <li class="PoliceStation-service">{{$service->name}}</li>@if (!$loop->last),@endif
                    @endforeach
                </ul>
            @endif

        @endforeach

    @endforeach

    {{--
    @json($location)
    --}}
@endsection
