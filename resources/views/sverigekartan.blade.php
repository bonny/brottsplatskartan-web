{{--

Template för sverigekartan

--}}

@extends('layouts.web')

@section('title', 'Brottskarta – brott och händelser från Polisen utmarkerade på karta')
@section('canonicalLink', route('sverigekartan'))

@section('content')

    <h1 class="sr-only">Brottskarta — brott och händelser från Polisen utmarkerade på karta</h1>

    <x-events-map map-size="fullscreen" />

    <p>Gamla Sverigekartan: <a href="/sverigekartan-iframe/">/sverigekartan-iframe/</a>.</p>
@endsection
