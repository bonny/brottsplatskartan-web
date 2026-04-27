<?php

namespace App\Console\Commands;

use App\Place;
use Carbon\Carbon;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Backfilla Wikidata-Q-id för alla platser i `places`-tabellen (todo #32).
 *
 * Auto-accept-regler:
 *   - exakt 1 träff från `wbsearchentities`
 *   - description matchar (kommun|stad|ort|län|tätort|kommun.*sverige)
 * Annars markeras `wikidata_review_needed = true` och lämnas till
 * manuell sanering.
 *
 * Wikidata API:t har 1000 calls/min utan auth. Vi kör med 100ms-paus
 * mellan calls = 600 calls/min för att vara extra snälla.
 */
#[Signature('wikidata:backfill {--limit= : Max antal platser att processa} {--force : Backfilla även redan verifierade}')]
#[Description('Backfilla Wikidata-Q-id för platser (todo #32). Auto-accept där entydig, manuell review-kö för rest.')]
class WikidataBackfill extends Command
{
    private const API_URL = 'https://www.wikidata.org/w/api.php';
    private const SLEEP_MS = 100;

    /**
     * Wikidata returnerar mest engelska descriptions även med language=sv.
     * Vi filtrerar till svenska platser via "Sweden"+plats-typ-mönster.
     * Prioriterad ordning: Municipality > urban area > parish > övrigt.
     */
    private const SWEDISH_PLACE_PATTERN = '/(municipality|urban area|locality|city|town|village|parish|district|county|hamlet).*sweden/i';

    private const PRIORITY_PATTERNS = [
        'municipality' => '/\bmunicipality\b.*sweden/i',
        'urban_area' => '/urban area.*sweden/i',
        'city' => '/\bcity\b.*sweden/i',
        'town' => '/\btown\b.*sweden/i',
        'locality' => '/\blocality\b.*sweden/i',
    ];

    public function handle(): int
    {
        $query = Place::query();

        if (!$this->option('force')) {
            $query->whereNull('wikidata_verified_at');
        }

        if ($limit = $this->option('limit')) {
            $query->limit((int) $limit);
        }

        $places = $query->get();
        $total = $places->count();

        if ($total === 0) {
            $this->info('Inga platser att processa.');
            return self::SUCCESS;
        }

        $this->info("Processar $total platser mot Wikidata API...");
        $bar = $this->output->createProgressBar($total);

        $autoAccepted = 0;
        $needsReview = 0;
        $noMatch = 0;
        $errors = 0;

        foreach ($places as $place) {
            try {
                $result = $this->lookupPlace($place->name);

                if ($result === null) {
                    $place->wikidata_qid = null;
                    $place->wikidata_review_needed = true;
                    $noMatch++;
                } else {
                    [$qid, $autoAccept] = $result;
                    $place->wikidata_qid = $qid;
                    $place->wikidata_review_needed = !$autoAccept;
                    $autoAccept ? $autoAccepted++ : $needsReview++;
                }

                $place->wikidata_verified_at = Carbon::now();
                $place->save();
            } catch (\Throwable $e) {
                $errors++;
                $this->newLine();
                $this->error("Fel för '{$place->name}': {$e->getMessage()}");
            }

            $bar->advance();
            usleep(self::SLEEP_MS * 1000);
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("Klart!");
        $this->table(
            ['Auto-accepted', 'Behöver review', 'Ingen träff', 'Fel'],
            [[$autoAccepted, $needsReview, $noMatch, $errors]],
        );

        if ($needsReview > 0) {
            $this->warn("$needsReview platser markerade för manuell review.");
            $this->line("Lista dem med: php artisan tinker -> App\\Place::where('wikidata_review_needed', true)->whereNotNull('wikidata_qid')->get()");
        }

        return self::SUCCESS;
    }

    /**
     * Slå upp en plats mot Wikidata. Returnerar [qid, autoAccept] eller null.
     */
    private function lookupPlace(string $name): ?array
    {
        $response = Http::timeout(10)
            ->withHeaders([
                'User-Agent' => 'Brottsplatskartan/1.0 (https://brottsplatskartan.se; par.thernstrom@gmail.com) wikidata-backfill',
            ])
            ->get(self::API_URL, [
                'action' => 'wbsearchentities',
                'search' => $name,
                'language' => 'sv',
                'type' => 'item',
                'format' => 'json',
                'limit' => 5,
            ]);

        if (!$response->successful()) {
            throw new \RuntimeException("Wikidata API HTTP {$response->status()}");
        }

        $hits = $response->json('search') ?? [];

        if (empty($hits)) {
            return null;
        }

        // Filtrera till svenska platser.
        $swedishHits = array_values(array_filter(
            $hits,
            fn ($hit) => preg_match(self::SWEDISH_PLACE_PATTERN, $hit['description'] ?? ''),
        ));

        if (empty($swedishHits)) {
            return null;
        }

        // Prioritera Municipality > urban area > city > town > locality.
        // Polisens RSS rapporterar oftast på kommunnivå, så Municipality
        // är rätt default för 90 % av svenska platser.
        foreach (self::PRIORITY_PATTERNS as $priorityHit) {
            foreach ($swedishHits as $hit) {
                if (preg_match($priorityHit, $hit['description'] ?? '')) {
                    return [$hit['id'], true];
                }
            }
        }

        // Svensk plats men ingen prioritets-typ matchar → använd första, flagga review.
        return [$swedishHits[0]['id'], false];
    }
}
