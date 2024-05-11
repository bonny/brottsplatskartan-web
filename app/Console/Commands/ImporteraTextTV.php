<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Setting;

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

    protected $appId = 'brottsplatskartan';

    protected function importMostRead()
    {
        $APIEndpoint = "https://texttv.nu/api/most_read/news?count=5&{$this->appId}";
        $mostRead = json_decode(file_get_contents($APIEndpoint), true);
        
        if ($mostRead === null) {
            $this->error('Kunde inte hämta mest lästa nyheterna');
        }

        Setting::set('texttv-most-read', $mostRead['pages']);
    }

    protected function importLastUpdated()
    {
        $APIEndpoint = "https://texttv.nu/api/last_updated/news?count=5&{$this->appId}";
        $lastUpdated = json_decode(file_get_contents($APIEndpoint), true);
        
        if ($lastUpdated === null) {
            $this->error('Kunde inte hämta senaste nyheterna');
        }

        Setting::set('texttv-last-updated', $lastUpdated['pages']);
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->importMostRead();
        $this->importLastUpdated();
        $this->info('Hämtade nyheter från text-tv.nu.');

        
    }
}
