<?php

namespace App\Console\Commands;

use App\Ai\Agents\NewsClassifier;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * AI-klassifikation av news_articles (todo #64). Körs efter regex-passet
 * (`app:news:classify`) och fångar artiklar som regex missar — bedrägerier,
 * stadsdels-baserade händelser, böjda termer m.m. Skriver `place_news`-rader
 * när AI hittar geografisk koppling, och loggar slutsats + motivering på
 * artikelraden för audit.
 *
 * Modell: Haiku 4.5 (~$26/år vid 300 art/dygn).
 */
class AiClassifyNewsArticles extends Command
{
    protected $signature = 'app:news:ai-classify
        {--limit=50 : Max antal artiklar per körning}
        {--hours=72 : Tidsfönster — bara artiklar med pubdate inom N timmar}
        {--rerun : Bortse från ai_classified_at och kör om alla artiklar inom fönstret}
        {--dry-run : Visa vad som skulle skickas till AI utan att anropa}';

    protected $description = 'Kompletterar regex-klassifikationen med AI (Haiku 4.5) för bättre recall.';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        $hours = (int) $this->option('hours');
        $rerun = (bool) $this->option('rerun');
        $dryRun = (bool) $this->option('dry-run');

        $cutoff = Carbon::now()->subHours($hours);

        $query = DB::table('news_articles')
            ->whereNotNull('classified_at')   // regex har sett artikeln
            ->where('pubdate', '>=', $cutoff)
            ->orderBy('pubdate', 'desc')
            ->limit($limit);

        if (!$rerun) {
            $query->whereNull('ai_classified_at');
        }

        $articles = $query->select('id', 'source', 'title', 'summary')->get();

        if ($articles->isEmpty()) {
            $this->info('Inga obehandlade artiklar inom fönstret.');
            return self::SUCCESS;
        }

        $this->info(sprintf('Klassificerar %d artiklar med Haiku 4.5...', $articles->count()));

        // Place-lookup: kommunnamn → place_id (för att mappa AI:s svar till
        // våra rader). Vi tar emot kommunnamn (utan suffix "kommun"), letar
        // efter exakta matchningar i `places`. Om flera platser delar namn
        // (osannolikt på kommunnivå men teoretiskt möjligt), tar vi alla.
        $kommunMap = $this->buildKommunMap();

        // Pre-filter (todo #81): skippa AI-anropet helt om titel + summary
        // inte innehåller en enda blåljus-term ur config-listan. Sparar
        // ~30-40 % av Haiku-volymen utan att rubba blåljus-recall — listan
        // är medvetet bred (~80 termer) och AI:n skulle ändå avfärda dessa
        // som "ej blåljus". Loggar `prefilter-skip` i ai_reason för audit.
        $blaljusTerms = (array) config('news-classification.blaljus_terms', []);

        $now = Carbon::now()->toDateTimeString();
        $stats = [
            'ai_blaljus' => 0,
            'place_rows_added' => 0,
            'no_place_match' => 0,
            'errors' => 0,
            'prefilter_skipped' => 0,
        ];

        foreach ($articles as $article) {
            $haystack = mb_strtolower(($article->title ?? '') . ' ' . ($article->summary ?? ''));

            $hasKeyword = false;
            foreach ($blaljusTerms as $term) {
                if (mb_strpos($haystack, mb_strtolower((string) $term)) !== false) {
                    $hasKeyword = true;
                    break;
                }
            }

            if (!$hasKeyword) {
                $stats['prefilter_skipped']++;
                if ($dryRun) {
                    $this->line('PREFILTER-SKIP: ' . mb_substr($article->title, 0, 80));
                    continue;
                }
                DB::table('news_articles')->where('id', $article->id)->update([
                    'ai_classified_at' => $now,
                    'ai_is_blaljus' => false,
                    'ai_reason' => '[prefilter-skip] ingen blåljus-term i titel/summary',
                ]);
                continue;
            }

            $userMessage = sprintf(
                "Källa: %s\nTitel: %s\nSammanfattning: %s",
                $article->source,
                $article->title,
                $article->summary ?? ''
            );

            if ($dryRun) {
                $this->line('DRY-RUN: ' . mb_substr($article->title, 0, 80));
                continue;
            }

            try {
                $response = (new NewsClassifier)->prompt($userMessage);
            } catch (\Exception $e) {
                $stats['errors']++;
                Log::warning("NewsClassifier fel artikel-{$article->id}: " . $e->getMessage());
                $this->warn("Fel artikel-{$article->id}: " . $e->getMessage());
                continue;
            }

            $isBlaljus = (bool) ($response['is_blaljus'] ?? false);
            $kommunNames = (array) ($response['kommun_names'] ?? []);
            $confidence = (string) ($response['confidence'] ?? 'okänd');
            $reason = mb_substr((string) ($response['reason'] ?? ''), 0, 500);
            $category = (string) ($response['category'] ?? '');

            // Skriv AI:s slutsats på artikeln (för audit + för att slippa
            // re-anrop nästa körning).
            DB::table('news_articles')->where('id', $article->id)->update([
                'ai_classified_at' => $now,
                'ai_is_blaljus' => $isBlaljus,
                'ai_reason' => "[{$category}/{$confidence}] " . $reason,
            ]);

            if (!$isBlaljus) {
                continue;
            }
            $stats['ai_blaljus']++;

            // Mappa AI:s kommunnamn → place_ids. Saknade namn loggas men
            // bryter inte flow (AI kan halucinera, eller artikeln nämner
            // en utländsk plats).
            $placeIds = [];
            foreach ($kommunNames as $name) {
                $key = mb_strtolower(trim((string) $name));
                if (isset($kommunMap[$key])) {
                    foreach ($kommunMap[$key] as $id) {
                        $placeIds[$id] = true;
                    }
                }
            }

            if ($placeIds === []) {
                $stats['no_place_match']++;
                continue;
            }

            $rows = [];
            foreach (array_keys($placeIds) as $placeId) {
                $rows[] = [
                    'place_id' => $placeId,
                    'news_article_id' => $article->id,
                    'pubdate' => DB::table('news_articles')->where('id', $article->id)->value('pubdate'),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            $stats['place_rows_added'] += DB::table('place_news')->insertOrIgnore($rows);
        }

        $this->info(sprintf(
            'Klart. Prefilter-skip: %d, AI-blåljus: %d, plats-rader: %d, ingen plats-match: %d, fel: %d.',
            $stats['prefilter_skipped'],
            $stats['ai_blaljus'],
            $stats['place_rows_added'],
            $stats['no_place_match'],
            $stats['errors']
        ));

        return self::SUCCESS;
    }

    /**
     * Bygger lookup: lowercased kommunnamn → list of place_ids.
     *
     * AI returnerar kommunnamn utan suffix ("Stockholm", inte "Stockholms
     * kommun"). Vi matchar exakt mot `places.name` — om två platser delar
     * namn returnerar vi alla id för försiktighet.
     *
     * @return array<string, list<int>>
     */
    private function buildKommunMap(): array
    {
        $map = [];
        DB::table('places')
            ->select('id', 'name')
            ->orderBy('id')
            ->chunkById(2000, function ($chunk) use (&$map) {
                foreach ($chunk as $row) {
                    $key = mb_strtolower(trim((string) $row->name));
                    if ($key === '') {
                        continue;
                    }
                    $map[$key][] = (int) $row->id;
                }
            });

        return $map;
    }
}
