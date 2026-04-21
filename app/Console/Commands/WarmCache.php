<?php

namespace App\Console\Commands;

use App\Helper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

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

    public function handle(): int
    {
        $baseUrl = rtrim(config('app.url'), '/');

        $urls = [
            '/',
            '/stockholm',
            '/vma',
            '/handelser',
            '/lan',
        ];

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

        $ok = 0;
        $fail = 0;

        foreach ($urls as $url) {
            $fullUrl = $baseUrl . $url;
            try {
                $response = Http::timeout(15)
                    ->withOptions(['verify' => false]) // ev. self-signed eller intern cert
                    ->get($fullUrl);

                if ($response->successful()) {
                    $this->line("  ✓ {$url} ({$response->status()})");
                    $ok++;
                } else {
                    $this->warn("  ⚠ {$url} returnerade {$response->status()}");
                    $fail++;
                }
            } catch (\Exception $e) {
                $this->error("  ✗ {$url}: {$e->getMessage()}");
                $fail++;
            }
        }

        $this->info("Pre-warm klart: {$ok} OK, {$fail} misslyckade");

        return $fail > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
