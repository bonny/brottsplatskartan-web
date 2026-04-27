<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Importera SCB:s kommunbefolkning till `scb_kommuner` (todo #37).
 *
 * Datakälla: SCB Befolkning-API (anonymt, JSON, CC0). 290 kommuner,
 * uppdateras årligen i februari. En batch-POST hämtar alla på en gång
 * (290 celler << 150 000-gränsen).
 *
 * Idempotent: kör om för ny årgång.
 */
#[Signature('scb:import-kommuner {--year=2024 : Befolknings-årgång}')]
#[Description('Importera SCB kommunbefolkning till scb_kommuner-tabellen.')]
class ImportScbKommuner extends Command
{
    private const API_URL = 'https://api.scb.se/OV0104/v1/doris/sv/ssd/BE/BE0101/BE0101A/BefolkningNy';

    public function handle(): int
    {
        $year = (string) $this->option('year');

        $this->info("Hämtar kommunbefolkning för {$year} från SCB...");

        // Hämta kod→namn-mappning från metadata-endpoint (GET).
        // SCB-API:ets data-svar innehåller bara koder, inte namn.
        $metaResponse = Http::timeout(60)->get(self::API_URL);
        if (!$metaResponse->successful()) {
            $this->error("Metadata-anrop misslyckades: HTTP {$metaResponse->status()}");
            return self::FAILURE;
        }
        $meta = $metaResponse->json();
        $regionVar = collect($meta['variables'] ?? [])->firstWhere('code', 'Region');
        if (!$regionVar) {
            $this->error('Hittade inte Region-variabeln i metadata.');
            return self::FAILURE;
        }
        $namesByKod = array_combine($regionVar['values'], $regionVar['valueTexts']);

        $body = [
            'query' => [
                [
                    'code' => 'Region',
                    'selection' => ['filter' => 'all', 'values' => ['*']],
                ],
                [
                    'code' => 'Tid',
                    'selection' => ['filter' => 'item', 'values' => [$year]],
                ],
            ],
            'response' => ['format' => 'json'],
        ];

        $response = Http::timeout(60)->post(self::API_URL, $body);

        if (!$response->successful()) {
            $this->error("SCB-API svarade {$response->status()}: " . $response->body());
            return self::FAILURE;
        }

        $payload = $response->json();
        $rows = $payload['data'] ?? [];
        $columns = $payload['columns'] ?? [];

        if (empty($rows)) {
            $this->error('Inga rader i svaret från SCB.');
            return self::FAILURE;
        }

        $this->info("Fick " . count($rows) . " kommuner från SCB.");

        // Län-namn från scb_tatorter (där det finns) — kompletterar SCB-namnen som
        // bara har kommunnamn, inte länsnamn.
        $lanByKod = DB::table('scb_tatorter')
            ->select('lan_kod', 'lan_namn')
            ->distinct()
            ->get()
            ->keyBy('lan_kod');

        $now = now();
        $batch = [];
        $skipped = 0;

        foreach ($rows as $row) {
            $kommunKod = $row['key'][0] ?? null;
            $befolkning = (int) ($row['values'][0] ?? 0);

            if (!$kommunKod || strlen($kommunKod) !== 4) {
                $skipped++;
                continue;
            }

            $lanKod = substr($kommunKod, 0, 2);
            $lanInfo = $lanByKod->get($lanKod);

            $batch[] = [
                'kommun_kod' => $kommunKod,
                'kommun_namn' => $namesByKod[$kommunKod] ?? "Okänd ({$kommunKod})",
                'lan_kod' => $lanKod,
                'lan_namn' => $lanInfo->lan_namn ?? null,
                'befolkning' => $befolkning,
                'ar' => (int) $year,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('scb_kommuner')->upsert(
            $batch,
            ['kommun_kod'],
            ['kommun_namn', 'lan_kod', 'lan_namn', 'befolkning', 'ar', 'updated_at']
        );

        $this->info("Klart. Importerade " . count($batch) . " kommuner. Skippade {$skipped}.");

        return self::SUCCESS;
    }
}
