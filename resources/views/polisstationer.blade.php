{{--

Template för polisstationer
/polissationer

--}}

@extends('layouts.web')
@section('title', 'Polisstationer')

@section('content')

    <h1>Polisstationer - Hitta din Polisstation</h1>

    <p>
        Sveriges alla polisstationer i Sverige, grupperat på län.
    </p>

    {{-- Översikt med ankarsnabblänkar till respektive län --}}
    <p>Hoppa direkt till län:</p>
    <ul class="PoliceStation-locationsNav">
        @foreach ($locationsByPlace as $place => $placeLocations)
            <li class="PoliceStation-locationsNav-item">
                <a href="#{{str_slug($place)}}">{{\App\Helper::lanLongNameToShortName($place)}}</a>@if (!$loop->last),@endif
            </li>
        @endforeach
    </ul>

    <div class="PoliceStations-mainListing PoliceStations-mainListing">

        @foreach ($locationsByPlace as $place => $placeLocations)

            <h2 class="PoliceStations-lanName" id="{{str_slug($place)}}">{{$place}}</h2>

            <div class="PoliceStations-lanLocations">
                @foreach ($placeLocations as $station)

                    <h3 class="PoliceStation-name">{{$station->name}}</h3>

                    {{-- $station->location->gps --}}
                    <p class="PoliceStation-street">
                        <a href="https://www.google.com/maps/search/?api=1&query={{$station->location->gps}}" rel="noopener" target="_blank">
                            {{$station->location->name}}
                        </a>
                    </p>

                    @if (is_array($station->services) && !empty($station->services))
                        <div class="PoliceStation-services">
                            <p class="PoliceStation-servicesTitle">Tjänster:</p>
                            <ul class="PoliceStation-servicesItems">
                                @foreach ($station->services as $service)
                                    <li class="PoliceStation-service">
                                        {{$service->name}}@if (!$loop->last),@endif
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                @endforeach
            </div>

        @endforeach
    </div>

    {{--
    @json($location)
    --}}
@endsection
