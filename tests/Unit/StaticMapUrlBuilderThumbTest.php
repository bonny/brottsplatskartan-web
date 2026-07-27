<?php

namespace Tests\Unit;

use App\CrimeEvent;
use App\Services\StaticMapUrlBuilder;
use Tests\TestCase;

class StaticMapUrlBuilderThumbTest extends TestCase
{
    private StaticMapUrlBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = new StaticMapUrlBuilder();
    }

    public function test_lan_radie_kapas_till_takvardet(): void
    {
        // lan är 5 000 m i PRECISION_RADIUS — ska kapas till 1 500.
        $this->assertSame(1500, $this->builder->thumbRadius(5000));
    }

    public function test_mindre_radie_lamnas_orord(): void
    {
        // closest (150), street (400) och town (1 500) ligger under taket.
        $this->assertSame(150, $this->builder->thumbRadius(150));
        $this->assertSame(400, $this->builder->thumbRadius(400));
        $this->assertSame(1500, $this->builder->thumbRadius(1500));
    }

    public function test_saknad_radie_far_takvardet(): void
    {
        // far/veryfar saknar radie helt — ska få taket istället för att
        // falla igenom till closeUpUrl() och dess rektangel.
        $this->assertSame(1500, $this->builder->thumbRadius(null));
    }

    public function test_thumbnail_far_hogre_padding(): void
    {
        $url = $this->builder->circleUrl($this->event(), 140, 140, 1, 'low');

        $this->assertStringContainsString('padding=0.6', $url);
        $this->assertStringNotContainsString('padding=0.35', $url);
    }

    public function test_storbild_behaller_ursprunglig_padding(): void
    {
        $url = $this->builder->circleUrl($this->event(), 617, 463, 1, 'high');

        $this->assertStringContainsString('padding=0.35', $url);
    }

    public function test_thumbnail_ritar_cirkel_for_veryfar(): void
    {
        // veryfar saknar radie i PRECISION_RADIUS. Förr gav det closeUpUrl()
        // (rektangel); nu ska det bli en cirkel-URL med path-parametrar.
        $url = $this->builder->circleUrl($this->event('veryfar'), 140, 140, 1, 'low');

        $this->assertStringContainsString('path=', $url);
        $this->assertStringContainsString('static/auto/140x140.jpg', $url);
        $this->assertStringContainsString('padding=0.6', $url);
    }

    public function test_precisionsfixturerna_ger_forvantad_niva(): void
    {
        // Skyddsnät: om getViewportSize()-trösklarna ändras ska det här
        // testet falla, inte de andra på ett förvirrande sätt.
        $this->assertSame('closest', $this->event('closest')->getViewPortSizeAsString());
        $this->assertSame('street', $this->event('street')->getViewPortSizeAsString());
        $this->assertSame('town', $this->event('town')->getViewPortSizeAsString());
        $this->assertSame('lan', $this->event('lan')->getViewPortSizeAsString());
        $this->assertSame('veryfar', $this->event('veryfar')->getViewPortSizeAsString());
    }

    /**
     * Precisionen räknas ut av getViewportSize() som ren aritmetik på
     * viewport-fälten: (ne_lat - sw_lat) + (ne_lng - sw_lng). Trösklarna i
     * getViewPortSizeAsString() är >20 veryfar, >6 far, >0.8 lan, >0.1 town,
     * >0.05 street, annars closest.
     *
     * OBS: spannet för 'closest' måste vara skilt från noll. Summan exakt 0
     * ger "veryfar" på grund av en bugg i getViewPortSizeAsString() —
     * `switch ($size)` jämför $size mot booleanen `$size > 20`, och PHP:s
     * lösa jämförelse gör att `0 == false` är sant. Buggen är latent i prod
     * (2 165 events träffas men ingen av dem har koordinat, så ingen
     * kartbild renderas) och spåras i todo #91, inte här.
     */
    private function event(string $precision = 'closest'): CrimeEvent
    {
        $span = match ($precision) {
            'veryfar' => 13.0,  // summa 26
            'far'     => 4.0,   // summa 8
            'lan'     => 0.5,   // summa 1.0
            'town'    => 0.1,   // summa 0.2
            'street'  => 0.04,  // summa 0.08
            'closest' => 0.01,  // summa 0.02 — se kommentaren ovan, inte 0
        };

        $event = new CrimeEvent();
        $event->id = 123456;
        $event->location_lat = 59.3293;
        $event->location_lng = 18.0686;
        $event->viewport_southwest_lat = 59.3293 - $span / 2;
        $event->viewport_northeast_lat = 59.3293 + $span / 2;
        $event->viewport_southwest_lng = 18.0686 - $span / 2;
        $event->viewport_northeast_lng = 18.0686 + $span / 2;

        return $event;
    }
}
