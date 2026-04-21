<?php

namespace App\Console\Commands;

use App\CrimeEvent;
use App\Helper;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;

/**
 * Bygger sitemap.xml och sparar i Redis under `sitemap.xml`.
 * Routen `/sitemap.xml` läser från cachen och serverar direkt.
 *
 * Tidigare skrevs filen till disk (public/ eller storage/app/) men
 * containern har inte skrivrättigheter till public/ och storage-
 * varianten krävde extra fil-I/O för varje request. Redis är redan
 * igång och ger snabbare respons.
 */
class GenerateSitemap extends Command
{
    public const CACHE_KEY = 'sitemap.xml';

    protected $signature = 'sitemap:generate {--events-days=90 : Antal dagar bakåt av events att inkludera}';
    protected $description = 'Bygger sitemap.xml och cachar i Redis';

    public function handle(): int
    {
        $sitemap = Sitemap::create();
        $now = now();

        $static = [
            '/' => ['freq' => Url::CHANGE_FREQUENCY_HOURLY, 'priority' => 1.0],
            '/handelser' => ['freq' => Url::CHANGE_FREQUENCY_HOURLY, 'priority' => 0.9],
            '/statistik' => ['freq' => Url::CHANGE_FREQUENCY_DAILY, 'priority' => 0.7],
            '/lan' => ['freq' => Url::CHANGE_FREQUENCY_DAILY, 'priority' => 0.8],
            '/plats' => ['freq' => Url::CHANGE_FREQUENCY_DAILY, 'priority' => 0.7],
            '/typ' => ['freq' => Url::CHANGE_FREQUENCY_DAILY, 'priority' => 0.6],
            '/vma' => ['freq' => Url::CHANGE_FREQUENCY_HOURLY, 'priority' => 0.7],
            '/om' => ['freq' => Url::CHANGE_FREQUENCY_MONTHLY, 'priority' => 0.3],
        ];

        foreach ($static as $path => $meta) {
            $sitemap->add(
                Url::create($path)
                    ->setLastModificationDate($now)
                    ->setChangeFrequency($meta['freq'])
                    ->setPriority($meta['priority'])
            );
        }

        foreach (Helper::getAllLan() as $lanName) {
            $slug = Str::slug($lanName);
            $sitemap->add(
                Url::create("/lan/{$slug}")
                    ->setLastModificationDate($now)
                    ->setChangeFrequency(Url::CHANGE_FREQUENCY_DAILY)
                    ->setPriority(0.7)
            );
        }

        $daysBack = (int) $this->option('events-days');
        $since = Carbon::now()->subDays($daysBack);

        $eventsAdded = 0;
        CrimeEvent::where('created_at', '>=', $since)
            ->orderBy('id', 'desc')
            ->chunk(500, function ($events) use ($sitemap, &$eventsAdded) {
                foreach ($events as $event) {
                    $permalink = $event->getPermalink();
                    if (! $permalink) {
                        continue;
                    }
                    $sitemap->add(
                        Url::create($permalink)
                            ->setLastModificationDate($event->updated_at ?? $event->created_at)
                            ->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY)
                            ->setPriority(0.5)
                    );
                    $eventsAdded++;
                }
            });

        $xml = $sitemap->render();
        Cache::forever(self::CACHE_KEY, $xml);

        $this->info('Sitemap cachad i Redis under nyckel "' . self::CACHE_KEY . '"');
        $this->line('  Statiska sidor: ' . count($static));
        $this->line('  Län: ' . count(Helper::getAllLan()));
        $this->line("  Händelser (senaste {$daysBack} dagar): {$eventsAdded}");
        $this->line('  XML-storlek: ' . number_format(strlen($xml) / 1024, 1) . ' KB');

        return self::SUCCESS;
    }
}
