{{--

Översiktskarta för månadsvy (todo #25). Visar alla månadens
geokodade events som klustrade markers.

CWV-strategi: kartan initialiseras först när användaren scrollar
till den (IntersectionObserver). Leaflet laddas defer via parent-
componenten och blockerar inte initial render.

Required vars:
- $events — Collection<CrimeEvent>
- $monthYearTitle — string ("April 2026")

--}}

@include('parts.leaflet-vendor')

@once
    @push('scripts')
        <script defer src="{{ URL::asset('js/month-map.js') }}"></script>
    @endpush
@endonce

@php
    $geocodedEvents = $events->filter(fn($e) => $e->geocoded && $e->location_lat && $e->location_lng);
    $mapData = $geocodedEvents->map(fn($e) => [
        'lat' => (float) $e->location_lat,
        'lng' => (float) $e->location_lng,
        'title' => $e->getSingleEventTitleShort(),
        'type' => $e->parsed_title ?? '',
        'time' => $e->created_at ? $e->created_at->isoFormat('D MMM HH:mm') : '',
        'permalink' => $e->getPermalink() ?? '',
    ])->values();
@endphp

@if ($mapData->count() > 0)
    <div class="MonthOverviewMap" aria-label="Karta över händelser i {{ $monthYearTitle }}">
        <div
            class="MonthOverviewMap__container"
            data-month-map-events="{{ json_encode($mapData, JSON_UNESCAPED_UNICODE) }}"
        >
            <div class="MonthOverviewMap__placeholder">
                Karta laddas när du scrollar hit&hellip;
            </div>
        </div>
    </div>
@endif
