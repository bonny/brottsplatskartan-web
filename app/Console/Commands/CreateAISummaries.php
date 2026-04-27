<?php

namespace App\Console\Commands;

use Artisan;
use App\CrimeEvent;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class CreateAISummaries extends Command {
    /**
     * Exempel:
     *   php artisan crimeevents:create-summaries --administrative_area_level_1="Stockholms län"
     *   php artisan crimeevents:create-summaries --vague-only --limit=100
     *   php artisan crimeevents:create-summaries --vague-only --dry-run
     *
     * @var string
     */
    protected $signature = 'crimeevents:create-summaries
        {--administrative_area_level_1=}
        {--vague-only : Bara events vars parsed_title matchar vagt mönster (CrimeEvent::isVagueTitle)}
        {--limit=100 : Max antal events per körning}
        {--days-back=1 : Hur många dagar bakåt att kolla}
        {--dry-run : Visa vilka events som skulle bearbetas, anropa inte AI}';

    /**
     * @var string
     */
    protected $description = 'Skapar AI-omskrivna titlar/sammanfattningar för nya händelser. Med --vague-only filtrerar via CrimeEvent::isVagueTitle.';

    public function handle() {
        $area = $this->option('administrative_area_level_1');
        $vagueOnly = (bool) $this->option('vague-only');
        $limit = (int) $this->option('limit');
        $daysBack = (int) $this->option('days-back');
        $dryRun = (bool) $this->option('dry-run');

        $query = CrimeEvent::query()
            ->whereNull('title_alt_1')
            ->where('created_at', '>=', now()->subDays($daysBack));

        if (!empty($area)) {
            $query->where('administrative_area_level_1', 'like', "{$area}%");
        }

        // Hämta alla kandidater (sedan filtrerar vi i PHP via shouldRewriteTitle).
        // Inte super-effektivt men lägger inte regex-logik i SQL.
        $candidates = $query->orderBy('created_at', 'desc')->limit($limit * 5)->get();

        if ($vagueOnly) {
            $candidates = $candidates->filter(fn (CrimeEvent $e) => CrimeEvent::shouldRewriteTitle($e) !== null);
        }

        $candidates = $candidates->take($limit);

        $this->line("Filter: area=" . ($area ?: '(alla)') . ", vague-only=" . ($vagueOnly ? 'ja' : 'nej') . ", days-back=$daysBack, limit=$limit");
        $this->line("Hittade {$candidates->count()} events att bearbeta" . ($dryRun ? ' (dry-run)' : ''));

        if ($dryRun) {
            foreach ($candidates as $event) {
                $bucket = CrimeEvent::isVagueTitle($event->parsed_title) ?? 'OK';
                $this->line(sprintf(
                    '#%d  bucket=%-15s  body=%4d  parsed_title="%s"',
                    $event->id,
                    $bucket,
                    mb_strlen($event->parsed_content ?? ''),
                    Str::limit($event->parsed_title ?? '', 50),
                ));
            }
            return Command::SUCCESS;
        }

        foreach ($candidates as $event) {
            $this->generateSummary($event);
        }

        return Command::SUCCESS;
    }

    protected function generateSummary($event) {
        $this->line("Genererar summering för " . $event->title . " - id " . $event->id);

        $exitCode = Artisan::call('crimeevents:create-summary', [
            'eventID' => [$event->id],
        ]);

        if ($exitCode !== 0) {
            $this->error('Misslyckades med att generera summering för ' . $event->title . ' - id ' . $event->id);
            return;
        }
    }
}
