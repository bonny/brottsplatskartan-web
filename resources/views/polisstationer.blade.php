{{--

Template för polisstationer
/polissationer

--}}

@extends('layouts.web')
@section('title', 'Polisstationer - hitta din Polisstation')

@section('content')

    <h1>Polisstationer i Sverige</h1>

    <p>
        Hitta din närmsta polisstation i vår lista med Sveriges alla polisstationer i Sverige, grupperade på län.
    </p>

    {{-- Översikt med ankarsnabblänkar till respektive län --}}
    <p>Hoppa direkt till län:</p>
    <ul class="PoliceStation-locationsNav">
        @foreach ($locationsByPlace as $place)
            <li class="PoliceStation-locationsNav-item">
                <a href="#{{str_slug($place['lanName'])}}">{{$place['lanShortName']}}</a>@if (!$loop->last),@endif
            </li>
        @endforeach
    </ul>

    <div class="PoliceStations-mainListing PoliceStations-mainListing">

        @foreach ($locationsByPlace as $place)

            <h2 class="PoliceStations-lanName" id="{{str_slug($place['lanName'])}}">{{$place['lanName']}}</h2>

            <div class="PoliceStations-lanLocations">
                @foreach ($place['policeStations'] as $station)

                    <h3 id="{{str_slug($place['lanName'] . '-' . $station->name)}}" class="PoliceStation-name">{{$station->name}}</h3>

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
