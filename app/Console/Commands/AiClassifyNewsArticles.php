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

        // Prefilter (todo #81 fas 2): skippa AI-anropet om titel + summary
        // inte innehåller en blåljus-term enligt fyra mekanismer:
        //  1. ord-prefix-stam (polis → polisens, polismannen)
        //  2. sammansättnings-suffix (Rönningemordet, dödsolyckan)
        //  3. foreign-veto (utländsk plats i titeln + ingen svensk markör)
        // Mätt mot 7d prod-data 2026-05-25: 98.8 % recall, 45.8 % skip-rate.
        // Förväntad besparing: ~$27/mån.
        [$prefixPattern, $suffixPattern, $foreignPattern, $swedishPattern]
            = $this->buildPrefilterPatterns();

        $now = Carbon::now()->toDateTimeString();
        $stats = [
            'ai_blaljus' => 0,
            'place_rows_added' => 0,
            'no_place_match' => 0,
            'errors' => 0,
            'prefilter_skipped' => 0,
            'prefilter_foreign_veto' => 0,
        ];

        foreach ($articles as $article) {
            $title = (string) ($article->title ?? '');
            $summary = (string) ($article->summary ?? '');
            $text = $title . ' ' . $summary;

            $hasTerm = $prefixPattern !== '' && preg_match($prefixPattern, $text) === 1;
            if (!$hasTerm && $suffixPattern !== '') {
                $hasTerm = preg_match($suffixPattern, $text) === 1;
            }

            if (!$hasTerm) {
                $stats['prefilter_skipped']++;
                if ($dryRun) {
                    $this->line('PREFILTER-SKIP (ingen term): ' . mb_substr($title, 0, 80));
                    continue;
                }
                DB::table('news_articles')->where('id', $article->id)->update([
                    'ai_classified_at' => $now,
                    'ai_is_blaljus' => false,
                    'ai_reason' => '[prefilter-skip] ingen blåljus-term i titel/summary',
                ]);
                continue;
            }

            // Foreign-veto: utländsk markör i titeln OCH ingen svensk
            // markör i hela texten. Swedish-check hoppas över när
            // foreign-pattern redan failat — sparar ett preg_match per
            // artikel som passerat term-filtret.
            $hasForeignInTitle = $foreignPattern !== ''
                && preg_match($foreignPattern, $title) === 1;
            $hasSwedish = $hasForeignInTitle
                && $swedishPattern !== ''
                && preg_match($swedishPattern, $text) === 1;

            if ($hasForeignInTitle && !$hasSwedish) {
                $stats['prefilter_foreign_veto']++;
                if ($dryRun) {
                    $this->line('PREFILTER-SKIP (foreign-veto): ' . mb_substr($title, 0, 80));
                    continue;
                }
                DB::table('news_articles')->where('id', $article->id)->update([
                    'ai_classified_at' => $now,
                    'ai_is_blaljus' => false,
                    'ai_reason' => '[prefilter-skip] utländsk plats i titel, ingen svensk markör',
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
            'Klart. Prefilter-skip: %d (utan term) + %d (foreign-veto), AI-blåljus: %d, '
            . 'plats-rader: %d, ingen plats-match: %d, fel: %d.',
            $stats['prefilter_skipped'],
            $stats['prefilter_foreign_veto'],
            $stats['ai_blaljus'],
            $stats['place_rows_added'],
            $stats['no_place_match'],
            $stats['errors']
        ));

        return self::SUCCESS;
    }

    /**
     * Bygger fyra regex-mönster för prefiltret:
     *  - prefix: `(?<![\p{L}])(stam)\p{L}*` — matchar ord som börjar med stam
     *  - suffix: `\p{L}(term)\p{L}*(?![\p{L}])` — kräver ordtecken före (sammansättning)
     *  - foreign: `(?<![\p{L}])(land)(?![\p{L}])` — matchar utländska orter som hela ord
     *  - swedish: `(?<![\p{L}])(markör)(?![\p{L}])` — matchar svenska markörer som hela ord
     *
     * Word-boundary baseras på Unicode-bokstäver (`\p{L}`) — `\b` är inte
     * multibyte-säker.
     *
     * Tom sträng returneras om motsvarande config-lista är tom.
     *
     * @return array{0: string, 1: string, 2: string, 3: string}
     */
    private function buildPrefilterPatterns(): array
    {
        $alternation = function (array $terms): string {
            $clean = [];
            foreach ($terms as $term) {
                $t = trim((string) $term);
                if ($t === '') {
                    continue;
                }
                $clean[$t] = true;
            }
            if ($clean === []) {
                return '';
            }
            // Längsta först — PCRE matchar det första alternativet, så
            // `polisens` föredras före `polis` när bägge listas.
            $sorted = array_keys($clean);
            usort($sorted, fn ($a, $b) => mb_strlen($b) - mb_strlen($a));
            $escaped = array_map(fn ($s) => preg_quote($s, '/'), $sorted);
            return implode('|', $escaped);
        };

        $prefixAlt = $alternation((array) config('news-classification.prefilter_prefix_stems', []));
        $suffixAlt = $alternation((array) config('news-classification.prefilter_suffix_terms', []));
        $foreignAlt = $alternation((array) config('news-classification.prefilter_foreign_places', []));
        $swedishAlt = $alternation((array) config('news-classification.prefilter_swedish_markers', []));

        return [
            $prefixAlt === '' ? '' : '/(?<![\p{L}])(?:'.$prefixAlt.')\p{L}*/iu',
            $suffixAlt === '' ? '' : '/\p{L}(?:'.$suffixAlt.')\p{L}*(?![\p{L}])/iu',
            $foreignAlt === '' ? '' : '/(?<![\p{L}])(?:'.$foreignAlt.')(?![\p{L}])/iu',
            $swedishAlt === '' ? '' : '/(?<![\p{L}])(?:'.$swedishAlt.')(?![\p{L}])/iu',
        ];
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
