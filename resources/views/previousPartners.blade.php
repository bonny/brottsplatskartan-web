{{--

Template for previousPartners in app view

--}}
<!DOCTYPE html>
<html>

<head>
    <title>previousPartners | Händelser och brott rapporterade till Polisen</title>
    <meta charset="utf-8">
    <!-- Google tag (gtag.js) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-L1WVBJ39GH"></script>
    <script>
        window.dataLayer = window.dataLayer || [];

        function gtag() {
            dataLayer.push(arguments);
        }
        gtag('js', new Date());

        gtag('config', 'G-L1WVBJ39GH');
    </script>
    <script async src='https://www.google-analytics.com/analytics.js'></script>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
            font-size: 16px;
            background: #f4f4f7;
            color: #111;
            max-width: 640px;
            margin: 0 auto;
            padding: 20px;
        }

        h1 {
            font-size: 1.5rem;
        }

        h2,
        h3,
        h4 {
            font-size: 1.25rem;
        }

        a {
            color: ;
            #15c;
            text-decoration: none;
        }

        img {
            max-width: 100%;
            height: auto;
        }

        .Event {
            background: #fff;
            padding: 20px;
            box-shadow: 0 1px 2px rgba(0, 0, 0, .3);
            margin-top: 20px;
            margin-bottom: 20px;
        }

        .Event__map {
            margin-top: 0;
        }

        .Event__title {
            font-size: 1.25rem;
            font-weight: normal;
        }

        .Event__teaser {
            font-weight: 300;
        }

        .Event__contentLink {
            text-decoration: none;
            color: inherit;
            display: none;
        }

        .Event__location {}
    </style>
</head>

<body>

    <h1>Händelser nära lat {{ $lat }}, long {{ $lng }}.</h1>

    {{-- <p>Detta är vyn för previousPartners. Alla texter här går att styra.</p> --}}

    @if ($events)
        <!-- Antal brott hämtade: {{ $events->count() }} -->
        <p>
            Visar de senaste brotten som rapporterats inom ungefär {{ $nearbyInKm }} km från din plats.
            Nyaste brotten visas först.
        </p>

        <div class="Events Events--overview">

            @foreach ($events as $event)
                @include('parts.crimeevent-previousPartners', ['overview' => true])
            @endforeach

        </div>

    @endif

</body>

</html>
