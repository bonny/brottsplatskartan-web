<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Pruning för events.source='trafikverket' (todo #50, Fas 1).
 *
 * Två retention-regler:
 *  - Olycka: 90 d efter end_time (matchar crime_events).
 *  - Övriga: 30 d efter end_time.
 *
 * Aktiva (end_time IS NULL eller framtid) påverkas aldrig.
 */
class TrafikverketPrune extends Command
{
    protected $signature = 'trafikverket:prune {--dry-run}';

    protected $description = 'Pruna utgångna Trafikverket-rader från events-tabellen.';

    public function handle(): int
    {
        $now = Carbon::now();
        $dryRun = (bool) $this->option('dry-run');

        $base = DB::table('events')
            ->where('source', 'trafikverket')
            ->whereNotNull('end_time');

        $olyckaCutoff = $now->copy()->subDays(90);
        $olyckaCount = (clone $base)
            ->where('message_type', 'Olycka')
            ->where('end_time', '<', $olyckaCutoff)
            ->count();

        $otherCutoff = $now->copy()->subDays(30);
        $otherCount = (clone $base)
            ->where('message_type', '!=', 'Olycka')
            ->where('end_time', '<', $otherCutoff)
            ->count();

        if ($dryRun) {
            $this->info(sprintf('[DRY-RUN] Olycka >90d: %d, övriga >30d: %d', $olyckaCount, $otherCount));
            return self::SUCCESS;
        }

        $deleted = 0;
        if ($olyckaCount > 0) {
            $deleted += (clone $base)
                ->where('message_type', 'Olycka')
                ->where('end_time', '<', $olyckaCutoff)
                ->delete();
        }
        if ($otherCount > 0) {
            $deleted += (clone $base)
                ->where('message_type', '!=', 'Olycka')
                ->where('end_time', '<', $otherCutoff)
                ->delete();
        }

        $this->info(sprintf('Klar. %d rader raderade (Olycka: %d, övriga: %d).', $deleted, $olyckaCount, $otherCount));
        return self::SUCCESS;
    }
}
