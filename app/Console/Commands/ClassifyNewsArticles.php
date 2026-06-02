<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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
        [$placePattern, $ambiguousPattern, $placeMap, $placeLanById] = $this->buildPlacePattern($minPlaceLen);

        if ($placeMap === []) {
            $this->warn('Inga platser hittades i `places`-tabellen — hoppar över klassifikation.');
            return self::SUCCESS;
        }

        $genericTitlePrefixes = array_map(
            'mb_strtolower',
            (array) config('news-classification.generic_title_prefixes', [])
        );
        $titleOnlySources = array_flip((array) config('news-classification.title_only_sources', []));
        $sourceToLan = (array) config('news-classification.source_to_lan', []);
        $sourcePrimaryPlaceId = $this->buildSourcePrimaryPlaceMap();

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

            // Samlings-/digest-sidor (svt-texttv "Inrikesnotiser",
            // "Morgonens nyheter i …") beskriver ingen enskild händelse —
            // hoppa över helt så de aldrig blir place_news-kandidater
            // (todo #82). Markeras ändå som classified ovan (rad sätts före
            // detta skip) → plockas inte upp i nästa batch.
            $titleLower = mb_strtolower(trim($article->title));
            if ($genericTitlePrefixes !== [] && Str::startsWith($titleLower, $genericTitlePrefixes)) {
                continue;
            }

            // Aggregator-källor: matcha bara mot title, inte summary.
            // Google News description listar andra artiklar → falska träffar.
            $useSummary = !isset($titleOnlySources[$article->source]);
            $rawText = trim($article->title.($useSummary ? ' '.($article->summary ?? '') : ''));
            $text = mb_strtolower($rawText);
            if ($text === '') {
                continue;
            }

            if (preg_match($blaljusPattern, $text) !== 1) {
                continue;
            }
            $blaljusHits++;

            // Källa-scope: lokala redaktioner får bara koppla artiklar till
            // sitt eget län. Källor utan scope (dn, aftonbladet, svt-texttv,
            // google-news-se m.fl.) matchar mot alla län.
            $allowedLans = null;
            if (isset($sourceToLan[$article->source])) {
                $allowedLans = array_flip((array) $sourceToLan[$article->source]);
            }

            $placeIds = [];
            $collectMatches = function (array $names) use (&$placeIds, $placeMap, $placeLanById, $allowedLans): void {
                foreach ($names as $matched) {
                    $key = mb_strtolower($matched);
                    if (!isset($placeMap[$key])) {
                        continue;
                    }
                    foreach ($placeMap[$key] as $id) {
                        if ($allowedLans !== null) {
                            $placeLan = $placeLanById[$id] ?? null;
                            if ($placeLan === null || !isset($allowedLans[$placeLan])) {
                                continue;
                            }
                        }
                        $placeIds[$id] = true;
                    }
                }
            };
            if ($placePattern !== '' && preg_match_all($placePattern, $text, $matches) > 0) {
                $collectMatches($matches[1]);
            }
            // Ambigua kommunnamn (Vara m.fl.) matchas case-sensitive mot
            // rå-texten — annars triggar verbet "vara" träff på kommunen.
            if ($ambiguousPattern !== '' && preg_match_all($ambiguousPattern, $rawText, $matches) > 0) {
                $collectMatches($matches[1]);
            }

            // Source-scope fallback: artikel klassad som blåljus men ingen
            // plats matchade i texten. Stora städer rapporteras ofta utifrån
            // stadsdelar (Bromma, Hornsgatan) som inte finns i `places`.
            // Lokala källor får då koppling till sin primära plats.
            if ($placeIds === [] && isset($sourcePrimaryPlaceId[$article->source])) {
                $placeIds[$sourcePrimaryPlaceId[$article->source]] = true;
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
     * Bygger map: source → place_id för fallback-koppling. Källor vars
     * primära plats inte finns i `places` (eller där name+lan inte
     * matchar) hoppas över med en varning — bättre än att tysta sluka
     * konfig-fel.
     *
     * @return array<string, int>
     */
    private function buildSourcePrimaryPlaceMap(): array
    {
        $config = (array) config('news-classification.source_to_primary_place', []);
        if ($config === []) {
            return [];
        }

        $map = [];
        foreach ($config as $source => $spec) {
            if (!is_array($spec) || count($spec) !== 2) {
                $this->warn("Ogiltig source_to_primary_place-konfig för '$source' — förväntar [name, lan].");
                continue;
            }
            [$name, $lan] = $spec;
            $id = DB::table('places')
                ->where('name', $name)
                ->where('lan', $lan)
                ->value('id');
            if ($id === null) {
                $this->warn("Hittade ingen plats för '$source' fallback ($name / $lan) — hoppar över.");
                continue;
            }
            $map[$source] = (int) $id;
        }
        return $map;
    }

    /**
     * Bygger två regex-mönster för plats-namn + lookup-mappar:
     *  - $pattern: case-insensitive mot lower-cased text (default)
     *  - $ambiguousPattern: case-sensitive mot rå-text för kommuner som
     *    kolliderar med vanliga ord (Vara, m.fl.)
     *  - $map: lowercased name → list of place_id (för matchning)
     *  - $lanById: place_id → lan (för källa-scope-filtrering)
     *
     * Två platser kan dela namn (samma ort i två län), så samma matchning
     * kan koppla till flera id.
     *
     * @return array{0: string, 1: string, 2: array<string, list<int>>, 3: array<int, ?string>}
     */
    private function buildPlacePattern(int $minLen): array
    {
        /** @var array<string, list<int>> $map */
        $map = [];
        /** @var array<int, ?string> $lanById */
        $lanById = [];

        DB::table('places')
            ->select('id', 'name', 'lan')
            ->orderBy('id')
            ->chunkById(2000, function ($chunk) use (&$map, &$lanById, $minLen) {
                foreach ($chunk as $row) {
                    $name = trim((string) $row->name);
                    if (mb_strlen($name) < $minLen) {
                        continue;
                    }
                    $key = mb_strtolower($name);
                    $id = (int) $row->id;
                    $map[$key][] = $id;
                    $lanById[$id] = $row->lan !== null ? (string) $row->lan : null;
                }
            });

        if ($map === []) {
            return ['', '', [], []];
        }

        $ambiguous = (array) config('news-classification.ambiguous_place_names', []);
        $ambiguousLower = array_flip(array_map('mb_strtolower', $ambiguous));

        $normalNames = [];
        $ambiguousNames = [];
        foreach (array_keys($map) as $name) {
            if (isset($ambiguousLower[$name])) {
                $ambiguousNames[] = $name;
            } else {
                $normalNames[] = $name;
            }
        }

        // Längsta först — då matchar PCRE "Sundsvalls kommun" före "Sundsvall"
        // när båda finns i texten.
        usort($normalNames, fn ($a, $b) => mb_strlen($b) - mb_strlen($a));
        usort($ambiguousNames, fn ($a, $b) => mb_strlen($b) - mb_strlen($a));

        $pattern = '';
        if ($normalNames !== []) {
            $escaped = array_map(fn ($n) => preg_quote($n, '/'), $normalNames);
            $pattern = '/(?<![\p{L}])('.implode('|', $escaped).')(?![\p{L}])/iu';
        }

        // Ambigua namn behåller ursprunglig casing från config — matchas mot
        // rå-text med `u`-flagga (utan `i`), så "Vara" träffar bara stort V.
        $ambiguousPattern = '';
        if ($ambiguousNames !== []) {
            $originalCase = array_map(fn ($n) => preg_quote($n, '/'), $ambiguous);
            $ambiguousPattern = '/(?<![\p{L}])('.implode('|', $originalCase).')(?![\p{L}])/u';
        }

        return [$pattern, $ambiguousPattern, $map, $lanById];
    }
}
