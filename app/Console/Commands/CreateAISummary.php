<?php

namespace App\Console\Commands;

use App\CrimeEvent;
use Illuminate\Console\Command;

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
    protected $description = 'Skapar en sammanfattning av händelserna via Open AI';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle() {
        $crimeEventIds = $this->argument('eventID');

        $this->line('Ok, let\'s go!');

        foreach ($crimeEventIds as $crimeEventId) {
            $this->generateSummary($crimeEventId);
        }
        return Command::SUCCESS;
    }

    protected function getChatInstruction() {
        return <<<END
        Du är en journalist som skriver för webbplatsen Brottsplatskartan.se. Dina läsare är intresserade av nyhetshändelser från så kallade "blåljusmyndigheter" (t.ex. Polis, Brandkår, Ambulans).

        Du kommer i nästa meddelande få en text som du skriver om. Texten ska vara neutral och saklig.
        Lägg inte till några egna åsikter eller kommentarer. Lägg inte till tidpunkt eller datum som inte finns i den ursprungliga texten.
        
        Den nya texten ska innehålla en SEO-vänlig rubrik och en brödtext av hög journalistisk kvalitet.

        Om det finns rader som innehåller texten "Uppdatering klockan hh:nn" ska de raderna behållas och inte skrivas om.

        Skriv "Rubrik: " före rubriken och "Text: " före texten.
        END;
    }

    // https://github.com/openai-php/client
    protected function generateSummary($crimeEventId) {
        $yourApiKey = getenv('OPEN_AI_API_KEY');
        $client = \OpenAI::client($yourApiKey);

        $crimeEvent = CrimeEvent::findOrFail($crimeEventId);
        $userMessageContent = "Typ: " . $crimeEvent->parsed_title . PHP_EOL;
        $userMessageContent .= "Rubrik: " . $crimeEvent->parsed_teaser . PHP_EOL;
        $userMessageContent .= "Text: " . strip_tags($crimeEvent->parsed_content);

        $this->newLine();
        $this->line("Hittade händelse " .  $crimeEvent->parsed_date . ': ' . $crimeEvent->parsed_title);
        $this->newLine();
        // echo "chat instructions:\n" . $this->getChatInstruction() . "\n";exit;
        $this->info('Text som skickas till OpenAI:');
        $this->line($userMessageContent);

        $result = $client->chat()->create([
            'model' => 'gpt-4o-mini',
            // 'model' => 'gpt-4',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $this->getChatInstruction(),
                ],
                [
                    'role' => 'user',
                    'content' => $userMessageContent,
                ],
            ],
        ]);

        $this->newLine();
        $this->info("Svar från Open AI:");
        $this->newLine();

        #$this->line("result");
        #$this->line(json_encode($result, JSON_PRETTY_PRINT));

        $content = $result->choices[0]->message->content;
        #$this->line('result->choices[0]->message->content');
        #$this->line($content);

        $lines = explode("\n", $content);
        $title = '';
        $text = '';
        
        // Hitta raden som börjar med "Rubrik: ".
        foreach ($lines as $line) {
            if (strpos($line, 'Rubrik: ') === 0) {
                $title = substr($line, 8);
                break;
            }
        }

        // Hitta raden som börjar med "Text: " och ta bort "Text: " och behåll all annan
        // text på den raden och följande rader.
        $foundText = false;
        foreach ($lines as $line) {
            if ($foundText) {
                $text .= $line . "\n";
            }
            if (strpos($line, 'Text: ') === 0) {
                $foundText = true;
                $text .= substr($line, 6) . "\n";
            }
        }

        $this->newLine();
        $this->line( "Rubrik: " . $title);
        $this->newLine();
        $this->line( "Text: " . $text);
    
        $crimeEvent->title_alt_1 = $title;
        $crimeEvent->description_alt_1 = $text;
        $crimeEvent->save();
    }
}
