{{--
    Permalink-vy för enskild Trafikverket-händelse (todo #50, Fas 1 pilot).
    Olänkad i menyn men indexerbar (samma policy som /trafik-listan).
--}}

@extends('layouts.web')

@php
    $headline = $event->message
        ?: $event->location_descriptor
        ?: $event->message_type;
    $titleSuffix = $event->road_number
        ? " — {$event->road_number}"
        : ($event->administrative_area_level_1 ? " — {$event->administrative_area_level_1}" : '');
    $pageTitle = mb_strimwidth($event->message_type . ': ' . $headline . $titleSuffix, 0, 100, '…');
@endphp

@section('title', $pageTitle)

@section('canonicalLink', route('trafik.show', $event->id))

@section('content')
    <div class="widget">
        <p>
            <a href="{{ route('trafik') }}">← Tillbaka till alla trafikhändelser</a>
        </p>

        <h1>{{ $event->message_type }}@if ($event->road_number) · {{ $event->road_number }}@endif</h1>

        @if ($event->message)
            <div class="teaser">
                <blockquote style="border-left: 4px solid #ccc; padding-left: 1rem; margin: 0;">
                    <p>{{ $event->message }}</p>
                </blockquote>
                <p>
                    <small>Trafikverket rapporterar.</small>
                </p>
            </div>
        @endif

        <h2>Plats</h2>
        <ul>
            @if ($event->road_number)
                <li><strong>Väg:</strong> {{ $event->road_number }}</li>
            @endif
            @if ($event->location_descriptor)
                <li><strong>Plats:</strong> {{ $event->location_descriptor }}</li>
            @endif
            @if ($event->administrative_area_level_1)
                <li>
                    <strong>Län:</strong>
                    @if ($event->county_no)
                        <a href="{{ url('/lan/' . urlencode($event->administrative_area_level_1)) }}">
                            {{ $event->administrative_area_level_1 }}
                        </a>
                    @else
                        {{ $event->administrative_area_level_1 }}
                    @endif
                </li>
            @endif
            <li><strong>Koordinater (WGS84):</strong> {{ number_format($event->lat, 5) }}, {{ number_format($event->lng, 5) }}</li>
        </ul>

        <h2>Tid</h2>
        <ul>
            <li><strong>Startade:</strong> {{ $event->start_time->format('Y-m-d H:i') }} ({{ $event->start_time->diffForHumans() }})</li>
            @if ($event->end_time)
                <li><strong>Beräknat slut:</strong> {{ $event->end_time->format('Y-m-d H:i') }}</li>
            @else
                <li><strong>Slut:</strong> tills vidare</li>
            @endif
            <li><strong>Senast uppdaterad:</strong> {{ $event->modified_time->format('Y-m-d H:i') }}</li>
        </ul>

        @if ($event->message_code || $event->severity_code || $event->icon_id)
            <h2>Klassning</h2>
            <ul>
                @if ($event->message_code)
                    <li><strong>Typ:</strong> {{ $event->message_code }}</li>
                @endif
                @if ($event->severity_code)
                    <li><strong>Påverkansgrad:</strong> {{ $event->severity_code }}/5</li>
                @endif
            </ul>
        @endif

        <h2>Källa</h2>
        <p>
            Data från
            <a href="https://trafikinfo.trafikverket.se/" target="_blank" rel="noopener">Trafikverkets öppna API</a>
            (CC0-licens).
            @if ($event->source_url)
                <a href="{{ $event->source_url }}" target="_blank" rel="noopener">Mer information hos Trafikverket →</a>
            @endif
        </p>

        <p style="color: #666; font-size: 0.85em; margin-top: 2rem;">
            Trafikverkets händelse-id: <code>{{ $event->external_id }}</code>
        </p>
    </div>
@endsection
