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
        // AI-tunga jobb körs bara i production. Annars dubbel-debiterar
        // varje dev-stack med scheduler igång (samma CLAUDE_API_KEY i
        // .env). Sätt SCHEDULE_AI_LOCAL=true i lokal .env för att tvinga
        // på AI-jobben tillfälligt — t.ex. när du testar en prompt-ändring.
        // Manuella `artisan ...`-anrop påverkas inte, bara schemaläggningen.
        $aiAllowed = static fn (): bool => app()->environment('production')
            || filter_var(config('services.scheduler.ai_local'), FILTER_VALIDATE_BOOLEAN);

        $schedule->command('model:prune')->daily();

        // Hämta nya polishändelser (tidigare host-cron, */12 * * * *).
        $schedule->command('crimeevents:fetch')
            ->cron('*/12 * * * *')
            ->withoutOverlapping();

        // Trafikverket Trafikinformation (todo #50, Fas 1).
        // 5 min matchar crimeevents:fetch — olyckor varar 1-2h, ingen UX-vinst
        // i tightare schedule. Backoff-strategi i kommandot hanterar 429/5xx.
        $schedule->command('trafikverket:fetch')
            ->everyFiveMinutes()
            ->withoutOverlapping()
            ->name('trafikverket-fetch');

        $schedule->command('trafikverket:prune')
            ->dailyAt('03:30')
            ->name('trafikverket-prune');

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
            ->withoutOverlapping()
            ->when($aiAllowed);

        // Auto-trigger för AI-titlar på vaga events i hela Sverige (todo #10
        // fas 2). Stockholm-jobbet ovan körs utan vague-filter — det här
        // täcker övriga län men bara på events vars titel matchar vagt
        // mönster (sammanfattning natt / brand / stöld / övrigt / mfl).
        // Rate-limit-säkert: ~20-30 nya vaga events/dag i hela landet.
        $schedule->command('crimeevents:create-summaries --vague-only --limit=100')
            ->everyFifteenMinutes()
            ->withoutOverlapping()
            ->when($aiAllowed);

        // Daily AI-sammanfattning för alla Tier 1-städer. Change-detection
        // i AISummaryService gör att AI bara körs när events ändrats — så
        // 5 städer × 48 körningar/dygn ger bara några AI-anrop totalt
        // (Stockholm dominerar; mindre städer har få nya events/dag).
        $schedule->command('summary:generate --all-tier1 --yesterday')
            ->dailyAt('06:00')
            ->name('daily-summary-tier1-yesterday')
            ->when($aiAllowed);

        $schedule->command('summary:generate --all-tier1')
            ->everyThirtyMinutes()
            ->withoutOverlapping()
            ->name('daily-summary-tier1-today')
            ->when($aiAllowed);

        // Månads-sammanfattning för Tier 1-städer (todo #27 Lager 3).
        // Två schemalägg:
        //
        // 1. Snapshot av föregående månad — körs 1:a varje månad kl 02:00 UTC.
        //    Engångsjobb per månad, månaden är slut så datan är immutabel.
        //
        // 2. Innevarande månad — körs var 6:e timme. Behövs eftersom
        //    månadsvyn för pågående månad är "live" (nya events tillkommer)
        //    och sammanfattningen ska reflektera senaste data. Service har
        //    change-detection så omkörning är gratis om events oförändrade.
        //    5 städer × 4 körningar/dygn × ~30s = stagger-effekten OK.
        $schedule->command('summary:generate-monthly --all-tier1')
            ->monthlyOn(1, '02:00')
            ->withoutOverlapping()
            ->name('monthly-summary-tier1-prev')
            ->when($aiAllowed);

        $schedule->command('summary:generate-monthly --all-tier1 --current')
            ->cron('0 */6 * * *')
            ->withoutOverlapping()
            ->name('monthly-summary-tier1-current')
            ->when($aiAllowed);

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

        // Prune ai_usage_logs (todo #81). 90 dagars retention räcker — det
        // är en monitorerings-tabell, inte ett audit-arkiv.
        $schedule->call(function (): void {
            \Illuminate\Support\Facades\DB::table('ai_usage_logs')
                ->where('created_at', '<', now()->subDays(90))
                ->delete();
        })
            ->dailyAt('04:00')
            ->name('ai-usage-logs-prune');

        // BRÅ-import (todo #38): kör 15 april årligen, 03:00 UTC. BRÅ
        // släpper kommunstatistiken runt 31 mars, så 2 veckor failsafe.
        // OBS: download-id i URL:en ändras varje år — KNOWN_URLS i
        // ImportBraAnmaldaBrott måste uppdateras manuellt innan jobbet
        // hittar ny årgång.
        $schedule->command('bra:import-anmalda-brott --year=' . (date('Y') - 1))
            ->yearlyOn(4, 15, '03:00')
            ->withoutOverlapping()
            ->name('bra-import');

        // MCF-import (todo #39): kör 15 april årligen, 03:30 UTC. MCF
        // släpper räddningsstatistiken runt 10 mars (samma cykel som BRÅ).
        // PxWeb-API är stabilt — ingen URL-uppdatering krävs.
        $schedule->command('mcf:import-raddningsinsatser --year=' . (date('Y') - 1))
            ->yearlyOn(4, 15, '03:30')
            ->withoutOverlapping()
            ->name('mcf-import');

        $schedule->command('app:news:fetch-rss')
            ->everyFifteenMinutes()
            ->withoutOverlapping()
            ->name('news-fetch-rss');

        // Klassifikation följer fetch men förskjuten 5 min så fetch hinner
        // skriva färdigt nya rader innan vi läser dem.
        $schedule->command('app:news:classify')
            ->cron('5,20,35,50 * * * *')
            ->withoutOverlapping()
            ->name('news-classify');

        // AI-klassifikation (todo #64) — fångar vad regex missar
        // (bedrägerier, stadsdelar, böjda termer). Körs 10 min efter regex,
        // var 30:e min för rimlig latens med låg kostnad. Limit 50/körning
        // = ~2400 art/dygn ceiling, men i praktiken bara nya artiklar
        // (ai_classified_at IS NULL) — typiskt 50-100/körning bara vid hög
        // RSS-aktivitet. Haiku 4.5 (~$26/år).
        $schedule->command('app:news:ai-classify --limit=50')
            ->cron('15,45 * * * *')
            ->withoutOverlapping()
            ->name('news-ai-classify')
            ->when($aiAllowed);

        $schedule->command('app:news:prune')
            ->dailyAt('03:45')
            ->withoutOverlapping()
            ->name('news-prune');

        // Event ↔ artikel-matchning (todo #63 fas 1) — AVSTÄNGD 2026-05-17.
        // Värdes-review #81 visade att bara 31 av 1925 events (1.6 %) får
        // Mediabevakning, dvs 98 % av besökarna ser ingenting från agenten.
        // Outbound-klick ~24/månad till $0.75/klick — för låg täckning för att
        // motivera $18/månad. Återaktivera när #60 fas 3 ger bredare urval
        // eller när bättre kandidat-filtrering finns på plats.
        // $schedule->command('app:event-news:match --limit=20')
        //     ->cron('25 */12 * * *')
        //     ->withoutOverlapping()
        //     ->name('event-news-match')
        //     ->when($aiAllowed);
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
