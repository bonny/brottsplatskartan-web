<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Importera MCF (tidigare MSB) räddningstjänstens insatser per kommun, år,
 * månad och händelsetyp till `mcf_raddningsinsatser` (todo #39).
 *
 * Datakälla: PxWeb v1 API på statistik.mcf.se, tabell B11. Öppen, ingen
 * inloggning. En query per år (~290 kommuner × 12 mån × 14 typer = ~49k
 * rader, ~2 MB JSON, ~1.5s svarstid).
 *
 * Idempotent — upsert per (kommun_kod, ar, manad, handelsetyp_id).
 * Rader med antal=0 importeras inte (håller tabellen ~50 % mindre).
 */
#[Signature('mcf:import-raddningsinsatser {--year= : Specifik årgång (utan flagga: alla år 1998–nuvarande)} {--from=1998 : Startår vid full import}')]
#[Description('Importera MCF räddningsinsatser per kommun från PxWeb-API.')]
class ImportMcfRaddningsinsatser extends Command
{
    private const API_URL = 'https://statistik.mcf.se/PxWeb/api/v1/sv/PxData/B/B1/B11';

    /**
     * MCF:s 14 övergripande händelsetyper (konvHandelsetypId → namn).
     * Hårdkodat — taxonomin är stabil sedan 2005 och vi vill inte vara
     * beroende av en separat metadata-fetch vid import.
     */
    private const HANDELSETYP_NAMN = [
        1 => 'Brand i byggnad',
        2 => 'Trafikolycka',
        3 => 'Brand i annat än byggnad',
        4 => 'Utsläpp av farligt ämne',
        5 => 'Drunkning eller drunkningstillbud',
        6 => 'Nödställd person',
        7 => 'Nödställt djur',
        8 => 'Stormskada',
        9 => 'Ras eller skred',
        11 => 'Översvämning av vattendrag',
        12 => 'Annan vattenskada',
        13 => 'Annan olycka eller tillbud',
        14 => 'Automatlarm utan brandtillbud',
        15 => 'Annan händelse utan risk för skada',
    ];

    public function handle(): int
    {
        $singleYear = $this->option('year') ? (int) $this->option('year') : null;
        $fromYear = (int) $this->option('from');
        $toYear = (int) date('Y');

        $years = $singleYear ? [$singleYear] : range($fromYear, $toYear);

        $totalInserted = 0;
        $totalSkipped = 0;
        $now = now();

        foreach ($years as $year) {
            $this->info("Hämtar år {$year} från MCF PxWeb...");

            $response = Http::timeout(60)
                ->acceptJson()
                ->withBody(json_encode([
                    'query' => [
                        ['code' => 'kommun', 'selection' => ['filter' => 'all', 'values' => ['*']]],
                        ['code' => 'ar', 'selection' => ['filter' => 'item', 'values' => [(string) $year]]],
                        ['code' => 'konvHandelsetypId', 'selection' => ['filter' => 'all', 'values' => ['*']]],
                        ['code' => 'manadNummer', 'selection' => ['filter' => 'all', 'values' => ['*']]],
                    ],
                    'response' => ['format' => 'json'],
                ]), 'application/json')
                ->post(self::API_URL);

            if (!$response->successful()) {
                $this->error("  HTTP {$response->status()} för {$year} — hoppar över.");
                $response = null;
                continue;
            }

            $rows = $response->json('data') ?? [];
            // Släpp Response-objektet (med Guzzle Stream + body-buffer) innan vi
            // bygger upp batchen — full historik-import (~30 år × 2.3 MB body)
            // exhaust:ar annars 256 MB innan loopen är klar.
            $response = null;

            if (empty($rows)) {
                $this->warn("  Inga rader för {$year} (data inte publicerad än?).");
                continue;
            }

            $batch = [];
            foreach ($rows as $row) {
                // key-ordning matchar query-ordningen: [konvHandelsetypId, kommun, manadNummer, ar]
                [$typId, $kommunKod, $manad, $ar] = $row['key'];
                $value = $row['values'][0] ?? null;

                // PxWeb representerar saknad data som "..", "." eller "-".
                if ($value === null || !is_numeric($value)) {
                    continue;
                }

                $antal = (int) $value;
                if ($antal === 0) {
                    $totalSkipped++;
                    continue;
                }

                $typIdInt = (int) $typId;
                $namn = self::HANDELSETYP_NAMN[$typIdInt] ?? "Typ {$typIdInt}";

                $batch[] = [
                    'kommun_kod' => $kommunKod,
                    'ar' => (int) $ar,
                    'manad' => (int) $manad,
                    'handelsetyp_id' => $typIdInt,
                    'handelsetyp_namn' => $namn,
                    'antal' => $antal,
                    'source_url' => self::API_URL,
                    'imported_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if (empty($batch)) {
                $this->warn("  Inga rader > 0 för {$year}.");
                continue;
            }

            // Chunka upserts — MariaDB max_allowed_packet kan klagga på ~50k rader.
            foreach (array_chunk($batch, 2000) as $chunk) {
                DB::table('mcf_raddningsinsatser')->upsert(
                    $chunk,
                    ['kommun_kod', 'ar', 'manad', 'handelsetyp_id'],
                    ['handelsetyp_namn', 'antal', 'source_url', 'imported_at', 'updated_at']
                );
            }

            $count = count($batch);
            $this->info("  Importerade {$count} rader för {$year}.");
            $totalInserted += $count;

            // Aktiv minneshushållning mellan år.
            unset($rows, $batch);
            gc_collect_cycles();
        }

        $this->info("Klart. Total: {$totalInserted} rader, {$totalSkipped} nollor överhoppade.");
        return self::SUCCESS;
    }
}
