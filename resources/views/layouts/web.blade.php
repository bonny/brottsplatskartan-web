{{--

Layout template for web

--}}
<!DOCTYPE html>
<html ⚡ lang="sv" class="amp-border-box">
<head>
    <script async src="https://cdn.ampproject.org/v0.js"></script>
    <script async custom-element="amp-analytics" src="https://cdn.ampproject.org/v0/amp-analytics-0.1.js"></script>
    <script async custom-element="amp-ad" src="https://cdn.ampproject.org/v0/amp-ad-0.1.js"></script>
    <script async custom-element="amp-sticky-ad" src="https://cdn.ampproject.org/v0/amp-sticky-ad-1.0.js"></script>
    <script async custom-element="amp-social-share" src="https://cdn.ampproject.org/v0/amp-social-share-0.1.js"></script>
    <script async custom-element="amp-form" src="https://cdn.ampproject.org/v0/amp-form-0.1.js"></script>
    <script async custom-element="amp-iframe" src="https://cdn.ampproject.org/v0/amp-iframe-0.1.js"></script>
    <script async custom-element="amp-install-serviceworker" src="https://cdn.ampproject.org/v0/amp-install-serviceworker-0.1.js"></script>
    <script async custom-element="amp-auto-ads" src="https://cdn.ampproject.org/v0/amp-auto-ads-0.1.js"></script>
    <script async custom-element="amp-twitter" src="https://cdn.ampproject.org/v0/amp-twitter-0.1.js"></script>
    <script async custom-element="amp-facebook" src="https://cdn.ampproject.org/v0/amp-facebook-0.1.js"></script>
    <script async custom-element="amp-facebook-page" src="https://cdn.ampproject.org/v0/amp-facebook-page-0.1.js"></script>

    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,minimum-scale=1,initial-scale=1">
    <meta content="IE=Edge" http-equiv="X-UA-Compatible">
    @hasSection('canonicalLink')
    <link rel="canonical" href="@yield('canonicalLink', '/')">
    @endif

    @include('feed::links')

    @hasSection('metaDescription')
        <meta name="description" content="@yield('metaDescription')">
        <meta property="og:description" content="@yield('metaDescription')">
        <meta name="twitter:description" content="@yield('metaDescription')">
    @endif

    @hasSection('ldJson')
        @yield('ldJson')
    @endif

    {{-- @else
        <meta property="og:description" content="Se var brott sker nära dig">
        <meta name="twitter:description" content="Se var brott sker nära dig"> --}}

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

    <title>@yield('title')@hasSection('showTitleTagline') → Brottsplatskartan @endif</title>

    {{-- Don't index some pages --}}
    @if (isset($robotsNoindex) && $robotsNoindex)
        <meta name="robots" content="noindex, follow">
    @endif

    @hasSection('metaContent')
        @yield('metaContent', '')
    @endif

    <meta name="apple-mobile-web-app-title" content="Brottsplatskartan">
    <meta name="application-name" content="Brottsplatskartan">

    <link rel="apple-touch-icon-precomposed" sizes="57x57" href="/apple-touch-icon-57x57.png" />
    <link rel="apple-touch-icon-precomposed" sizes="114x114" href="/apple-touch-icon-114x114.png" />
    <link rel="apple-touch-icon-precomposed" sizes="72x72" href="/apple-touch-icon-72x72.png" />
    <link rel="apple-touch-icon-precomposed" sizes="144x144" href="/apple-touch-icon-144x144.png" />
    <link rel="apple-touch-icon-precomposed" sizes="60x60" href="/apple-touch-icon-60x60.png" />
    <link rel="apple-touch-icon-precomposed" sizes="120x120" href="/apple-touch-icon-120x120.png" />
    <link rel="apple-touch-icon-precomposed" sizes="76x76" href="/apple-touch-icon-76x76.png" />
    <link rel="apple-touch-icon-precomposed" sizes="152x152" href="/apple-touch-icon-152x152.png" />
    <link rel="icon" type="image/png" href="/favicon-196x196.png" sizes="196x196" />
    <link rel="icon" type="image/png" href="/favicon-96x96.png" sizes="96x96" />
    <link rel="icon" type="image/png" href="/favicon-32x32.png" sizes="32x32" />
    <link rel="icon" type="image/png" href="/favicon-16x16.png" sizes="16x16" />
    <link rel="icon" type="image/png" href="/favicon-128.png" sizes="128x128" />
    <meta name="theme-color" content="#ffcc33">

    <meta name="apple-itunes-app" content="app-id=1174082309">

    <link rel="manifest" href="/manifest.webmanifest">

    <style amp-boilerplate>body{-webkit-animation:-amp-start 8s steps(1,end) 0s 1 normal both;-moz-animation:-amp-start 8s steps(1,end) 0s 1 normal both;-ms-animation:-amp-start 8s steps(1,end) 0s 1 normal both;animation:-amp-start 8s steps(1,end) 0s 1 normal both}@-webkit-keyframes -amp-start{from{visibility:hidden}to{visibility:visible}}@-moz-keyframes -amp-start{from{visibility:hidden}to{visibility:visible}}@-ms-keyframes -amp-start{from{visibility:hidden}to{visibility:visible}}@-o-keyframes -amp-start{from{visibility:hidden}to{visibility:visible}}@keyframes -amp-start{from{visibility:hidden}to{visibility:visible}}</style><noscript><style amp-boilerplate>body{-webkit-animation:none;-moz-animation:none;-ms-animation:none;animation:none}</style></noscript>
    <style amp-custom>{!! HTMLMin::css(file_get_contents( public_path("css/styles.css") )) !!}</style>

</head>
<body>

    <amp-auto-ads type="adsense" data-ad-client="ca-pub-1689239266452655"></amp-auto-ads>

    <div class="container">

        <header class="SiteHeader">
            <div class="SiteHeader__inner">

                @if (Auth::check())
                    <p class='SiteHeader__loggedin'>Inloggad. Coolt. <a href="{{ route('logout') }}">Logga ut</a></p>
                @endif

                <h1 class="SiteTitle"><a href="/">
                    <div class="SiteHeader__icon">
                        <amp-img src="/img/brottsplatskartan-logotyp.png" width=282 height=36 alt="Brottsplatskartan"></amp-img>
                    </div>
                </a></h1>

                <p class="SiteTagline"><em>Se på karta var brott sker</em></p>

                <nav class="SiteNav">
                    <ul class="SiteNav__items">
                        <li class="SiteNav__item SiteNav__item--latest">
                            <a href="{{ route('start', [], false) }}">
                                <svg fill="#fff" height="18" viewBox="0 0 24 24" width="18" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8z"/>
                                    <path d="M0 0h24v24H0z" fill="none"/>
                                    <path d="M12.5 7H11v6l5.25 3.15.75-1.23-4.5-2.67z"/>
                                </svg>
                                <span>Händelser</span>
                            </a>
                        </li><li class="SiteNav__item SiteNav__item--lan">
                            <a href="{{ route('lanOverview', [], false) }}">
                                <svg fill="#fff" height="18" viewBox="0 0 24 24" width="18" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M15 11V5l-3-3-3 3v2H3v14h18V11h-6zm-8 8H5v-2h2v2zm0-4H5v-2h2v2zm0-4H5V9h2v2zm6 8h-2v-2h2v2zm0-4h-2v-2h2v2zm0-4h-2V9h2v2zm0-4h-2V5h2v2zm6 12h-2v-2h2v2zm0-4h-2v-2h2v2z"/>
                                    <path d="M0 0h24v24H0z" fill="none"/>
                                </svg>
                                <span>Län</span>
                            </a>
                        </li><li class="SiteNav__item SiteNav__item--geo">
                            <a href="/geo.php">
                                <svg fill="#fff" height="18" viewBox="0 0 24 24" width="18" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M0 0h24v24H0V0z" fill="none"/>
                                    <path d="M21 3L3 10.53v.98l6.84 2.65L12.48 21h.98L21 3z"/>
                                </svg>
                                <span>Nära mig</span>
                            </a>
                        </li><li class="SiteNav__item SiteNav__item--search">
                            <a href="{{ route('search', [], false) }}">
                                <svg fill="#fff" height="18" viewBox="0 0 24 24" width="18" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/>
                                    <path d="M0 0h24v24H0z" fill="none"/>
                                </svg>
                                <span>Sök</span>
                            </a>
                        </li>

                    </ul>
                </nav>

            </div>

        </header>

        <div class="Ad">
            <div class="Ad__intro">Annons</div>
            <amp-ad width=320 height=50
                type="adsense"
                data-ad-client="ca-pub-1689239266452655"
                data-ad-slot="9307455607"
                layout="responsive"
                >
            </amp-ad>
        </div>

        @include('parts.breadcrumb', ["single" => true])

        {{-- Output debug data, if set --}}
        @if (isset($debugData) && ! empty($debugData) )
            <pre>
{{ json_encode($debugData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}
            </pre>
        @endif
        @if (isset($debugData) && ! empty($debugData["itemGeocodeURL"]) )
            itemGeocodeURL:<br>
            <a href="{{ $debugData["itemGeocodeURL"] }}">{{ $debugData["itemGeocodeURL"] }}</a>
        @endif

        <main class="MainContent">

            @yield('content')

            <div class="Ad">
                <div class="Ad__intro">Annons</div>
                <amp-ad width=320 height=50
                    type="adsense"
                    data-ad-client="ca-pub-1689239266452655"
                    data-ad-slot="7743150002"
                    layout="responsive"
                    >
                </amp-ad>
            </div>

        </main>

        <aside class="MainSidebar">
            @yield('sidebar')
        </aside>

    </div>

    <!-- matchat innehåll - since 16 Dec 2017 -->
    {{-- <amp-ad width=300 height=520
        type="adsense"
        data-ad-client="ca-pub-1689239266452655"
        data-ad-slot="9696533065"
        layout="responsive"
        >
    </amp-ad>--}}

{{--
320x520 hade nån annan
     <script async src="//pagead2.googlesyndication.com/pagead/js/adsbygoogle.js"></script>
    <ins class="adsbygoogle"
         style="display:block"
         data-ad-format="autorelaxed"
         data-ad-client="ca-pub-1689239266452655"
         data-ad-slot="9696533065"></ins>
    <script>
         (adsbygoogle = window.adsbygoogle || []).push({});
    </script>
 --}}
    <footer class="SiteFooter">
        @include('parts.sitefooter')
    </footer>

    @if (env("APP_ENV") != "local")
    <amp-analytics type="googleanalytics" id="analytics-ga">
      <script type="application/json">
      {
        "vars": {
          "account": "UA-181460-13"
        },
        "triggers": {
          "trackPageview": {
            "on": "visible",
            "request": "pageview"
            },
          "outboundLinks": {
            "on": "click",
            "selector": "a.external",
            "request": "event",
            "vars": {
              "eventCategory": "outbound",
              "eventAction": "click",
              "eventLabel": "${outboundLink}"
            }
          }
        }
      }
      </script>
    </amp-analytics>
    @endif

    <amp-pixel src="<?php echo env('APP_URL')?>/pixel?path=CANONICAL_PATH&rand=RANDOM" layout="nodisplay"></amp-pixel>

    <amp-sticky-ad layout="nodisplay">
        <amp-ad width=320 height=50
            type="adsense"
            data-ad-client="ca-pub-1689239266452655"
            data-ad-slot="5942966405"
            >
        </amp-ad>
    </amp-sticky-ad>

    @if (env('APP_ENV')=='production')
        <amp-install-serviceworker
          src="https://brottsplatskartan.se/serviceworker.js"
          layout="nodisplay">
        </amp-install-serviceworker>
    @endif

</body>
</html>
