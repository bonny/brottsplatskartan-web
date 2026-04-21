<?php

namespace App\Http\Middleware;

use App\CacheProfiles\BrottsplatskartanCacheProfile;
use Closure;
use Illuminate\Http\Request;
use Spatie\ResponseCache\Attributes\NoCache;
use Spatie\ResponseCache\Middlewares\FlexibleCacheResponse as SpatieFlexibleCacheResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applicerar Spatie Response Cache 8:s flexible/SWR på alla requests
 * som normalt skulle cachas, med lifetime och grace läst från vår
 * cacheprofil. Faller tillbaka till vanlig sync-cache när
 * profilen rapporterar grace=0 (t.ex. /vma) — då serveras färska
 * svar alltid inom TTL utan SWR-overhead.
 */
class FlexibleCacheResponse extends SpatieFlexibleCacheResponse
{
    public function handle(Request $request, Closure $next, ...$args): Response
    {
        $attribute = $this->getAttributeFromRequest($request);

        if ($attribute instanceof NoCache
            || ! $this->responseCache->enabled($request)
            || $this->responseCache->shouldBypass($request)
        ) {
            return $next($request);
        }

        // shouldCacheRequest styr vilka requests som alls ska cachas.
        $profile = app(BrottsplatskartanCacheProfile::class);
        if (! $profile->shouldCacheRequest($request)) {
            return $next($request);
        }

        $lifetime = $profile->cacheLifetimeInSeconds($request);
        $grace = $profile->graceInSeconds($request);

        if ($grace <= 0) {
            // Ingen SWR — delegera till sync CacheResponse-beteendet via föräldern.
            return (new \Spatie\ResponseCache\Middlewares\CacheResponse($this->responseCache))
                ->handle($request, $next);
        }

        return $this->handleFlexibleCache($request, $next, [$lifetime, $grace], tags: []);
    }
}
