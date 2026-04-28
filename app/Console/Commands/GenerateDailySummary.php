<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\AISummaryService;
use App\Tier1;
use Carbon\Carbon;

class GenerateDailySummary extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'summary:generate
        {area=stockholm : Område-slug (stockholm, malmo, goteborg, helsingborg, uppsala)}
        {--date= : Specifikt datum (YYYY-MM-DD format)}
        {--yesterday}
        {--all-tier1 : Kör alla 5 Tier 1-städer}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Genererar AI-sammanfattning av dagens brottshändelser för ett område';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $summaryService = new AISummaryService();

        // Bestäm vilket datum som ska summeras
        if ($this->option('yesterday')) {
            $date = Carbon::yesterday();
        } elseif ($this->option('date')) {
            $date = Carbon::parse($this->option('date'));
        } else {
            $date = Carbon::today();
        }

        $areas = $this->option('all-tier1')
            ? Tier1::slugs()
            : [$this->argument('area')];

        $hasError = false;

        foreach ($areas as $area) {
            $this->info("Genererar sammanfattning för {$area} den {$date->format('Y-m-d')}...");

            try {
                $result = $summaryService->generateDailySummary($area, $date);
                $summary = $result['summary'];
                $aiGenerated = $result['ai_generated'];

                if ($summary) {
                    if ($aiGenerated) {
                        $this->info("✅ Ny sammanfattning genererad med AI för {$area}!");
                    } else {
                        $this->info("ℹ️  Oförändrad — använder befintlig sammanfattning för {$area}");
                    }
                    $this->info("Antal händelser: {$summary->events_count}");
                } else {
                    $this->warn("⚠️  Ingen sammanfattning kunde genereras för {$area} (inga events?)");
                }
            } catch (\Throwable $e) {
                $this->error("❌ Fel för {$area}: " . $e->getMessage());
                $hasError = true;
            }
        }

        return $hasError ? self::FAILURE : self::SUCCESS;
    }
}
