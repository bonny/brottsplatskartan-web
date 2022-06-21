<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use \Illuminate\Support\Str;

class RedirectIndexPhp
{
    /**
     * Av någon anledning har URLer liknande
     * https://brottsplatskartan.se/index.php/vma/19%20jun%202022-viktigt-meddelande-till-allmanheten-i-skara-i-skara-kommun-vastra-gotalands-lan-13
     * indexerats vilket är kasst.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $strRequestUri = Str::of($request->getRequestUri());
        $isIndexRequestUri = $strRequestUri->is('/index.php/*');
        if ($isIndexRequestUri) {
            $redirectToUri = $strRequestUri->replace('/index.php/', '/');
            return redirect($redirectToUri, 301);
        }
        return $next($request);
    }
}
