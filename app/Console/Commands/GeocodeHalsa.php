<?php

namespace App\Console\Commands;

use App\Services\GeokodHalsa;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Hälsosiffror för geokodningen över en valfri period (todo #111, fas A2).
 *
 * Bara läsning. Räknas i efterhand ur crime_events, så inget behöver
 * schemaläggas eller sparas — kör före och efter en ändring och jämför.
 *
 *   php artisan geocode:halsa                     # senaste 30 dagarna
 *   php artisan geocode:halsa --fran=2026-09-01 --till=2026-10-01
 *
 * - Länstest: geokodad punkt mot länsgränsen för `polisen_location_name`
 *   (Polisens län, oberoende av Google). `administrative_area_level_1` duger
 *   inte som facit — den kommer från Google själv.
 * Beräkningen ligger i App\Services\GeokodHalsa (delas med /status).
 *
 * - Bara publika händelser (CrimeEvents globala scope), samma urval som
 *   baslinjen i #108 och det användarna ser. Icke-publika geokodas också
 *   men räknas inte.
 * - Precision: `location_geometry_type` för alla, `google_types` +
 *   `partial_match` för händelser geokodade efter 2026-10-10.
 */
#[Signature('geocode:halsa {--dagar=30 : Antal dagar bakåt} {--fran= : Startdatum (Y-m-d), ersätter --dagar} {--till= : Slutdatum (Y-m-d), exklusivt} {--lista=15 : Max antal händelser utanför länet att lista}')]
#[Description('Visar hälsosiffror för geokodningen: länstest och precisionsfördelning.')]
class GeocodeHalsa extends Command
{
    public function handle(): int
    {
        $fran = $this->option('fran') ?: now()->subDays((int) $this->option('dagar'))->toDateString();
        $till = $this->option('till') ?: now()->addDay()->toDateString();

        $r = app(GeokodHalsa::class)->berakna($fran, $till);
        $antal = $r['antal'];
        $geokodade = $r['geokodade'];
        $utanfor = $r['utanfor'];
        $lanTestade = $r['lan_testade'];
        $okantLan = $r['okant_lan'];
        $medTyper = $r['med_typer'];

        $this->info("Period {$fran} – {$till} (till exklusivt)");
        $this->line("Publika händelser: {$antal}, geokodade: {$geokodade}");
        $this->newLine();

        $this->info('Länstest (punkt mot Polisens län)');
        $this->line(sprintf(
            'Utanför länet: %d av %d (%s)%s',
            count($utanfor),
            $lanTestade,
            $this->procent(count($utanfor), $lanTestade),
            $okantLan ? ", {$okantLan} utan känt län" : ''
        ));
        foreach (array_slice($utanfor, 0, (int) $this->option('lista')) as $event) {
            $this->line("  {$event['id']}  {$event['lan']}  {$event['titel']}");
        }
        $this->newLine();

        $this->info('location_geometry_type');
        $this->tabell($r['geometrityper'], $geokodade);

        $this->info("Precisionsklass från google_types ({$medTyper} händelser med typer)");
        if ($medTyper > 0) {
            $this->tabell($r['klasser'], $medTyper);
            $this->line(sprintf('partial_match: %d (%s)', $r['partial'], $this->procent($r['partial'], $medTyper)));
        } else {
            $this->line('  inga än — sparas för händelser geokodade efter 2026-10-10');
        }

        return self::SUCCESS;
    }

    /**
     * @param array<string, int> $rader
     */
    private function tabell(array $rader, int $total): void
    {
        arsort($rader);
        $this->table(
            ['', 'antal', 'andel'],
            array_map(fn ($namn, $n) => [$namn, $n, $this->procent($n, $total)], array_keys($rader), $rader)
        );
    }

    private function procent(int $del, int $total): string
    {
        return $total > 0 ? number_format(100 * $del / $total, 1, ',', '') . ' %' : '–';
    }
}
