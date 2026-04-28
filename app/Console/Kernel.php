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

        // Auto-trigger för AI-titlar på vaga events i hela Sverige (todo #10
        // fas 2). Stockholm-jobbet ovan körs utan vague-filter — det här
        // täcker övriga län men bara på events vars titel matchar vagt
        // mönster (sammanfattning natt / brand / stöld / övrigt / mfl).
        // Rate-limit-säkert: ~20-30 nya vaga events/dag i hela landet.
        $schedule->command('crimeevents:create-summaries --vague-only --limit=100')
            ->everyFifteenMinutes()
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

        // Generera sitemap.xml var 30:e minut. Sajten är tidskritisk
        // (polishändelser "just nu") så nattlig sitemap läcker för
        // mycket tid innan nya URL:er dyker upp för sökmotorer.
        // ~1-2s att generera (cachade counts + 90 dagar events).
        $schedule->command('sitemap:generate')
            ->everyThirtyMinutes()
            ->withoutOverlapping()
            ->name('sitemap');

        // BRÅ-import (todo #38): kör 15 april årligen, 03:00 UTC. BRÅ
        // släpper kommunstatistiken runt 31 mars, så 2 veckor failsafe.
        // OBS: download-id i URL:en ändras varje år — KNOWN_URLS i
        // ImportBraAnmaldaBrott måste uppdateras manuellt innan jobbet
        // hittar ny årgång.
        $schedule->command('bra:import-anmalda-brott --year=' . (date('Y') - 1))
            ->yearlyOn(4, 15, '03:00')
            ->withoutOverlapping()
            ->name('bra-import');
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
