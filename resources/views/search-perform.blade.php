<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,minimum-scale=1,initial-scale=1">
    <title>{{ $s }} – söker efter händelser från Polisen...</title>
    <style>
        html,
        body {
            background: white;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
            font-weight: normal;
            font-size: 16px;
            line-height: 1.4;
            text-align: center;
        }

        p {
            margin-top: 10%;
        }
    </style>

    <!-- Google tag (gtag.js) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-L1WVBJ39GH"></script>
    <script>
        window.dataLayer = window.dataLayer || [];

        function gtag() {
            dataLayer.push(arguments);
        }

        gtag('js', new Date());

        gtag(
            'event',
            'page_view', 
            {
                'event_callback': () => {
                    setTimeout(() => {
                        document.location = @json($redirectToUrl);
                    }, 500);
                }
            }
        );
    </script>
</head>

<body>
    <p>Vänta, söker efter <em>{{ $s }}</em> ...</p>
</body>
