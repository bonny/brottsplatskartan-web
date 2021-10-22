<amp-consent layout="nodisplay" id="consent-element" class="consent-element">
    <script type="application/json">
        {
            "consentInstanceId": "my-consent",
            "consentRequired": true,
            "xcheckConsentHref": "https://example.com/api/check-consent",
            "promptUI": "consent-ui",
            "xonUpdateHref": "https://example.com/update-consent",
            "uiConfig": {
                "overlay": true
            }
        }
    </script>
    <div id="consent-ui" class="consent-ui">
        <h2 class="consent-ui__headline">Vi behöver ditt samtycke</h2>
        <div class="consent-ui__text">
            <p class="m-0">
                Får vi använda din data till att skräddarsy annonser åt dig?
            </p>
            <p class="m-0">
                Vi och våra partners samlar in och använder cookies till annonsanpassning,
                mätning och för att möjliggöra viktig webbplatsfunktionalitet.
                <a href="/sida/cookies/" class="site-cookie-banner__readmore">Läs mer om hur vi &amp; våra partners
                    samlar in och använder data.</a>
            </p>
        </div>
        <p class="m-0 flex justify-center">
            <button on="tap:consent-element.accept" class="consent-ui__btnOk">Godkänn alla cookies</button>
            {{-- <button on="tap:consent-element.reject" class="consent-ui__btnNo">Neka</button> --}}
            {{-- <button on="tap:consent-element.dismiss">Dismiss</button> --}}
        </p>
    </div>
</amp-consent>
