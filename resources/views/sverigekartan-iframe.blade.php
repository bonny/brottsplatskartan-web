<!doctype html>
<html>

<head>
    <title>Sverigekartan – karta med polisens händelser i hela Sverige</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.3.3/dist/leaflet.css"
        integrity="sha512-Rksm5RenBEKSKFjgI3a41vrjkw4EVPlJ3+OiI65vTjIdo9brlAacEuKOiQ5OFh7cOI1bkDwLqdLw3Zg0cRJAAQ=="
        crossorigin="" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.3.0/dist/MarkerCluster.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.3.0/dist/MarkerCluster.Default.css" />
    <script src="https://unpkg.com/leaflet@1.3.3/dist/leaflet.js"
        integrity="sha512-tAGcCfR4Sc5ZP5ZoVz0quoZDYX5aCtEm/eu1KhSLj2c9eFrylXZknQYmxUssFaVJKvvc0dJQixhGjG2yXWiV9Q=="
        crossorigin=""></script>
    <link rel="stylesheet" href="//unpkg.com/leaflet-gesture-handling/dist/leaflet-gesture-handling.min.css"
        type="text/css">
    <script src="//unpkg.com/leaflet-gesture-handling"></script>
    <script src="https://unpkg.com/leaflet.markercluster@1.3.0/dist/leaflet.markercluster.js"></script>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
    <link rel="stylesheet" href="/css/styles.css" />
    <style>
        body {
            margin: 0;
            padding: 0;
        }

        /* h1 {
            font-size: 1.2rem;
        } */

        /* body {
            border-top: 2px solid rgb(255, 204, 51);
        } */

        #mapid {
            height: 90vh;
            background: #eee;
            z-index: 5;
            opacity: 1;
            transition: opacity .25s ease-in-out;
        }

        /* .FullScreenMap__intro,
        .FullScreenMap__outro {
            z-index: 10;
            background-color: rgba(255, 255, 255, .9);
            padding: 25px;
            transition: all .15s ease-in-out;
            border-top: 2px solid rgb(255, 204, 51);
            border-bottom: 2px solid rgb(255, 204, 51);
        } */

        /* .FullScreenMap__intro p:last-child,
        .FullScreenMap__outro p:last-child {
            margin-bottom: 0;
        }

        .FullScreenMap__intro p:first-child,
        .FullScreenMap__outro p:first-child {
            margin-top: 0;
        } */

        /* .FullScreenMap__links {
            border-top: 1px solid #ccc;
            padding-top: 1rem;
        } */

        .map-loading {
            /* display: none; */
            /*position: absolute;
            z-index: 10;
            top: 20%;
            left: 50%;
            transform: translateX(-50%) translateY(-50%);
            */
            padding: 25px;
            border-bottom: 2px solid rgb(255, 204, 51);
        }

        .map-loading-text {
            margin: 0;
        }

        .map-loading-text--done {
            display: block;
        }

        .map-loading-text--loading {
            display: none;
        }

        .is-loading-events .map-loading-text--loading {
            display: block;
        }

        .is-loading-events .map-loading-text--done {
            display: none;
        }

        .is-loading-events #mapid {
            opacity: .25;
        }

        .is-loading-events .map-loading {
            display: block;
        }

        @media screen and (min-width: 1000px) {
            .FullScreenMap__intro {
                position: absolute;
                z-index: 10;
                background-color: rgba(255, 255, 255, .9);
                top: 0;
                right: 0;
                width: 300px;
                padding: 25px;
                transition: all .15s ease-in-out;
            }
        }
    </style>
</head>

<body>

    {{-- <header class="FullScreenMap__intro">
        <h1>Sverigekartan – Brottsplatskartans karta med brott och händelser från hela Sverige</h1>
        <p>
            Här på sverigekartan visas de senaste <a href="/">händelserna som rapporterats
                in till Brottsplatskartan</a> av Polisen.
        </p>
        <p>Observera att platserna inte är exakta.</p>
    </header> --}}

    <div class="map-loading">
        <p class="map-loading-text map-loading-text--loading">Hämtar händelser från Polisen...</p>
        <p class="map-loading-text map-loading-text--done">Kartan visar de 300 senaste händelserna från Polisen.</p>
    </div>

    <div id="mapid"></div>

    {{-- <footer class="FullScreenMap__outro">
        <p>Se fler händelser från Polisen:</p>
        <p class="FullScreenMap__links">
            <a href="/">» Brottsplatskartans startsida</a>
            <br><a href="/nara-hitta-plats">» Polishändelser nära dig</a>
        </p>
    </footer> --}}

    <script>
        var mymap = L.map(
            'mapid', {
                gestureHandling: true
            }
        );
        mymap.setView([{{ $lat }}, {{ $lng }}], {{ $zoom }});

        /*
        https://stackoverflow.com/questions/17382012/is-there-a-way-to-resize-marker-icons-depending-on-zoom-level-in-leaflet
        https://gis.stackexchange.com/questions/159648/leaflet-circlemarker-changes-with-zoom
        */
        var brottsplatskartanIcon = L.icon({
            //iconUrl: '/img/brottsplatskartan-logotyp-symbol-only.png',
            // iconUrl: '/img/map-marker-1.svg',
            iconUrl: '/img/map-marker-2.svg?bust=4',
            // shadowUrl: 'leaf-shadow.png',
            iconSize: [40, 40], // size of the icon
            //shadowSize:   [50, 64], // size of the shadow
            iconAnchor: [20, 20], // point of the icon which will correspond to marker's location
            // shadowAnchor: [4, 62],  // the same for the shadow
            popupAnchor: [0, -10] // point from which the popup should open relative to the iconAnchor
        });


        // Moveend is also triggered when zoom changes.
        mymap.on('moveend', handleMapZoomMoveChanges);
        mymap.on('zoomend', handleMapZoomEndChanges);


        var markers = [];

        var OpenStreetMapTileLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="http://www.openstreetmap.org/copyright">OpenStreetMap</a>'
        });

        OpenStreetMapTileLayer.addTo(mymap);

        /*
        var marker = L.marker([59.323611,18.074444]).addTo(mymap);
        var marker2 = L.marker([59.315,18.073056]).addTo(mymap);
        var marker3 = L.marker([59.324458, 18.074922]).addTo(mymap);
        */

        // marker2.bindPopup("<b>Hello world!</b><br>I am a popup.");

        function handleMapZoomMoveChanges(e) {
            let mapCenter = e.target.getCenter();
            let mapZoom = e.target.getZoom();
            let latRounded = Math.round(mapCenter.lat * 1000000) / 1000000;
            let lngRounded = Math.round(mapCenter.lng * 1000000) / 1000000;

            // Create URL similar to Google Maps.
            let newUrl = `/sverigekartan-iframe/@${latRounded},${lngRounded},${mapZoom}z`
            history.pushState({}, "", newUrl);
        }

        function handleMapZoomEndChanges(e) {
            console.log('handleMapZoomEndChanges', e);
        }

        function getEvents() {
            var apiUrl = '/api/events?app=brottsplatskartan&limit=300';
            let events = fetch(apiUrl);

            events.then(addMarkers);
        }

        function addMarkers(eventsResponse) {
            eventsResponse.json().then(function(events) {

                events.data.forEach(function(event) {
                    // console.log('addMarkers event', event);
                    let popupContent = `
                        <div class="Event--v2">
                            <h1 class="Event__title">
                                <a class="Event__titleLink" href="${event.permalink}" target="_blank">
                                    <span class="Event__type">${event.title_type}</span>
                                    <span class="Event__teaser">${event.description}</span>
                                </a>
                            </h1>
                            <p class="Event__meta">
                                <span class="Event__location">${event.location_string}</span>
                                <span class="Event__dateHuman">${event.date_human}</span>
                            </p>
                        </div>
                    `;

                    var marker = L.marker([event.lat, event.lng], {
                        icon: brottsplatskartanIcon
                    })
                    // marker.addTo(mymap);
                    marker.bindPopup(popupContent);
                    markers.push(marker);

                    /*
                    var circle = L.circle([event.lat, event.lng], {
                        color: 'red',
                        fillColor: '#f03',
                        fillOpacity: 0.5,
                        radius: 1000
                    }).addTo(mymap);
                    circle.bindPopup(popupContent);
                    */

                    /*
                    var polygon = L.polygon([
                        [event.viewport_northeast_lat, event.viewport_northeast_lng],
                        [event.viewport_southwest_lat, event.viewport_northeast_lng],
                        [event.viewport_southwest_lat, event.viewport_southwest_lng],
                        [event.viewport_northeast_lat, event.viewport_southwest_lng],
                    ], {
                        color: '#aaa',
                        weight: 1,
                        fillColor: '#f03',
                        fillOpacity: 0.3
                    });
                    // polygon.addTo(mymap);
                    polygon.bindPopup(popupContent);
                    markers.push(polygon);
                    */

                    /*
                        viewport_northeast_lat
                        viewport_northeast_lng
                        viewport_southwest_lat
                        viewport_southwest_lng

                $image_src .= "|{$this->viewport_northeast_lat},{$this->viewport_northeast_lng}";
                $image_src .= "|{$this->viewport_southwest_lat},{$this->viewport_northeast_lng}";

                $image_src .= "|{$this->viewport_southwest_lat},{$this->viewport_southwest_lng}";
                $image_src .= "|{$this->viewport_northeast_lat},{$this->viewport_southwest_lng}";

                        */
                }); // each marker

                clusterize(markers);
                setLoadingStatus(false);
            });
        }

        function clusterize(markers) {
            var markersCluserGroup = L.markerClusterGroup();

            markers.forEach((marker) => {
                markersCluserGroup.addLayer(marker);
            });

            mymap.addLayer(markersCluserGroup);
        }

        function setLoadingStatus(isLoading) {
            document.body.classList.toggle('is-loading-events', isLoading);
        }

        setLoadingStatus(true);
        getEvents();
    </script>

    <!-- Google tag (gtag.js) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-L1WVBJ39GH"></script>
    <script>
        window.dataLayer = window.dataLayer || [];

        function gtag() {
            dataLayer.push(arguments);
        }
        gtag('js', new Date());

        gtag('config', 'G-L1WVBJ39GH');
    </script>

</body>

</html>
