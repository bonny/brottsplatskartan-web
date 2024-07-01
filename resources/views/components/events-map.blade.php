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

        <script src="{{ URL::asset('js/leaflet-loader.js') }}"></script>
        <script src="{{ URL::asset('js/events-map.js') }}"></script>
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
            .leaflet-control-loader {
                z-index: 2000;
                position: absolute;
                top: 50%;
                left: 50%;
                margin-top: -40px;
                margin-left: -50px;
                height: 80px;
                width: 100px;
                border-radius: 10px;
                /* background: url('images/leaflet-loader.gif') center center no-repeat rgba(255,255,255,0.8); */
                background-color: rgba(255, 255, 255, .5);
                background-repeat: no-repeat;
                background-position: center;
                background-image: url('data:image/gif;base64,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');
            }

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
                height: 70dvh;
                background-color: antiquewhite;
                background-image: url('/img/share-img-blur.jpg');
                background-size: cover;
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
                color: #fff;
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

            /* .EventsMap-marker-icon--near,
                                                                                                                                                                    .EventsMap-marker-icon--nearer {
                                                                                                                                                                        border: 2px solid rgba(255, 255, 255, .5);
                                                                                                                                                                    } */

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
                display: grid;
                place-items: center;
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
                filter: invert(1);
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
            <div class="EventsMap" data-events-map-size="{{ $mapSize }}">Laddar karta...</div>
        </div>
    </div>
</div>
