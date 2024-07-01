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
        <link rel="stylesheet" href="{{ URL::asset('css/events-map.css') }}">
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
@endonce

<div class="widget">
    <h2 class="widget__title">Händelsekarta</h2>
    <div class="widget__fullwidth">
        <div class="EventsMap__container">
            <div class="EventsMap" data-events-map-size="{{ $mapSize }}">Laddar karta...</div>
        </div>
    </div>
</div>
