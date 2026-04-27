<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 301:ar äldre URL-mönster för Tier 1-städer till deras dedikerade
 * /<stad>-sidor (CityController).
 *
 * Konsoliderar SEO-värde — Google rankade tidigare flera versioner
 * separat (`/plats/Malmö` + `/plats/malmö` + `/plats/malmo`) av
 * samma stad.
 */
class CityRedirectMiddleware
{
    /**
     * Mönster → city-slug. Mönster matchas case-insensitive mot
     * `request->path()` (utan inledande `/`). Slug:en är target,
     * dvs `route('city', ['city' => $slug])`.
     */
    private const REDIRECTS = [
        // Stockholm — gamla URL:er och varianter
        'plats/stockholm' => 'stockholm',
        'plats/stockholms-län' => 'stockholm',
        'plats/stockholm-stockholms-län' => 'stockholm',
        'plats/stockholms-län-stockholms-län' => 'stockholm',
        'plats/södra-stockholm-stockholms-län' => 'stockholm',
        'plats/stockholm-city' => 'stockholm',
        'sida/stockholm' => 'stockholm',
        'lan/stockholm' => 'stockholm',
        'lan/stockholms-lan' => 'stockholm',
        'lan/Stockholms län' => 'stockholm',
        'lan/stockholms%20lan' => 'stockholm',
        'lan/Stockholm%20County' => 'stockholm',

        // Malmö
        'plats/malmö' => 'malmo',
        'plats/malmo' => 'malmo',
        'plats/malmö-skåne-län' => 'malmo',
        'plats/malmo-skane-lan' => 'malmo',

        // Göteborg
        'plats/göteborg' => 'goteborg',
        'plats/goteborg' => 'goteborg',
        'plats/göteborg-västra-götalands-län' => 'goteborg',
        'plats/goteborg-vastra-gotalands-lan' => 'goteborg',

        // Helsingborg
        'plats/helsingborg' => 'helsingborg',
        'plats/helsingborg-skåne-län' => 'helsingborg',
        'plats/helsingborg-skane-lan' => 'helsingborg',

        // Uppsala
        'plats/uppsala' => 'uppsala',
        'plats/uppsala-uppsala-län' => 'uppsala',
        'plats/uppsala-uppsala-lan' => 'uppsala',
        // Uppsala län (todo #35) — län-sidan dominaras av Uppsala stad
        // (~43 % av läns-invånare). Konsolidera SEO-equity till /uppsala
        // efter samma mönster som Stockholm.
        'lan/uppsala' => 'uppsala',
        'lan/uppsala-lan' => 'uppsala',
        'lan/Uppsala län' => 'uppsala',
        'lan/uppsala%20lan' => 'uppsala',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $path = urldecode($request->path());

        // todo #33: Tier 1-månadsvyer 301:as från /plats/{tier1}/handelser/{year}/{month}
        // till /{tier1}/handelser/{year}/{month} så URL-equity konsolideras
        // under stadens primära namespace.
        if (preg_match('#^plats/([^/]+)/handelser/(\d{4})/(\d{2})$#i', $path, $matches)) {
            $platsSlug = mb_strtolower($matches[1]);
            foreach (self::REDIRECTS as $pattern => $citySlug) {
                $platsPattern = preg_replace('#^plats/#i', '', $pattern);
                if ($platsPattern === $platsSlug) {
                    return redirect()->route('cityMonth', [
                        'city' => $citySlug,
                        'year' => $matches[2],
                        'month' => $matches[3],
                    ], 301);
                }
            }
            // Inte Tier 1 — släpp igenom till PlatsController::month().
            return $next($request);
        }

        // Övriga datum-routes (dagsvyer, gamla format) släpps igenom utan
        // redirect. Renderas av PlatsController::day() (todo #25/#29).
        if (preg_match('#^plats/[^/]+/handelser/#i', $path)) {
            return $next($request);
        }

        foreach (self::REDIRECTS as $pattern => $citySlug) {
            if (stripos($path, $pattern) === 0) {
                return redirect()->route('city', ['city' => $citySlug], 301);
            }
        }

        return $next($request);
    }
}
