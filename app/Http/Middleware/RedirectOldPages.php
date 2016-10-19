<?php

namespace App\Http\Middleware;

use Closure;

class RedirectOldPages
{
    /**
     * Handle an incoming request;
     * check for old page/url and redirect
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {

        $response = $next($request);

        if ( 404 == $response->status() ) {

            // for example 'http://brottsplatskartan.dev/lan/gotlands-lan'
            $url = $request->Url();

            // for example 'lan/gotlands-lan'
            $path = $request->path();

            // redirect gamla län-urls, slutar med 'lan/gotlands-lan'
            // hel url är t.ex. 'www.brottsplatskartan.se/lan/stockholms-lan'
            if (starts_with($path, "lan/") && ends_with($path, "-lan")) {

                // gammal län-url

                // oldLan = 'gotlands-lan'
                $oldLan = str_replace("lan/", "", $path);

                $arrOldToNewLan = [
                    'blekinge-lan' => 'Blekinge län',
                    'dalarnas-lan' => 'Dalarnas län',
                    'gotlands-lan' => 'Gotlands län',
                    'gavleborgs-lan' => 'Gävleborgs län',
                    'hallands-lan' => 'Hallands län',
                    'jamtlands-lan' => 'Jämtlands län',
                    'jonkopings-lan' => 'Jönköpings län',
                    'kalmar-lan' => 'Kalmar län',
                    'kronobergs-lan' => 'Kronobergs län',
                    'norrbottens-lan' => 'Norrbottens län',
                    'skane-lan' => 'Skåne län',
                    'stockholms-lan' => 'Stockholms län',
                    'sodermanlands-lan' => 'Södermanlands län',
                    'uppsala-lan' => 'Uppsala län',
                    'varmlands-lan' => 'Värmlands län',
                    'vasterbottens-lan' => 'Västerbottens län',
                    'vasternorrlands-lan' => 'Västernorrlands län',
                    'vastmanlands-lan' => 'Västmanlands län',
                    'vastra-gotalands-lan' => 'Västra Götalands län ',
                    'orebro-lan' => 'Örebro län',
                    'ostergotlands-lan' => 'Östergötlands län',
                ];

                #echo "gammalt län '$oldLan'";
                #echo "redirect to: " . $arrOldToNewLan[$oldLan];

                return redirect(route("lanSingle", ["lan" => $arrOldToNewLan[$oldLan]]), 301);

            } else {

                // echo "<br>404 found for<br>url $url<br>$path";

            }

            #return redirect("/lan/Uppsala län/");

        }

        return $response;
    }
}
