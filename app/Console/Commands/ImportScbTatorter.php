<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PDO;

/**
 * Importera SCB:s statistiska tätorter till `scb_tatorter` (todo #37).
 *
 * Datakälla: SCB Geopackage `Tatorter_2023` (CC0). 2017 rader,
 * uppdateras vart 2-3 år. URL via WFS i body, `outputFormat=geopackage`.
 *
 * Idempotent: kör om för att uppdatera när SCB släpper ny version.
 * Polygon-kolumnen ignoreras — vi behöver bara (kod, namn, kommun, län, befolkning).
 */
#[Signature('scb:import-tatorter {--year=2023 : SCB-årgång att importera} {--from-file= : Använd lokal .gpkg-fil istället för nedladdning}')]
#[Description('Importera SCB tätorter till scb_tatorter-tabellen.')]
class ImportScbTatorter extends Command
{
    private const WFS_URL_TEMPLATE = 'https://geodata.scb.se/geoserver/stat/wfs?service=WFS&REQUEST=GetFeature&version=1.1.0&TYPENAMES=stat:Tatorter_%d&outputFormat=geopackage';

    public function handle(): int
    {
        $year = (int) $this->option('year');
        $fromFile = $this->option('from-file');

        $gpkgPath = $fromFile ?: $this->downloadGeopackage($year);

        if (!file_exists($gpkgPath)) {
            $this->error("GeoPackage-fil saknas: {$gpkgPath}");
            return self::FAILURE;
        }

        $this->info("Öppnar {$gpkgPath}");
        $pdo = new PDO("sqlite:{$gpkgPath}");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $tableName = "Tatorter_{$year}";
        $count = (int) $pdo->query("SELECT COUNT(*) FROM {$tableName}")->fetchColumn();
        $this->info("Hittade {$count} tätorter i {$tableName}");

        $stmt = $pdo->query("SELECT tatortskod, tatort, kommun, kommunnamn, lan, lannamn, bef, area_ha, ar FROM {$tableName}");
        $bar = $this->output->createProgressBar($count);
        $bar->start();

        $imported = 0;
        $now = now();
        $batch = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $batch[] = [
                'tatortskod' => $row['tatortskod'],
                'tatort' => $row['tatort'],
                'kommun_kod' => $row['kommun'],
                'kommun_namn' => $row['kommunnamn'],
                'lan_kod' => $row['lan'],
                'lan_namn' => $row['lannamn'],
                'befolkning' => (int) $row['bef'],
                'area_ha' => $row['area_ha'] !== null ? (int) $row['area_ha'] : null,
                'ar' => (int) $row['ar'],
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($batch) >= 200) {
                $this->flushBatch($batch);
                $imported += count($batch);
                $bar->advance(count($batch));
                $batch = [];
            }
        }

        if ($batch) {
            $this->flushBatch($batch);
            $imported += count($batch);
            $bar->advance(count($batch));
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Klart. Importerade/uppdaterade {$imported} tätorter.");

        return self::SUCCESS;
    }

    private function downloadGeopackage(int $year): string
    {
        $dir = storage_path('app/scb');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $path = "{$dir}/tatorter_{$year}.gpkg";

        if (file_exists($path) && filesize($path) > 1_000_000) {
            $this->info("Använder cached fil: {$path}");
            return $path;
        }

        $url = sprintf(self::WFS_URL_TEMPLATE, $year);
        $this->info("Laddar ner från SCB: {$url}");

        $response = Http::timeout(120)->get($url);
        if (!$response->successful()) {
            throw new \RuntimeException("Nedladdning misslyckades: HTTP {$response->status()}");
        }

        file_put_contents($path, $response->body());
        $this->info("Sparade " . number_format(filesize($path) / 1024 / 1024, 1) . " MB till {$path}");

        return $path;
    }

    /**
     * @param array<int, array<string, mixed>> $batch
     */
    private function flushBatch(array $batch): void
    {
        DB::table('scb_tatorter')->upsert(
            $batch,
            ['tatortskod'],
            ['tatort', 'kommun_kod', 'kommun_namn', 'lan_kod', 'lan_namn', 'befolkning', 'area_ha', 'ar', 'updated_at']
        );
    }
}
