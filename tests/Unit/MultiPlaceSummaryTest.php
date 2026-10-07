<?php

namespace Tests\Unit;

use App\CrimeEvent;
use App\Services\StaticMapUrlBuilder;
use Tests\TestCase;

/**
 * Sammanfattningar ("Sammanfattning natt" m.fl.) nämner många platser i
 * länet men plottas på en samordningspunkt. De ska få en områdeskarta
 * utan prick istället för en cirkel (todo #78).
 */
class MultiPlaceSummaryTest extends TestCase
{
    private function event(string $title): CrimeEvent
    {
        return (new CrimeEvent())->forceFill([
            'id'             => 123,
            'parsed_title'   => $title,
            'location_lat'   => 57.3708434,
            'location_lng'   => 14.3439173,
            'viewport_northeast_lat' => 57.3708434,
            'viewport_northeast_lng' => 14.3439173,
            'viewport_southwest_lat' => 57.3708434,
            'viewport_southwest_lng' => 14.3439173,
        ]);
    }

    public function test_sammanfattningstitlar_detekteras(): void
    {
        // Alla varianter som förekommit på prod senaste året.
        foreach (['Sammanfattning natt', 'Sammanfattning kväll och natt', 'Sammanfattning helg', 'Sammanfattning eftermiddag'] as $title) {
            $this->assertTrue($this->event($title)->isMultiPlaceSummary(), $title);
        }
    }

    public function test_vanliga_handelser_detekteras_inte(): void
    {
        foreach (['Inbrott', 'Trafikolycka, personskada', 'Sammanfattningar', ''] as $title) {
            $this->assertFalse($this->event($title)->isMultiPlaceSummary(), $title);
        }
    }

    public function test_kort_url_byter_till_area_utom_for_far(): void
    {
        $event = $this->event('Sammanfattning natt');

        $this->assertSame('/k/v1/area-123-140x140.jpg', $event->getKortKartbildUrl('circle-low', 140, 140));
        $this->assertSame('/k/v1/area-123-617x463@2x.jpg', $event->getKortKartbildUrl('circle', 617, 463, 2));
        $this->assertSame('/k/v1/far-123-213x332.jpg', $event->getKortKartbildUrl('far', 213, 332));
        $this->assertSame('/k/v1/circle-123-617x463.jpg', $this->event('Inbrott')->getKortKartbildUrl('circle', 617, 463));
    }

    public function test_area_url_saknar_markering_och_zoomar_ut(): void
    {
        $url = (new StaticMapUrlBuilder())->areaUrl($this->event('Sammanfattning natt'), 140, 140, 2);

        $this->assertStringNotContainsString('path=', $url);
        $this->assertStringContainsString('/static/14.34392,57.37084,6.3/140x140@2x.jpg', $url);
    }
}
