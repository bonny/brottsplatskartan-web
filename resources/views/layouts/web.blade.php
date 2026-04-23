{{--

Layout template for web

--}}
<!DOCTYPE html>
<html lang="sv">

<head>
    @stack('scripts')
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,minimum-scale=1,initial-scale=1">
    <meta content="IE=Edge" http-equiv="X-UA-Compatible">

    {{-- Preconnect till tredjepart som laddas tidigt (LCP/TBT) --}}
    @php
        $_tileHost = parse_url((string) config('services.tileserver.url'), PHP_URL_HOST);
    @endphp
    @if ($_tileHost)
        <link rel="preconnect" href="//{{ $_tileHost }}" crossorigin>
        <link rel="dns-prefetch" href="//{{ $_tileHost }}">
    @endif
    <link rel="preconnect" href="https://www.googletagmanager.com">
    <link rel="preconnect" href="https://www.google-analytics.com" crossorigin>
    <link rel="dns-prefetch" href="https://pagead2.googlesyndication.com">

    {{-- Canonical: använd explicit sektion om satt, annars aktuell URL utan querystring --}}
    @hasSection('canonicalLink')
        <link rel="canonical" href="@yield('canonicalLink')">
    @else
        <link rel="canonical" href="{{ url()->current() }}">
    @endif

    {{-- Markdown-alternativ för LLM/AI-agenter. Samma URL + .md-suffix serverar
         ren markdown (via spatie/laravel-markdown-response för generiska sidor,
         custom renderer för event-sidor). --}}
    <link rel="alternate" type="text/markdown" href="{{ url()->current() }}.md">

    @include('feed::links')

    {{-- Meta description: fallback till kort sajtbeskrivning om sidan inte definierar egen --}}
    @php
        $_metaDescription = View::hasSection('metaDescription')
            ? trim($__env->yieldContent('metaDescription'))
            : 'Brottsplatskartan visar aktuella polishändelser från hela Sverige på karta. Sök efter brott i ditt län, din kommun eller på en specifik plats.';
    @endphp
    <meta name="description" content="{{ $_metaDescription }}">
    <meta property="og:description" content="{{ $_metaDescription }}">
    <meta name="twitter:description" content="{{ $_metaDescription }}">

    {{-- Kombinerad robots-meta (tidigare dubbel) --}}
    @php
        $_robots = ['max-image-preview:large'];
        if (isset($robotsNoindex) && $robotsNoindex) {
            $_robots = ['noindex', 'follow', 'max-image-preview:large'];
        }
    @endphp
    <meta name="robots" content="{{ implode(', ', $_robots) }}">

    @hasSection('ldJson')
        @yield('ldJson')
    @endif

    {{-- Global Organization + WebSite JSON-LD (en gång per sida) --}}
    @php
        $_siteLd = [
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'Organization',
                    '@id' => url('/#organization'),
                    'name' => 'Brottsplatskartan',
                    'url' => url('/'),
                    'logo' => url('/apple-touch-icon-152x152.png'),
                    'sameAs' => ['https://twitter.com/brottsplatser'],
                ],
                [
                    '@type' => 'WebSite',
                    '@id' => url('/#website'),
                    'url' => url('/'),
                    'name' => 'Brottsplatskartan',
                    'description' => 'Polishändelser på karta över Sverige',
                    'inLanguage' => 'sv-SE',
                    'publisher' => ['@id' => url('/#organization')],
                    'potentialAction' => [
                        '@type' => 'SearchAction',
                        'target' => [
                            '@type' => 'EntryPoint',
                            'urlTemplate' => url('/sok') . '?q={search_term_string}',
                        ],
                        'query-input' => 'required name=search_term_string',
                    ],
                ],
            ],
        ];
    @endphp
    <script type="application/ld+json">
    {!! json_encode($_siteLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
    </script>

    @hasSection('metaImage')
        <meta property="og:image" content="@yield('metaImage')" />
        <meta name="twitter:image" content="@yield('metaImage')">
        <meta name="twitter:card" content="summary_large_image">
        @hasSection('metaImageWidth')
            <meta property="og:image:width" content="@yield('metaImageWidth')" />
            <meta property="og:image:height" content="@yield('metaImageHeight')" />
        @endif
    @else
        <meta name="twitter:card" content="summary">
    @endif

    <meta property="og:site_name" content="Brottsplatskartan.se - brott på karta" />
    <meta property="fb:admins" content="685381489" />
    <meta property="fb:admins" content="523547944" />
    <meta property="fb:app_id" content="105986239475133" />
    <meta property="og:locale" content="sv_SE" />

    @hasSection('ogType')
        <meta property="og:type" content="@yield('ogType')" />
    @else
        <meta property="og:type" content="website" />
    @endif

    @hasSection('ogUrl')
        <meta property="og:url" content="@yield('ogUrl')" />
    @endif

    <meta property="og:title" content="@yield('title')" />
    <meta name="twitter:site" content="@brottsplatser">
    <meta name="twitter:title" content="@yield('title')">

    <title>@yield('title')@hasSection('showTitleTagline')
            → Brottsplatskartan
        @endif
    </title>

    @hasSection('metaContent')
        @yield('metaContent', '')
    @endif

    <meta name="apple-mobile-web-app-title" content="Brottsplatskartan">
    <meta name="application-name" content="Brottsplatskartan">

    <link rel="apple-touch-icon" sizes="57x57" href="/apple-touch-icon-57x57.png" />
    <link rel="apple-touch-icon" sizes="114x114" href="/apple-touch-icon-114x114.png" />
    <link rel="apple-touch-icon" sizes="72x72" href="/apple-touch-icon-72x72.png" />
    <link rel="apple-touch-icon" sizes="144x144" href="/apple-touch-icon-144x144.png" />
    <link rel="apple-touch-icon" sizes="60x60" href="/apple-touch-icon-60x60.png" />
    <link rel="apple-touch-icon" sizes="120x120" href="/apple-touch-icon-120x120.png" />
    <link rel="apple-touch-icon" sizes="76x76" href="/apple-touch-icon-76x76.png" />
    <link rel="apple-touch-icon" sizes="152x152" href="/apple-touch-icon-152x152.png" />
    <link rel="icon" type="image/png" href="/favicon-196x196.png" sizes="196x196" />
    <link rel="icon" type="image/png" href="/favicon-96x96.png" sizes="96x96" />
    <link rel="icon" type="image/png" href="/favicon-32x32.png" sizes="32x32" />
    <link rel="icon" type="image/png" href="/favicon-16x16.png" sizes="16x16" />
    <link rel="icon" type="image/png" href="/favicon-128.png" sizes="128x128" />
    <meta name="theme-color" content="#ffcc33">
    <meta name="apple-itunes-app" content="app-id=1174082309">
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="stylesheet" type="text/css" href="/css/styles.css" />
    <link rel="stylesheet" type="text/css" href="/css/charts.min.css" />

    @stack('styles')

    <script src="/js/scroll-snap-slider.iife.js"></script>

    @if (env('APP_ENV') != 'local')
        <!-- Global site tag (gtag.js) - Google Analytics -->
        <script async src="https://www.googletagmanager.com/gtag/js?id=G-L1WVBJ39GH"></script>
        <script>
            window.dataLayer = window.dataLayer || [];

            function gtag() {
                dataLayer.push(arguments);
            }
            gtag('js', new Date());

            gtag('config', 'G-L1WVBJ39GH');
        </script>
        <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-1689239266452655"
            crossorigin="anonymous"></script>
    @endif

</head>

<body class="@if ($shared_notification_bar_contents) has-notification-bar @endif">
    @include('parts.notificationbar')
    @include('parts.bar-events')
    @include('parts.siteheader')

    <div class="container">
        @include('parts.vma-siteheader-alerts')
        @yield('beforeBreadcrumb')
        @include('parts.breadcrumb', ['single' => true])

        {{-- Output debug data, if set --}}
        @if (isset($debugData) && !empty($debugData))
            <pre>
    {{ json_encode($debugData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}
                </pre>
        @endif
        @if (isset($debugData) && !empty($debugData['itemGeocodeURL']))
            itemGeocodeURL:<br>
            <a href="{{ $debugData['itemGeocodeURL'] }}">{{ $debugData['itemGeocodeURL'] }}</a>
        @endif

        @yield('beforeMainContent')

        <main class="MainContent">
            @yield('content')
        </main>

        <aside class="MainSidebar">
            @yield('sidebar')
        </aside>

    </div>

    @include('parts.sitefooter')

    {{--
        Pixel,
        ladda via JS för att minimera laddning via bots.
    --}}
    @php
        $pixelUrl = sprintf(
            '%1$s?path=%2$s&rand=%3$s',
            url('/pixel'), // 1
            Request::path(), // 2
            rand(), // 3
        );
    @endphp

    <script>
        (function() {
            let i = new Image();
            i.src = '{{ $pixelUrl }}';
        })();
    </script>

    <script>
        new ScrollSnapSlider.ScrollSnapSlider({
            element: document.querySelector('.sitebar__EventsItems'),
        }).with([
            new ScrollSnapSlider.ScrollSnapAutoplay(4000),
        ]);
    </script>

    @stack('footerscripts')
</body>

</html>
