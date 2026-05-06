<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Tar bort Set-Cookie-headers från svaret för agent-discovery-routes
 * (sitemap, .well-known/*, llms.txt). Dessa ska vara cache-vänliga och
 * sessions-fria — externa scannrar (t.ex. isitagentready.com) avvisar
 * sitemap som "not found" när Laravel-sessionen sätter en `laravel_session`-
 * cookie, och cachande proxies (Caddy/Cloudflare) bypassar cache när
 * Set-Cookie finns på svaret.
 *
 * Måste ligga FÖRST i web-gruppen så den kör SIST på response — efter
 * StartSession + AddQueuedCookiesToResponse hunnit lägga till cookies.
 *
 * Samma mönster som StripCookiesForCachableImages (för /k/v1/*).
 */
class StripCookiesForAgentDiscovery
{
    public function handle(Request $request, Closure $next): SymfonyResponse
    {
        /** @var SymfonyResponse $response */
        $response = $next($request);

        $matches = $request->is(
            'sitemap.xml',
            'sitemap-*.xml',
            '.well-known/*',
            'llms.txt',
        );

        if ($matches) {
            $response->headers->remove('Set-Cookie');
            foreach ($response->headers->getCookies() as $cookie) {
                $response->headers->removeCookie(
                    $cookie->getName(),
                    $cookie->getPath(),
                    $cookie->getDomain()
                );
            }
        }

        return $response;
    }
}
