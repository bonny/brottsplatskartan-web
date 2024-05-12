@once
    @push('scripts')
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
            integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
            integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
        <link rel="stylesheet" href="//unpkg.com/leaflet-gesture-handling/dist/leaflet-gesture-handling.min.css" type="text/css">
        <script src="//unpkg.com/leaflet-gesture-handling"></script>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet.locatecontrol@0.81.0/dist/L.Control.Locate.min.css" />
        <script src="https://cdn.jsdelivr.net/npm/leaflet.locatecontrol@0.81.0/dist/L.Control.Locate.min.js" charset="utf-8">
        </script>
        <script>
            L.Control.ExpandButton = L.Control.extend({
                onAdd: function(map) {
                    var html = L.DomUtil.create('div');
                    html.innerHTML = '';

                    var expandButton = L.DomUtil.create('button', 'EventsMap-control-expand', html);
                    var buttonText = L.DomUtil.create('span', '', expandButton);
                    buttonText.innerText = 'Expandera';

                    var img = L.DomUtil.create('img', 'leaflet-control-watermark', expandButton);
                    img.src = '/img/expand_content_24dp_FILL0_wght400_GRAD0_opsz24.svg';

                    L.DomEvent.on(expandButton, 'click', function(evt) {
                        console.log("click expand", evt);
                        // evt.stopPropagation();
                        let isExpanded = map.getContainer().classList.contains('is-expanded');

                        if (isExpanded) {
                            map.getContainer().classList.remove('is-expanded');
                            map.gestureHandling.enable();
                        } else {
                            map.getContainer().classList.add('is-expanded');
                            map.gestureHandling.disable();
                            // Få plats med Sverige.
                            // Men bara om man inte rört kartan, irriterade att man hoppar bort från där man var annars.
                            // map.setView([65.15531, 15], 5);
                        }

                        // Invalidate size after resize.
                        map.invalidateSize({
                            pan: true
                        });

                        // }, 0);
                    });

                    return html;
                },

                onRemove: function(map) {
                    // Nothing to do here
                },
            });

            L.control.ExpandButton = function(opts) {
                return new L.Control.ExpandButton(opts);
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
                // blockerElm;
                expandBtnElm;
                zoom = {
                    default: 5,
                };
                location = {
                    default: [59, 15],
                };

                constructor(mapContainer) {
                    this.mapContainer = mapContainer;
                    this.initMap();
                }

                async loadMarkers() {
                    const response = await fetch('/api/eventsMap');
                    const data = await response.json();
                    const events = data.data

                    events.forEach(event => {
                        L.marker([event.lat, event.lng])
                            .setIcon(markerIconFar)
                            .addTo(this.map)
                            .bindPopup(`
                                <div class="EventsMap-marker-content">
                                    <div class="EventsMap-marker-contentImage">
                                        <img class="EventsMap-marker-image" src="${event.image}" alt="" />
                                    </div>
                                    <a href="${event.permalink}?utm_source=brottsplatskartan&utm_content=maplink" class="EventsMap-marker-contentText EventsMap-marker-contentLink">
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
                    // this.blockerElm.classList.toggle('EventsMap__blocker--active');

                    // Invalidate size after css anim has finished, so tiles are loaded.
                    setTimeout(() => {
                        this.map.invalidateSize({
                            pan: true
                        });
                    }, 250);
                }

                initMap() {
                    this.map = L.map(this.mapContainer, {
                        zoomControl: false,
                        attributionControl: false,
                        gestureHandling: true
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
                        // this.expandBtnElm = parentElement.querySelector('.EventsMap-blocker-expand');
                        // this.blockerElm = parentElement.querySelector('.EventsMap__blocker');

                        // this.blockerElm.addEventListener('click', (evt) => {
                        //     this.expandMap();
                        // });

                        // this.expandBtnElm.addEventListener('click', (evt) => {
                        //     this.expandMap();
                        // });
                    });

                    /**
                     * När zoom-nivå är 10 eller mer så visar vi större ikoner.
                     */
                    this.map.on('zoomend', () => {
                        if (this.map.getZoom() >= 10) {
                            this.map.eachLayer(function(layer) {
                                if (layer instanceof L.Marker) {
                                    layer.setIcon(markerIconNear);
                                }
                            });
                        } else {
                            this.map.eachLayer(function(layer) {
                                if (layer instanceof L.Marker) {
                                    layer.setIcon(markerIconFar);
                                }
                            });
                        }
                    });

                    this.map.setView(this.location.default, this.zoom.default);

                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OSM</a>'
                    }).addTo(this.map);

                    let x = L.control.ExpandButton({
                        position: 'bottomright'
                    }).addTo(this.map);

                    L.control.locate({
                        locateOptions: {
                            maxZoom: 10,
                        },
                        clickBehavior: {
                            inView: 'setView',
                            outOfView: 'setView'
                        },
                        strings: {
                            title: "Visa var jag är",
                            metersUnit: "meter",
                            feetUnit: "feet",
                            popup: "Du är inom {distance} {unit} från denna punkt",
                            outsideMapBoundsMsg: "Du verkar befinna dig utanför kartans gränser"
                        }
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

            .EventsMap__container {}

            .EventsMap {
                z-index: 1;
                display: flex;
                align-items: center;
                place-content: center;
                height: 300px;
                background-color: antiquewhite;
                background-image: url('/img/share-img-blur.jpg');
            }

            .EventsMap.is-expanded {
                position: fixed !important;
                height: 100vh;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
            }

            .EventsMap-blocker-expand {
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

            .EventsMap-control-expand {
                display: flex;
                align-items: center;
                gap: var(--default-margin-half);
            }

            .EventsMap.is-expanded .EventsMap-control-expand span {
                display: none;
            }

            .EventsMap-blocker-expand img,
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
            {{-- <div class="EventsMap-blocker-expand">
                <img src="/img/expand_content_24dp_FILL0_wght400_GRAD0_opsz24.svg" alt="Expandera karta">
            </div> --}}

            {{-- <div class="EventsMap__blocker EventsMap__blocker--active"></div> --}}

            <div class="EventsMap">Laddar karta...</div>
        </div>

    </div>
</div>
