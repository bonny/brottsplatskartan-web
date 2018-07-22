{{--

Template för ort-översikt

--}}


@extends('layouts.web')

@section('title', 'Brott på platser och orter i Sverige')
@section('metaDescription', e("Se var brott sker på en mängd olika orter och platser i Sverige. Brottsplatskartan visar alla brott på en karta - direkt från Polisen."))
@section('canonicalLink', '/plats')

@section('content')

    <div class="widget">
        <h1 class="widget__title">Se senaste brotten på dessa platser</h1>

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

    </div>

@endsection
