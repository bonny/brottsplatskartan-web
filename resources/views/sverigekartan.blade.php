{{--

Template för sverigekartan

--}}

@extends('layouts.web')

@section('title',
    'Sverigekartan – Brottsplatskartans karta med brott och händelser från hela Sverige utmarkerade på
    karta')
@section('canonicalLink', route('sverigekartan'))

@section('content')

    <x-events-map map-size="fullscreen" />

    <p>Gamla Sverigekartan: <a href="/sverigekartan-iframe/">/sverigekartan-iframe/</a>.</p>
@endsection
