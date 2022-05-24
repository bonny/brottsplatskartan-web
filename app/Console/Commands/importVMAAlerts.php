<?php

namespace App\Console\Commands;

use App\Http\Controllers\VMAAlerts;
use Illuminate\Console\Command;

class importVMAAlerts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'vma_alerts:import';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import VMA-alerts';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        VMAAlerts::import();
    }
}
