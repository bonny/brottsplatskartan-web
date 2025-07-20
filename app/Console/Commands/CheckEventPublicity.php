<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ContentFilterService;
use Illuminate\Support\Str;

class CheckEventPublicity extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'crimeevents:check-publicity 
                            {--apply : Applicera ändringarna istället för bara visa dem}
                            {--since=30 : Antal dagar bakåt att kontrollera}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Kontrollerar vilka händelser som ska markeras som icke-publika';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $contentFilterService = new ContentFilterService();
        $since = (int) $this->option('since');
        $apply = $this->option('apply');

        $this->info("Kontrollerar händelser från de senaste {$since} dagarna...");

        if ($apply) {
            // Applicera ändringarna
            $this->info('Applicerar ändringarna...');
            
            // Först räkna totalt antal händelser att kontrollera
            $totalEvents = \App\CrimeEvent::withoutGlobalScope('public')
                ->where('created_at', '>=', now()->subDays($since))
                ->where('is_public', true)
                ->count();
            
            $this->info("Kontrollerar {$totalEvents} händelser...");
            
            // Skapa progress bar
            $progressBar = $this->output->createProgressBar($totalEvents);
            $progressBar->setFormat('verbose');
            $progressBar->start();
            
            $foundCount = 0;
            $eventsToUpdate = $contentFilterService->getEventsToMarkAsNonPublic($since, function ($processed, $found) use ($progressBar, &$foundCount) {
                $foundCount = $found;
                $progressBar->setProgress($processed);
                $progressBar->setMessage("Hittade: {$found}", 'found');
            });
            
            $progressBar->finish();
            $this->newLine(2);
            
            // Nu markera som icke-publika
            $updatedCount = 0;
            $updatedEvents = [];

            foreach ($eventsToUpdate as $event) {
                $event->is_public = false;
                $event->save();
                
                $updatedCount++;
                $updatedEvents[] = [
                    'id' => $event->id,
                    'title' => $event->title,
                    'reason' => $contentFilterService->isPressNotice($event) ? 'Presstalesperson-meddelande' : 'Okänd anledning'
                ];
            }
            
            $this->info("✅ Markerade {$updatedCount} händelser som icke-publika:");
            
            if ($updatedCount > 0) {
                $this->table(
                    ['ID', 'Titel', 'Anledning'],
                    collect($updatedEvents)->map(function ($event) {
                        return [
                            $event['id'],
                            Str::limit($event['title'], 50),
                            $event['reason']
                        ];
                    })->toArray()
                );
            }
        } else {
            // Endast visa vad som skulle ändras (dry-run)
            $this->info('🔍 Dry-run läge - visar händelser som skulle markeras som icke-publika:');
            
            // Först räkna totalt antal händelser att kontrollera
            $totalEvents = \App\CrimeEvent::withoutGlobalScope('public')
                ->where('created_at', '>=', now()->subDays($since))
                ->where('is_public', true)
                ->count();
            
            $this->info("Kontrollerar {$totalEvents} händelser...");
            
            if ($totalEvents > 10000) {
                $this->warn("⚠️  Detta är många händelser ({$totalEvents}). Processen kan ta tid.");
            }
            
            // Skapa progress bar
            $progressBar = $this->output->createProgressBar($totalEvents);
            $progressBar->setFormat('verbose');
            $progressBar->start();
            
            $foundCount = 0;
            $eventsToUpdate = $contentFilterService->getEventsToMarkAsNonPublic($since, function ($processed, $found) use ($progressBar, &$foundCount) {
                $foundCount = $found;
                $progressBar->setProgress($processed);
                $progressBar->setMessage("Hittade: {$found}", 'found');
            });
            
            $progressBar->finish();
            $this->newLine(2);
            
            if ($eventsToUpdate->isEmpty()) {
                $this->info('✅ Inga händelser behöver markeras som icke-publika.');
            } else {
                $this->warn("Hittade {$eventsToUpdate->count()} händelser som skulle markeras som icke-publika:");
                
                $this->table(
                    ['ID', 'Titel', 'Anledning'],
                    $eventsToUpdate->map(function ($event) use ($contentFilterService) {
                        $reason = 'Okänd';
                        if ($contentFilterService->isPressNotice($event)) {
                            $reason = 'Presstalesperson-meddelande';
                        }
                        
                        return [
                            $event->id,
                            Str::limit($event->title, 50),
                            $reason
                        ];
                    })->toArray()
                );
                
                $this->info('');
                $this->info('För att applicera ändringarna, kör kommandot med --apply flaggan:');
                $this->info("php artisan crimeevents:check-publicity --apply --since={$since}");
            }
        }
        
        return 0;
    }
}
