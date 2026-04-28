<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Importera BRÅ:s anmälda brott per kommun till `bra_anmalda_brott` (todo #38).
 *
 * Datakälla: BRÅ:s årliga kommun-CSV (UTF-8 BOM, semikolon, CRLF, ~290 rader).
 * URL:erna är inte programmatiskt härledbara — download-id ändras varje år.
 * Lös via inbyggd URL-tabell ELLER explicit --url-flagga.
 *
 * Idempotent: kör om för uppdaterad årgång.
 */
#[Signature('bra:import-anmalda-brott {--year= : Årgång (krävs om --url saknas)} {--url= : Explicit CSV-URL}')]
#[Description('Importera BRÅ:s anmälda brott per kommun till bra_anmalda_brott-tabellen.')]
class ImportBraAnmaldaBrott extends Command
{
    /**
     * Kända URL:er per årgång. Uppdatera när BRÅ släpper ny.
     * Senaste publicering: 2025 (släppt 2026-03-27).
     */
    private const KNOWN_URLS = [
        2024 => 'https://bra.se/download/18.41109aad195b25241f818dbf/1742982579590/Anm%C3%A4lda%20brott%20kommunerna%202024.csv',
        2025 => 'https://bra.se/download/18.3b6b697b19d24d83762a45f/1774625599791/Anm%C3%A4lda%20brott%20kommunerna%202025.csv',
    ];

    public function handle(): int
    {
        $year = $this->option('year') ? (int) $this->option('year') : null;
        $url = $this->option('url') ?: ($year ? (self::KNOWN_URLS[$year] ?? null) : null);

        if (!$url) {
            $this->error('Ange --year (känd årgång) eller --url (explicit CSV-URL).');
            $this->line('Kända år: ' . implode(', ', array_keys(self::KNOWN_URLS)));
            return self::FAILURE;
        }

        if (!$year) {
            // Plocka årtal ur URL ("...kommunerna%20YYYY.csv" eller "...kommunerna YYYY.csv").
            if (preg_match('/(\d{4})\.csv/i', $url, $m)) {
                $year = (int) $m[1];
            } else {
                $this->error('Kunde inte härleda årtal från URL — ange --year=YYYY explicit.');
                return self::FAILURE;
            }
        }

        $this->info("Hämtar BRÅ-CSV för {$year} från {$url}");

        $response = Http::timeout(60)->get($url);
        if (!$response->successful()) {
            $this->error("CSV-nedladdning misslyckades: HTTP {$response->status()}");
            return self::FAILURE;
        }

        $csv = $response->body();
        // Strippa UTF-8 BOM om finns.
        if (substr($csv, 0, 3) === "\xEF\xBB\xBF") {
            $csv = substr($csv, 3);
        }

        $lines = preg_split("/\r\n|\n|\r/", $csv);
        if (!$lines) {
            $this->error('CSV var tom.');
            return self::FAILURE;
        }

        $header = array_shift($lines);
        if ($header === null || stripos($header, 'Kommun') === false) {
            $this->error("Oväntat header-format: {$header}");
            return self::FAILURE;
        }

        // Bygg namn→kod-mappning från scb_kommuner (290 rader, joinas på namn).
        $kommunByName = DB::table('scb_kommuner')
            ->select('kommun_kod', 'kommun_namn')
            ->get()
            ->keyBy(fn ($r) => $this->normalizeName($r->kommun_namn));

        if ($kommunByName->isEmpty()) {
            $this->error('scb_kommuner är tom. Kör scb:import-kommuner först.');
            return self::FAILURE;
        }

        $now = now();
        $batch = [];
        $unmatched = [];
        $skipped = 0;

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $cols = str_getcsv($line, ';');
            if (count($cols) < 3) {
                $skipped++;
                continue;
            }

            [$namn, $antal, $per100k] = $cols;
            $namn = trim($namn);

            // BRÅ använder mellanslag som tusentalsavskiljare ("2 537" → 2537).
            $antalInt = (int) preg_replace('/\s+/', '', $antal);
            $per100kInt = (int) preg_replace('/\s+/', '', $per100k);

            // Skippa "Hela landet"-rad och liknande aggregat.
            if ($namn === '' || stripos($namn, 'Hela landet') !== false || stripos($namn, 'Riket') !== false) {
                continue;
            }

            $key = $this->normalizeName($namn);
            $kommun = $kommunByName->get($key);

            if (!$kommun) {
                $unmatched[] = $namn;
                continue;
            }

            $batch[] = [
                'kommun_kod' => $kommun->kommun_kod,
                'ar' => $year,
                'antal' => $antalInt,
                'per_100k' => $per100kInt,
                'source_url' => $url,
                'imported_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (empty($batch)) {
            $this->error('Inga rader att importera.');
            return self::FAILURE;
        }

        DB::table('bra_anmalda_brott')->upsert(
            $batch,
            ['kommun_kod', 'ar'],
            ['antal', 'per_100k', 'source_url', 'imported_at', 'updated_at']
        );

        $this->info('Klart. Importerade ' . count($batch) . " kommuner för {$year}.");

        if (!empty($unmatched)) {
            $this->warn('Kommunnamn som inte matchade scb_kommuner (' . count($unmatched) . '):');
            foreach ($unmatched as $name) {
                $this->line("  - {$name}");
            }
        }

        if ($skipped > 0) {
            $this->warn("Skippade {$skipped} ofullständiga rader.");
        }

        return self::SUCCESS;
    }

    /**
     * Normalisera kommunnamn för join: lowercase + trim.
     * Behåll åäö (utf8mb4_bin-collation kräver exakt match med rätt accenter).
     */
    private function normalizeName(string $name): string
    {
        return mb_strtolower(trim($name));
    }
}
