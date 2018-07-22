<!doctype html>
<html>
<head>
    <title>Karta - Brottsplatskartan</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.3.3/dist/leaflet.css"
    integrity="sha512-Rksm5RenBEKSKFjgI3a41vrjkw4EVPlJ3+OiI65vTjIdo9brlAacEuKOiQ5OFh7cOI1bkDwLqdLw3Zg0cRJAAQ=="
    crossorigin=""/>
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.3.0/dist/MarkerCluster.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.3.0/dist/MarkerCluster.Default.css" />
    <script src="https://unpkg.com/leaflet@1.3.3/dist/leaflet.js"
    integrity="sha512-tAGcCfR4Sc5ZP5ZoVz0quoZDYX5aCtEm/eu1KhSLj2c9eFrylXZknQYmxUssFaVJKvvc0dJQixhGjG2yXWiV9Q=="
    crossorigin=""></script>
    <script src="https://unpkg.com/leaflet.markercluster@1.3.0/dist/leaflet.markercluster.js"></script>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
    <link rel="stylesheet" href="/css/styles.css" />
    <style>
        body {
            padding-top: 0;
        }
        #mapid {
            height: 100vh;
            background: #eee;
        }
    </style>
</head>
<body>

    <h1>Sverigekartan – brott och händelser i Sverige på kartan</h1>

    <div id="mapid"></div>

    <script>

        var mymap = L.map('mapid').setView([59,18], 5);
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

        function getEvents() {
            var apiUrl = '/api/events?app=brottsplatskartan&limit=500';
            let events = fetch(apiUrl);

            events.then(addMarkers);
            console.log('events', events);
        }

        function addMarkers(eventsResponse) {
            eventsResponse.json().then(function(events) {

                events.data.forEach(function(event) {
                    console.log('addMarkers event', event);
                    let popupContent = `
                        <div class="Event--v2">
                            <h1 class="Event__title">
                                <a class="Event__titleLink" href="${event.permalink}">
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

                    var marker = L.marker([event.lat, event.lng])
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

            });
        }

        function clusterize(markers) {
            var markersCluserGroup = L.markerClusterGroup();

            markers.forEach((marker) => {
                markersCluserGroup.addLayer(marker);
            });

            mymap.addLayer(markersCluserGroup);
        }

        getEvents();

    </script>
    <!-- Global site tag (gtag.js) - Google Analytics -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=UA-181460-13"></script>
    <script>
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    gtag('js', new Date());

    gtag('config', 'UA-181460-13');
    </script>
</body>
</html>
