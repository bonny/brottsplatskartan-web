{{--
    En rad i en Trafikverket-händelselista (/trafik och /{lan}/trafik).

    Renderar en liten statisk kartbild till vänster (om händelsen har
    koordinater) och radens innehåll via slot till höger. Kartmönstret
    återanvänder samma helper som detaljsidan — Event::getStaticMapUrl() —
    så bilderna är CDN-cachade och laddas lazy. Syftet är visuell
    förankring och trovärdighet, inte interaktivitet.
--}}
@props(['event'])

@php
    $hasLatLng = $event->lat && $event->lng;
    $mapUrl = $hasLatLng ? $event->getStaticMapUrl(100, 100) : null;
    $mapUrl2x = $hasLatLng ? $event->getStaticMapUrl(100, 100, 2) : null;
    $mapAlt = 'Karta som visar platsen för ' . $event->message_type
        . ($event->road_number ? ' på ' . $event->road_number : '')
        . ($event->administrative_area_level_1 ? ' i ' . $event->administrative_area_level_1 : '');
@endphp

<li style="display: flex; gap: 0.75rem; align-items: flex-start; border-bottom: 1px solid #eee; padding: 0.75rem 0;">
    @if ($hasLatLng)
        <img
            src="{{ $mapUrl }}"
            srcset="{{ $mapUrl }} 1x, {{ $mapUrl2x }} 2x"
            width="100"
            height="100"
            alt="{{ $mapAlt }}"
            loading="lazy"
            decoding="async"
            style="flex: 0 0 auto; width: 100px; height: 100px; border-radius: 4px; display: block;"
        >
    @endif
    <div style="flex: 1 1 auto; min-width: 0;">
        {{ $slot }}
    </div>
</li>
