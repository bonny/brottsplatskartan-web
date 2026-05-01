<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Klassificerar news_articles (todo #64): markerar blåljus-relaterade
 * artiklar och kopplar dem till matchande platser i `places`. Idempotent
 * via `classified_at` (en artikel klassas högst en gång) och unique-
 * index på (place_id, news_article_id).
 */
class ClassifyNewsArticles extends Command
{
    protected $signature = 'app:news:classify {--limit= : Override batch_size från config}';

    protected $description = 'Klassificerar news_articles mot blåljus-termer + plats-namn.';

    public function handle(): int
    {
        $batchSize = (int) ($this->option('limit') ?? config('news-classification.batch_size', 2000));
        $minPlaceLen = (int) config('news-classification.min_place_name_length', 4);

        $blaljusPattern = $this->buildBlaljusPattern();
        [$placePattern, $placeMap] = $this->buildPlacePattern($minPlaceLen);

        if ($placeMap === []) {
            $this->warn('Inga platser hittades i `places`-tabellen — hoppar över klassifikation.');
            return self::SUCCESS;
        }

        $titleOnlySources = array_flip((array) config('news-classification.title_only_sources', []));

        $articles = DB::table('news_articles')
            ->select('id', 'source', 'title', 'summary', 'pubdate')
            ->whereNull('classified_at')
            ->orderBy('id')
            ->limit($batchSize)
            ->get();

        if ($articles->isEmpty()) {
            $this->info('Inga obearbetade artiklar.');
            return self::SUCCESS;
        }

        $now = Carbon::now()->toDateTimeString();
        $blaljusHits = 0;
        $placeRows = 0;
        $classifiedIds = [];

        foreach ($articles as $article) {
            $classifiedIds[] = $article->id;

            // Aggregator-källor: matcha bara mot title, inte summary.
            // Google News description listar andra artiklar → falska träffar.
            $useSummary = !isset($titleOnlySources[$article->source]);
            $rawText = $article->title.($useSummary ? ' '.($article->summary ?? '') : '');
            $text = mb_strtolower(trim($rawText));
            if ($text === '') {
                continue;
            }

            if (preg_match($blaljusPattern, $text) !== 1) {
                continue;
            }
            $blaljusHits++;

            if (preg_match_all($placePattern, $text, $matches) === 0) {
                continue;
            }

            $placeIds = [];
            foreach ($matches[1] as $matched) {
                $key = mb_strtolower($matched);
                if (!isset($placeMap[$key])) {
                    continue;
                }
                foreach ($placeMap[$key] as $id) {
                    $placeIds[$id] = true;
                }
            }

            if ($placeIds === []) {
                continue;
            }

            $rows = [];
            foreach (array_keys($placeIds) as $placeId) {
                $rows[] = [
                    'place_id' => $placeId,
                    'news_article_id' => $article->id,
                    'pubdate' => $article->pubdate,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            $placeRows += DB::table('place_news')->insertOrIgnore($rows);
        }

        // Markera alla bearbetade artiklar i en query — billigare än per-rad-update.
        if ($classifiedIds !== []) {
            DB::table('news_articles')
                ->whereIn('id', $classifiedIds)
                ->update(['classified_at' => $now]);
        }

        $this->info(sprintf(
            'Klassade %d artiklar, blåljus: %d, nya place_news-rader: %d.',
            count($classifiedIds),
            $blaljusHits,
            $placeRows
        ));

        return self::SUCCESS;
    }

    /**
     * Bygger en sammanslagen regex för blåljus-termer. Word-boundary via
     * Unicode-bokstavsklass (`\p{L}`) — `\b` är inte multibyte-säker.
     */
    private function buildBlaljusPattern(): string
    {
        $terms = (array) config('news-classification.blaljus_terms', []);
        $escaped = array_map(fn ($t) => preg_quote((string) $t, '/'), $terms);
        return '/(?<![\p{L}])(?:'.implode('|', $escaped).')(?![\p{L}])/iu';
    }

    /**
     * Bygger en sammanslagen regex för plats-namn + en lookup-map från
     * lowercased name → list of place_id. Två platser kan dela namn
     * (samma ort i två län), så samma matchning kan koppla till flera id.
     *
     * @return array{0: string, 1: array<string, list<int>>}
     */
    private function buildPlacePattern(int $minLen): array
    {
        /** @var array<string, list<int>> $map */
        $map = [];

        DB::table('places')
            ->select('id', 'name')
            ->orderBy('id')
            ->chunkById(2000, function ($chunk) use (&$map, $minLen) {
                foreach ($chunk as $row) {
                    $name = trim((string) $row->name);
                    if (mb_strlen($name) < $minLen) {
                        continue;
                    }
                    $key = mb_strtolower($name);
                    $map[$key][] = (int) $row->id;
                }
            });

        if ($map === []) {
            return ['', []];
        }

        // Längsta först — då matchar PCRE "Sundsvalls kommun" före "Sundsvall"
        // när båda finns i texten.
        $names = array_keys($map);
        usort($names, fn ($a, $b) => mb_strlen($b) - mb_strlen($a));

        $escaped = array_map(fn ($n) => preg_quote($n, '/'), $names);
        $pattern = '/(?<![\p{L}])('.implode('|', $escaped).')(?![\p{L}])/iu';

        return [$pattern, $map];
    }
}
