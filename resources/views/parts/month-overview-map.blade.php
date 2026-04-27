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

@once
    @push('scripts')
        {{-- Self-hostade Leaflet + plugins. defer säkerställer parsing innan
             execution. Återanvänder samma assets som events-map-componenten. --}}
        <link rel="stylesheet" href="{{ URL::asset('vendor/leaflet/leaflet.min.css') }}">
        <script defer src="{{ URL::asset('vendor/leaflet/leaflet.min.js') }}"></script>

        <link rel="stylesheet" href="{{ URL::asset('vendor/leaflet/markercluster/MarkerCluster.min.css') }}">
        <link rel="stylesheet" href="{{ URL::asset('vendor/leaflet/markercluster/MarkerCluster.Default.min.css') }}">
        <script defer src="{{ URL::asset('vendor/leaflet/markercluster/leaflet.markercluster.min.js') }}"></script>

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
