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

    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,minimum-scale=1,initial-scale=1">
    <meta content="IE=Edge" http-equiv="X-UA-Compatible">
    <link rel="canonical" href="@yield('canonicalLink', '/')">

    @hasSection('metaDescription')
        <meta property="og:description" content="@yield('metaDescription')">
        <meta name="description" content="@yield('metaDescription')">
        <meta name="twitter:description" content="@yield('metaDescription')">
    @endif

    @hasSection('metaImage')
        <meta property="og:image" content="@yield('metaImage')" />
        <meta name="twitter:image" content="@yield('metaImage')">
        <meta name="twitter:card" content="summary_large_image">
    @else
        <meta name="twitter:card" content="summary">
    @endif

    <meta property="og:site_name" content="Brottsplatskartan.se - brott på karta" />
    <meta property="fb:admins" content="685381489,523547944" />
    <meta property="og:locale" content="sv_SE" />
    <meta property="og:type" content="website" />
    <meta property="og:title" content="@yield('title')" />

    <meta name="twitter:site" content="@brottsplatser">
    <meta name="twitter:title" content="@yield('title')">

    <title>@yield('title') → Brottsplatskartan</title>

    <style amp-boilerplate>body{-webkit-animation:-amp-start 8s steps(1,end) 0s 1 normal both;-moz-animation:-amp-start 8s steps(1,end) 0s 1 normal both;-ms-animation:-amp-start 8s steps(1,end) 0s 1 normal both;animation:-amp-start 8s steps(1,end) 0s 1 normal both}@-webkit-keyframes -amp-start{from{visibility:hidden}to{visibility:visible}}@-moz-keyframes -amp-start{from{visibility:hidden}to{visibility:visible}}@-ms-keyframes -amp-start{from{visibility:hidden}to{visibility:visible}}@-o-keyframes -amp-start{from{visibility:hidden}to{visibility:visible}}@keyframes -amp-start{from{visibility:hidden}to{visibility:visible}}</style><noscript><style amp-boilerplate>body{-webkit-animation:none;-moz-animation:none;-ms-animation:none;animation:none}</style></noscript>
    <style amp-custom>@include('css-styles')</style>

</head>
<body>

    <div class="container">

        <header class="SiteHeader">
            <div class="SiteHeader__inner">
                <h1 class="SiteTitle"><a href="/">Brottsplatskartan.se</a></h1>
                <p class="SiteTagline"><em>Se på karta var brott sker</em></p>

                <form method="get" action="{{ route("search") }}" class="HeaderSearch">
                    <input type="text" name="s" value="" class="HeaderSearch__s" placeholder="Sök">
                    <button type="submit" class="HeaderSearch__submit">Sök</button>
                </form>

            </div>
        </header>

        @include('parts.breadcrumb', ["single" => true])

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
