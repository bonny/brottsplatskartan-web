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
                    // var buttonText = L.DomUtil.create('span', '', expandButton);
                    // buttonText.innerText = 'Expandera karta';

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
                iconSize: [8, 8],
            });

            var markerIconNear = L.divIcon({
                className: 'EventsMap-marker-icon EventsMap-marker-icon--near',
                iconSize: [20, 20],
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
                            .bindPopup(`
                                <div class="EventsMap-marker-content">
                                    <div class="EventsMap-marker-contentImage">
                                        <img class="EventsMap-marker-image" src="${event.image}" alt="" />
                                    </div>
                                    <a href="${event.permalink}" class="EventsMap-marker-contentText EventsMap-marker-contentLink">
                                        ${event.time} • ${event.type}
                                        <strong>${event.headline}</strong>
                                        <div>Läs mer →</div>
                                    </a>
                                </div>
                            `);
                    });
                }

                initMap() {
                    console.log('EventsMap.initMap:', this.mapContainer);

                    // Init in Jönköping
                    let map = L.map(this.mapContainer, {
                        zoomControl: false,
                        attributionControl: false,
                    });
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
                        position: 'bottomright'
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
                max-width: 80vw;
            }

            .EventsMap-marker-icon {
                background-color: var(--color-red);
                border-radius: 50%;
                border: 1px solid rgba(255, 255, 255, .25);
            }

            .EventsMap-marker-content {
                display: flex;
                flex-direction: row;
                gap: var(--default-margin);
            }

            .EventsMap-marker-contentImage {
                flex: 1 0 60px;
            }

            .EventsMap-marker-image {
                max-width: 100%;
                height: auto;
            }

            .EventsMap-marker-contentText {
                display: flex;
                flex-direction: column;
                gap: var(--default-margin-third);
            }

            .EventsMap-marker-contentText strong {
                font-size: var(--font-size-small);
            }

            .EventsMap-marker-contentText a {
                display: block;
            }

            .EventsMap-marker-contentLink {}

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

<div class="widget">
    <h2 class="widget__title">Sverigekartan</h2>
    <div class="widget__fullwidth">
        <div class="EventsMap">Laddar karta...</div>
    </div>
</div>
