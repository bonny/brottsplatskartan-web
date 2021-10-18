{{--

Layout template for web

--}}
<?php
// Visa inte annonser för besökare som kommer via Coyards.
// utm_source=coyards
$showAds = true;
$noAdsReason = '';
// if (request()->get('utm_source') === 'coyards') {
//     $showAds = false;
//     $noAdsReason .= ' sourceCoyards ';
// }

?>
<!DOCTYPE html>
<html ⚡ lang="sv" class="amp-border-box">
<head>
    <script async src="https://cdn.ampproject.org/v0.js"></script>
    <script async custom-element="amp-analytics" src="https://cdn.ampproject.org/v0/amp-analytics-0.1.js"></script>
    <script async custom-element="amp-sticky-ad" src="https://cdn.ampproject.org/v0/amp-sticky-ad-1.0.js"></script>
    <script async custom-element="amp-social-share" src="https://cdn.ampproject.org/v0/amp-social-share-0.1.js"></script>
    <script async custom-element="amp-form" src="https://cdn.ampproject.org/v0/amp-form-0.1.js"></script>
    <script async custom-element="amp-iframe" src="https://cdn.ampproject.org/v0/amp-iframe-0.1.js"></script>
    <script async custom-element="amp-install-serviceworker" src="https://cdn.ampproject.org/v0/amp-install-serviceworker-0.1.js"></script>
    <script async custom-element="amp-twitter" src="https://cdn.ampproject.org/v0/amp-twitter-0.1.js"></script>
    <script async custom-element="amp-facebook" src="https://cdn.ampproject.org/v0/amp-facebook-0.1.js"></script>
    <script async custom-element="amp-facebook-page" src="https://cdn.ampproject.org/v0/amp-facebook-page-0.1.js"></script>
    <script async custom-element="amp-carousel" src="https://cdn.ampproject.org/v0/amp-carousel-0.2.js"></script>
    <script async custom-element="amp-accordion" src="https://cdn.ampproject.org/v0/amp-accordion-0.1.js"></script>
    <script async custom-element="amp-sidebar" src="https://cdn.ampproject.org/v0/amp-sidebar-0.1.js"></script>
    <script async custom-element="amp-position-observer" src="https://cdn.ampproject.org/v0/amp-position-observer-0.1.js"></script>
    <script async custom-template="amp-mustache" src="https://cdn.ampproject.org/v0/amp-mustache-0.2.js"></script>
    <script async custom-element="amp-list" src="https://cdn.ampproject.org/v0/amp-list-0.1.js"></script>
    <script async custom-element="amp-animation" src="https://cdn.ampproject.org/v0/amp-animation-0.1.js"></script>
    <script async custom-element="amp-consent" src="https://cdn.ampproject.org/v0/amp-consent-0.1.js"></script>
    <meta name="amp-consent-blocking" content="amp-analytics,amp-ad,amp-auto-ads" />
    <?php
    if ($showAds) {
      ?>
      <script async custom-element="amp-ad" src="https://cdn.ampproject.org/v0/amp-ad-0.1.js"></script>
      <script async custom-element="amp-auto-ads" src="https://cdn.ampproject.org/v0/amp-auto-ads-0.1.js"></script>
      <?php
    }
    ?>
    
    @stack('scripts')

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

    <meta name="robots" content="max-image-preview:large" />

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

    <style amp-boilerplate>body{-webkit-animation:-amp-start 8s steps(1,end) 0s 1 normal both;-moz-animation:-amp-start 8s steps(1,end) 0s 1 normal both;-ms-animation:-amp-start 8s steps(1,end) 0s 1 normal both;animation:-amp-start 8s steps(1,end) 0s 1 normal both}@-webkit-keyframes -amp-start{from{visibility:hidden}to{visibility:visible}}@-moz-keyframes -amp-start{from{visibility:hidden}to{visibility:visible}}@-ms-keyframes -amp-start{from{visibility:hidden}to{visibility:visible}}@-o-keyframes -amp-start{from{visibility:hidden}to{visibility:visible}}@keyframes -amp-start{from{visibility:hidden}to{visibility:visible}}</style><noscript><style amp-boilerplate>body{-webkit-animation:none;-moz-animation:none;-ms-animation:none;animation:none}</style></noscript>
    <style amp-custom>{!! HTMLMin::css(file_get_contents( public_path("css/styles.css") )) !!}</style>

</head>
<body class="@if ($shared_notification_bar_contents) has-notification-bar @endif {{$noAdsReason}}">
    <?php
    if ($showAds) {
      ?>
      <amp-auto-ads type="adsense" data-ad-client="ca-pub-1689239266452655"></amp-auto-ads>
      <?php
    }
    ?>
  
    <amp-animation id="shrinkAnim" layout="nodisplay">
      <script type="application/json">
        {
          "duration": "250ms",
          "easing": "ease-in-out",
          "fill": "both",
          "iterations": "1",
          "direction": "alternate",
          "animations": [{
              "selector": "#SiteHeader",
              "keyframes": [{
                "transform": "translateY(-4rem)"
              }]
            },
            {
              "selector": ".SiteTitle",
              "keyframes": [{
                "transform": "translateY(16px) scale(0.75)"
              }]
            }
          ]
        }
      </script>
    </amp-animation>
    <amp-animation id="expandAnim" layout="nodisplay">
      <script type="application/json">
        {
          "duration": "250ms",
          "easing": "ease-out",
          "fill": "both",
          "iterations": "1",
          "direction": "alternate",
          "animations": [{
              "selector": "#SiteHeader",
              "keyframes": [{
                "transform": "translateY(0)"
              }]
            },
            {
              "selector": ".SiteTitle",
              "keyframes": [{
                "transform": "translateY(0) scale(1)"
              }]
            }
          ]
        }
      </script>
    </amp-animation>

    <div class="container">

        @include('parts.notificationbar')
        @include('parts.siteheader')

        <div id="HeaderAnimationMarker">
            <amp-position-observer on="enter:expandAnim.start; exit:shrinkAnim.start;" layout="nodisplay"></amp-position-observer>
        </div>

        @if ($showAds)
          <div class="Ad">
              {{-- <div class="Ad__intro">Annons</div>
              <amp-ad width=320 height=100
                  type="adsense"
                  data-ad-client="ca-pub-1689239266452655"
                  data-ad-slot="9307455607"
                  layout="responsive"
                  >
                  <div overflow></div>
              </amp-ad> --}}
              <amp-ad width="100vw" height=320
                  type="adsense"
                  data-ad-client="ca-pub-1689239266452655"
                  data-ad-slot="9307455607"
                  data-auto-format="rspv"
                  data-full-width>
                <div overflow></div>
              </amp-ad>
         </div>
        @endif

        @yield('beforeBreadcrumb')
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

        @yield('beforeMainContent')

        <main class="MainContent">

            @yield('content')

            {{-- <div class="Ad">
                <div class="Ad__intro">Annons</div>
                <amp-ad width=320 height=50
                    type="adsense"
                    data-ad-client="ca-pub-1689239266452655"
                    data-ad-slot="7743150002"
                    layout="responsive"
                    >
                </amp-ad>
            </div> --}}

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

    @include('parts.sidebar')

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

    {{-- <amp-sticky-ad layout="nodisplay">
        <amp-ad width=320 height=50
            type="adsense"
            data-ad-client="ca-pub-1689239266452655"
            data-ad-slot="5942966405"
            >
        </amp-ad>
    </amp-sticky-ad> --}}

    @if (env('APP_ENV')=='production')
        <amp-install-serviceworker
          src="https://brottsplatskartan.se/serviceworker.js"
          layout="nodisplay">
        </amp-install-serviceworker>
    @endif

</body>
</html>
