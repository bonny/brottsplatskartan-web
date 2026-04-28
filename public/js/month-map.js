/**
 * Översiktskarta för månadsvy (todo #25).
 *
 * Initialiseras först när kart-containern närmar sig viewport
 * (IntersectionObserver, rootMargin 200px). Leaflet + markercluster
 * laddas defer via parent-componenten och förväntas vara på plats
 * när initMap() körs.
 *
 * CWV-strategi: ingen JS körs förrän användaren scrollar nära kartan,
 * vilket håller LCP/INP/CLS lågt på initial render.
 */
(function () {
    'use strict';

    const ROOT_MARGIN = '200px';
    const containers = document.querySelectorAll('.MonthOverviewMap__container');

    if (containers.length === 0) {
        return;
    }

    function initMap(container) {
        if (container.dataset.mapInitialized === 'true') {
            return;
        }
        container.dataset.mapInitialized = 'true';

        // Vänta tills Leaflet har laddat (defer-script). Worst case 50ms.
        if (typeof L === 'undefined') {
            setTimeout(() => initMap(container), 50);
            container.dataset.mapInitialized = 'false';
            return;
        }

        let events;
        try {
            events = JSON.parse(container.dataset.monthMapEvents);
        } catch (e) {
            console.error('MonthMap: kunde inte parsea events-JSON', e);
            return;
        }

        if (!Array.isArray(events) || events.length === 0) {
            return;
        }

        // Ta bort placeholder.
        const placeholder = container.querySelector('.MonthOverviewMap__placeholder');
        if (placeholder) {
            placeholder.remove();
        }

        const mapElm = document.createElement('div');
        mapElm.className = 'MonthOverviewMap__leaflet';
        container.appendChild(mapElm);

        const map = L.map(mapElm, {
            scrollWheelZoom: false,
            attributionControl: true,
        });

        // Använd egen tileserver (todo #30 — self-hostad, WebP).
        L.tileLayer('https://kartbilder.brottsplatskartan.se/styles/basic-preview/{z}/{x}/{y}.webp', {
            maxZoom: 18,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        }).addTo(map);

        // Custom divIcon — Leaflet's default PNG-markers serveras inte
        // (vendor/leaflet/images/ saknas). Vi använder CSS-styled dot
        // för att slippa binärfiler och matcha sajtens färgtema.
        const markerIcon = L.divIcon({
            className: 'MonthOverviewMap__marker',
            iconSize: [14, 14],
            iconAnchor: [7, 7],
            popupAnchor: [0, -7],
            html: '<span class="MonthOverviewMap__markerDot"></span>',
        });

        const markers = events.map((e) => {
            const marker = L.marker([e.lat, e.lng], { icon: markerIcon });
            const popupHtml = `
                <strong><a href="${e.permalink}">${e.title}</a></strong><br>
                ${e.time} &middot; ${e.type}
            `;
            marker.bindPopup(popupHtml, { direction: 'bottom' });
            return marker;
        });

        // markercluster om plugin finns laddad, annars vanlig layer.
        let layer;
        if (typeof L.markerClusterGroup === 'function') {
            layer = L.markerClusterGroup({ maxClusterRadius: 40 });
            layer.addLayers(markers);
        } else {
            layer = L.layerGroup(markers);
        }
        map.addLayer(layer);

        // Auto-fit till markers (eller fallback till Sverige).
        if (markers.length > 0) {
            const group = L.featureGroup(markers);
            map.fitBounds(group.getBounds(), { padding: [20, 20], maxZoom: 14 });
        } else {
            map.setView([62, 15], 5);
        }
    }

    if ('IntersectionObserver' in window) {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    initMap(entry.target);
                    observer.unobserve(entry.target);
                }
            });
        }, { rootMargin: ROOT_MARGIN });

        containers.forEach((c) => observer.observe(c));
    } else {
        // Fallback: init direkt.
        containers.forEach(initMap);
    }
})();
