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
        <div class="consent-ui__text">
            <h2 class="consent-ui__headline">brottsplatskartan.se behöver samtycke till att använda dina personuppgifter
                för att:</h2>
            <p class="m-0">
                Anpassade annonser och anpassat innehåll, annons- och innehållsmätning, målgruppsstatistik och
                produktutveckling
            </p>
            <p class="m-0">
                Lagra och/eller få åtkomst till information på en enhet
            </p>
            <p class="m-0">
                Dina personuppgifter kommer att behandlas och information från enheten (cookies, unika identifierare,
                och annan enhetsdata) kan lagras av och delas med tredjepartsleverantörer eller användas av den här
                webbplatsen eller appen.
            </p>
            <p class="m-0">
                Vissa leverantörer kan behandla dina personuppgifter baserat på berättigat intresse. Du kan avvisa
                behandlingen genom att hantera alternativen nedan. Titta efter en länk längst ned på sidan eller i vår
                integritetspolicy. Via den kan du återkalla ditt samtycke.
            </p>
            {{-- <p class="m-0">
                Vi och våra partners samlar in och använder cookies till annonsanpassning,
                mätning och för att möjliggöra viktig webbplatsfunktionalitet.
                <a href="/sida/cookies/" class="site-cookie-banner__readmore">Läs mer om hur vi &amp; våra partners
                    samlar in och använder data.</a>
            </p> --}}
        </div>
        <p class="consent-ui__actions">
            <button on="tap:consent-element.accept" class="consent-ui__btnOk">Jag samtycker</button>
            {{-- <button on="tap:consent-element.reject" class="consent-ui__btnNo">Neka</button> --}}
            {{-- <button on="tap:consent-element.dismiss">Dismiss</button> --}}
        </p>
    </div>
</amp-consent>
