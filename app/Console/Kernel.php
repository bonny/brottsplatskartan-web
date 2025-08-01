<?php

namespace App\Console;

use App\Console\Commands\ImportVMAAlerts;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->command('model:prune')->daily();
        $schedule->command(ImportVMAAlerts::class)->everyFiveMinutes();
        $schedule->command('app:importera-texttv')->everyFiveMinutes();
        $schedule->command('crimeevents:create-summaries --administrative_area_level_1=stockholm')->everyFiveMinutes();
        
        // Generera gårdagens sammanfattning tidigt på morgonen (klar för dagen)
        $schedule->command('summary:generate stockholm --yesterday')->dailyAt('06:00');
        
        // Generera AI-sammanfattning för Stockholm var 30:e minut (optimerad för att bara köra AI när händelser ändrats)
        $schedule->command('summary:generate stockholm')->everyThirtyMinutes();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');
    }
}
