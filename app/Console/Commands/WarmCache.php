<?php

namespace App\Console\Commands;

use App\Helper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Pre-warm response cache genom att pinga populära sidor.
 *
 * Körs automatiskt via Kernel.php var 15:e minut men kan också köras
 * manuellt:
 *
 *     docker compose -f compose.yaml exec app php artisan cache:warm
 *
 * Varför: Spatie Response Cache (7.7.2) saknar SWR. När TTL löper ut
 * måste FÖRSTA användaren vänta på hela regenereringen (~1-3 s för
 * /stockholm). Pre-warm via scheduler ser till att bots + scheduler,
 * inte användare, betalar den kostnaden.
 */
class WarmCache extends Command
{
    protected $signature = 'cache:warm {--only-hot : Bara topp-sidor, skippa alla län}';

    protected $description = 'Pre-warmar response cache genom att pinga populära URL:er';

    /** De mest besökta sidorna — värms alltid och får ett nytt försök. */
    private const HETA = ['/', '/stockholm', '/vma', '/handelser', '/lan'];

    public function handle(): int
    {
        $baseUrl = rtrim(config('app.url'), '/');

        $urls = self::HETA;

        // Lägg till alla län om inte --only-hot
        if (!$this->option('only-hot')) {
            try {
                foreach (Helper::getAllLan() as $lanName) {
                    // Slugifiera: "Stockholms län" -> "stockholms-lan"
                    $slug = \Illuminate\Support\Str::slug($lanName);
                    $urls[] = "/lan/{$slug}";
                }
            } catch (\Exception $e) {
                $this->warn("Kunde inte hämta län-lista: {$e->getMessage()}");
            }
        }

        $misslyckade = [];
        foreach ($urls as $url) {
            if (! $this->varm($baseUrl . $url, $url, 15)) {
                $misslyckade[] = $url;
            }
        }

        // En kall sida (direkt efter en deploy) kan ta längre tid än 15 s
        // första gången. Bara de mest besökta sidorna får ett nytt försök med
        // längre timeout — under last skulle 45 s × alla län dra ut körningen
        // förbi sitt eget intervall och belasta sajten ytterligare.
        $kvar = [];
        foreach ($misslyckade as $url) {
            if (! in_array($url, self::HETA, true) || ! $this->varm($baseUrl . $url, $url, 45)) {
                $kvar[] = $url;
            }
        }

        $antal = count($urls);
        $this->info(sprintf('Pre-warm klart: %d OK, %d misslyckade', $antal - count($kvar), count($kvar)));
        if ($kvar !== []) {
            Log::warning('cache:warm: sidor svarade inte', ['urls' => $kvar]);
        }

        // Förvärmningen gör bara sajten snabbare — enstaka långsamma sidor
        // är inget fel. Rapportera fel (syns på /status) bara när startsidan
        // inte svarar eller mer än en fjärdedel av sidorna föll.
        $allvarligt = in_array('/', $kvar, true) || count($kvar) > $antal / 4;

        return $allvarligt ? Command::FAILURE : Command::SUCCESS;
    }

    private function varm(string $fullUrl, string $url, int $timeout): bool
    {
        try {
            $response = Http::timeout($timeout)
                ->withOptions(['verify' => false]) // ev. self-signed eller intern cert
                ->get($fullUrl);

            if ($response->successful()) {
                $this->line("  ✓ {$url} ({$response->status()})");
                return true;
            }
            $this->warn("  ⚠ {$url} returnerade {$response->status()}");
        } catch (\Exception $e) {
            $this->error("  ✗ {$url}: {$e->getMessage()}");
        }

        return false;
    }
}
