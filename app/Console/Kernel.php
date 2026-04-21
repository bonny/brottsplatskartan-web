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

        // Hämta nya polishändelser (tidigare host-cron, */12 * * * *).
        $schedule->command('crimeevents:fetch')
            ->cron('*/12 * * * *')
            ->withoutOverlapping();

        // Kolla uppdateringar för befintliga händelser (tidigare host-cron, */33 * * * *).
        $schedule->command('crimeevents:checkForUpdates')
            ->cron('*/33 * * * *')
            ->withoutOverlapping();

        $schedule->command(ImportVMAAlerts::class)
            ->everyFiveMinutes()
            ->withoutOverlapping();

        $schedule->command('app:importera-texttv')
            ->everyFiveMinutes()
            ->withoutOverlapping();

        $schedule->command('crimeevents:create-summaries --administrative_area_level_1=stockholm')
            ->everyFiveMinutes()
            ->withoutOverlapping();

        // Generera gårdagens sammanfattning tidigt på morgonen (klar för dagen)
        $schedule->command('summary:generate stockholm --yesterday')->dailyAt('06:00');

        // Generera AI-sammanfattning för Stockholm var 30:e minut (optimerad för att bara köra AI när händelser ändrats)
        $schedule->command('summary:generate stockholm')
            ->everyThirtyMinutes()
            ->withoutOverlapping();

        // Pre-warma response cache på populära sidor var 15:e minut så
        // användare aldrig träffar kall cache. Låg kostnad: ~25 HTTP-
        // requests/15 min mot oss själva. Viktigt tills responsecache
        // 8.x (SWR) är i produktion.
        $schedule->command('cache:warm')
            ->everyFifteenMinutes()
            ->withoutOverlapping()
            ->name('warm-cache');

        // Generera sitemap.xml en gång per dag — tar ~30-60s för ~10k events.
        $schedule->command('sitemap:generate')
            ->dailyAt('04:00')
            ->withoutOverlapping()
            ->name('sitemap');
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
