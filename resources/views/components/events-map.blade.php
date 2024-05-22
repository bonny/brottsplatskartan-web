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

        <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.4.1/dist/MarkerCluster.css" type="text/css">
        <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.4.1/dist/MarkerCluster.Default.css"
            type="text/css">
        <script src="https://unpkg.com/leaflet.markercluster@1.4.1/dist/leaflet.markercluster.js" charset="utf-8"></script>

        <script>
            /**
             * Expandera kartan.
             * 
             * @param {L.Map} map
             */
            function expandMap(map) {
                let isExpanded = map.getContainer().classList.contains('is-expanded');

                if (isExpanded) {
                    document.body.classList.remove('map-is-expanded');
                    map.getContainer().classList.remove('is-expanded');
                    map.gestureHandling.enable();
                } else {
                    document.body.classList.add('map-is-expanded');
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
            }

            L.Control.ExpandButton = L.Control.extend({
                onAdd: function(map) {
                    var html = L.DomUtil.create('div');
                    html.innerHTML = '';

                    var expandButton = L.DomUtil.create('button', 'leaflet-bar EventsMap-control-expand', html);

                    var buttonText = L.DomUtil.create('span', 'EventsMap-control-expandText', expandButton);
                    buttonText.innerText = 'Maximera';

                    var imgExpand = L.DomUtil.create('img', 'EventsMap-control-expandImg', expandButton);
                    imgExpand.src = '/img/expand_content_24dp_FILL0_wght400_GRAD0_opsz24.svg';

                    var imgMinimize = L.DomUtil.create('img', 'EventsMap-control-collapseImg', expandButton);
                    imgMinimize.src = '/img/collapse_content_24dp_FILL0_wght400_GRAD0_opsz24.svg';

                    L.DomEvent.on(expandButton, 'click', function(evt) {
                        expandMap(map);
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
                iconSize: [25, 25],
                html: '<span class="EventsMap-marker-icon-inner"></span><span class="EventsMap-marker-icon-innerIcon"></span>'
            });

            var markerIconNearer = L.divIcon({
                className: 'EventsMap-marker-icon EventsMap-marker-icon--nearer',
                iconSize: [50, 50],
                html: '<span class="EventsMap-marker-icon-inner"></span><span class="EventsMap-marker-icon-innerIcon"></span>'
            });

            class EventsMap {
                map;
                mapContainer;
                // blockerElm;
                expandBtnElm;
                zoom = {
                    default: 5,
                    fullscreen: 6,
                };
                location = {
                    default: [59, 15],
                    fullscreen: [60, 15],
                };

                crimeTypesToClass = {
                    'anträffad död': 'murder',
                    'mord/dråp': 'murder',
                    'mord/dråp försök': 'murder',
                    'arbetsplatsolycka': 'workplace',
                    'brand': 'fire',
                    'djur': 'pets',
                    'farligt föremål, misstänkt': 'unknown',
                    'försvunnen person': 'missing-person',
                    'fylleri/lob': 'sportsbar',
                    'alkohollagen': 'sportsbar',
                    'knivlagen': 'knife',
                    'vapenlagen': 'gun',
                    'skottlossning': 'gun',
                    'skottlossning, misstänkt': 'gun',
                    'kontroll person/fordon': 'car',
                    'misshandel, grov': 'blackeye',
                    'misshandel': 'blackeye',
                    'bråk': 'blackeye',
                    'motorfordon, anträffat stulet': 'car',
                    'motorfordon, stöld': 'car',
                    'narkotikabrott': 'narcotics',
                    'mord/dråp, försök': 'murder',
                    'olaga hot': 'unknown',
                    'olaga intrång': 'forbidden-sign',
                    'olovlig körning': 'car',
                    'övrigt': 'unknown',
                    'polisinsats/kommendering': 'police',
                    'räddningsinsats': 'police',
                    'rån, försök': 'robbery',
                    'rån övrigt': 'robbery',
                    'rån': 'robbery',
                    'stöld, försök': 'robbery',
                    'stöld': 'robbery',
                    'inbrott': 'burglary',
                    'inbrott, försök': 'burglary',
                    'stöld/inbrott': 'burglary',
                    'larm inbrott': 'burglary',
                    'larm överfall': 'burglary',
                    'varningslarm/haveri': 'unknown',
                    'häleri': 'burglary',
                    'rattfylleri': 'drunk-driver',
                    'sammanfattning natt': 'summarize',
                    'sammanfattning kväll och natt': 'summarize',
                    'sedlighetsbrott': 'molestation',
                    'skadegörelse': 'unknown',
                    'trafikbrott': 'car',
                    'trafikhinder': 'traffic',
                    'trafikkontroll': 'traffic',
                    'trafikolycka, personskada': 'car',
                    'trafikolycka, smitning från': 'car',
                    'trafikolycka, vilt': 'car',
                    'trafikolycka': 'car',
                    'trafikolycka, singel': 'car',
                    'våld/hot mot tjänsteman': 'unknown',
                    'våldtäkt': 'molestation',
                    'ofredande/förargelse': 'bad-behavior',
                    'åldringsbrott': 'unknown',
                    'vållande till kroppsskada': 'unknown',
                    'ordningslagen': 'unknown',
                    'sjölagen': 'unknown',
                    'sjukdom/olycksfall': 'unknown',
                };

                constructor(mapContainer, options = {}) {
                    this.mapContainer = mapContainer;
                    this.options = options;
                    this.initMap();
                }

                async loadMarkers() {
                    const response = await fetch('/api/eventsMap');
                    const data = await response.json();
                    const events = data.data
                    const markers = [];

                    events.forEach(event => {
                        const oneMarker = L.marker([event.lat, event.lng], {
                                icon: markerIconFar,
                                crimeEventData: event
                            })
                            .addTo(this.map)
                            .bindPopup(`
                                <div class="EventsMap-marker-content">
                                    <a target="_blank" href="${event.permalink}?utm_source=brottsplatskartan&utm_medium=maplink" class="EventsMap-marker-contentText EventsMap-marker-contentLink">
                                        ${event.time_human} • ${event.type}
                                        <strong>${event.headline}</strong>
                                        <!-- <div class="EventsMap-marker-contentLinkIcon">Läs mer →</div> -->
                                    </a>
                                </div>
                            `);

                        markers.push(oneMarker);
                    });

                    console.log('markers', markers);

                    // var clusterGroupMarkers = L.markerClusterGroup({});
                    // clusterGroupMarkers.addLayers(markers);
                    // map.addLayer(clusterGroupMarkers);
                }

                // expandMap() {
                //     this.map.getContainer().classList.toggle('is-expanded');

                //     // Enable click-through on blocker.
                //     // this.blockerElm.classList.toggle('EventsMap__blocker--active');

                //     // Invalidate size after css anim has finished, so tiles are loaded.
                //     setTimeout(() => {
                //         this.map.invalidateSize({
                //             pan: true
                //         });
                //     }, 250);
                // }

                initMap() {
                    this.map = L.map(this.mapContainer, {
                        zoomControl: true,
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
                        //expandMap
                        console.log('map loaded. options:', this.options);
                        if (this.options.size === 'fullscreen') {
                            expandMap(this.map);
                            //this.map.setZoom(this.zoom.fullscreen);
                            this.map.flyTo(this.location.fullscreen, this.zoom.fullscreen);
                        }
                    });

                    /**
                     * När zoom-nivå är 10 eller mer så visar vi större ikoner.
                     */
                    this.map.on('zoomend', () => {
                        let that = this;
                        // console.log('that', that);
                        if (this.map.getZoom() >= 8) {
                            // Sätt större ikon om man zoomat in.
                            let iconToSet = markerIconNear;
                            if (this.map.getZoom() > 10) {
                                // Ännu större!
                                iconToSet = markerIconNearer;
                            }

                            this.map.eachLayer(function(layer) {
                                if (layer instanceof L.Marker && layer._icon.classList.contains(
                                        'EventsMap-marker-icon')) {
                                    layer.setIcon(iconToSet);

                                    let innerElm = layer._icon.querySelector(
                                        '.EventsMap-marker-icon-inner');
                                    let innerIconElm = layer._icon.querySelector(
                                        '.EventsMap-marker-icon-innerIcon');

                                    // Brottstyp utan mellanslag mellan ord (så max 1 mellanslag efter varandra).
                                    let crimeEventType = layer.options.crimeEventData.type.toLowerCase();
                                    crimeEventType = crimeEventType.replace(/\s\s+/g, ' ');

                                    if (that.crimeTypesToClass[crimeEventType]) {
                                        innerIconElm.classList.add(
                                            `EventsMap-marker-icon-innerIcon--${that.crimeTypesToClass[crimeEventType]}`
                                        );
                                    } else {
                                        console.log('marker no icon support', crimeEventType);
                                    }
                                }
                            });
                        } else {
                            // Sätt mindre ikon om man är utzoomad.
                            this.map.eachLayer(function(layer) {
                                if (layer instanceof L.Marker && layer._icon.classList.contains(
                                        'EventsMap-marker-icon')) {
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
                    window.expandButtonControl = x;

                    L.control.locate({
                        locateOptions: {
                            maxZoom: 11,
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
                        },
                        position: 'bottomright'
                    }).addTo(map);
                }
            }

            document.addEventListener('DOMContentLoaded', function() {
                let mapContainers = document.querySelectorAll('.EventsMap');
                mapContainers.forEach(element => {
                    new EventsMap(element, {
                        size: '{{ $mapSize }}'
                    });
                });
            });
        </script>
    @endpush

    @push('footerscripts')
        <script>
            // Sätt standardvärden innan vi räknar ut höjd på saker.
            document.body.style.setProperty('--ad-top-height', '0px');
            document.body.style.setProperty('--ad-bottom-height', '0px');
            document.body.style.setProperty('--header-elms-height', '0px');
            // Grippy is the expand/collapse button. Is 30 px high.
            document.body.style.setProperty('--grippy-height', '0px');

            // Räkna ut höjd på sidhuvud och händelserna.
            let headerElmsHeight = Array.from(document.querySelectorAll('.sitebar__Events, .SiteHeader')).reduce(function(acc,
                current) {
                return acc + current.offsetHeight;
            }, 0);

            document.body.style.setProperty('--header-elms-height', `${headerElmsHeight}px`);

            // Höjden på .sitebar__Events.
            let sitebarEventsElm = document.querySelector('.sitebar__Events');
            let sitebarEventsHeight = sitebarEventsElm.offsetHeight;
            document.body.style.setProperty('--sitebar-events-height', `${sitebarEventsHeight}px`);

            console.log('headerElmsHeight', headerElmsHeight);

            (function() {
                // Select the node that will be observed for mutations
                const targetNode = document.body;
                if (targetNode === null) {
                    console.log("No target node found for mutation observer.", targetNode);
                    return;
                }

                // Options for the observer (which mutations to observe)
                const config = {
                    attributes: false,
                    childList: true,
                    subtree: false,
                };

                /**
                 * Sätter CSS-vars på body baserat på en annons.
                 */
                function setVarsBasedOnAd(adElm) {
                    let dataset = adElm.dataset;
                    let bodyStyle = document.body.style;

                    // Annons blivit synlig och är visad.
                    if (dataset.adsbygoogleStatus === "done" && dataset.anchorStatus === "displayed" && dataset
                        .anchorShown === "true") {
                        // om top: 0px så är annonsen i toppen av sidan.
                        // om bottom: 0px så är annonsen i botten av sidan.
                        // när man fäller ihop annonsen så blir top: auto och bottom: auto så vi kan inte använda
                        // det värdet för att avgöra om annonsen är i toppen eller botten.
                        // men det andra hållets värde är auto så vi kan använda det för att avgöra var annonsen är.
                        if (adElm.style.bottom === "auto") {
                            console.log("setVarsBasedOnAd: Ad is at top of page.", adElm);
                            bodyStyle.setProperty('--ad-top-height', adElm.style.height);
                        } else if (adElm.style.top === "auto") {
                            console.log("setVarsBasedOnAd: Ad is at bottom of page.", adElm);
                            bodyStyle.setProperty('--ad-bottom-height', adElm.style.height);
                        }
                    }

                    if (dataset.adsbygoogleStatus === "done" && dataset.anchorStatus === "dismissed" && dataset
                        .anchorShown === "true") {
                        console.log("setVarsBasedOnAd: Ad is hidden.", adElm);
                    }
                }

                // Callback function to execute when mutations are observed
                const callback = (mutationList, observer) => {
                    // console.log("Mutation list: ", mutationList);
                    for (const mutation of mutationList) {
                        if (mutation.type === "childList") {
                            // console.log("A child node has been added or removed.");
                            // console.log("added nodes: ", mutation.addedNodes);
                            // Check for class "adsbygoogle.adsbygoogle-noablate".
                            for (const node of mutation.addedNodes) {
                                if (node instanceof HTMLElement && node.classList.contains("adsbygoogle-noablate")) {
                                    // console.log("Found sticky ad: ", node);

                                    // Keep track of attribute changes on this ad.
                                    const adObserver = new MutationObserver((adMutationsList, adObserver) => {
                                        for (const mutation of adMutationsList) {
                                            if (mutation.type === "attributes") {
                                                // Ad fälls ut och är visad.
                                                if (node.dataset.adsbygoogleStatus === "done" && node.dataset
                                                    .anchorStatus ===
                                                    "displayed" && node.dataset.anchorShown === "true") {
                                                    setVarsBasedOnAd(node);
                                                }

                                                // När anchorStatus = dismissed så är annonsen inte längre synlig för anv. klickat på fäll ihop-knappen.
                                                if (node.dataset.adsbygoogleStatus === "done" && node.dataset
                                                    .anchorStatus ===
                                                    "dismissed" && node.dataset.anchorShown === "true") {
                                                    setVarsBasedOnAd(node);
                                                }

                                            }
                                        }
                                    });

                                    adObserver.observe(node, {
                                        attributes: true,
                                        attributeFilter: [
                                            'data-adsbygoogle-status',
                                            'data-anchor-status',
                                            'data-ad-status',
                                        ],
                                    });
                                }
                            }
                        }
                    }
                };

                // Create an observer instance linked to the callback function
                const observer = new MutationObserver(callback);

                // Start observing the target node for configured mutations
                observer.observe(targetNode, config);
            })
            ();
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

            body.map-is-expanded {
                overflow: hidden;
                position: fixed;
                top: 0;
                right: 0;
                bottom: 0;
                left: 0;
            }

            body.map-is-expanded .sitebar__Events {
                top: var(--ad-top-height);
                position: fixed;
                left: 0;
                right: 0;
                z-index: 1;
            }

            body.map-is-expanded .SiteHeader {
                top: calc(var(--ad-top-height) + var(--sitebar-events-height));
                position: fixed;
                left: 0;
                right: 0;
            }

            .EventsMap.is-expanded {
                position: fixed !important;
                height: calc(100dvh - var(--header-elms-height) - var(--ad-top-height) - var(--ad-bottom-height));
                top: calc(var(--header-elms-height) + var(--ad-top-height));
                bottom: var(--ad-bottom-height);
                left: 0;
                right: 0;
                z-index: 20;
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
                margin: 0;
                appearance: none;
                color: inherit;
                background-color: #fff;
                /* border: 2px solid rgba(255, 255, 255, .5); */
                padding: 3px;
                /* border-radius: 2px; */
            }

            .EventsMap.is-expanded .EventsMap-control-expandText {
                display: none;
            }

            .EventsMap .EventsMap-control-collapseImg {
                display: none;
            }

            .EventsMap.is-expanded .EventsMap-control-expandImg {
                display: none;
            }

            .EventsMap.is-expanded .EventsMap-control-collapseImg {
                display: block;
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
                display: flex;
                align-items: center;
                justify-content: center;
                transition:
                    ease-in-out width .25s,
                    ease-in-out height .25s,
                    ease-in-out margin .25s;
            }

            .EventsMap-marker-icon--near,
            .EventsMap-marker-icon--nearer {
                border: 2px solid rgba(255, 255, 255, .5);
            }

            .EventsMap-marker-content {
                display: flex;
                flex-direction: row;
                gap: var(--default-margin);
                font-size: 1rem;
            }

            .EventsMap-marker-contentText {
                display: flex;
                flex-direction: column;
                gap: var(--default-margin-third);
                font-size: 1.1rem;
            }

            .EventsMap-marker-contentLinkIcon {
                text-align: right;
            }

            .EventsMap-marker-contentText strong {
                /* font-size: var(--font-size-small); */
            }

            .EventsMap-marker-contentText a {
                display: block;
            }

            .EventsMap a {
                color: inherit;
            }

            .EventsMap-marker-contentLink {}

            .EventsMap-marker-icon-inner {
                display: grid;
                place-items: center;
                position: absolute;
                width: 25px;
                height: 25px;
                background-color: var(--color-red-2);
                border-radius: 50%;
                animation: ease-in-out markerPulse 2s infinite;
            }

            .EventsMap-marker-icon-innerIcon {
                width: 20px;
                height: 20px;
                color: white;
                z-index: 0;
                background-size: cover;
                background-repeat: no-repeat;
                transition: ease-in-out width .25s, ease-in-out height .25s;
            }

            .EventsMap-marker-icon--nearer .EventsMap-marker-icon-innerIcon {
                width: 30px;
                height: 30px;
            }

            .EventsMap-marker-icon--nearer .EventsMap-marker-icon-inner {
                width: 50px;
                height: 50px;
            }

            .EventsMap-marker-icon-innerIcon--fire {
                background-image: url('/img/local_fire_department_24dp_FILL0_wght400_GRAD0_opsz24.svg');
            }

            .EventsMap-marker-icon-innerIcon--house {
                background-image: url('/img/home_24dp_FILL0_wght400_GRAD0_opsz24.svg');
            }

            .EventsMap-marker-icon-innerIcon--missing-person {
                background-image: url('/img/person_alert_24dp_FILL0_wght400_GRAD0_opsz24.svg');
            }

            .EventsMap-marker-icon-innerIcon--pets {
                background-image: url('/img/pets_24dp_FILL0_wght400_GRAD0_opsz24.svg');
            }

            .EventsMap-marker-icon-innerIcon--car {
                background-image: url('/img/car_crash_24dp_FILL0_wght400_GRAD0_opsz24.svg');
            }

            .EventsMap-marker-icon-innerIcon--traffic {
                background-image: url('/img/traffic_24dp_FILL0_wght400_GRAD0_opsz24.svg');
            }

            .EventsMap-marker-icon-innerIcon--police,
            .EventsMap-marker-icon-innerIcon--unknown {
                background-image: url('/img/local_police_24dp_FILL0_wght400_GRAD0_opsz24.svg');
            }

            /* murder by Aldric Rodríguez from <a href="https://thenounproject.com/browse/icons/term/murder/" target="_blank" title="murder Icons">Noun Project</a> (CC BY 3.0) */
            .EventsMap-marker-icon-innerIcon--murder {
                /* background-image: url('/img/noun-murder-810013.svg'); */
                background-image: url('/img/noun-murder-810010.svg');
                filter: invert(1);
            }

            /* Workplace by MUHAMMAT SUKIRMAN from <a href="https://thenounproject.com/browse/icons/term/workplace/" target="_blank" title="Workplace Icons">Noun Project</a> (CC BY 3.0) */
            .EventsMap-marker-icon-innerIcon--workplace {
                background-image: url('/img/noun-workplace-5973332.svg');
                filter: invert(1);
            }

            .EventsMap-marker-icon-innerIcon--sportsbar {
                background-image: url('/img/sports_bar_24dp_FILL0_wght400_GRAD0_opsz24.svg');
            }

            /* Knife by Royyan Wijaya from <a href="https://thenounproject.com/browse/icons/term/knife/" target="_blank" title="Knife Icons">Noun Project</a> (CC BY 3.0) */
            .EventsMap-marker-icon-innerIcon--knife {
                background-image: url('/img/noun-knife-1659779.svg');
                filter: invert(1);
            }

            /* Gun by David Khai from <a href="https://thenounproject.com/browse/icons/term/gun/" target="_blank" title="Gun Icons">Noun Project</a> (CC BY 3.0) */
            .EventsMap-marker-icon-innerIcon--gun {
                background-image: url('/img/noun-gun-479957.svg');
                filter: invert(1);
            }

            /* Black Eye by Dan Nemmers from <a href="https://thenounproject.com/browse/icons/term/black-eye/" target="_blank" title="Black Eye Icons">Noun Project</a> (CC BY 3.0) */
            .EventsMap-marker-icon-innerIcon--blackeye {
                background-image: url('/img/noun-black-eye-22280.svg');
                filter: invert(1);
            }

            /* narcotics by Natthapong Mueangmoon from <a href="https://thenounproject.com/browse/icons/term/narcotics/" target="_blank" title="narcotics Icons">Noun Project</a> (CC BY 3.0) */
            .EventsMap-marker-icon-innerIcon--narcotics {
                background-image: url('/img/noun-narcotics-5895354.svg');
            }

            /* Forbidden Sign by Andy Horvath from <a href="https://thenounproject.com/browse/icons/term/forbidden-sign/" target="_blank" title="Forbidden Sign Icons">Noun Project</a> (CC BY 3.0) */
            .EventsMap-marker-icon-innerIcon--forbidden-sign {
                background-image: url('/img/noun-forbidden-sign-4589694.svg');
                filter: invert(1);
            }

            .EventsMap-marker-icon-innerIcon--summarize {
                background-image: url('/img/summarize_24dp_FILL0_wght400_GRAD0_opsz24.svg');
            }

            /* robbery by Luiz Carvalho from <a href="https://thenounproject.com/browse/icons/term/robbery/" target="_blank" title="robbery Icons">Noun Project</a> (CC BY 3.0) */
            .EventsMap-marker-icon-innerIcon--robbery {
                background-image: url('/img/noun-robbery-4353961.svg');
                filter: invert(1);
            }

            /* burglary by Luis Prado from <a href="https://thenounproject.com/browse/icons/term/burglary/" target="_blank" title="burglary Icons">Noun Project</a> (CC BY 3.0) */
            .EventsMap-marker-icon-innerIcon--burglary {
                background-image: url('/img/noun-burglary-80199.svg');
                filter: invert(1);
            }

            /* drunk driver by Clément Payot from <a href="https://thenounproject.com/browse/icons/term/drunk-driver/" target="_blank" title="drunk driver Icons">Noun Project</a> (CC BY 3.0) */
            .EventsMap-marker-icon-innerIcon--drunk-driver {
                background-image: url('/img/noun-drunk-driver-4088846.svg');
                filter: invert(1);
            }

            /* bad by Adrien Coquet from <a href="https://thenounproject.com/browse/icons/term/bad/" target="_blank" title="bad Icons">Noun Project</a> (CC BY 3.0) */
            .EventsMap-marker-icon-innerIcon--bad-behavior {
                background-image: url('/img/noun-drunk-driver-4088846.svg');
                filter: invert(1);
            }

            /* molestation by Teewara soontorn from <a href="https://thenounproject.com/browse/icons/term/molestation/" target="_blank" title="molestation Icons">Noun Project</a> (CC BY 3.0) */
            .EventsMap-marker-icon-innerIcon--molestation {
                background-image: url('/img/noun-molestation-4019945.svg');
            }




            @keyframes markerPulse {
                0% {
                    transform: scale(1);
                    opacity: 0;
                }

                50% {
                    transform: scale(1.2);
                    opacity: .5;
                }

                100% {
                    transform: scale(1.5);
                    opacity: 0;
                }
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

<div class="widget">
    <h2 class="widget__title">Händelsekarta</h2>
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
