{{--

Template för län-översikt

--}}


@extends('layouts.web')

@section('title', 'Välj ditt län | Brott i Sveriges län')
@section('metaDescription', e("På Brottsplatskartan kan du län för län se på en karta var i Sverige brott sker - direkt från Polisen"))
@section('canonicalLink', '/lan')

@section('metaImage', config('app.url') . "/img/start-share-image.png")
@section('metaImageWidth', 600)
@section('metaImageHeight', 315)

@section('content')

    <h1>Se senaste brotten i ditt län</h1>

    <p>
        Välj län nedan för att se de senaste brotten
        och händelserna i det länet eller
        <a href="/">visa brott från alla län</a>.
    </p>

    <div class="LanListing">

        @foreach ($lan as $oneLan)

            <div class="LanListing__lan">

                <h2 class="LanListing__title">
                    <a href="{{ route("lanSingle", ["lan"=>$oneLan->administrative_area_level_1]) }}">
                        {{ $oneLan->administrative_area_level_1 }}
                    </a>
                </h2>

                <p class="LanListing__events">
                    <b>{{ $oneLan->numEvents["numEventsToday"] }}</b> händelser idag
                    <br><b>{{ $oneLan->numEvents["last7days"] }}</b> händelser senaste 7 dagarna
                    <br><b>{{ $oneLan->numEvents["last30days"] }}</b> händelser senaste 30 dagarna
                </p>

            </div>

        @endforeach

    </div>

@endsection
