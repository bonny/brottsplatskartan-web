<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,minimum-scale=1,initial-scale=1">
    <title>{{$s}} – söker efter händelser från Polisen...</title>
    <style>
        html, body {
            background: white;
            font-family: -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Oxygen-Sans,Ubuntu,Cantarell,"Helvetica Neue",sans-serif;
            font-weight: normal;
            font-size: 16px;
            line-height: 1.4;
            text-align: center;
        }
        p {
            margin-top: 10%;
        }
    </style>
</head>
<body>

    <p>Vänta, söker efter <em>{{$s}}</em> ...</p>

    <script>

    let redirecToUrl = @json($redirectToUrl);

    (function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
    (i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
    m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
    })(window,document,'script','https://www.google-analytics.com/analytics.js','ga');

    ga('create', 'UA-181460-13', 'auto');
    ga('send', 'pageview', {
        hitCallback: function() {
            document.location = redirecToUrl;
        }
    });
    </script>
</body>
