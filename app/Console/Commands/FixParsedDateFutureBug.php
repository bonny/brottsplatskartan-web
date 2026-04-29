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

    protected $description = 'Backfill: korrigera events där parsed_date hamnat efter pubdate.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $query = CrimeEvent::withoutGlobalScopes()
            ->whereNotNull('pubdate')
            ->whereNotNull('parsed_date')
            ->whereRaw('parsed_date > FROM_UNIXTIME(pubdate)');

        $total = $query->count();
        $this->info("Hittade $total rader med parsed_date > pubdate");

        if ($dryRun) {
            $this->warn('Dry-run: inga ändringar gjorda.');
            return self::SUCCESS;
        }

        $fixed = 0;
        $query->chunkById(500, function ($chunk) use (&$fixed) {
            foreach ($chunk as $e) {
                $oldParsed = Carbon::parse($e->parsed_date);
                $pubdate = Carbon::createFromTimestamp($e->pubdate);
                $titleMins = $oldParsed->hour * 60 + $oldParsed->minute;
                $pubMins = $pubdate->hour * 60 + $pubdate->minute;
                $base = $titleMins > $pubMins ? $pubdate->copy()->subDay() : $pubdate->copy();
                $newParsed = $base->setTime($oldParsed->hour, $oldParsed->minute, 0);
                if ((string) $e->parsed_date !== (string) $newParsed) {
                    $e->parsed_date = $newParsed;
                    $e->save();
                    $fixed++;
                }
            }
        });

        $this->info("Fixade $fixed rader. Kvar med parsed_date > pubdate: "
            . CrimeEvent::withoutGlobalScopes()->whereRaw('parsed_date > FROM_UNIXTIME(pubdate)')->count());

        return self::SUCCESS;
    }
}
