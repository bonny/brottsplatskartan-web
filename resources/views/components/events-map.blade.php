@once
    @push('scripts')
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
            integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
            integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
        <script>
            L.Control.Watermark = L.Control.extend({
                onAdd: function(map) {
                    var html = L.DomUtil.create('div');
                    html.innerHTML = '';

                    var expandButton = L.DomUtil.create('button', 'EventsMap-control-expand', html);

                    var img = L.DomUtil.create('img', 'leaflet-control-watermark', expandButton);
                    img.src = '/img/expand_content_24dp_FILL0_wght400_GRAD0_opsz24.svg';

                    L.DomEvent.on(expandButton, 'click', function(evt) {
                        console.log("click expand", evt);
                        evt.stopPropagation();
                        map.getContainer().classList.toggle('is-expanded');

                        // Invalidate size after css anim has finished.
                        setTimeout(() => {
                            map.invalidateSize({
                                pan: false
                            });
                        }, 250);
                    });

                    return html;
                },

                onRemove: function(map) {
                    // Nothing to do here
                },
            });

            L.control.watermark = function(opts) {
                return new L.Control.Watermark(opts);
            }

            class EventsMap {
                mapContainer = null;

                constructor(mapContainer) {
                    this.mapContainer = mapContainer;
                    console.log('EventsMap.constructor:', this.mapContainer);
                    this.initMap();
                }

                initMap() {
                    console.log('EventsMap.initMap:', this.mapContainer);

                    // Init in Jönköping
                    let map = L.map(this.mapContainer).setView([59.1, 14.5], 6);

                    window.map = map;
                    console.log('window.map now available', window.map);

                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OSM</a>'
                    }).addTo(map);

                    L.control.watermark({
                        position: 'topright'
                    }).addTo(map);

                    // map.on("click", function(evt) {
                    //     console.log("click expand", evt);
                    //     evt.stopPropagation();
                    //     map.getContainer().classList.toggle('is-expanded');
                    //     map.invalidateSize();
                    // });


                    // L.marker([51.5, -0.09]).addTo(map)
                    //     .bindPopup('A pretty CSS3 popup.<br> Easily customizable.')
                    //     .openPopup();
                }
            }

            document.addEventListener('DOMContentLoaded', function() {
                let mapContainers = document.querySelectorAll('.EventsMap');
                mapContainers.forEach(element => {
                    console.log('element:', element);
                    new EventsMap(element);
                });

                // var map = L.map('EventsMap').setView([51.505, -0.09], 13);

                // L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                //     attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
                // }).addTo(map);

                // L.marker([51.5, -0.09]).addTo(map)
                //     .bindPopup('A pretty CSS3 popup.<br> Easily customizable.')
                //     .openPopup();
            });
        </script>
    @endpush

    @push('styles')
        <style>
            .EventsMap {
                height: 200px;
                background-color: antiquewhite;
                transition: height 0.25s ease-in-out;
            }

            .EventsMap.is-expanded {
                height: 70vh;
            }

            .EventsMap-control-expand {
                line-height: 1;
                margin: 0;
                padding: 2px;
                cursor: pointer;
            }
            .EventsMap-control-expand img {
                display: block;
            }
        </style>
    @endpush
@endonce

<div>
    <p>Karta.</p>
    <div class="EventsMap">Laddar karta...</div>
</div>
