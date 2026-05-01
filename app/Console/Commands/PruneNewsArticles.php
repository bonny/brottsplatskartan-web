<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PruneNewsArticles extends Command
{
    protected $signature = 'app:news:prune {--days= : Override retention_days från config}';

    protected $description = 'Prunar news_articles äldre än retention-fönstret.';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?? config('news-feeds.retention_days', 90));
        $cutoff = Carbon::now()->subDays($days);

        $deleted = DB::table('news_articles')
            ->where('fetched_at', '<', $cutoff)
            ->delete();

        $this->info(sprintf('Tog bort %d rader äldre än %s (%dd).', $deleted, $cutoff->toDateTimeString(), $days));

        return self::SUCCESS;
    }
}
