<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Rapport över Anthropic-API-användning som loggats av `LogAiUsage`-listenern.
 * Se todo #81. Exempel:
 *   php artisan ai:usage
 *   php artisan ai:usage --days=30 --by=agent
 *   php artisan ai:usage --by=model
 *   php artisan ai:usage --by=day
 */
class AiUsageReport extends Command
{
    protected $signature = 'ai:usage
        {--days=7 : Hur många dagar bakåt att summera}
        {--by=day : Gruppera efter day|agent|model}';

    protected $description = 'Rapportera Anthropic-API-kostnad från ai_usage_logs (todo #81)';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $by = (string) $this->option('by');

        if (! in_array($by, ['day', 'agent', 'model'], true)) {
            $this->error("Ogiltigt --by-värde: {$by}. Använd day|agent|model.");
            return Command::FAILURE;
        }

        $since = now()->subDays($days);

        $groupCol = match ($by) {
            'day' => DB::raw('DATE(created_at)'),
            'agent' => 'agent',
            'model' => 'model',
        };

        $rows = DB::table('ai_usage_logs')
            ->where('created_at', '>=', $since)
            ->select(
                $groupCol,
                DB::raw('COUNT(*) AS calls'),
                DB::raw('SUM(input_tokens) AS input'),
                DB::raw('SUM(output_tokens) AS output'),
                DB::raw('SUM(cache_read_tokens) AS cache_read'),
                DB::raw('SUM(cache_write_tokens) AS cache_write'),
                DB::raw('SUM(cost_usd_micros) AS cost_micros'),
                DB::raw('SUM(CASE WHEN cost_usd_micros IS NULL THEN 1 ELSE 0 END) AS unpriced_calls'),
            )
            ->groupBy($by === 'day' ? DB::raw('DATE(created_at)') : $by)
            ->orderByDesc($by === 'day' ? DB::raw('DATE(created_at)') : 'calls')
            ->get();

        if ($rows->isEmpty()) {
            $this->line("Inga ai_usage_logs senaste {$days} dagar.");
            return Command::SUCCESS;
        }

        $columnKey = match ($by) {
            'day' => 'DATE(created_at)',
            'agent' => 'agent',
            'model' => 'model',
        };

        $tableRows = $rows->map(fn ($r) => [
            $r->$columnKey,
            (int) $r->calls,
            number_format((int) $r->input),
            number_format((int) $r->output),
            number_format((int) $r->cache_read),
            number_format((int) $r->cache_write),
            $r->cost_micros !== null ? '$' . number_format($r->cost_micros / 1_000_000, 4) : 'n/a',
            (int) $r->unpriced_calls > 0 ? (int) $r->unpriced_calls : '',
        ])->toArray();

        $this->line("AI-användning senaste {$days} dagar (grupperat per {$by}):");
        $this->table(
            [ucfirst($by), 'Calls', 'Input', 'Output', 'Cache read', 'Cache write', 'Cost USD', 'Unpriced'],
            $tableRows,
        );

        $total = $rows->sum('cost_micros');
        $totalCalls = $rows->sum('calls');
        $this->line(sprintf(
            'Total: %d anrop, $%s USD (snitt $%s/dygn)',
            $totalCalls,
            number_format($total / 1_000_000, 4),
            number_format(($total / 1_000_000) / $days, 4),
        ));

        $unpricedTotal = $rows->sum('unpriced_calls');
        if ($unpricedTotal > 0) {
            $this->warn("Varning: {$unpricedTotal} anrop saknar pris (okänd modell i config/ai-pricing.php). Kör med --by=model för att hitta modellen.");
        }

        return Command::SUCCESS;
    }
}
