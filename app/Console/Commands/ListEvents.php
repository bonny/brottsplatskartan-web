<?php

namespace App\Console\Commands;

use App\CrimeEvent;
use Illuminate\Console\Command;

class ListEvents extends Command {
    /**
     * Signature for the command.
     * 
     * @var string
     */
    protected $signature = 'crimeevents:list';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Lista händelser';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle() {
        $this->line('Senaste händelserna:');

        $events = CrimeEvent::select(['parsed_date', 'id', 'title'])->limit(10)->orderByDesc('parsed_date')->get();

        $this->table(['Parsed date', 'ID', 'Title'], $events);
        return Command::SUCCESS;
    }
}
