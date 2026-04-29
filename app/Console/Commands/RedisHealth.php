<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

/**
 * Visar minnesanvändning + eviction-statistik för Redis.
 *
 * Användning:
 *
 *     docker compose exec app php artisan redis:health
 *     docker compose exec app php artisan redis:health --json
 *
 * Tröskelvärden för varning:
 * - peak >80 % av maxmemory  → överväg höjning
 * - evicted_keys > 0          → cache slits ut, taket sannolikt för litet
 * - mem_fragmentation_ratio >1.5 eller <1.0 → fragmentering/swap
 */
class RedisHealth extends Command
{
    protected $signature = 'redis:health {--json : Skriv ut rådata som JSON}';

    protected $description = 'Visar Redis minnesanvändning och eviction-statistik';

    public function handle(): int
    {
        /** @var \Illuminate\Redis\Connections\Connection $conn */
        $conn = Redis::connection();

        $memory = $this->parseInfo($conn->command('info', ['memory']));
        $stats = $this->parseInfo($conn->command('info', ['stats']));

        if ($this->option('json')) {
            $this->line(json_encode([
                'memory' => $memory,
                'stats' => array_intersect_key($stats, array_flip([
                    'evicted_keys',
                    'expired_keys',
                    'keyspace_hits',
                    'keyspace_misses',
                ])),
            ], JSON_PRETTY_PRINT));
            return self::SUCCESS;
        }

        $used = (int) ($memory['used_memory'] ?? 0);
        $peak = (int) ($memory['used_memory_peak'] ?? 0);
        $max = (int) ($memory['maxmemory'] ?? 0);
        $frag = (float) ($memory['mem_fragmentation_ratio'] ?? 0);
        $evicted = (int) ($stats['evicted_keys'] ?? 0);
        $hits = (int) ($stats['keyspace_hits'] ?? 0);
        $misses = (int) ($stats['keyspace_misses'] ?? 0);
        $hitRate = ($hits + $misses) > 0
            ? round($hits / ($hits + $misses) * 100, 1)
            : 0.0;

        $this->info('Redis minnesstatus');
        $this->table(['Nyckel', 'Värde'], [
            ['Använt nu', $this->humanBytes($used)],
            ['Peak', $this->humanBytes($peak)],
            ['Maxmemory', $max > 0 ? $this->humanBytes($max) : '(obegränsat)'],
            ['Peak / max', $max > 0 ? round($peak / $max * 100, 1) . ' %' : '–'],
            ['Fragmentation ratio', number_format($frag, 2)],
            ['Evicted keys (sedan start)', number_format($evicted)],
            ['Hit rate', $hitRate . ' % (' . number_format($hits) . ' / ' . number_format($hits + $misses) . ')'],
        ]);

        $warnings = [];
        if ($max > 0 && $peak / $max > 0.8) {
            $warnings[] = 'Peak ligger över 80 % av maxmemory — överväg att höja taket.';
        }
        if ($evicted > 0) {
            $warnings[] = "Eviction har skett ({$evicted} nycklar) — cache slits ut i förtid.";
        }
        if ($frag > 1.5) {
            $warnings[] = 'Fragmentation ratio >1.5 — minnet är fragmenterat.';
        }
        if ($frag > 0 && $frag < 1.0) {
            $warnings[] = 'Fragmentation ratio <1.0 — Redis swappar (allvarligt).';
        }

        if ($warnings === []) {
            $this->info('Inga varningar — Redis mår bra.');
            return self::SUCCESS;
        }

        foreach ($warnings as $w) {
            $this->warn('⚠ ' . $w);
        }
        return self::SUCCESS;
    }

    /**
     * Redis INFO-svar är "key:value\r\n" — gör om till assoc array.
     *
     * @param  string|array<mixed>  $raw
     * @return array<string,string>
     */
    private function parseInfo(string|array $raw): array
    {
        if (is_array($raw)) {
            $raw = implode("\n", array_map(
                fn ($k, $v) => "{$k}:{$v}",
                array_keys($raw),
                array_values($raw)
            ));
        }

        $out = [];
        foreach (preg_split('/\r?\n/', $raw) as $line) {
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            [$k, $v] = array_pad(explode(':', $line, 2), 2, '');
            $out[$k] = $v;
        }
        return $out;
    }

    private function humanBytes(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = (int) floor(log($bytes, 1024));
        $i = min($i, count($units) - 1);
        return number_format($bytes / (1024 ** $i), 2) . ' ' . $units[$i];
    }
}
