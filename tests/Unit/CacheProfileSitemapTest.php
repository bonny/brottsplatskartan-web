<?php

namespace Tests\Unit;

use App\CacheProfiles\BrottsplatskartanCacheProfile;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Sitemaps cachas redan i Redis av sitemap:generate och servas därifrån
 * av SitemapController::serveCached(). Responscachen lagrade en andra
 * kopia av samma XML — den största posten var 26 MB.
 */
class CacheProfileSitemapTest extends TestCase
{
    private function shouldCache(string $uri): bool
    {
        return (new BrottsplatskartanCacheProfile())
            ->shouldCacheRequest(Request::create($uri, 'GET'));
    }

    public function test_sitemap_index_cachas_inte(): void
    {
        $this->assertFalse($this->shouldCache('/sitemap.xml'));
    }

    public function test_sitemap_main_cachas_inte(): void
    {
        $this->assertFalse($this->shouldCache('/sitemap-main.xml'));
    }

    public function test_events_sitemap_per_ar_cachas_inte(): void
    {
        $this->assertFalse($this->shouldCache('/sitemap-events-2026.xml'));
    }

    public function test_vanliga_sidor_cachas_fortfarande(): void
    {
        // Positiv kontroll: exkluderingen får inte bli för bred.
        $this->assertTrue($this->shouldCache('/'));
        $this->assertTrue($this->shouldCache('/lan/skane-lan'));
    }
}
