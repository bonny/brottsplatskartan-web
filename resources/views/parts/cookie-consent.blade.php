<amp-consent layout="nodisplay" id="consent-element">
    <script type="application/json">
        {
            "consentInstanceId": "my-consent",
            "consentRequired": true,
            "xcheckConsentHref": "https://example.com/api/check-consent",
            "promptUI": "consent-ui",
            "xonUpdateHref": "https://example.com/update-consent"
        }
    </script>
    <div id="consent-ui">
        <h2 class="site-cookie-banner__messageHeadline">Får vi använda din data till att skräddarsy annonser åt dig?</h2>
        <p>
            Vi och våra partners samlar in och använder cookies till annonsanpassning,
            mätning och för att möjliggöra viktig webbplatsfunktionalitet.
            <a href="/sida/cookies/" class="site-cookie-banner__readmore">Läs mer om hur TextTV.nu och våra partners
                samlar in och använder data.</a>
        </p>

        <button on="tap:consent-element.accept">Jag godkänner</button>
        <button on="tap:consent-element.reject">Neka</button>
        {{-- <button on="tap:consent-element.dismiss">Dismiss</button> --}}
    </div>
</amp-consent>

<p>this is cookie-consent!</p>
