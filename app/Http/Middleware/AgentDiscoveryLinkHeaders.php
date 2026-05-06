<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Lägger till `Link`-headers (RFC 8288) på startsidan så agent-scannrar
 * (isitagentready.com m.fl.) hittar våra discovery-resurser:
 *
 *   - /llms.txt          → rel="alternate" type="text/plain"
 *   - /.well-known/api-catalog → rel="api-catalog" (RFC 9727)
 *   - /docs/API.md (via GitHub) → rel="service-doc"
 *
 * Bara på startsidan (path "/") för att inte ladda alla svar med headers
 * — discovery sker via root.
 */
class AgentDiscoveryLinkHeaders
{
    public function handle(Request $request, Closure $next): SymfonyResponse
    {
        /** @var SymfonyResponse $response */
        $response = $next($request);

        if ($request->path() !== '/' && $request->path() !== '') {
            return $response;
        }

        $links = [
            '</llms.txt>; rel="alternate"; type="text/plain"; title="LLM-sajtguide"',
            '</.well-known/api-catalog>; rel="api-catalog"; type="application/linkset+json"',
            '<https://github.com/bonny/brottsplatskartan-web/blob/main/docs/API.md>; rel="service-doc"',
        ];

        foreach ($links as $link) {
            $response->headers->set('Link', $link, false);
        }

        return $response;
    }
}
