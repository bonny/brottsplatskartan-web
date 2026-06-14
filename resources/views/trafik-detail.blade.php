{{--
    Permalink-vy för enskild Trafikverket-händelse (todo #50, Fas 1 pilot).
    Olänkad i menyn men indexerbar (samma policy som /trafik-listan).
--}}

@extends('layouts.web')

@php
    // Trafikverkets data innehåller ibland dubbla mellanslag — normalisera så
    // de inte läcker in i title/description.
    $normalize = fn ($s) => trim(preg_replace('/\s+/', ' ', (string) $s));

    $msgType = $normalize($event->message_type);
    $lan = $normalize($event->administrative_area_level_1);
    $road = $normalize($event->road_number);
    $placeShort = $road ?: mb_strimwidth($normalize($event->location_descriptor), 0, 50, '…');

    // Title: typ + plats + län → lokal + temporal sökintention.
    $titleCore = $msgType;
    if ($road) {
        $titleCore .= ' på ' . $road;
    } elseif ($placeShort) {
        $titleCore .= ' vid ' . $placeShort;
    }
    if ($lan) {
        $titleCore .= ', ' . $lan;
    }
    $pageTitle = mb_strimwidth($titleCore . ' – trafikinformation', 0, 70, '…');

    // Meta description: platskontext + aktuell status (pågår/avslutad) + tid.
    $isOngoing = !$event->end_time || $event->end_time->isFuture();
    $status = $isOngoing
        ? 'Pågår sedan ' . $event->start_time->isoFormat('D MMMM YYYY')
        : 'Avslutades ' . $event->end_time->isoFormat('D MMMM YYYY');

    $metaPrefix = $msgType;
    if ($road) {
        $metaPrefix .= ' på ' . $road;
    }
    if ($lan) {
        $metaPrefix .= ' i ' . $lan;
    }
    $metaBody = $normalize($event->message ?: $event->location_descriptor ?: '');
    $metaDescription = mb_strimwidth(trim("{$metaPrefix}. {$status}." . ($metaBody ? " {$metaBody}" : '')), 0, 160, '…');

    // Social-bild: den statiska kartbilden i og-format (1200×630).
    $ogImage = $event->getStaticMapUrl(1200, 630);
@endphp

@section('title', $pageTitle)

@section('metaDescription', e($metaDescription))

@section('canonicalLink', $event->getPermalink(true))

@section('ogType', 'article')

@if ($ogImage)
    @section('metaImage', $ogImage)
    @section('metaImageWidth', '1200')
    @section('metaImageHeight', '630')
@endif

@section('content')
    <div class="widget">
        {{-- Synliga brödsmulor (UX + crawlbara interna länkar till de eviga
             aggregaten). Site-mallen renderar dessutom BreadcrumbList-JSON-LD
             via parts/breadcrumb (dolt .Breadcrumbs-wrapper). Egen scoped klass
             så vi slipper global `.breadcrumbs a { display:block }` som staplar.
             Flex med gap → jämn spacing + radbryter snyggt på mobil. --}}
        <nav class="TrafikBreadcrumbs" aria-label="Brödsmulor"
             style="display:flex; flex-wrap:wrap; align-items:center; gap:.35rem .6rem; font-size:.8125rem; margin-bottom:1.25rem;">
            <a href="{{ route('start') }}">Hem</a>
            <span aria-hidden="true" style="color:#c0c0c0;">›</span>
            <a href="{{ route('trafik') }}">Trafik</a>
            @if ($lan)
                <span aria-hidden="true" style="color:#c0c0c0;">›</span>
                <a href="{{ route('trafikLan', ['lan' => \App\Helper::lanSlug($event->administrative_area_level_1)]) }}">{{ $event->administrative_area_level_1 }}</a>
            @endif
            <span aria-hidden="true" style="color:#c0c0c0;">›</span>
            <span aria-current="page" style="color:#666; font-weight:600;">{{ $msgType }}@if ($road) · {{ $road }}@endif</span>
        </nav>

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

        @if ($event->lat && $event->lng)
            @php
                $mapAlt = 'Karta som visar platsen för ' . $event->message_type
                    . ($event->road_number ? ' på ' . $event->road_number : '')
                    . ($event->administrative_area_level_1 ? ' i ' . $event->administrative_area_level_1 : '');
            @endphp
            <figure class="trafik-detail__map" style="margin: 0 0 1rem;">
                <img
                    src="{{ $event->getStaticMapUrl(640, 360) }}"
                    srcset="{{ $event->getStaticMapUrl(640, 360) }} 1x, {{ $event->getStaticMapUrl(640, 360, 2) }} 2x"
                    width="640"
                    height="360"
                    alt="{{ $mapAlt }}"
                    loading="lazy"
                    decoding="async"
                    style="width: 100%; height: auto; border-radius: 4px; display: block;"
                >
                <figcaption style="font-size: 0.8em; color: #666; margin-top: 0.25rem;">
                    Plats enligt Trafikverket. Kartdata © OpenStreetMap.
                </figcaption>
            </figure>
        @endif

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
                    <a href="{{ route('trafikLan', ['lan' => \App\Helper::lanSlug($event->administrative_area_level_1)]) }}">
                        Trafikhändelser i {{ $event->administrative_area_level_1 }}
                    </a>
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
