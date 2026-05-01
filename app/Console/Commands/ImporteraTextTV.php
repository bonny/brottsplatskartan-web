<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Setting;

class ImporteraTextTV extends Command
{
    protected $signature = 'app:importera-texttv';

    protected $description = 'Importerar text-tv från SVT:s text-tv-sidor.';

    protected $appId = 'brottsplatskartan';

    /**
     * Hämtar och returnerar pages-array, eller null vid fel.
     */
    protected function fetchPages(string $endpoint): ?array
    {
        $response = @file_get_contents($endpoint);
        if ($response === false) {
            return null;
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded) || !isset($decoded['pages']) || !is_array($decoded['pages'])) {
            return null;
        }

        return $decoded['pages'];
    }

    protected function importMostRead(): array
    {
        $pages = $this->fetchPages("https://texttv.nu/api/most_read/news?count=5&{$this->appId}");
        if ($pages === null) {
            $this->error('Kunde inte hämta mest lästa nyheterna');
            return [];
        }

        Setting::set('texttv-most-read', $pages);
        return $pages;
    }

    protected function importLastUpdated(): array
    {
        $pages = $this->fetchPages("https://texttv.nu/api/last_updated/news?count=5&{$this->appId}");
        if ($pages === null) {
            $this->error('Kunde inte hämta senaste nyheterna');
            return [];
        }

        Setting::set('texttv-last-updated', $pages);
        return $pages;
    }

    /**
     * Skriver text-tv-sidor till news_articles så klassifikations-pipelinen
     * (todo #64) kan koppla blåljus-relaterade sidor till platser.
     * Dedup via content_hash på source|permalink — same page i båda
     * endpoints ger en rad, inte två.
     */
    protected function writeToNewsArticles(array $pages): int
    {
        if ($pages === []) {
            return 0;
        }

        $now = Carbon::now()->toDateTimeString();
        $rows = [];

        foreach ($pages as $page) {
            $url = trim((string) ($page['permalink'] ?? ''));
            $title = trim((string) ($page['title'] ?? ''));
            if ($url === '' || $title === '' || !preg_match('#^https?://#i', $url)) {
                continue;
            }

            $rawContent = (string) ($page['page_content'] ?? '');
            $summary = trim(preg_replace('/\s+/u', ' ', $rawContent) ?? '');

            $pubdate = null;
            if (!empty($page['date_added_unix'])) {
                $pubdate = Carbon::createFromTimestamp((int) $page['date_added_unix'])->toDateTimeString();
            } elseif (!empty($page['date_added'])) {
                try {
                    $pubdate = Carbon::parse((string) $page['date_added'])->toDateTimeString();
                } catch (\Throwable) {
                    $pubdate = null;
                }
            }

            $rows[] = [
                'source' => 'svt-texttv',
                'url' => Str::limit($url, 2000, ''),
                'title' => Str::limit($title, 500, ''),
                'summary' => $summary !== '' ? Str::limit($summary, 2000, '') : null,
                'pubdate' => $pubdate,
                'content_hash' => hash('sha256', 'svt-texttv|'.$url),
                'fetched_at' => $now,
            ];
        }

        if ($rows === []) {
            return 0;
        }

        return DB::table('news_articles')->insertOrIgnore($rows);
    }

    public function handle()
    {
        $mostRead = $this->importMostRead();
        $lastUpdated = $this->importLastUpdated();

        $newRows = $this->writeToNewsArticles(array_merge($lastUpdated, $mostRead));

        $this->info(sprintf(
            'Hämtade %d sidor från text-tv.nu (%d nya i news_articles).',
            count($mostRead) + count($lastUpdated),
            $newRows
        ));
    }
}
