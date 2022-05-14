<?php
namespace App\Http\Middleware;

use Closure;
/**
 * Skicka vidare requests som har GET ?byt-sida= <url>.
 * Används av sidan intrbrott för att välja i dropdown.
 */
class RedirectBytSida
{
    /**
     * Handle an incoming request;
     * check for old page/url and redirect.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {

        $response = $next($request);

        if ($request->has('byt-sida')) {
            $sidaAttBytaTill = $request->get('byt-sida');
            return redirect($sidaAttBytaTill);
        }

        return $response;
    }
}
