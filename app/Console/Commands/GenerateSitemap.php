<?php

namespace App\Console\Commands;

use App\CrimeEvent;
use App\Helper;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\SitemapIndex;
use Spatie\Sitemap\Tags\Sitemap as IndexEntry;
use Spatie\Sitemap\Tags\Url;

/**
 * Bygger sitemap-suite:
 *
 *   /sitemap.xml                    index, cachas i Redis
 *   /sitemap-main.xml               statiska + län + plats + typ, Redis
 *   /sitemap-events-{year}.xml      aktuellt år: Redis (regen var 30 min)
 *                                   historiska år: storage/app/sitemaps/
 *
 * Historiska år byggs en gång via `sitemap:generate-historical` och
 * läggs på disk — de ändras aldrig. Redis är bara för de delar som
 * regenereras ofta. Disk-placeringen är storage/app/sitemaps/ som
 * app-containern har skrivrätt till (och som persistar via volume).
 */
class GenerateSitemap extends Command
{
    public const CACHE_PREFIX = 'sitemap:';

    protected $signature = 'sitemap:generate';
    protected $description = 'Bygger main + aktuellt-års events + index i Redis';

    public function handle(): int
    {
        $now = now();
        $currentYear = (int) $now->format('Y');

        // 1. Main sitemap: statiska sidor + län
        $main = Sitemap::create();

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
            $main->add(
                Url::create($path)
                    ->setLastModificationDate($now)
                    ->setChangeFrequency($meta['freq'])
                    ->setPriority($meta['priority'])
            );
        }

        foreach (Helper::getAllLan() as $lanName) {
            $slug = Str::slug($lanName);
            $main->add(
                Url::create("/lan/{$slug}")
                    ->setLastModificationDate($now)
                    ->setChangeFrequency(Url::CHANGE_FREQUENCY_DAILY)
                    ->setPriority(0.7)
            );

            // Månadsvyer för länet — 12 senaste månader (todo #25).
            // Tomma månader 301:as till länets startsida i kontrollern,
            // så Google rensar dem naturligt.
            foreach ($this->recentMonths(12) as $m) {
                $main->add(
                    Url::create(sprintf('/lan/%s/handelser/%s/%s', rawurlencode($lanName), $m['year'], $m['month']))
                        ->setLastModificationDate($m['lastmod'])
                        ->setChangeFrequency(Url::CHANGE_FREQUENCY_MONTHLY)
                        ->setPriority(0.6)
                );
            }
        }

        // Tier 1-städer: månadsvyer via /plats/{slug}/handelser/{year}/{month}.
        // Slug-listan matchar CityRedirectMiddleware (todo #24).
        $tier1Cities = ['uppsala', 'stockholm', 'malmo', 'goteborg', 'helsingborg'];
        foreach ($tier1Cities as $cityCity) {
            foreach ($this->recentMonths(12) as $m) {
                $main->add(
                    Url::create(sprintf('/plats/%s/handelser/%s/%s', $cityCity, $m['year'], $m['month']))
                        ->setLastModificationDate($m['lastmod'])
                        ->setChangeFrequency(Url::CHANGE_FREQUENCY_MONTHLY)
                        ->setPriority(0.6)
                );
            }
        }

        Cache::forever(self::CACHE_PREFIX . 'main', $main->render());

        // 2. Events för aktuellt år
        $eventsYear = $this->buildYearSitemap($currentYear);
        $count = $eventsYear['count'];
        Cache::forever(self::CACHE_PREFIX . "events-{$currentYear}", $eventsYear['xml']);

        // 3. Index — refererar main + alla år (aktuellt i Redis, historiska på disk)
        $index = SitemapIndex::create();
        $index->add(IndexEntry::create(url('/sitemap-main.xml'))->setLastModificationDate($now));
        $index->add(IndexEntry::create(url("/sitemap-events-{$currentYear}.xml"))->setLastModificationDate($now));

        foreach ($this->historicalYears($currentYear) as $year) {
            $path = $this->historicalPath($year);
            if (file_exists($path)) {
                $index->add(
                    IndexEntry::create(url("/sitemap-events-{$year}.xml"))
                        ->setLastModificationDate(Carbon::createFromTimestamp(filemtime($path)))
                );
            }
        }

        Cache::forever(self::CACHE_PREFIX . 'index', $index->render());

        $this->info('Sitemap-suite cachad i Redis.');
        $this->line('  main (statiska + län): OK');
        $this->line("  events-{$currentYear}: {$count} händelser");
        $this->line('  index: skapad');

        return self::SUCCESS;
    }

    private function buildYearSitemap(int $year): array
    {
        $sitemap = Sitemap::create();
        $start = Carbon::create($year, 1, 1, 0, 0, 0);
        $end = Carbon::create($year, 12, 31, 23, 59, 59);
        $count = 0;

        CrimeEvent::whereBetween('created_at', [$start, $end])
            ->orderBy('id', 'desc')
            ->chunk(1000, function ($events) use ($sitemap, &$count) {
                foreach ($events as $event) {
                    $permalink = $event->getPermalink();
                    if (! $permalink) {
                        continue;
                    }
                    // Tunna events sätts till noindex i routen — exkludera ur
                    // sitemap också så Google inte återupptäcker dem (#29).
                    if ($event->isThinForSeo()) {
                        continue;
                    }
                    $url = Url::create($permalink)
                        ->setLastModificationDate($event->updated_at ?? $event->created_at)
                        ->setChangeFrequency(Url::CHANGE_FREQUENCY_MONTHLY)
                        ->setPriority(0.5);

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

        return ['xml' => $sitemap->render(), 'count' => $count];
    }

    public static function historicalPath(int $year): string
    {
        return storage_path("app/sitemaps/sitemap-events-{$year}.xml");
    }

    /**
     * Bygg en lista över de N senaste månaderna (inkl. aktuell), för
     * sitemap-URL:er till månadsvyer (todo #25).
     *
     * @return list<array{year: string, month: string, lastmod: Carbon}>
     */
    private function recentMonths(int $count): array
    {
        $months = [];
        $start = now()->startOfMonth();
        for ($i = 0; $i < $count; $i++) {
            $m = (clone $start)->subMonths($i);
            // lastmod: aktuell månad använder now() (uppdateras dagligen),
            // historiska månader använder månadens slut (statiskt).
            $lastmod = $i === 0 ? now() : (clone $m)->endOfMonth();
            $months[] = [
                'year' => $m->format('Y'),
                'month' => $m->format('m'),
                'lastmod' => $lastmod,
            ];
        }
        return $months;
    }

    /**
     * Alla år mellan första events-året i DB och året innan aktuellt.
     */
    public function historicalYears(int $currentYear): array
    {
        $minYear = (int) Cache::remember(
            'sitemap:min-event-year',
            7 * DAY_IN_SECONDS,
            fn () => CrimeEvent::min(\DB::raw('YEAR(created_at)')) ?: $currentYear
        );
        return range($minYear, $currentYear - 1);
    }
}
