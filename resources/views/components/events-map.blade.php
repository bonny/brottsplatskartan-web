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
                        // evt.stopPropagation();
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
                blockerElm;
                expandBtnElm;
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

                expandMap() {
                    this.map.getContainer().classList.toggle('is-expanded');

                    // Enable click-through on blocker.
                    this.blockerElm.classList.toggle('EventsMap__blocker--active');

                    // Invalidate size after css anim has finished, so tiles are loaded.
                    setTimeout(() => {
                        this.map.invalidateSize({
                            pan: true
                        });
                    }, 250);

                }

                initMap() {

                    console.log('EventsMap.initMap:', this.mapContainer);

                    this.map = L.map(this.mapContainer, {
                        zoomControl: false,
                        attributionControl: false,
                        // scrollWheelZoom: false,
                        // touchZoom: false,
                        // dragging: false,
                        // tap: false,
                        // click: false,
                    });

                    window.map = this.map;
                    console.log('window.map now available', window.map);

                    this.map.on('load', () => {
                        this.loadMarkers();

                        // Map is "disabled" by default, no interaction because elements are on top of it.
                        let parentElement = this.mapContainer.closest('.EventsMap__container');
                        this.expandBtnElm = parentElement.querySelector('.EventsMap-control-expand');
                        this.blockerElm = parentElement.querySelector('.EventsMap__blocker');

                        this.blockerElm.addEventListener('click', (evt) => {
                            this.expandMap();
                        });

                        this.expandBtnElm.addEventListener('click', (evt) => {
                            this.expandMap();
                        });

                    });

                    this.map.setView([62, 15.5], this.zoom.default);

                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OSM</a>'
                    }).addTo(this.map);

                    L.control.watermark({
                        position: 'bottomright'
                    }).addTo(this.map);
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
            .EventsMap__container {
                position: relative;
            }

            .EventsMap__blocker {
                display: none;
                background-color: transparent;
                position: absolute;
                z-index: 1050;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
            }

            .EventsMap__blocker--active {
                display: block;
            }

            .EventsMap {
                display: flex;
                align-items: center;
                place-content: center;
                height: 200px;
                background-color: antiquewhite;
                transition: height 0.25s ease-in-out;
            }

            .EventsMap.is-expanded {
                height: 70vh;
            }

            .EventsMap-control-expand {
                position: absolute;
                right: 20px;
                top: 20px;
                z-index: 1055;
                line-height: 1;
                margin: 0;
                padding: 2px;
                cursor: pointer;
                background-color: #eee;
                padding: 5px;
                border: 1px solid #ccc;
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

        <div class="EventsMap__container">
            <div class="EventsMap-control-expand">
                <img src="/img/expand_content_24dp_FILL0_wght400_GRAD0_opsz24.svg" alt="Expandera karta">
            </div>

            <div class="EventsMap__blocker EventsMap__blocker--active"></div>

            <div class="EventsMap">Laddar karta...</div>
        </div>

    </div>
</div>
