<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sätter X-Robots-Tag: noindex, follow när requesten har en ?t=-parameter.
 *
 * Bakgrund: en gammal "↻ Uppdatera"-länk genererade URLer som
 * /?t=17768469041346097384 för att busta response-cachen. Googlebot
 * indexerade dessa varianter trots rel="nofollow" + canonical. Länken
 * är borttagen, men befintliga indexerade URLer behöver en stark
 * avindexerings-signal.
 */
class NoIndexCacheBuster {
    public function handle(Request $request, Closure $next): Response {
        $response = $next($request);

        if ($request->query->has('t')) {
            $response->headers->set('X-Robots-Tag', 'noindex, follow');
        }

        return $response;
    }
}
