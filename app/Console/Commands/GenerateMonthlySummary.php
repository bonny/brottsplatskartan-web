<?php

namespace App\Console\Commands;

use App\Services\AISummaryService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GenerateMonthlySummary extends Command
{
    protected $signature = 'summary:generate-monthly
        {area=stockholm : Område-slug (stockholm, malmo, goteborg, helsingborg, uppsala)}
        {--year= : År (YYYY) — default föregående månads år}
        {--month= : Månad (1-12) — default föregående månad}
        {--all-tier1 : Kör alla 5 Tier 1-städer}';

    protected $description = 'Genererar AI-månadssammanfattning för Tier 1-stad (todo #27 Lager 3)';

    public function handle(): int
    {
        $service = new AISummaryService();

        // Default: föregående månad. Vid månadsskifte vill vi sammanfatta
        // den månad som just slutat, inte den som pågår.
        $now = Carbon::now()->subMonth();
        $year = (int) ($this->option('year') ?? $now->year);
        $month = (int) ($this->option('month') ?? $now->month);

        if ($month < 1 || $month > 12) {
            $this->error("Ogiltig månad: {$month}. Använd 1-12.");
            return self::FAILURE;
        }

        $areas = $this->option('all-tier1')
            ? \App\Http\Controllers\CityController::tier1Slugs()
            : [$this->argument('area')];

        $hasError = false;

        foreach ($areas as $area) {
            $label = "{$area} {$year}-" . str_pad((string) $month, 2, '0', STR_PAD_LEFT);
            $this->info("Genererar månadssammanfattning för {$label}...");

            try {
                $result = $service->generateMonthlySummary($area, $year, $month);
                $summary = $result['summary'];

                if (!$summary) {
                    $this->warn("⚠️  Ingen sammanfattning genererad för {$label} (inga events?)");
                    continue;
                }

                if ($result['ai_generated']) {
                    $this->info("✅ Ny sammanfattning för {$label} ({$summary->events_count} events)");
                } else {
                    $this->info("ℹ️  Oförändrad — använder befintlig för {$label}");
                }
            } catch (\Throwable $e) {
                $this->error("❌ Fel för {$label}: " . $e->getMessage());
                $hasError = true;
            }
        }

        return $hasError ? self::FAILURE : self::SUCCESS;
    }
}
