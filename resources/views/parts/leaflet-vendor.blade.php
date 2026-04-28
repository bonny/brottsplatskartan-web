{{--

Self-hostade Leaflet + plugins. Pushas via @once så att kombinerade vyer
(t.ex. events-map + month-overview-map på samma månadssida) inte emittar
dubbla <script>-taggar.

Källa: leaflet@1.9.4, leaflet-gesture-handling@1.2.2,
leaflet.locatecontrol@0.81.0, leaflet.markercluster@1.4.1.

--}}

@once
    @push('scripts')
        <link rel="stylesheet" href="{{ URL::asset('vendor/leaflet/leaflet.min.css') }}">
        <script defer src="{{ URL::asset('vendor/leaflet/leaflet.min.js') }}"></script>

        <link rel="stylesheet" href="{{ URL::asset('vendor/leaflet/gesture-handling/leaflet-gesture-handling.min.css') }}">
        <script defer src="{{ URL::asset('vendor/leaflet/gesture-handling/leaflet-gesture-handling.min.js') }}"></script>

        <link rel="stylesheet" href="{{ URL::asset('vendor/leaflet/locatecontrol/L.Control.Locate.min.css') }}">
        <script defer src="{{ URL::asset('vendor/leaflet/locatecontrol/L.Control.Locate.min.js') }}"></script>

        <link rel="stylesheet" href="{{ URL::asset('vendor/leaflet/markercluster/MarkerCluster.min.css') }}">
        <link rel="stylesheet" href="{{ URL::asset('vendor/leaflet/markercluster/MarkerCluster.Default.min.css') }}">
        <script defer src="{{ URL::asset('vendor/leaflet/markercluster/leaflet.markercluster.min.js') }}"></script>
    @endpush
@endonce
