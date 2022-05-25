<?php

namespace App\Console\Commands;

use App\Http\Controllers\VMAAlerts;
use Illuminate\Console\Command;

class ImportVMAAlerts extends Command
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
        $this->info(sprintf('Startar import av VMA-meddelanden från %s.', config('app.vma_alerts_url')));
        ['importedAlerts' => $importedAlerts] = VMAAlerts::import();
        $this->line(sprintf('%d meddelanden importerades eller uppdaterades.', count($importedAlerts)));
        $this->line('Import klar.');
    }
}
