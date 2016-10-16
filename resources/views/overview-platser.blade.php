{{--

Template för ort-översikt

--}}


@extends('layouts.web')

@section('title', 'Ort')
@section('canonicalLink', '/orter')

@section('content')

    <h1>Se senaste brotten på dessa platser</h1>

    <p>
        Välj en ort eller plats för att se de senaste brotten
        och händelserna.
    </p>

    <p>All data kommer direkt från Polisen.</p>

    <div class="PlatsListing">

    @foreach ($orter as $oneOrt)

        <h2 class="PlatsListing__plats">
            <a href="{{ route("platsSingle", ["ort"=>$oneOrt->parsed_title_location]) }}">
                {{ $oneOrt->parsed_title_location }}
            </a>
        </h2>

    @endforeach

    </div>

@endsection
