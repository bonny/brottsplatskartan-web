<?php

namespace App\Console\Commands;

use App\CrimeEvent;
use App\Lansgeometri;
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
    /**
     * Googles typer grupperade till en precisionsklass, mest exakt först.
     * Första gruppen som matchar någon av träffens typer vinner.
     */
    private const PRECISIONSKLASSER = [
        'adress' => ['street_address', 'premise', 'subpremise'],
        'korsning' => ['intersection'],
        'poi' => ['point_of_interest', 'establishment', 'transit_station', 'park', 'airport'],
        'gata' => ['route'],
        'stadsdel' => ['sublocality', 'sublocality_level_1', 'neighborhood'],
        'ort' => ['locality', 'postal_town'],
        'kommun' => ['administrative_area_level_2'],
        'län' => ['administrative_area_level_1'],
    ];

    public function handle(): int
    {
        $fran = $this->option('fran') ?: now()->subDays((int) $this->option('dagar'))->toDateString();
        $till = $this->option('till') ?: now()->addDay()->toDateString();

        $query = CrimeEvent::query()
            ->where('created_at', '>=', $fran)
            ->where('created_at', '<', $till)
            ->select([
                'id', 'parsed_title', 'polisen_location_name', 'geocoded',
                'location_lat', 'location_lng', 'location_geometry_type',
                'google_types', 'google_partial_match',
            ]);

        $antal = 0;
        $geokodade = 0;
        $geometrityper = [];
        $klasser = [];
        $medTyper = 0;
        $partial = 0;
        $lanTestade = 0;
        $utanfor = [];
        $okantLan = 0;

        foreach ($query->lazyById(500) as $event) {
            $antal++;
            if (! $event->geocoded || ! $event->location_lat) {
                continue;
            }
            $geokodade++;

            $typ = $event->location_geometry_type ?: '(saknas)';
            $geometrityper[$typ] = ($geometrityper[$typ] ?? 0) + 1;

            if ($event->google_types !== null) {
                $medTyper++;
                $klass = $this->precisionsklass($event->google_types);
                $klasser[$klass] = ($klasser[$klass] ?? 0) + 1;
                if ($event->google_partial_match) {
                    $partial++;
                }
            }

            $inom = Lansgeometri::innehaller($event->polisen_location_name, (float) $event->location_lat, (float) $event->location_lng);
            if ($inom === null) {
                $okantLan++;
            } else {
                $lanTestade++;
                if (! $inom) {
                    $utanfor[] = $event;
                }
            }
        }

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
            $this->line("  {$event->id}  {$event->polisen_location_name}  {$event->parsed_title}");
        }
        $this->newLine();

        $this->info('location_geometry_type');
        $this->tabell($geometrityper, $geokodade);

        $this->info("Precisionsklass från google_types ({$medTyper} händelser med typer)");
        if ($medTyper > 0) {
            $this->tabell($klasser, $medTyper);
            $this->line(sprintf('partial_match: %d (%s)', $partial, $this->procent($partial, $medTyper)));
        } else {
            $this->line('  inga än — sparas för händelser geokodade efter 2026-10-10');
        }

        return self::SUCCESS;
    }

    /**
     * @param array<int, string> $types
     */
    private function precisionsklass(array $types): string
    {
        foreach (self::PRECISIONSKLASSER as $klass => $klassTyper) {
            if (array_intersect($types, $klassTyper)) {
                return $klass;
            }
        }

        return 'annan';
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
