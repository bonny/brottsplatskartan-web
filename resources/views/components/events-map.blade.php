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
                                pan: true
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

            var markerIconFar = L.divIcon({
                className: 'EventsMap-marker-icon EventsMap-marker-icon--far',
            });

            var markerIconNear = L.divIcon({
                className: 'EventsMap-marker-icon EventsMap-marker-icon--near',
            });

            class EventsMap {
                map;
                mapContainer;
                zoom = {
                    default: 4,
                };

                constructor(mapContainer) {
                    this.mapContainer = mapContainer;
                    console.log('EventsMap.constructor:', this.mapContainer);
                    this.initMap();
                }

                async loadMarkers() {
                    console.log('EventsMap.loadMarkers:', this);

                    const response = await fetch('/api/eventsMap');
                    const data = await response.json();
                    const events = data.data
                    console.log('EventsMap.loadMarkers data:', events);

                    events.forEach(event => {
                        L.marker([event.lat, event.lng])
                            .setIcon(markerIconFar)
                            .addTo(this.map)
                            .bindPopup(`${event.time} ${event.headline}`);
                    });
                }

                initMap() {
                    console.log('EventsMap.initMap:', this.mapContainer);

                    // Init in Jönköping
                    let map = L.map(this.mapContainer);
                    this.map = map;

                    window.map = map;
                    console.log('window.map now available', window.map);

                    map.on('load', () => {
                        this.loadMarkers();
                    });

                    map.setView([62, 15.5], this.zoom.default);

                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OSM</a>'
                    }).addTo(map);

                    L.control.watermark({
                        position: 'topright'
                    }).addTo(map);
                }
            }

            document.addEventListener('DOMContentLoaded', function() {
                let mapContainers = document.querySelectorAll('.EventsMap');
                mapContainers.forEach(element => {
                    new EventsMap(element);
                });
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

            .EventsMap .leaflet-marker-icon,
            .EventsMap .leaflet-marker-shadow {
                animation: fadein .75s;
            }

            .EventsMap .leaflet-popup {
                max-width: 50vw;
            }

            .EventsMap-marker-icon {
                --icon-size: 10px;
                background-color: var(--color-red);
                border-radius: 50%;
                border: 1px solid rgba(255, 255, 255, .25);
                width: var(--icon-size);
                height: var(--icon-size);
            }

            @keyframes fadein {
                from {
                    opacity: 0;
                }

                to {
                    opacity: 1;
                }
            }
        </style>
    @endpush
@endonce

<div class="EventsMap">Laddar karta...</div>
