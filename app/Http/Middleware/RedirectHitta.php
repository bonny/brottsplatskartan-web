<?php

namespace App\Http\Middleware;

use Closure;

class RedirectHitta
{
    /**
     * Skicka vidare besökare från Hitta.se till
     * lite vettigare sidor.
     *
    Exempel på referers från Hitta.se:

    array:4 [▼
        "scheme" => "https"
        "host" => "www.hitta.se"
        "path" => "/stockholms län/stockholm/mosebacke (område)/område/2001313196"
        "query" => "vad=Mosebacke (Område)"
        ]

    array:4 [▼
        "scheme" => "https"
        "host" => "www.hitta.se"
        "path" => "/stockholms län/stockholm/gamla stan (område)/område/2001310504"
        "query" => "vad=Gamla Stan (Område)"
    ]

    array:4 [▼
        "scheme" => "https"
        "host" => "www.hitta.se"
        "path" => "/västra götalands län/göteborg/göteborg (postort)/område/2000006865"
        "query" => "vad=Göteborg (Postort)"
    ]

    array:4 [▼
        "scheme" => "https"
        "host" => "www.hitta.se"
        "path" => "/uppsala län/björklinge/björklinge (postort)/område/2000006270"
        "query" => "vad=Björklinge (Postort)"
    ]

     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {

        $response = $next($request);

        $referer = $request->server('HTTP_REFERER');

        // Avbryt om ingen referer finns.
        if (!$referer) {
            return $response;
        }

        // Avbryt om referer inte är från hitta.se.
        if (!starts_with($referer, 'https://www.hitta.se')) {
            return $response;
        }

        // Avbryt om detta redan är en omredigering från den här mellanvaran.
        if ($request->get('hitta') === 'referer') {
            return $response;
        }

        $decodedReferer = urldecode($referer);
        $parsedRefererUrl = parse_url($decodedReferer);
        $refererPath = $parsedRefererUrl['path'];

        $refererPathCleaned = trim($refererPath, '/ ()');

        /*
        array:5 [▼
            0 => "stockholms län"
            1 => "stockholm"
            2 => "gamla stan (område)"
            3 => "område"
            4 => "2001310504"
        ]
        */
        $refererPathExploded = explode('/', $refererPathCleaned);
        $refererPathCounty = $refererPathExploded[0];
        $redirectToUrl = "/lan/{$refererPathCounty}/?hitta=referer";

        return redirect($redirectToUrl);
    }
}
