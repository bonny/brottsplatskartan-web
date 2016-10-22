<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Hämtar din position...</title>
    <style>
        html, body {
            background: white;
            font-family: -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Oxygen-Sans,Ubuntu,Cantarell,"Helvetica Neue",sans-serif;
            /*font-family: sans-serif;*/
            font-weight: normal;
            font-size: 16px;
            line-height: 1.4;
        }
    </style>
</head>
<body>

    <p>Vänta, hämtar din position ...</p>

    <?php

    /**
     * Page only here to get geolocation since AMP does not allow js
     */
    ?>

    <script>

    (function() {

        if (! "geolocation" in navigator) {
            console.log("no geolocation support found");
            return;
        }

        console.log("geolocation support found");

        navigator.geolocation.getCurrentPosition(function(position) {

            console.log("got geolocation position", position);

            do_something(position.coords.latitude, position.coords.longitude);

        }, function(err) {

            /*
            Some error examples:

                User denies location
                    {code: 1, message: "User denied Geolocation"}

                

            */
            console.log("did not get geolocation position", err);

        });

    })();

    </script>

</body>
