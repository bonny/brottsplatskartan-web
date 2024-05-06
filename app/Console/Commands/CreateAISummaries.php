<?php

namespace App\Console\Commands;

use Artisan;
use App\CrimeEvent;
use Illuminate\Console\Command;

class CreateAISummaries extends Command {
    /**
     * $ valet php artisan crimeevents:create-summaries --administrative_area_level_1="Stockholms län"
     *
     * @var string
     */
    protected $signature = 'crimeevents:create-summaries {--administrative_area_level_1=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Skapar en sammanfattning av händelser för en viss area.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle() {
        $administrative_area_level_1 = $this->option('administrative_area_level_1');
    
        if (empty($administrative_area_level_1)) {
            $this->error('Saknar area, ange t.ex.: --administrative_area_level_1="Stockholms län"');
            return Command::FAILURE;
        }

        $this->line('Okej, låt oss skapa lite summeringar av händelser i en viss area.');
        $this->line("Area: " . $administrative_area_level_1);

        $daysBack = 1;

        // Hämta händelser i området men max nn dagar gammal för att inte bli för mycket.
        $events_in_area = CrimeEvent::where('administrative_area_level_1', $administrative_area_level_1)
            ->where('title_alt_1', null)
            ->where('created_at', '>=', now()->subDays($daysBack))
            ->get();

        $this->line("Hittade " . $events_in_area->count() . " händelser i området som är max " . $daysBack . " dagar gamla.");

        $events_in_area->each(function ($event) {
            $this->generateSummary($event);
        });
    }

    protected function generateSummary($event) {
        $this->line("Genererar summering för " . $event->title . " - id " . $event->id);
        
        $exitCode = Artisan::call('crimeevents:create-summary', [
            'eventID' => [$event->id]
        ]);

        if ($exitCode !== 0) {
            $this->error('Misslyckades med att generera summering för ' . $event->title . ' - id ' . $event->id);
            return;
        }

        //$output = Artisan::output();
        //dd('$output', $output);
    }
}