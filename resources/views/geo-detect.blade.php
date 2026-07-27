<!DOCTYPE html>
<html lang="sv">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,minimum-scale=1,initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Hämtar din position …</title>

    {{-- Fristående sida, inte layouts.web. Sidan lever bara någon sekund innan
         den redirectar, och layouts.web drar in CMP, annonser och Leaflet —
         meningslöst här, och CMP-dialogen skulle dessutom lägga sig över
         behörighetsdialogen för platsåtkomst. Vi tar med sajtens header (ren
         markup, noll script) och styles.css så användaren har en väg ut medan
         positionen hämtas. Todo #97. --}}
    @php
        $_stylesPath = public_path('css/styles.css');
        $_stylesVer = file_exists($_stylesPath) ? filemtime($_stylesPath) : '1';
    @endphp
    <link rel="stylesheet" type="text/css" href="/css/styles.css?v={{ $_stylesVer }}" />

    <style>
        .GeoDetect {
            max-width: 40rem;
            margin: var(--default-margin-triple) auto;
            padding: 0 var(--default-margin);
            text-align: center;
        }

        .GeoDetect__status {
            font-size: var(--font-size-medium);
            margin-bottom: var(--default-margin-double);
        }

        .GeoDetect__fallback {
            color: var(--color-gray-1);
        }
    </style>
</head>

<body>
    @include('parts.siteheader')

    <div class="GeoDetect">
        {{-- role="status" så skärmläsare annonserar att något pågår. --}}
        <p class="GeoDetect__status" role="status">Hämtar din position …</p>

        {{-- Utväg för den som tvekar inför behörighetsdialogen. Utan den är
             enda alternativet att vänta ut timeouten nedan. --}}
        <p class="GeoDetect__fallback">
            Tar det för lång tid?
            <a href="/nara?error=1">Visa de senaste händelserna</a>
        </p>
    </div>

    <script>
        (function () {
            // Sidan finns bara för att hämta position med JS och skicka
            // vidare. All felhantering går till samma ställe: /nara?error=1
            // visar mest lästa och senaste händelserna i stället.
            var fallbackUrl = "/nara?error=1";

            if (!("geolocation" in navigator)) {
                document.location = fallbackUrl;
                return;
            }

            navigator.geolocation.getCurrentPosition(
                function (position) {
                    // Avrunda för att öka cachebarhet, gruppera snyggare i GA
                    // och göra besöken mindre identifierbara.
                    var lat = position.coords.latitude.toFixed(2);
                    var lng = position.coords.longitude.toFixed(2);

                    document.location = "/nara?lat=" + lat + "&lng=" + lng;
                },
                function (err) {
                    // Tre fall hamnar här: nekad behörighet (kod 1),
                    // positionen gick inte att bestämma (kod 2) och timeout
                    // (kod 3). Alla tre ska sluta på samma sida.
                    document.location = fallbackUrl;
                },
                {
                    // Utan timeout hänger sidan för alltid om användaren
                    // aldrig svarar på behörighetsdialogen. En fix tar typiskt
                    // 1–3 s när behörighet redan finns.
                    timeout: 8000,
                    // Låt en position som hämtats för under en minut sedan
                    // användas direkt — återbesök blir omedelbara.
                    maximumAge: 60000,
                    // Behövs inte: koordinaten avrundas till två decimaler.
                    enableHighAccuracy: false,
                }
            );
        })();
    </script>
</body>

</html>
