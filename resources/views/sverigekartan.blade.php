{{--

Template för sverigekartan

--}}

@extends('layouts.web')

@section('title', 'Brottskarta – brott och händelser från Polisen utmarkerade på karta')
@section('canonicalLink', route('sverigekartan'))

{{-- CLS-fix (#70): server-side sätta map-is-expanded så body har position: fixed
     från första paint, undviker shift när JS toggar klassen efter Leaflet-mount. --}}
@section('bodyClass', 'map-is-expanded')

@section('content')

    <h1 class="sr-only">Brottskarta — brott och händelser från Polisen utmarkerade på karta</h1>

    <x-events-map map-size="fullscreen" />

    <p>Gamla Sverigekartan: <a href="/sverigekartan-iframe/">/sverigekartan-iframe/</a>.</p>
@endsection
