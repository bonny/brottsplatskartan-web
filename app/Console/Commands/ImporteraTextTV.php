<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ImporteraTextTV extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:importera-texttv';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Importerar text-tv från SVT:s text-tv-sidor.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $appId = 'brottsplatskartan';
        $mostReadAPIEndpoint = "https://texttv.nu/api/most_read/news?count=5&{$appId}";
        $mostRead = json_decode(file_get_contents($mostReadAPIEndpoint), true);
        
        if ($mostRead === null) {
            $this->error('Kunde inte hämta mest lästa nyheter från text-tv.nu.');
            return;
        }

        $this->info('Hämtade mest lästa nyheter från text-tv.nu.');
        
    }
}
