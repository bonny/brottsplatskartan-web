<?php

namespace Tests\Unit;

use App\CrimeEvent;
use App\Services\ContentFilterService;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Alla texter nedan är hämtade ordagrant från prod (365 dagars mätning
 * 2026-07-29). Event-ID:t står i nyckeln så fallen går att slå upp igen.
 *
 * Poängen med filtret: pressfrasen ("regionalt mediecenter", "RMC",
 * media.x@polisen.se) förekommer BÅDE i rena press-administrativa
 * meddelanden och som fotnot i riktiga händelser. Det som skiljer dem åt
 * är var frasen står och hur mycket text som finns runt den.
 */
class ContentFilterServiceTest extends TestCase
{
    private function ärMediecenterInfo(string $parsedContent): bool
    {
        $event = new CrimeEvent();
        $event->parsed_content = $parsedContent;

        return (new ContentFilterService())->isMediaCenterInfo($event);
    }

    private function filtrerasHändelse(CrimeEvent $event): bool
    {
        return (new ContentFilterService())->isMediaCenterInfo($event);
    }

    /**
     * Rena press-administrativa meddelanden — ingen händelse, bara
     * mediecentrets öppettider. Ska döljas.
     *
     * @return array<string, array{0: string}>
     */
    public static function mediecenterInfoProvider(): array
    {
        return [
            '506947 RMC stängt' => [
                'Idag, onsdag den 29 juli, är RMC stängt. Det går alltid att mejla '
                . 'till media.bergslagen@polisen.se vars inflöde vi hanterar i mån av tid.',
            ],

            '506034 mediecentrets standardtext' => [
                'Regionalt mediecenter som normalt nås på 010-567 05 56 hanterar '
                . 'vanligtvis utredningsfrågor, dygnslistor samt frågor som ej rör '
                . 'operativa pågående händelser. Vår mejl media.bergslagen@polisen.se '
                . 'bevakas i mån av tid.  Numret för pågående operativa händelser som '
                . 'nås på 010-569 46 00 bemannas som vanligt.',
            ],

            '503096 hänvisning till mejladress' => [
                'Frågor eller ärenden hänvisas till media.vast@polisen.se, där vi '
                . 'svarar i mån av tid under dagen. Vårt pressnummer 01056-90700 är '
                . 'dock öppet som vanligt för pågående ärenden.',
            ],

            '501740 enradare' => [
                'Frågor hänvisas till media.vast@polisen.se.',
            ],

            // 554 tecken — längsta rena skräpet vi hittat. Ligger strax under
            // 600-gränsen, så gränsen får inte sänkas utan att detta släpps ut.
            '502407 förändrade öppettider' => [
                'Under måndag till onsdag har det regionala mediecentret förändrade '
                . 'öppettider på grund av personalbrist. Det regionala mediecentret '
                . 'hanterar äldre ärenden, frågor om vem som är förundersökningsledare, '
                . 'inkomna anmälningar, övriga frågor och ej akuta, pågående händelser.'
                . 'Medieförfrågningar av brådskande dignitet hänvisas till '
                . 'media.bergslagen@polisen.se så kommer vi svara i mån av tid.  '
                . 'Följande öppettider gäller:Måndag: 10.00 till 12.00 Tisdag: STÄNGT'
                . 'Onsdag: 09.00-11.30 Det operativa numret för pågående händelser är '
                . 'öppet som vanligt (010-569 46 00).',
            ],

            // Felmärkt av polisen som "Sammanfattning natt" men innehåller
            // ingen händelse — därför filtrerar vi på innehåll, inte på titel.
            '387564 press-admin märkt som sammanfattning' => [
                'Torsdag 9 oktober är vi på konferens och svarar därför inte i vår '
                . 'medietelefon, vi hänvisar istället frågor till vår e-postlåda '
                . 'media.vast@polisen.se. Frågor om pågående händelser besvaras av '
                . 'presstalesperson på regionledningscentralen precis som vanligt.  '
                . 'Händelser; Det har varit en lugn natt i Halland och inga händelser '
                . 'finns att förmedla i detta forum.',
            ],

            // Ingen händelse, ingen brottsplats — bara vem media får ringa.
            // Pressfrasen står på offset 249, alltså precis i den zon där
            // gränsen måste ligga rätt.
            '364142 pressavisering utan händelse' => [
                'Klockan 13 onsdag 10/september kommer biträdande '
                . 'lokalpolisområdeschef Linköping Jonna Hedbrant att finnas '
                . 'tillgänglig för intervjuer i Skäggetorp. Specifika frågor angående '
                . 'utredningen besvaras av åklagare som leder förundersökningen. '
                . 'Kontakt till regionalt mediacenter: media.ost@polisen.se eller '
                . '010-5667880',
            ],

            'html strippas innan mätning' => [
                '<p>Idag, tisdag den 7 april, stänger RMC klockan 14.00.</p>'
                . '<p>Det går alltid bra att mejla till media.bergslagen@polisen.se</p>',
            ],
        ];
    }

    #[DataProvider('mediecenterInfoProvider')]
    public function testMediecenterInfoFiltreras(string $parsedContent): void
    {
        $this->assertTrue($this->ärMediecenterInfo($parsedContent));
    }

    /**
     * Riktiga händelser som nämner mediecentret som fotnot. Får ALDRIG döljas.
     *
     * @return array<string, array{0: string}>
     */
    public static function riktigHändelseProvider(): array
    {
        return [
            // 508 tecken totalt, pressfrasen på offset 483 — den enda anledningen
            // till att positionsvillkoret finns. Enbart längdgräns dolde denna.
            '499648 misshandel och våldsamt upplopp' => [
                'När personalen ska ingripa möts de av motstånd från andra på den '
                . 'aktuella sektionen. Misshandeln som skulle beivras upphörde dock '
                . 'och det finns inga rapporter om att någon kommit till allvarlig '
                . 'skada. I samband med händelsen beslutade arrangören om ett kort '
                . 'matchavbrott, men efter ett samverkansmöte kunde matchen återupptas '
                . 'och slutföras i god ordning. En anmälan om misshandel och våldsamt '
                . 'upplopp kommer att upprättas. Polisinsatschef Martin Arkdal Thorell '
                . 'finns tillgänglig för mediafrågor t o m kl. 22.',
            ],

            '341738 mordet vid moskén i Örebro' => [
                'Vid 13.45 inkommer samtal om ett misstänkt grovt våldsbrott i '
                . 'anslutning till en moské i Örebro. Flera räddningsinstanser samt '
                . 'polis befinner sig på platsen.Allmänheten ombeds att inte ta sig mot '
                . 'platsen och respektera de avspärrningar som råder på platsen. '
                . 'Uppdatering 14.20 Det finns skadade personer men vi har i nuläget '
                . 'ingen information om skadeläge. Vi uppmanar allmänhet att INTE närma '
                . 'sig platsen. Uppdatering 14.35 Polisen har inlett förundersökning '
                . 'gällande försök till mord. Två personer har skadats i samband med '
                . 'skottlossning. Uppdatering 16.08 Med anledning av händelsen håller '
                . 'polisen en pressträff i polishuset i Örebro klockan 18.00. Anmälan '
                . 'sker på mailadress media.bergslagen@polisen.se Uppdatering 17.30 En '
                . 'man i tjugofemårsåldern har avlidit av de skador han ådragit sig.',
            ],

            '477790 grova rånen i Huskvarna' => [
                'Två personer greps på tisdagsmorgonen misstänkta för delaktighet i de '
                . 'grova rån som ägt rum i Huskvarna.  En person i 20-årsåldern har '
                . 'anhållits av åklagare på sannolika skäl misstänkt för två fall av '
                . 'grovt rån, försök till grov våldtäkt och två fall av försök till '
                . 'grovt rån. En person under 18 år har anhållits på skälig misstanke '
                . 'för samma brott. Brotten har riktats mot äldre personer, samtliga '
                . 'över 80 år, i Huskvarna under perioden 29 december till 11 januari. '
                . 'För media: Frågor om förundersökningen hänvisas till kammaråklagare '
                . 'Viktor Wetterö och senior åklagare Jessica Andersson som är '
                . 'tillgängliga för korta kommentarer på telefon idag den 20 januari '
                . 'kl 11.15–12.15. Mejla media.ost@polisen.se för kontakt.',
            ],

            'kort händelse utan pressfras' => [
                'Polisen bistår med trafikdirigering vid en vägskada vid rondellen i '
                . 'Norra backa.',
            ],

            'tom text' => [''],
        ];
    }

    #[DataProvider('riktigHändelseProvider')]
    public function testRiktigHändelseSläppsIgenom(string $parsedContent): void
    {
        $this->assertFalse($this->ärMediecenterInfo($parsedContent));
    }

    public function testMediecenterInfoGörHändelsenIckePublik(): void
    {
        $event = new CrimeEvent();
        $event->is_public = true;
        $event->parsed_content = 'Idag, torsdag 7 maj, har RMC, regionalt '
            . 'mediecenter, stängt hela dagen. Det går alltid att nå oss via mejl, '
            . 'media.bergslagen@polisen.se';

        $this->assertFalse((new ContentFilterService())->shouldBePublic($event));
    }

    /**
     * En kort riktig händelse som avslutas med en mediehänvisning hamnar
     * innanför både längd- och positionsgränsen. Skyddet är brottskategorin:
     * pressmeddelanden filas alltid som Information/Övrigt, aldrig som Brand.
     *
     * Verkligt fall, event 152427 — hela texten är 348 tecken och frasen
     * står på offset 240.
     */
    public function testKortBrandhändelseMedMediehänvisningSläppsIgenom(): void
    {
        $event = new CrimeEvent();
        $event->parsed_title = 'Brand';
        $event->parsed_content = 'Enligt SOS så är det rökutveckling/brand från '
            . 'Ullevigaraget. Räddningstjänst och polis larmas till platsen. Ännu '
            . 'oklart om det finns personer i garaget eller personskador. Kl 12.43: '
            . 'Polis på plats. Lyder under räddningstjänsten i insatsen. Mediafrågor '
            . 'hänvisas till räddningstjänsten. Det visar sig röra sig om en bil med '
            . 'motorproblem, ingen brand.';

        $this->assertFalse($this->filtrerasHändelse($event));
    }

    public function testNattsammanfattningFårFortfarandeFiltreras(): void
    {
        $event = new CrimeEvent();
        $event->parsed_title = 'Sammanfattning natt';
        $event->parsed_content = 'Idag, onsdag den 29 juli, är RMC stängt. Det går '
            . 'alltid att mejla till media.bergslagen@polisen.se.';

        $this->assertTrue($this->filtrerasHändelse($event));
    }

    /**
     * parsed_content skrapas från polisen.se och blir NULL för alltid om
     * skrapningen fallerar en gång — parseItem() ignorerar 'ERROR' och
     * parseItemForLocations() sätter scanned_for_locations ändå, så raden
     * plockas aldrig upp igen. description kommer från JSON-API:t och
     * finns alltid.
     */
    public function testFallerTillbakaPåDescriptionNärParsedContentSaknas(): void
    {
        $event = new CrimeEvent();
        $event->parsed_title = 'Information';
        $event->parsed_content = null;
        $event->description = 'Idag är RMC, Regionalt mediecenter, stängt. '
            . 'Det går alltid att mejla till media.bergslagen@polisen.se.';

        $this->assertTrue($this->filtrerasHändelse($event));
    }

    /**
     * preg_replace med /u returnerar null på ogiltig UTF-8. Utan fallback
     * blir brödtexten tom och hela kontrollen tyst verkningslös.
     */
    public function testOgiltigUtf8VoidarInteKontrollen(): void
    {
        $event = new CrimeEvent();
        $event->parsed_title = 'Information';
        $event->parsed_content = "Idag är RMC st\xE4ngt. Mejla media.bergslagen@polisen.se";

        $this->assertTrue($this->filtrerasHändelse($event));
    }

    public function testFilteranledningNämnerMediecenter(): void
    {
        $event = new CrimeEvent();
        $event->parsed_content = 'Frågor hänvisas till media.vast@polisen.se.';

        $this->assertSame(
            'Mediecenter-information',
            (new ContentFilterService())->getFilterReason($event)
        );
    }
}
