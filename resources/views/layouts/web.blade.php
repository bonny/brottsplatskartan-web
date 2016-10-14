<!DOCTYPE html>
<html ⚡ lang="sv">
<head>
    <script async src="https://cdn.ampproject.org/v0.js"></script>
    <script async custom-element="amp-analytics" src="https://cdn.ampproject.org/v0/amp-analytics-0.1.js"></script>
    <script async custom-element="amp-ad" src="https://cdn.ampproject.org/v0/amp-ad-0.1.js"></script>
    <script async custom-element="amp-sticky-ad" src="https://cdn.ampproject.org/v0/amp-sticky-ad-0.1.js"></script>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,minimum-scale=1,initial-scale=1">
    <meta content="IE=Edge" http-equiv="X-UA-Compatible">
    <link rel="canonical" href="/">

    <!-- <meta property="og:description" content="">
    <meta name="description" content=""> -->

    <title>@yield('title') - Brottsplatskartan</title>

    <style amp-boilerplate>body{-webkit-animation:-amp-start 8s steps(1,end) 0s 1 normal both;-moz-animation:-amp-start 8s steps(1,end) 0s 1 normal both;-ms-animation:-amp-start 8s steps(1,end) 0s 1 normal both;animation:-amp-start 8s steps(1,end) 0s 1 normal both}@-webkit-keyframes -amp-start{from{visibility:hidden}to{visibility:visible}}@-moz-keyframes -amp-start{from{visibility:hidden}to{visibility:visible}}@-ms-keyframes -amp-start{from{visibility:hidden}to{visibility:visible}}@-o-keyframes -amp-start{from{visibility:hidden}to{visibility:visible}}@keyframes -amp-start{from{visibility:hidden}to{visibility:visible}}</style><noscript><style amp-boilerplate>body{-webkit-animation:none;-moz-animation:none;-ms-animation:none;animation:none}</style></noscript>
    <style amp-custom>
        html, body {
            background: white;
            font-family: -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Oxygen-Sans,Ubuntu,Cantarell,"Helvetica Neue",sans-serif;
            font-size: 16px;
            line-height: 1.4;
        }

        body {
            padding-top: 80px;
        }

        h1, h2, h3, h4, p, ul, ol {
            margin-top: .25rem;
            margin-bottom: .25rem;
        }

        .container {
            box-sizing: border-box;
            margin: 0 auto;
            max-width: 1000px;
            padding: 0 20px;
        }

        .SiteHeader {
            background: #fff;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 5;
            padding: 1em;
            box-shadow: 2px 1px 1px rgba(0,0,0,0.15);
            font-size: .75rem;
        }

        .SiteTitle {
            margin: 0;
            line-height: 1;
            text-transform: uppercase;
        }
        .SiteTagline {
            margin-top: .5em;
            margin-bottom: 0;
        }
        .SiteTitle a {
            text-decoration: none;
            color: inherit;
        }

        .Event {
            margin-top: 2rem;
            margin-bottom: 2rem;
        }

        .Event__title {
            line-height: 1;
        }

        .Event__teaser {
            font-weight: bold;
        }
        .Event__mapImage {
        }

        .pagination {
            text-align: center;
            width: 100%;
            line-height: 1;
        }
        .pagination li {
            display: inline-block;
        }

        .pagination li > a,
        .pagination li > span {
            display: block;
            padding: .25em;
        }
        .pagination li > span {
            font-weight: bold;
        }
        .pagination li > a:hover {
            background: #ccc;
        }
    </style>
</head>
<body>

    <div class="container">

        @yield('content')

    </container>

</body>
</html>
