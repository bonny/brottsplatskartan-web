<?php

namespace App\Console\Commands;

use App\CrimeEvent;
use App\Helper;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;

/**
 * Genererar public/sitemap.xml nattligen.
 *
 * Scope (april 2026): startsidan, län-översikt + alla 21 län,
 * plats-översikt, typ-översikt, blog/static-sidor + händelser de
 * senaste 90 dagarna. Äldre händelser (miljoner URL:er) utelämnas
 * medvetet — de är fortfarande länkade via interna navigering och
 * indexeras organiskt, men belastar inte sitemap:en.
 */
class GenerateSitemap extends Command
{
    protected $signature = 'sitemap:generate {--events-days=90 : Antal dagar bakåt av events att inkludera}';
    protected $description = 'Genererar public/sitemap.xml';

    public function handle(): int
    {
        $sitemap = Sitemap::create();
        $now = now();

        // Top-level statiska sidor
        $static = [
            '/' => ['freq' => Url::CHANGE_FREQUENCY_HOURLY, 'priority' => 1.0],
            '/handelser' => ['freq' => Url::CHANGE_FREQUENCY_HOURLY, 'priority' => 0.9],
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

        // Län — alla 21
        foreach (Helper::getAllLan() as $lanName) {
            $slug = Str::slug($lanName);
            $sitemap->add(
                Url::create("/lan/{$slug}")
                    ->setLastModificationDate($now)
                    ->setChangeFrequency(Url::CHANGE_FREQUENCY_DAILY)
                    ->setPriority(0.7)
            );
        }

        // Händelser senaste N dagar
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

        $path = public_path('sitemap.xml');
        $sitemap->writeToFile($path);

        $this->info("Sitemap skriven till {$path}");
        $this->line("  Statiska sidor: " . count($static));
        $this->line("  Län: " . count(Helper::getAllLan()));
        $this->line("  Händelser (senaste {$daysBack} dagar): {$eventsAdded}");

        return self::SUCCESS;
    }
}
