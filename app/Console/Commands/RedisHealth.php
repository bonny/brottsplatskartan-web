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
        $server = $this->parseInfo($conn->command('info', ['server']));

        $lastCacheFlush = $conn->command('get', ['bpk:meta:last-cache-flush']);
        $lastResponseFlush = $conn->command('get', ['bpk:meta:last-responsecache-flush']);

        if ($this->option('json')) {
            $this->line(json_encode([
                'server' => array_intersect_key($server, array_flip([
                    'redis_version',
                    'uptime_in_seconds',
                    'uptime_in_days',
                ])),
                'memory' => $memory,
                'stats' => array_intersect_key($stats, array_flip([
                    'evicted_keys',
                    'expired_keys',
                    'keyspace_hits',
                    'keyspace_misses',
                ])),
                'last_cache_flush' => $lastCacheFlush,
                'last_responsecache_flush' => $lastResponseFlush,
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

        $uptime = (int) ($server['uptime_in_seconds'] ?? 0);
        $version = $server['redis_version'] ?? '?';

        $this->info('Redis minnesstatus');
        $this->table(['Nyckel', 'Värde'], [
            ['Version', $version],
            ['Uptime', $this->humanDuration($uptime)],
            ['Använt nu', $this->humanBytes($used)],
            ['Peak', $this->humanBytes($peak)],
            ['Maxmemory', $max > 0 ? $this->humanBytes($max) : '(obegränsat)'],
            ['Peak / max', $max > 0 ? round($peak / $max * 100, 1) . ' %' : '–'],
            ['Fragmentation ratio', number_format($frag, 2)],
            ['Evicted keys (sedan start)', number_format($evicted)],
            ['Hit rate', $hitRate . ' % (' . number_format($hits) . ' / ' . number_format($hits + $misses) . ')'],
            ['Senaste cache:clear', $this->formatFlushTime($lastCacheFlush)],
            ['Senaste responsecache:clear', $this->formatFlushTime($lastResponseFlush)],
        ]);

        $warnings = [];
        if ($max > 0 && $peak / $max > 0.8) {
            $warnings[] = 'Peak ligger över 80 % av maxmemory — överväg att höja taket.';
        }
        if ($evicted > 0) {
            $warnings[] = "Eviction har skett ({$evicted} nycklar) — cache slits ut i förtid.";
        }
        // Fragmentation ratio är bara meningsfull när used_memory är stort nog
        // att allokator-overhead (~20–30 MB baseline) inte dominerar. Under
        // ~100 MB blir ratio mekaniskt hög utan att spegla riktig fragmentering.
        $fragMeaningful = $used > 100 * 1024 * 1024;
        if ($frag > 1.5 && $fragMeaningful) {
            $warnings[] = 'Fragmentation ratio >1.5 — minnet är fragmenterat.';
        }
        if ($frag > 0 && $frag < 1.0 && $fragMeaningful) {
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

    private function formatFlushTime(mixed $iso): string
    {
        if (!is_string($iso) || $iso === '') {
            return 'aldrig (sedan listener installerades)';
        }
        try {
            $when = \Carbon\Carbon::parse($iso);
            return $when->format('Y-m-d H:i') . ' (' . $when->diffForHumans() . ')';
        } catch (\Exception) {
            return $iso;
        }
    }

    private function humanDuration(int $seconds): string
    {
        if ($seconds <= 0) {
            return '–';
        }
        $days = intdiv($seconds, 86400);
        $hours = intdiv($seconds % 86400, 3600);
        $mins = intdiv($seconds % 3600, 60);

        $parts = [];
        if ($days > 0) {
            $parts[] = "{$days}d";
        }
        if ($hours > 0) {
            $parts[] = "{$hours}h";
        }
        if ($mins > 0 && $days === 0) {
            $parts[] = "{$mins}m";
        }
        if ($parts === []) {
            $parts[] = "{$seconds}s";
        }
        return implode(' ', $parts);
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
