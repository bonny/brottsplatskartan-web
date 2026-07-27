<?php

namespace Tests\Unit;

use App\CrimeEvent;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CrimeEventViewPortSizeTest extends TestCase
{
    /**
     * Bygger ett event med given viewport-spännvidd. getViewportSize() räknar
     * (ne_lat - sw_lat) + (ne_lng - sw_lng), så spannet läggs på båda axlarna
     * och summan blir 2 × $span.
     */
    private function event(float $span): CrimeEvent
    {
        $event = new CrimeEvent();
        $event->viewport_southwest_lat = 59.0;
        $event->viewport_northeast_lat = 59.0 + $span;
        $event->viewport_southwest_lng = 18.0;
        $event->viewport_northeast_lng = 18.0 + $span;

        return $event;
    }

    /**
     * @return array<string, array{0: float, 1: string}>
     */
    public static function precisionProvider(): array
    {
        return [
            // Trösklarna i getViewPortSizeAsString(): >20 veryfar, >6 far,
            // >0.8 lan, >0.1 town, >0.05 street, annars closest.
            'veryfar' => [13.0, 'veryfar'],  // summa 26
            'far' => [4.0, 'far'],           // summa 8
            'lan' => [0.5, 'lan'],           // summa 1.0
            'town' => [0.1, 'town'],         // summa 0.2
            'street' => [0.04, 'street'],    // summa 0.08
            'closest' => [0.01, 'closest'],  // summa 0.02
        ];
    }

    #[DataProvider('precisionProvider')]
    public function test_spannvidd_ger_ratt_precision(float $span, string $forvantad): void
    {
        $this->assertSame($forvantad, $this->event($span)->getViewPortSizeAsString());
    }

    /**
     * Regressionstest för todo #91. `switch ($size)` jämförde $size mot
     * booleanen `$size > 20`, och PHP:s lösa jämförelse gör att `0 == false`
     * är sant — summan exakt 0 matchade därför första caset och gav
     * "veryfar" i stället för "closest". 2 165 events i prod träffades
     * (0,65 %), men inget av dem hade koordinat, så ingen felaktig kartbild
     * renderades. Rätt form är `switch (true)`.
     */
    public function test_viewport_summa_noll_ger_closest(): void
    {
        $this->assertSame('closest', $this->event(0.0)->getViewPortSizeAsString());
    }

    /**
     * Samma bugg, andra vägen in: ett event helt utan viewport-fält ger
     * också summan 0 och ska bli "closest", inte "veryfar".
     */
    public function test_event_utan_viewportfalt_ger_closest(): void
    {
        $this->assertSame('closest', (new CrimeEvent())->getViewPortSizeAsString());
    }

    public function test_negativ_summa_ger_closest(): void
    {
        // Trasig data (ne < sw) ska falla till den snävaste nivån, inte till
        // veryfar. Utan switch(true) gav -1 också en boolean-jämförelse.
        $this->assertSame('closest', $this->event(-0.5)->getViewPortSizeAsString());
    }
}
