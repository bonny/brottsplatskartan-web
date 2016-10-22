<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,minimum-scale=1,initial-scale=1">
    <title>Hämtar din position...</title>
    <style>
        html, body {
            background: white;
            font-family: -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Oxygen-Sans,Ubuntu,Cantarell,"Helvetica Neue",sans-serif;
            /*font-family: sans-serif;*/
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

    <p>Vänta, hämtar din position ...</p>

    <?php

    /**
     * Page only here to get geolocation since AMP does not allow js
     */
    ?>

    <script>

    (function() {

        if (! "geolocation" in navigator) {
            // console.log("no geolocation support found");

            var url = "/nara?error=1";
            document.location = url;

            return;
        }

        console.log("geolocation support found");

        navigator.geolocation.getCurrentPosition(function(position) {

            //  Coordinates { latitude: 59.3162378, longitude: 18.0840469, altitude: 0, accuracy: 20, altitudeAccuracy: 0, heading: NaN, speed: NaN }
            // console.log("got geolocation position", position.coords);

            var url = "/nara?lat=" + position.coords.latitude + "&lng=" + position.coords.longitude;
            document.location = url;

        }, function(err) {

            /*
            Some error examples:

                User denies location
                    {code: 1, message: "User denied Geolocation"}

                User appoves but get failed anyway
                    { code: 2, message: "Network location provider at 'https://www.googleapis.com/' : Returned error code 400."}

            */
            // console.log("did not get geolocation position", err);

            var url = "/nara?error=1";
            document.location = url;

        });

    })();

    </script>

</body>
