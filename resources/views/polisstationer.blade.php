{{--

Template för polisstationer
/polisstationer

--}}

@extends('layouts.web')
@section('title', 'Polisstationer - hitta din Polisstation')

@section('content')

    <div class="widget">

        <h1 class="widget__title">Polisstationer i Sverige</h1>

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

                <div class="PoliceStations-wrap" id="{{str_slug($place['lanName'])}}">

                    <h2 class="PoliceStations-lanName">{{$place['lanName']}}</h2>

                    <div class="PoliceStations-lanLocations">
                        @foreach ($place['policeStations'] as $station)

                            <div class="PoliceStation-wrap" id="{{str_slug($place['lanName'] . '-' . $station->name)}}">
                                <h3 class="PoliceStation-name">{{$station->name}}</h3>

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
                            </div>

                        @endforeach
                    </div>

                </div>

            @endforeach
        </div>

    </div>

@endsection

@section('sidebar')
    @include('parts.lan-and-cities')
    @include('parts.follow-us')
@endsection
