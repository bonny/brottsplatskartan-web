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
        
        // Generera AI-sammanfattning för Stockholm flera gånger per dag
        $schedule->command('summary:generate stockholm')->at('08:00');
        $schedule->command('summary:generate stockholm')->at('12:00'); 
        $schedule->command('summary:generate stockholm')->at('16:00');
        $schedule->command('summary:generate stockholm')->at('20:00');
        
        // Generera AI-sammanfattning för Stockholm varje dag kl 23:00
        $schedule->command('summary:generate stockholm --yesterday')->dailyAt('23:00');
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
