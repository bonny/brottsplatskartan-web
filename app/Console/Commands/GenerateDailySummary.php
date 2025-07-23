<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\AISummaryService;
use Carbon\Carbon;

class GenerateDailySummary extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'summary:generate {area=stockholm} {--date= : Specifikt datum (YYYY-MM-DD format)} {--yesterday}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Genererar AI-sammanfattning av dagens brottshändelser för ett område';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $summaryService = new AISummaryService();
        $area = $this->argument('area');
        
        // Bestäm vilket datum som ska summeras
        if ($this->option('yesterday')) {
            $date = Carbon::yesterday();
        } elseif ($this->option('date')) {
            $date = Carbon::parse($this->option('date'));
        } else {
            $date = Carbon::today();
        }

        $this->info("Genererar sammanfattning för {$area} den {$date->format('Y-m-d')}...");

        try {
            $result = $summaryService->generateDailySummary($area, $date);
            $summary = $result['summary'];
            $aiGenerated = $result['ai_generated'];
            
            if ($summary) {
                if ($aiGenerated) {
                    $this->info("✅ Ny sammanfattning genererad med AI!");
                } else {
                    $this->info("ℹ️ Händelserna har inte ändrats - använder befintlig sammanfattning");
                }
                $this->info("Antal händelser: {$summary->events_count}");
                $this->line("Sammanfattning:");
                $this->line($summary->summary);
            } else {
                $this->warn("⚠️ Ingen sammanfattning kunde genereras. Kontrollera att det finns händelser för det angivna datumet.");
            }
        } catch (\Exception $e) {
            $this->error("❌ Fel vid generering av sammanfattning: " . $e->getMessage());
        }
    }
}
