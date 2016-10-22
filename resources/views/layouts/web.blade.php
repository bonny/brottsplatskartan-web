{{--

Layout template for web

--}}
<!DOCTYPE html>
<html ⚡ lang="sv" class="amp-border-box">
<head>
    <script async src="https://cdn.ampproject.org/v0.js"></script>
    <script async custom-element="amp-analytics" src="https://cdn.ampproject.org/v0/amp-analytics-0.1.js"></script>
    <script async custom-element="amp-ad" src="https://cdn.ampproject.org/v0/amp-ad-0.1.js"></script>
    <script async custom-element="amp-sticky-ad" src="https://cdn.ampproject.org/v0/amp-sticky-ad-0.1.js"></script>
    <script async custom-element="amp-social-share" src="https://cdn.ampproject.org/v0/amp-social-share-0.1.js"></script>
    <script async custom-element="amp-form" src="https://cdn.ampproject.org/v0/amp-form-0.1.js"></script>
    <script async custom-element="amp-iframe" src="https://cdn.ampproject.org/v0/amp-iframe-0.1.js"></script>

    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,minimum-scale=1,initial-scale=1">
    <meta content="IE=Edge" http-equiv="X-UA-Compatible">
    <link rel="canonical" href="@yield('canonicalLink', '/')">

    @hasSection('metaDescription')
        <meta name="description" content="@yield('metaDescription')">
        <meta property="og:description" content="@yield('metaDescription')">
        <meta name="twitter:description" content="@yield('metaDescription')">
    @else
        <meta property="og:description" content="Se var brott sker nära dig">
        <meta name="twitter:description" content="Se var brott sker nära dig">
    @endif

    @hasSection('metaImage')
        <meta property="og:image" content="@yield('metaImage')" />
        <meta property="og:image:width" content="640" />
        <meta property="og:image:height" content="640" />
        <meta name="twitter:image" content="@yield('metaImage')">
        <meta name="twitter:card" content="summary_large_image">
    @else
        <meta name="twitter:card" content="summary">
    @endif

    <meta property="og:site_name" content="Brottsplatskartan.se - brott på karta" />
    <meta property="fb:admins" content="685381489" />
    <meta property="fb:admins" content="523547944" />
    <meta property="fb:app_id" content="105986239475133" />
    <meta property="og:locale" content="sv_SE" />
    <meta property="og:type" content="website" />
    <meta property="og:title" content="@yield('title')" />

    <meta name="twitter:site" content="@brottsplatser">
    <meta name="twitter:title" content="@yield('title')">

    <title>@yield('title') → Brottsplatskartan</title>

    <link rel="icon" href="/favicon-16.png" sizes="16x16" type="image/png">
    <link rel="icon" href="/favicon-32.png" sizes="32x32" type="image/png">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">

    <style amp-boilerplate>body{-webkit-animation:-amp-start 8s steps(1,end) 0s 1 normal both;-moz-animation:-amp-start 8s steps(1,end) 0s 1 normal both;-ms-animation:-amp-start 8s steps(1,end) 0s 1 normal both;animation:-amp-start 8s steps(1,end) 0s 1 normal both}@-webkit-keyframes -amp-start{from{visibility:hidden}to{visibility:visible}}@-moz-keyframes -amp-start{from{visibility:hidden}to{visibility:visible}}@-ms-keyframes -amp-start{from{visibility:hidden}to{visibility:visible}}@-o-keyframes -amp-start{from{visibility:hidden}to{visibility:visible}}@keyframes -amp-start{from{visibility:hidden}to{visibility:visible}}</style><noscript><style amp-boilerplate>body{-webkit-animation:none;-moz-animation:none;-ms-animation:none;animation:none}</style></noscript>
    <style amp-custom>@include('css-styles')</style>

</head>
<body>

    <div class="container">

        <header class="SiteHeader">
            <div class="SiteHeader__inner">
                <h1 class="SiteTitle"><a href="/">
                    <svg class="SiteHeader__icon" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" version="1.1" x="0px" y="0px" viewBox="0 0 100 100" enable-background="new 0 0 100 100" xml:space="preserve"><path d="M36.442,95.585c0,0,36.799,0,40.132,0c0,0,7.47,0.001,7.47-7.418c0-3.103,0-5.146,0-5.146H42.98L36.442,95.585z"></path><path d="M74.482,24.585L49.148,72.071l34.895-0.082c0,0,0-37.425,0-40.527c0-6.877-4.712-6.877-6.55-6.877  S74.482,24.585,74.482,24.585z"></path><path d="M51.767,53.718c0,0,15.898-24.624,15.898-33.405S60.548,4.415,51.767,4.415s-15.899,7.119-15.899,15.898  C35.867,29.094,51.767,53.718,51.767,53.718z M51.766,11.365c4.722,0,8.55,3.827,8.55,8.549c0,4.723-3.827,8.55-8.55,8.55  s-8.55-3.827-8.55-8.55C43.217,15.194,47.044,11.365,51.766,11.365z"></path><g><path d="M29.636,24.585c0,0-6.729,0-8.338,0c-8.257,0-8.257,7.987-8.257,7.987c0,3.409,0,51.597,0,54.892   c0,8.121,6.303,8.121,6.303,8.121c2.643,0,2.965,0,2.965,0l22.154-41.35L29.636,24.585z"></path></g></svg>
                    Brottsplatskartan.se
                </a></h1>
                <p class="SiteTagline"><em>Se på karta var brott sker</em></p>

                <!-- <form method="get" action="{{ route("search", null, false) }}" class="HeaderSearch" target="_top">
                    <input type="text" name="s" value="" class="HeaderSearch__s" placeholder="Sök">
                    <button type="submit" class="HeaderSearch__submit">Sök</button>
                </form> -->

                <nav class="SiteNav">
                    <ul class="SiteNav__items">
                        <li class="SiteNav__item SiteNav__item--latest">
                            <a href="{{ route('start', [], false) }}">
                                <svg fill="#000000" height="18" viewBox="0 0 24 24" width="18" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8z"/>
                                    <path d="M0 0h24v24H0z" fill="none"/>
                                    <path d="M12.5 7H11v6l5.25 3.15.75-1.23-4.5-2.67z"/>
                                </svg>
                                <span>Senaste</span>
                            </a>

                        <li class="SiteNav__item SiteNav__item--lan">
                            <a href="{{ route('lanOverview', [], false) }}">
                                <svg fill="#000000" height="18" viewBox="0 0 24 24" width="18" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M15 11V5l-3-3-3 3v2H3v14h18V11h-6zm-8 8H5v-2h2v2zm0-4H5v-2h2v2zm0-4H5V9h2v2zm6 8h-2v-2h2v2zm0-4h-2v-2h2v2zm0-4h-2V9h2v2zm0-4h-2V5h2v2zm6 12h-2v-2h2v2zm0-4h-2v-2h2v2z"/>
                                    <path d="M0 0h24v24H0z" fill="none"/>
                                </svg>
                                <span>Län</span>
                            </a>

                        <li class="SiteNav__item SiteNav__item--geo">
                            <a href="/geo.php">
                                <!-- <svg fill="#000000" height="18" viewBox="0 0 24 24" width="18" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M0 0h24v24H0z" fill="none"/>
                                    <path d="M12 8c-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4-1.79-4-4-4zm8.94 3c-.46-4.17-3.77-7.48-7.94-7.94V1h-2v2.06C6.83 3.52 3.52 6.83 3.06 11H1v2h2.06c.46 4.17 3.77 7.48 7.94 7.94V23h2v-2.06c4.17-.46 7.48-3.77 7.94-7.94H23v-2h-2.06zM12 19c-3.87 0-7-3.13-7-7s3.13-7 7-7 7 3.13 7 7-3.13 7-7 7z"/>
                                </svg> -->
                                <svg fill="#000000" height="18" viewBox="0 0 24 24" width="18" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M0 0h24v24H0V0z" fill="none"/>
                                    <path d="M21 3L3 10.53v.98l6.84 2.65L12.48 21h.98L21 3z"/>
                                </svg>
                                <span>Nära mig</span>
                            </a>

                        <li class="SiteNav__item SiteNav__item--search">
                            <a href="{{ route('search', [], false) }}">
                                <svg fill="#000000" height="18" viewBox="0 0 24 24" width="18" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/>
                                    <path d="M0 0h24v24H0z" fill="none"/>
                                </svg>
                                <span>Sök</span>
                            </a>

                    </ul>
                </nav>

            </div>

        </header>

        @include('parts.breadcrumb', ["single" => true])

        @yield('content')

    </div>

    <footer class="SiteFooter">

        <p>Brottsplatskartan</p>

        <ul>
            <li><a href="{{ route("page", ["pagename" => "om"]) }}">Om brotten och kartan</a></li>
            <li><a href="{{ route("page", ["pagename" => "api"]) }}">Brottsplatser API</a></li>
        </ul>

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
          }
        }
      }
      </script>
    </amp-analytics>
    @endif

    Annons:
    <amp-ad width=320 height=100
        type="adsense"
        data-ad-client="ca-pub-1689239266452655"
        data-ad-slot="7743150002"
        layout="responsive"
        >
    </amp-ad>

    <amp-sticky-ad layout="nodisplay">
        <amp-ad width=320 height=50
            type="adsense"
            data-ad-client="ca-pub-1689239266452655"
            data-ad-slot="5942966405"
            >
        </amp-ad>
    </amp-sticky-ad>

</body>
</html>
