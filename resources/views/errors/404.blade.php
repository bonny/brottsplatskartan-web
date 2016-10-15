{{--
Template för 404
--}}

@extends('layouts.web')

@section('title', 'Kunde inte hitta sidan (felkod 404)')

@section('content')

    <h1>Oups, vi kunde inte hitta den här sidan</h1>

    <p>Vi kunde tyvärr inte hitta någon sida på den här adressen.</p>

    <p>För att fortsätta så kan du <a href="/">gå till startsidan</a> för att se
        de senaste brotten i hela Sverige eller så kan du välja ett län nedan för att se senaste brotten i det länet.</p>

    {{--
    <h2>Senaste händelserna</h2>

    @if ($events)

        @foreach ($events as $event)

            @include('parts.crimeevent', ["overview" => true])

        @endforeach

    @endif
    --}}

    <!-- <h2>Visa händelser från Polisen för ditt län</h2> -->

    <div class="LanListing">

    @foreach ($lan as $oneLan)

        <h2 class="LanListing__lan">
            <a href="{{ route("lanSingle", ["lan"=>$oneLan->administrative_area_level_1]) }}">
                {{ $oneLan->administrative_area_level_1 }}
            </a>
        </h2>

    @endforeach

    </div>

@endsection
