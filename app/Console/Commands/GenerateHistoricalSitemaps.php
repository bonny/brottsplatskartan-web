<?php

namespace App\Console\Commands;

use App\CrimeEvent;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;

/**
 * Bygger sitemap-filer för historiska år en gång och sparar till
 * storage/app/sitemaps/sitemap-events-YYYY.xml.
 *
 *   php artisan sitemap:generate-historical        # alla saknade år
 *   php artisan sitemap:generate-historical --year=2022  # bara ett år
 *   php artisan sitemap:generate-historical --force      # även existerande
 */
class GenerateHistoricalSitemaps extends Command
{
    protected $signature = 'sitemap:generate-historical
        {--year= : Specifikt år}
        {--force : Bygg om även om fil redan finns}';

    protected $description = 'Bygger sitemap-filer för historiska år (engångsjobb per år)';

    public function handle(): int
    {
        $currentYear = (int) now()->format('Y');
        $dir = storage_path('app/sitemaps');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        if ($year = $this->option('year')) {
            $years = [(int) $year];
        } else {
            $minYear = (int) (CrimeEvent::min(DB::raw('YEAR(created_at)')) ?: $currentYear);
            $years = range($minYear, $currentYear - 1);
        }

        foreach ($years as $year) {
            $path = GenerateSitemap::historicalPath($year);
            if (file_exists($path) && !$this->option('force')) {
                $this->line("  {$year}: hoppar över (finns redan). Använd --force för att bygga om.");
                continue;
            }

            $this->line("  {$year}: bygger...");
            $sitemap = Sitemap::create();
            $start = Carbon::create($year, 1, 1, 0, 0, 0);
            $end = Carbon::create($year, 12, 31, 23, 59, 59);
            $count = 0;

            CrimeEvent::whereBetween('created_at', [$start, $end])
                ->orderBy('id', 'desc')
                ->chunk(1000, function ($events) use ($sitemap, &$count) {
                    foreach ($events as $event) {
                        $permalink = $event->getPermalink();
                        if (!$permalink) {
                            continue;
                        }
                        // Tunna events sätts till noindex — exkludera ur sitemap (#29).
                        if ($event->isThinForSeo()) {
                            continue;
                        }
                        $url = Url::create($permalink)
                            ->setLastModificationDate($event->updated_at ?? $event->created_at)
                            ->setChangeFrequency(Url::CHANGE_FREQUENCY_YEARLY)
                            ->setPriority(0.4);

                        if ($event->geocoded) {
                            $url->addImage(
                                url: $event->getStaticImageSrc(800, 600),
                                caption: $event->getMapAltText(),
                                title: $event->getSingleEventTitleShort(),
                            );
                        }

                        $sitemap->add($url);
                        $count++;
                    }
                });

            if ($count === 0) {
                $this->warn("  {$year}: inga events, hoppar över fil.");
                continue;
            }

            $sitemap->writeToFile($path);
            $sizeKb = number_format(filesize($path) / 1024, 1);
            $this->info("  {$year}: {$count} events, {$sizeKb} KB → {$path}");
        }

        $this->info('Klart. Glöm inte att köra `sitemap:generate` efteråt så att index uppdateras.');
        return self::SUCCESS;
    }
}
