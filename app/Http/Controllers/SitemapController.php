<?php

namespace App\Http\Controllers;

use App\Console\Commands\GenerateSitemap;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

class SitemapController extends Controller
{
    /**
     * Servar en cachad sitemap-del från Redis. Om cachen är tom (ex.
     * efter Redis-restart) körs sitemap:generate synkront som fallback.
     */
    public static function serveCached(string $slug)
    {
        $cacheKey = GenerateSitemap::CACHE_PREFIX . $slug;
        $xml = Cache::get($cacheKey);
        if (!$xml) {
            Artisan::call('sitemap:generate');
            $xml = Cache::get($cacheKey);
        }
        if (!$xml) {
            abort(404, 'Sitemap saknas.');
        }
        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
    }

    /**
     * Servar events-sitemap per år. Aktuellt år från Redis, historiska
     * från storage/app/sitemaps/.
     */
    public static function serveEventsYear(int $year)
    {
        $currentYear = (int) now()->format('Y');

        if ($year === $currentYear) {
            return self::serveCached("events-{$year}");
        }

        $path = GenerateSitemap::historicalPath($year);
        if (!file_exists($path)) {
            abort(404, "Historisk sitemap för {$year} saknas. Kör `artisan sitemap:generate-historical --year={$year}`.");
        }
        return response(file_get_contents($path), 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
    }
}
