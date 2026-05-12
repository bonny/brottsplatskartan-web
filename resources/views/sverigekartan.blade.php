{{--

Template för sverigekartan

--}}

@extends('layouts.web')

@section('title', 'Brottskarta – brott och händelser från Polisen utmarkerade på karta')
@section('canonicalLink', route('sverigekartan'))

{{-- CLS-fix (#70): server-side sätta map-is-expanded så body har position: fixed
     från första paint, undviker shift när JS toggar klassen efter Leaflet-mount. --}}
@section('bodyClass', 'map-is-expanded')

{{-- CLS-fix (#70 iter #3): inline kritisk CSS för fullscreen-layouten så
     reglerna gäller redan vid första HTML-parse, INNAN extern events-map.css
     laddats. Iter #2 (position:fixed på body) lyckades inte på mobile —
     dvh-dynamics flyttade body-höjd → footer.SiteFooter shiftade. Bytte
     till `overflow:hidden; height:100vh` (statisk vh). --}}
@push('styles')
    <style>
        /* Speglar regler i events-map.css. Inline här för att garantera
           cascade-träff före extern stylesheet på fullscreen-karta-routes. */
        body.map-is-expanded {
            overflow: hidden;
            height: 100vh;
        }
    </style>
@endpush

@section('content')

    <h1 class="sr-only">Brottskarta — brott och händelser från Polisen utmarkerade på karta</h1>

    <x-events-map map-size="fullscreen" />

    <p>Gamla Sverigekartan: <a href="/sverigekartan-iframe/">/sverigekartan-iframe/</a>.</p>
@endsection
