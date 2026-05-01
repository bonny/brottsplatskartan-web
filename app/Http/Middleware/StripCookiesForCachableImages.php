<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Tar bort alla Set-Cookie-headers från response för cachable image-routes
 * (`/k/v1/*`). Defense-in-depth utöver routens `withoutMiddleware`-skipp:
 * fångar cookies som framtida paket skulle kunna injicera via middleware
 * — t.ex. analytics-paket, A/B-test-paket eller liknande.
 *
 * Måste ligga FÖRST i web-gruppen så att den kör SIST på response —
 * efter alla andra mw fått chans att lägga till cookies.
 *
 * Begränsning: cookies som sätts via Laravels `RequestHandled`-event
 * (Debugbar i dev) hamnar på response EFTER middleware-stacken är klar
 * och fångas INTE här. I prod är Debugbar inaktiv, så det spelar ingen
 * roll. Verifierat 2026-05-01: prod-svaret innehåller noll Set-Cookie.
 *
 * Inspirerat av Aaron Francis' StripImageTransformCookies-mönster:
 * https://aaronfrancis.com/2025/a-cookieless-cache-friendly-image-proxy-in-laravel-inspired-by-cloudflare-9e95f7e0
 */
class StripCookiesForCachableImages
{
    public function handle(Request $request, Closure $next): SymfonyResponse
    {
        /** @var SymfonyResponse $response */
        $response = $next($request);

        if ($request->is('k/v1/*')) {
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
