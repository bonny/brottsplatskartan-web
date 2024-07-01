const ICON_NEAR_ZOOM_LEVEL = 8;
const ICON_NEARER_ZOOM_LEVEL = 10;

const crimeTypesToClass = {
    "anträffad död": "murder",
    "mord/dråp": "murder",
    "mord/dråp försök": "murder",
    arbetsplatsolycka: "workplace",
    brand: "fire",
    djur: "pets",
    "farligt föremål, misstänkt": "unknown",
    "försvunnen person": "missing-person",
    "fylleri/lob": "sportsbar",
    alkohollagen: "sportsbar",
    knivlagen: "knife",
    vapenlagen: "gun",
    skottlossning: "gun",
    "skottlossning, misstänkt": "gun",
    "kontroll person/fordon": "car",
    "misshandel, grov": "blackeye",
    misshandel: "blackeye",
    bråk: "blackeye",
    "motorfordon, anträffat stulet": "car",
    "motorfordon, stöld": "car",
    narkotikabrott: "narcotics",
    "mord/dråp, försök": "murder",
    "olaga hot": "unknown",
    "olaga intrång": "forbidden-sign",
    "olovlig körning": "car",
    övrigt: "unknown",
    "polisinsats/kommendering": "police",
    räddningsinsats: "police",
    "rån, försök": "robbery",
    "rån övrigt": "robbery",
    "rån väpnat": "robbery",
    rån: "robbery",
    "stöld, försök": "robbery",
    stöld: "robbery",
    inbrott: "burglary",
    "inbrott, försök": "burglary",
    "stöld/inbrott": "burglary",
    "larm inbrott": "burglary",
    "larm överfall": "burglary",
    "varningslarm/haveri": "unknown",
    häleri: "burglary",
    rattfylleri: "drunk-driver",
    "sammanfattning natt": "summarize",
    "sammanfattning kväll och natt": "summarize",
    sedlighetsbrott: "molestation",
    skadegörelse: "unknown",
    trafikbrott: "car",
    trafikhinder: "traffic",
    trafikkontroll: "traffic",
    "trafikolycka, personskada": "car",
    "trafikolycka, smitning från": "car",
    "trafikolycka, vilt": "car",
    trafikolycka: "car",
    "trafikolycka, singel": "car",
    "våld/hot mot tjänsteman": "unknown",
    våldtäkt: "molestation",
    "ofredande/förargelse": "bad-behavior",
    åldringsbrott: "unknown",
    "vållande till kroppsskada": "unknown",
    ordningslagen: "unknown",
    sjölagen: "unknown",
    "sjukdom/olycksfall": "unknown",
    // missing:
    // - miljöbrott
    // - fjällräddning
    // - hemfridsbrott
    // bad icons:
    // - våldtäkt
    // - misshandel
};

/**
 * Expandera kartan.
 *
 * @param {L.Map} map
 */
function expandMap(map) {
    let isExpanded = map.getContainer().classList.contains("is-expanded");

    if (isExpanded) {
        document.body.classList.remove("map-is-expanded");
        map.getContainer().classList.remove("is-expanded");
        map.gestureHandling.enable();
    } else {
        document.body.classList.add("map-is-expanded");
        map.getContainer().classList.add("is-expanded");
        map.gestureHandling.disable();
        // Få plats med Sverige.
        // Men bara om man inte rört kartan, irriterade att man hoppar bort från där man var annars.
        // map.setView([65.15531, 15], 5);
    }

    // Invalidate size after resize.
    map.invalidateSize({
        pan: true,
    });
}

L.Control.ExpandButton = L.Control.extend({
    onAdd: function (map) {
        var html = L.DomUtil.create("div");
        html.innerHTML = "";

        var expandButton = L.DomUtil.create(
            "button",
            "leaflet-bar EventsMap-control-expand",
            html
        );

        var buttonText = L.DomUtil.create(
            "span",
            "EventsMap-control-expandText",
            expandButton
        );
        buttonText.innerText = "Maximera";

        var imgExpand = L.DomUtil.create(
            "img",
            "EventsMap-control-expandImg",
            expandButton
        );
        imgExpand.src =
            "/img/expand_content_24dp_FILL0_wght400_GRAD0_opsz24.svg";

        var imgMinimize = L.DomUtil.create(
            "img",
            "EventsMap-control-collapseImg",
            expandButton
        );
        imgMinimize.src =
            "/img/collapse_content_24dp_FILL0_wght400_GRAD0_opsz24.svg";

        L.DomEvent.on(expandButton, "click", function (evt) {
            expandMap(map);
        });

        return html;
    },

    onRemove: function (map) {
        // Nothing to do here
    },
});

L.control.ExpandButton = function (opts) {
    return new L.Control.ExpandButton(opts);
};

function setLayerIcon(layer, map, classToAdd = "", innerText = "") {
    layer.setIcon(getLayerIcon(layer, map, classToAdd, innerText));
}

function getLayerIcon(layer, map, classToAdd = "", innerText = "") {
    const zoomLevel = map.getZoom();

    // let markerType; // normal | cluster | spiderfied
    // if (layer instanceof L.MarkerCluster) {
    //     markerType = "cluster";
    // } else if (layer instanceof L.Marker) {
    //     markerType = "normal";
    // } else {
    //     console.log("Unknown marker type", layer);
    // }
    // console.log("markerType", markerType);

    // Brottstyp utan mellanslag mellan ord (så max 1 mellanslag efter varandra).
    let crimeEventTypeClassForInnerIcon = "";
    let crimeEventType =
        layer?.options?.crimeEventData?.type?.toLowerCase() || "";
    crimeEventType = crimeEventType.replace(/\s\s+/g, " ");

    if (crimeTypesToClass[crimeEventType]) {
        crimeEventTypeClassForInnerIcon = `EventsMap-marker-icon-innerIcon--${crimeTypesToClass[crimeEventType]}`;
    } else if (crimeEventType) {
        console.log("No icon found for marker", crimeEventType, layer);
    }

    // Default zoomed out icons.
    let className = `EventsMap-marker-icon EventsMap-marker-icon--far ${classToAdd}`;
    let iconSize = [10, 10];
    let html = '<span class="EventsMap-marker-icon-inner"></span>';

    if (zoomLevel >= ICON_NEAR_ZOOM_LEVEL) {
        className = `EventsMap-marker-icon EventsMap-marker-icon--near ${classToAdd}`;
        iconSize = [25, 25];
        html = `<span class="EventsMap-marker-icon-inner"></span><span class="EventsMap-marker-icon-innerIcon ${crimeEventTypeClassForInnerIcon}">${innerText}</span>`;

        if (zoomLevel > ICON_NEARER_ZOOM_LEVEL) {
            className = `EventsMap-marker-icon EventsMap-marker-icon--nearer ${classToAdd}`;
            iconSize = [50, 50];
            html = `<span class="EventsMap-marker-icon-inner"></span><span class="EventsMap-marker-icon-innerIcon ${crimeEventTypeClassForInnerIcon}">${innerText}</span>`;
        }
    }

    return L.divIcon({
        className,
        iconSize,
        html,
    });
}

class EventsMap {
    map;
    mapContainer;
    // blockerElm;
    expandBtnElm;
    zoom = {
        default: 6,
        fullscreen: 7,
    };
    location = {
        default: [59, 15],
        fullscreen: [60, 15],
    };

    constructor(mapContainer, options = {}) {
        this.mapContainer = mapContainer;
        this.options = options;
        this.initMap();
    }

    async loadMarkers() {
        const response = await fetch("/api/eventsMap");
        const data = await response.json();
        const events = data.data;
        const markers = [];

        events.forEach((event) => {
            const oneMarker = //.addTo(this.map)
                L.marker([event.lat, event.lng], {
                    icon: getLayerIcon(null, map, "", ""),
                    crimeEventData: event,
                })
                    .bindTooltip(
                        `
                        <div class="EventsMap-markerTooltip">
                            <img class="EventsMap-markerTooltip-image" src="${event.image}" alt="">
                            <h1 class="EventsMap-markerTooltip-headline">${event.headline}</h1>
                            <div class="EventsMap-markerTooltip-text">${event.time_human} • ${event.type}</div>
                        </div>
                    `,
                        { direction: "bottom", permanent: false }
                    )
                    // .bindPopup(
                    //     `
                    //     <div class="EventsMap-marker-content">
                    //         <a target="_blank" href="${event.permalink}?utm_source=brottsplatskartan&utm_medium=maplink" class="EventsMap-marker-contentText EventsMap-marker-contentLink">
                    //             ${event.time_human} • ${event.type}
                    //             <strong>${event.headline}</strong>
                    //             <!-- <div class="EventsMap-marker-contentLinkIcon">Läs mer →</div> -->
                    //         </a>
                    //     </div>                `
                    // )
                    .on("click", function (evt) {
                        const link = evt.target.options.crimeEventData.permalink + "?utm_source=brottsplatskartan&utm_medium=maplink";
                        window.open(link, "_blank");
                    });

            markers.push(oneMarker);
        });

        console.log("markers loaded", markers);

        const markerClusterOptions = {
            // Small number to create very small clusters since I don't like when they are big.
            maxClusterRadius: 10,
            // spiderfyOnMaxZoom: false,
            // disableClusteringAtZoom: 5,
            // spiderfyDistanceMultiplier: 1,
            // Icon create function is called after each zoom level change.
            iconCreateFunction: function (cluster) {
                return getLayerIcon(cluster, map, "", cluster.getChildCount());
            },
        };
        var clusterGroupMarkers = L.markerClusterGroup(markerClusterOptions);
        clusterGroupMarkers.addLayers(markers);
        map.addLayer(clusterGroupMarkers);

        // When marker clusters icon are clicked and markers are spiderfied = expanded then
        // make sure child markers are of correct size.
        clusterGroupMarkers.on("spiderfied", function (props) {
            const { markers, cluster } = props;
            for (let i = 0; i < markers.length; i++) {
                setLayerIcon(markers[i], map, "", "");
            }
        });

        clusterGroupMarkers.on("unspiderfied", function (props) {
            const { markers, cluster } = props;
            console.log("unspiderfied", cluster, markers);
        });

        // animationend
        clusterGroupMarkers.on("animationend", function (props) {
            console.log("cluser animationend", props, this);
        });

        // map.on('spiderfied unspiderfied', function() {
        //     console.log('unspiderfied 2');
        // });

        this.controlLoader.hide();
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
            gestureHandling: true,
            // scrollWheelZoom: false,
            // touchZoom: false,
            // dragging: false,
            // tap: false,
            // click: false,
        });

        window.map = this.map;
        console.log("window.map now available", window.map);

        this.controlLoader = L.control.loader().addTo(this.map);
        this.controlLoader.show();

        this.map.on("load", () => {
            this.loadMarkers();

            // Map is "disabled" by default, no interaction because elements are on top of it.
            let parentElement = this.mapContainer.closest(
                ".EventsMap__container"
            );
            // this.expandBtnElm = parentElement.querySelector('.EventsMap-blocker-expand');
            // this.blockerElm = parentElement.querySelector('.EventsMap__blocker');

            // this.blockerElm.addEventListener('click', (evt) => {
            //     this.expandMap();
            // });

            // this.expandBtnElm.addEventListener('click', (evt) => {
            //     this.expandMap();
            // });
            //expandMap
            console.log("map loaded. options:", this.options);
            if (this.options.size === "fullscreen") {
                expandMap(this.map);
                //this.map.setZoom(this.zoom.fullscreen);
                this.map.flyTo(this.location.fullscreen, this.zoom.fullscreen);
            }
        });

        this.map.on("locateactivate", () => {
            expandMap(this.map);
        });

        /**
         * Set icons depending on zoom level.
         */
        this.map.on("zoomend", () => {
            // For each marker, set the icon.
            this.map.eachLayer(function (layer) {
                // Ensure to act only on markers with crime events data.
                // If this is a MarkerCluster-layer then act on child markers.
                // Todo: Code in here should be in a function.
                if (typeof layer.getAllChildMarkers === "function") {
                    layer.getAllChildMarkers().forEach((childMarker) => {
                        // console.log("childMarker", childMarker);
                        setLayerIcon(childMarker, map, "", "");
                    });
                    return;
                }

                if (!layer.options.crimeEventData) {
                    // console.log(
                    //     "no crimeEventData for layer",
                    //     layer,
                    //     typeof layer.getAllChildMarkers
                    // );
                    return;
                }

                setLayerIcon(layer, map, "", "");
            });
        });

        this.map.setView(this.location.default, this.zoom.default);

        L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
            attribution:
                '&copy; <a href="https://www.openstreetmap.org/copyright">OSM</a>',
        }).addTo(this.map);

        L.control
            .ExpandButton({
                position: "bottomright",
            })
            .addTo(this.map);

        window.locateControl = L.control
            .locate({
                locateOptions: {
                    maxZoom: 11,
                },
                clickBehavior: {
                    inView: "setView",
                    outOfView: "setView",
                },
                strings: {
                    title: "Visa var jag är",
                    metersUnit: "meter",
                    feetUnit: "feet",
                    popup: "Du är inom {distance} {unit} från denna punkt",
                    outsideMapBoundsMsg:
                        "Du verkar befinna dig utanför kartans gränser",
                },
                position: "bottomright",
            })
            .addTo(map);
    }
}

document.addEventListener("DOMContentLoaded", function () {
    let mapContainers = document.querySelectorAll(".EventsMap");

    if (!mapContainers.length) {
        return;
    }

    mapContainers.forEach((element) => {
        new EventsMap(element, {
            size: element.getAttribute("data-events-map-size"),
        });
    });
});
