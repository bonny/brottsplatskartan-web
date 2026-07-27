<?php

namespace Tests\Unit;

use App\CrimeEvent;
use Tests\TestCase;

class CrimeEventIconGroupTest extends TestCase
{
    private function grupp(?string $parsedTitle): string
    {
        $event = new CrimeEvent();
        $event->parsed_title = $parsedTitle;

        return $event->getIconGroup();
    }

    /**
     * @return array<string, array{0: ?string, 1: string}>
     */
    public static function kategoriProvider(): array
    {
        return [
            // Trafik — 34 % av volymen
            'trafikolycka'            => ['Trafikolycka', 'trafik'],
            'trafikolycka personskada' => ['Trafikolycka,  personskada', 'trafik'],
            'trafikkontroll'          => ['Trafikkontroll', 'trafik'],
            'trafikbrott'             => ['Trafikbrott', 'trafik'],

            // Fallgrop: "Rattfylleri" innehåller "fylleri" (→ person)
            'rattfylleri blir trafik' => ['Rattfylleri', 'trafik'],

            // Sammanfattning — 22 %
            'sammanfattning natt'     => ['Sammanfattning natt', 'sammanfattning'],
            'sammanfattning kvall'    => ['Sammanfattning kväll och natt', 'sammanfattning'],

            // Brand — 7 %
            'brand'                   => ['Brand', 'brand'],

            // Fallgrop: "Mordbrand" innehåller "mord" (→ vald)
            'mordbrand blir brand'    => ['Mordbrand', 'brand'],

            // Vald — 9 %
            'misshandel'              => ['Misshandel', 'vald'],
            'ran'                     => ['Rån', 'vald'],
            'mord utan brand'         => ['Mord/dråp, försök', 'vald'],

            // Stold — 5 %
            'stold'                   => ['Stöld', 'stold'],
            'stold inbrott'           => ['Stöld/inbrott', 'stold'],
            'skadegorelse'            => ['Skadegörelse', 'stold'],

            // Person — 4 %
            'forsvunnen person'       => ['Försvunnen person', 'person'],
            'fylleri utan ratt'       => ['Fylleri', 'person'],

            // Olycka — 3 %
            'arbetsplatsolycka'       => ['Arbetsplatsolycka', 'olycka'],

            // Fallback — ~16 %
            'knivlagen'               => ['Knivlagen', 'ovrigt'],
            'vapenlagen'              => ['Vapenlagen', 'ovrigt'],
            'bedrageri'               => ['Bedrägeri', 'ovrigt'],
            'information'             => ['Information', 'ovrigt'],
            'null ger fallback'       => [null, 'ovrigt'],
            'tom strang ger fallback' => ['', 'ovrigt'],
        ];
    }

    /**
     * @dataProvider kategoriProvider
     */
    public function test_kategori_mappas_till_ratt_ikongrupp(?string $parsedTitle, string $forvantad): void
    {
        $this->assertSame($forvantad, $this->grupp($parsedTitle));
    }

    public function test_gruppen_ar_alltid_en_kand_grupp(): void
    {
        $kanda = ['trafik', 'sammanfattning', 'brand', 'vald', 'stold', 'person', 'olycka', 'ovrigt'];

        foreach (['Trafikolycka', 'Mordbrand', 'Rattfylleri', 'Knivlagen', 'Något Helt Nytt'] as $titel) {
            $this->assertContains($this->grupp($titel), $kanda, "Okänd grupp för: {$titel}");
        }
    }
}
