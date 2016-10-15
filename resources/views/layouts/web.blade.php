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
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,minimum-scale=1,initial-scale=1">
    <meta content="IE=Edge" http-equiv="X-UA-Compatible">
    <link rel="canonical" href="@yield('canonicalLink', '/')">

    @hasSection('metaDescription')
    <meta property="og:description" content="@yield('metaDescription')">
    <meta name="description" content="@yield('metaDescription')">
    @endif

    <title>@yield('title') → Brottsplatskartan</title>

    <style amp-boilerplate>body{-webkit-animation:-amp-start 8s steps(1,end) 0s 1 normal both;-moz-animation:-amp-start 8s steps(1,end) 0s 1 normal both;-ms-animation:-amp-start 8s steps(1,end) 0s 1 normal both;animation:-amp-start 8s steps(1,end) 0s 1 normal both}@-webkit-keyframes -amp-start{from{visibility:hidden}to{visibility:visible}}@-moz-keyframes -amp-start{from{visibility:hidden}to{visibility:visible}}@-ms-keyframes -amp-start{from{visibility:hidden}to{visibility:visible}}@-o-keyframes -amp-start{from{visibility:hidden}to{visibility:visible}}@keyframes -amp-start{from{visibility:hidden}to{visibility:visible}}</style><noscript><style amp-boilerplate>body{-webkit-animation:none;-moz-animation:none;-ms-animation:none;animation:none}</style></noscript>
    <style amp-custom>
        @include('css-styles')
    </style>

</head>
<body>

    <div class="container">

        <header class="SiteHeader">
            <h1 class="SiteTitle"><a href="/">Brottsplatskartan.se</a></h1>
            <p class="SiteTagline"><em>Visar på karta vad brotten sker</em></p>
        </header>

        @yield('content')

    </div>

    <footer class="SiteFooter">

        <p>Brottsplatskartan</p>

        <p>
            Om brotten och kartan
        </p>

    </footer>

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

    Annons:
    <amp-ad width=300 height=250
        type="adsense"
        data-ad-client="ca-pub-1689239266452655"
        data-ad-slot="7743150002"
        layout="responsive"
        >
    </amp-ad>

    <!--
    Annons sticky:
    <amp-sticky-ad layout="nodisplay">
        <amp-ad width=300 height=250
            type="adsense"
            data-ad-client="ca-pub-1689239266452655"
            data-ad-slot="9307455607"
            layout="responsive"
            >
        </amp-ad>
    </amp-sticky-ad>
    -->

</body>
</html>
