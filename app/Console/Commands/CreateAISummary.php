<?php

namespace App\Console\Commands;

use App\Ai\Agents\EventTitleRewriter;
use App\CrimeEvent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CreateAISummary extends Command {
    /**
     * $ valet php artisan crimeevents:create-summary
     *
     * @var string
     */
    protected $signature = 'crimeevents:create-summary {eventID*}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Skapar en SEO-vänlig rubrik och brödtext för händelser via Claude AI (laravel/ai)';

    public function handle() {
        $crimeEventIds = $this->argument('eventID');

        $this->line('Ok, let\'s go!');

        foreach ($crimeEventIds as $crimeEventId) {
            $this->generateSummary($crimeEventId);
        }
        return Command::SUCCESS;
    }

    protected function generateSummary($crimeEventId) {
        $crimeEvent = CrimeEvent::findOrFail($crimeEventId);

        $userMessage = "Typ: " . $crimeEvent->parsed_title . PHP_EOL
            . "Rubrik: " . $crimeEvent->parsed_teaser . PHP_EOL
            . "Text: " . strip_tags($crimeEvent->parsed_content);

        $this->newLine();
        $this->line("Hittade händelse " .  $crimeEvent->parsed_date . ': ' . $crimeEvent->parsed_title);
        $this->newLine();
        $this->info('Text som skickas till Claude:');
        $this->line($userMessage);

        try {
            $response = (new EventTitleRewriter)->prompt($userMessage);
        } catch (\Exception $e) {
            $this->error("EventTitleRewriter fel: " . $e->getMessage());
            Log::error("EventTitleRewriter fel för event {$crimeEventId}: " . $e->getMessage());
            return;
        }

        $title = $response['title'] ?? '';
        $description = $response['description'] ?? '';

        if ($title === '' || $description === '') {
            $this->error("Tom title eller description i structured response för event {$crimeEventId}");
            Log::warning("Tom AI-output för event {$crimeEventId}");
            return;
        }

        $this->newLine();
        $this->info("Svar från Claude:");
        $this->newLine();
        $this->line("Rubrik: " . $title);
        $this->newLine();
        $this->line("Text: " . $description);

        $crimeEvent->title_alt_1 = $title;
        $crimeEvent->description_alt_1 = $description;
        $crimeEvent->save();
    }
}
