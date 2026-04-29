<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\CrimeEvent;
use Carbon\Carbon;

/**
 * Engångs-backfill för events där parsed_date är efter pubdate eller i framtiden.
 *
 * Bakgrunden: titeln "DD månad HH.MM" parsades med Carbon::parse som
 * defaultar till innevarande år. Vid natt-händelser (titel kl 22.00,
 * publicerad 06:58 morgonen efter) eller årsskifte (titel "31 december",
 * publicerad 1 januari) blir parsed_date felaktigt framtida eller fel år.
 *
 * Heuristik: om title-tid > pubdate-tid kan eventet inte ha skett samma
 * dag (Polisen publicerar inte innan något händer), alltså = dagen före.
 */
class FixParsedDateFutureBug extends Command
{
    protected $signature = 'crimeevents:fix-parsed-date-future-bug
                            {--dry-run : Visa antal utan att skriva}';

    protected $description = 'Backfill: korrigera events där parsed_date hamnat i framtiden.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $query = CrimeEvent::withoutGlobalScopes()
            ->whereNotNull('parsed_date')
            ->where('parsed_date', '>', now());

        $total = $query->count();
        $this->info("Hittade $total rader med parsed_date i framtiden");

        if ($dryRun) {
            $this->warn('Dry-run: inga ändringar gjorda.');
            return self::SUCCESS;
        }

        $fixed = 0;
        $query->chunkById(500, function ($chunk) use (&$fixed) {
            foreach ($chunk as $e) {
                $parsedDate = Carbon::parse($e->parsed_date);
                $pubdate = $e->pubdate
                    ? Carbon::createFromTimestamp($e->pubdate)
                    : Carbon::now();
                $isYearBoundary = $parsedDate->month === 12 && $pubdate->month === 1;
                $newDate = $isYearBoundary ? $parsedDate->subYear() : $parsedDate->subDay();
                $e->parsed_date = $newDate;
                $e->save();
                $fixed++;
            }
        });

        $this->info("Fixade $fixed rader. Kvar i framtiden: "
            . CrimeEvent::withoutGlobalScopes()->where('parsed_date', '>', now())->count());

        return self::SUCCESS;
    }
}
