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

            <h2 class="LanListing__lan">
                <a href="{{ route("lanSingle", ["lan"=>$oneLan->administrative_area_level_1]) }}">
                    {{ $oneLan->administrative_area_level_1 }}
                </a>
            </h2>

            <p>
                Antal händelser:
                <br>idag: {{ $oneLan->numEvents["numEventsToday"] }}
                <br>senaste 7 dagarna: {{ $oneLan->numEvents["last7days"] }}
                <br>senaste 30 dagarna: {{ $oneLan->numEvents["last30days"] }}
            </p>

        @endforeach

    </div>

@endsection
