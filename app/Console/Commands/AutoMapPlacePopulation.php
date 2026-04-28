<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Auto-mappa Brottsplatskartans `parsed_title_location` → SCB-koder (todo #37).
 *
 * Tre matchningssteg, första som träffar vinner:
 *   1. Tätort (sammanhängande bebyggelse, mest precis)
 *   2. Kommun (bredare, för städer = kommun-huvudort)
 *   3. Län (för "Västerbottens län"-fall där Polisen inte specificerat ort)
 *
 * Resten markeras `source=none` och måste granskas manuellt.
 * Idempotent: kör om för att fånga nya platser eller uppdatera mappning.
 */
#[Signature('place-population:auto-map {--dry-run : Visa vad som skulle mappas utan att skriva till DB}')]
#[Description('Auto-mappa parsed_title_location → SCB tätort/kommun/län.')]
class AutoMapPlacePopulation extends Command
{
    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        if ($dryRun) {
            $this->warn('Dry-run: inga skrivningar.');
        }

        // Hämta alla unika platsnamn från crime_events (publika).
        $places = DB::table('crime_events')
            ->select('parsed_title_location', DB::raw('COUNT(*) as antal'))
            ->where('is_public', 1)
            ->whereNotNull('parsed_title_location')
            ->where('parsed_title_location', '!=', '')
            ->groupBy('parsed_title_location')
            ->orderByDesc('antal')
            ->get();

        $this->info("Hittade {$places->count()} unika platsnamn att mappa.");

        // Bygg slag-tabeller från SCB i två varianter:
        //   strict:     case-folded only (åäö preserveras) — för exakt match
        //   fuzzy:      accent-stripped — fallback när Polisens RSS missar diakritiska tecken
        // Strict före fuzzy hindrar att t.ex. "Håbo" mappas mot tätorten "Habo".
        //
        // ORDNING: ascending på befolkning så största kommer SIST. keyBy gör
        // last-write-wins, så största tätorten med ett delat namn vinner. Utan
        // detta valdes Lund i Gävle (580 inv) över Lund i Skåne (98 308 inv).
        $tatortRows = DB::table('scb_tatorter')
            ->orderBy('befolkning')
            ->get(['tatortskod', 'tatort']);
        $tatorterStrict = $tatortRows->keyBy(fn($r) => mb_strtolower(trim($r->tatort)));
        $tatorterFuzzy = $tatortRows->keyBy(fn($r) => self::normalize($r->tatort));

        $kommunRows = DB::table('scb_kommuner')->get(['kommun_kod', 'kommun_namn']);
        $kommunerStrict = $kommunRows->keyBy(fn($r) => mb_strtolower(trim($r->kommun_namn)));
        $kommunerFuzzy = $kommunRows->keyBy(fn($r) => self::normalize($r->kommun_namn));

        // Län — bygg från distinct värden i scb_kommuner med gemensam lan_kod.
        $lanList = DB::table('scb_kommuner')
            ->select('lan_kod', 'lan_namn')
            ->whereNotNull('lan_namn')
            ->distinct()
            ->get();
        $lanByName = [];
        foreach ($lanList as $l) {
            $lanByName[self::normalize($l->lan_namn)] = $l;
        }

        $stats = ['scb_tatort' => 0, 'scb_kommun' => 0, 'scb_lan' => 0, 'none' => 0];
        $rows = [];
        $now = now();

        foreach ($places as $p) {
            $name = $p->parsed_title_location;
            $strict = mb_strtolower(trim($name));
            $fuzzy = self::normalize($name);

            $row = [
                'bpk_place_name' => $name,
                'scb_tatortskod' => null,
                'scb_kommun_kod' => null,
                'scb_lan_kod' => null,
                'source' => 'none',
                'notes' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            // Match-prioritet:
            //   1. strikt tätort  2. strikt kommun  3. fuzzy tätort  4. fuzzy kommun  5. län
            if (isset($tatorterStrict[$strict])) {
                $row['scb_tatortskod'] = $tatorterStrict[$strict]->tatortskod;
                $row['source'] = 'scb_tatort';
                $stats['scb_tatort']++;
            } elseif (isset($kommunerStrict[$strict])) {
                $row['scb_kommun_kod'] = $kommunerStrict[$strict]->kommun_kod;
                $row['source'] = 'scb_kommun';
                $stats['scb_kommun']++;
            } elseif (isset($tatorterFuzzy[$fuzzy])) {
                $row['scb_tatortskod'] = $tatorterFuzzy[$fuzzy]->tatortskod;
                $row['source'] = 'scb_tatort';
                $row['notes'] = 'Fuzzy-match (accent-okänslig)';
                $stats['scb_tatort']++;
            } elseif (isset($kommunerFuzzy[$fuzzy])) {
                $row['scb_kommun_kod'] = $kommunerFuzzy[$fuzzy]->kommun_kod;
                $row['source'] = 'scb_kommun';
                $row['notes'] = 'Fuzzy-match (accent-okänslig)';
                $stats['scb_kommun']++;
            } else {
                $stripped = self::stripLanSuffix($fuzzy);
                if (isset($lanByName[$stripped])) {
                    $row['scb_lan_kod'] = $lanByName[$stripped]->lan_kod;
                    $row['source'] = 'scb_lan';
                    $stats['scb_lan']++;
                } else {
                    $stats['none']++;
                }
            }

            $rows[] = $row;
        }

        if (!$dryRun) {
            // Wipe + insert i transaktion (mappning kan ändra sig mellan
            // körningar). delete() istället för truncate() — TRUNCATE auto-
            // commitar i MariaDB och kan inte rullas tillbaka, så ett fail
            // mid-loop skulle lämna tabellen tom och bryta BRÅ-fuzzy-resolvern
            // + stadssidor som lookup:ar mot den.
            DB::transaction(function () use ($rows) {
                DB::table('place_population')->delete();
                foreach (array_chunk($rows, 200) as $chunk) {
                    DB::table('place_population')->insert($chunk);
                }
            });
        }

        $this->newLine();
        $this->info('Resultat:');
        $this->line("  Tätort:    {$stats['scb_tatort']}");
        $this->line("  Kommun:    {$stats['scb_kommun']}");
        $this->line("  Län:       {$stats['scb_lan']}");
        $this->line("  Omappad:   {$stats['none']}");

        // Visa de 20 största omappade platserna för manuell granskning.
        $unmapped = collect($rows)
            ->filter(fn($r) => $r['source'] === 'none')
            ->take(20);
        if ($unmapped->isNotEmpty()) {
            $this->newLine();
            $this->info('Topp 20 omappade platser (kräver manuell granskning):');
            $unmappedNames = $unmapped->pluck('bpk_place_name');
            $countByName = $places->keyBy('parsed_title_location');
            foreach ($unmappedNames as $name) {
                $antal = $countByName[$name]->antal ?? 0;
                $this->line("  " . str_pad((string) $antal, 6, ' ', STR_PAD_LEFT) . "  {$name}");
            }
        }

        return self::SUCCESS;
    }

    /**
     * Normalisera platsnamn för matchning: lowercase, åäö → aao, trim, strip whitespace.
     */
    private static function normalize(string $s): string
    {
        $s = mb_strtolower(trim($s));
        $s = strtr($s, [
            'å' => 'a',
            'ä' => 'a',
            'ö' => 'o',
            'é' => 'e',
            'è' => 'e',
            'ü' => 'u',
        ]);
        // Kollapsa whitespace.
        $s = preg_replace('/\s+/', ' ', $s);
        return $s;
    }

    /**
     * Strippa " län" / " lans" suffix för matchning mot lan_namn.
     * "västerbottens län" → "västerbottens" — fortfarande inte match.
     * Vi behöver också strippa "s" från slutet (genitiv).
     */
    private static function stripLanSuffix(string $s): string
    {
        $s = preg_replace('/\s+l[aä]ns?$/u', '', $s);
        $s = preg_replace('/s$/u', '', $s);
        return $s;
    }
}
