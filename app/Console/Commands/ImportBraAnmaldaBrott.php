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
     *
     * 2022 + 2015–2020 finns inte som per-kommun-CSV på bra.se — bara via
     * BRÅ:s interaktiva databas. Inte i scope för automatisk import.
     */
    private const KNOWN_URLS = [
        2021 => 'https://bra.se/download/18.2e6195c8190a64a78d4201c4/1723120294356/anmalda_brott_kommuner_2021.csv',
        2023 => 'https://bra.se/download/18.349839a519329944199b20/1731934941054/Anmalda%20brott%20i%20kommunerna%202023.csv',
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

        // 2023+ har header "Kommun;Antal;Per 100 000 inv.". 2021 saknar header
        // helt och första raden är data ("Ale kommun;2731;8547"). Ta bort
        // header bara om första raden ser ut som en header (icke-numerisk
        // andra-kolumn).
        $firstLine = $lines[0];
        if ($firstLine && stripos($firstLine, 'Kommun') !== false && !preg_match('/;\s*\d/', $firstLine)) {
            array_shift($lines);
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

            // 2021-formatet har " kommun"-suffix på namnet ("Ale kommun" istället
            // för "Ale"). Strippa konsekvent. Innehåller också stadsdelar
            // (Spånga-Tensta etc.) som inte finns i scb_kommuner — de hamnar
            // i $unmatched och skippas, vilket är korrekt.
            $namn = preg_replace('/\s+kommun$/i', '', $namn);

            // BRÅ använder mellanslag som tusentalsavskiljare ("2 537" → 2537).
            $antalInt = (int) preg_replace('/\s+/', '', $antal);
            $per100kInt = (int) preg_replace('/\s+/', '', $per100k);

            // Skippa "Hela landet"-rad och liknande aggregat.
            if ($namn === '' || stripos($namn, 'Hela landet') !== false || stripos($namn, 'Riket') !== false) {
                continue;
            }

            $key = $this->normalizeName($namn);
            $kommun = $kommunByName->get($key);

            // 2021-formatet använder genitiv ("Arjeplogs kommun" istället för
            // "Arjeplog kommun"). Om strict match misslyckas och namnet slutar
            // på "s", testa utan "s". Fungerar för "Arjeplogs" → "Arjeplog",
            // "Burlövs" → "Burlöv", men inte för legit-s-namn som "Borås"
            // (matchar redan i strict).
            if (!$kommun && str_ends_with($key, 's')) {
                $kommun = $kommunByName->get(substr($key, 0, -1));
            }

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
            // 2021-formatet inkluderar stadsdelar (Spånga-Tensta, Hässelby-Vällingby
            // etc.) som inte finns i scb_kommuner — det är korrekt att skippa dem.
            // Visa bara de första 15 så loggen inte sväller.
            $this->warn('Namn som inte matchade scb_kommuner (' . count($unmatched) . ' st — ofta stadsdelar):');
            foreach (array_slice($unmatched, 0, 15) as $name) {
                $this->line("  - {$name}");
            }
            if (count($unmatched) > 15) {
                $this->line('  ... + ' . (count($unmatched) - 15) . ' till.');
            }
        }

        if ($skipped > 0) {
            $this->warn("Skippade {$skipped} ofullständiga rader.");
        }

        return self::SUCCESS;
    }

    /**
     * Normalisera kommunnamn för join: lowercase + trim + dash-normalisering.
     * Behåll åäö (utf8mb4_bin-collation kräver exakt match med rätt accenter).
     *
     * 2021-CSV:n använder EM-DASH (U+2013) i "Malung–Sälen", scb_kommuner har
     * vanlig bindestreck. Ersätt alla dash-varianter med ASCII-dash.
     */
    private function normalizeName(string $name): string
    {
        $normalized = mb_strtolower(trim($name));
        return str_replace(["\u{2013}", "\u{2014}", "\u{2212}"], '-', $normalized);
    }
}
