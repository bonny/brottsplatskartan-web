<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use SimplePie\SimplePie;
use Throwable;

class FetchNewsRss extends Command
{
    protected $signature = 'app:news:fetch-rss {--source= : Hämta bara en specifik source}';

    protected $description = 'Hämtar nyheter från RSS-feeds.';

    public function handle(): int
    {
        $feeds = config('news-feeds.feeds', []);
        $only = $this->option('source');

        if ($only !== null) {
            $feeds = array_values(array_filter($feeds, fn ($f) => $f['source'] === $only));
            if ($feeds === []) {
                $this->error("Hittade ingen feed med source={$only}.");
                return self::FAILURE;
            }
        }

        $totalNew = 0;
        $totalDupes = 0;
        $totalErrors = 0;

        foreach ($feeds as $feed) {
            try {
                [$new, $dupes] = $this->fetchFeed($feed['source'], $feed['url']);
                $totalNew += $new;
                $totalDupes += $dupes;
                $this->line(sprintf('  %-20s  %3d nya, %3d dupes', $feed['source'], $new, $dupes));
            } catch (Throwable $e) {
                $totalErrors++;
                $this->warn(sprintf('  %-20s  FEL: %s', $feed['source'], $e->getMessage()));
                Log::warning('news:fetch-rss feed failed', [
                    'source' => $feed['source'],
                    'url' => $feed['url'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info(sprintf('Klar. Nya: %d, dupes: %d, fel: %d', $totalNew, $totalDupes, $totalErrors));

        return self::SUCCESS;
    }

    /**
     * @return array{0:int,1:int} [nyaSparade, hoppadeDupes]
     */
    private function fetchFeed(string $source, string $url): array
    {
        $sp = new SimplePie();
        $sp->set_feed_url($url);
        $sp->enable_cache(false);
        $sp->set_timeout((int) config('news-feeds.http_timeout', 8));
        $sp->set_useragent('Brottsplatskartan/1.0 (+https://brottsplatskartan.se)');
        $sp->init();

        if ($sp->error()) {
            $err = $sp->error();
            throw new \RuntimeException(is_array($err) ? implode('; ', $err) : (string) $err);
        }

        $now = Carbon::now()->toDateTimeString();
        $rows = [];

        foreach ($sp->get_items() as $item) {
            $fullUrl = (string) ($item->get_permalink() ?? '');
            $title = trim((string) ($item->get_title() ?? ''));

            if ($fullUrl === '' || $title === '') {
                continue;
            }

            // Hasha fulla URL:en innan kolumn-trunkering — annars kan två
            // URL:er som skiljer sig efter pos 2000 hashas lika.
            $hash = hash('sha256', $source.'|'.$fullUrl);

            $rawSummary = (string) ($item->get_description() ?? '');
            $summary = trim(strip_tags(html_entity_decode($rawSummary, ENT_QUOTES | ENT_HTML5, 'UTF-8')));

            $pubTs = $item->get_date('U');
            $pubdate = is_numeric($pubTs) ? Carbon::createFromTimestamp((int) $pubTs)->toDateTimeString() : null;

            $rows[] = [
                'source' => $source,
                'url' => Str::limit($fullUrl, 2000, ''),
                'title' => Str::limit($title, 500, ''),
                'summary' => $summary !== '' ? Str::limit($summary, 2000, '') : null,
                'pubdate' => $pubdate,
                'content_hash' => $hash,
                'fetched_at' => $now,
            ];
        }

        if ($rows === []) {
            return [0, 0];
        }

        $new = 0;
        foreach (array_chunk($rows, 100) as $chunk) {
            $new += DB::table('news_articles')->insertOrIgnore($chunk);
        }

        return [$new, count($rows) - $new];
    }
}
