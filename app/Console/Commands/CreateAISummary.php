<?php

namespace App\Console\Commands;

use App\CrimeEvent;
use Illuminate\Console\Command;
use MO;

class CreateAISummary extends Command {
    /**
     * $ valet php artisan crimeevents:create-summary
     *
     * @var string
     */
    protected $signature = 'crimeevents:create-summary {eventID}';

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
        $crimeEventId = $this->argument('eventID');
        $this->line('Ok, let\'s go!');
        $this->line('Skapar sammanfattning av händelse med id ' . $crimeEventId);
        $this->generateSummary($crimeEventId);
        return Command::SUCCESS;
    }

    protected function getChatInstruction() {
        return <<<END
        Du är en journalist som får en text och skriver om den.
        Du skriver en rubrik och en brödtext.
        Texterna du skriver är neutrala i tonen.
        Du skriver på svenska.
        Du bedömer inte insatser från Polis, Brandkår och så vidare som bra eller dåliga.
        Du skriver för en webbplats med namn Brottsplatskartan på adress https://brottsplatskartan.se som rapporterar om händelser från Polis, Brandkår, Ambulans, och andra blåljusmyndigheter.
        Du skriver en SEO-vänlig och klickinbjudande rubrik först i varje text. Rubriken ska vara skapa nyfikenhet.
        Du behåller citat om det finns i texten.
        Brodera ut texten och gör den längre än originalet.
        När flera händelser finns rapporterade i samma text så infogar du en radbrytning innan varje ny händelse.
        
        Ge svaret i JSON-format så att en dator kan tolka det.

        Exempel på svar:
        {
          "title": "Rubrik",
          "content": "Text som är lite lång.\\nOch här är en rad till.",
        }
        END;
    }

    // https://github.com/openai-php/client
    protected function generateSummary($crimeEventId) {
        $yourApiKey = getenv('OPEN_AI_API_KEY');
        $client = \OpenAI::client($yourApiKey);

        $crimeEvent = CrimeEvent::find($crimeEventId);
        $userMessageContent = $crimeEvent->parsed_title . ': ' . $crimeEvent->parsed_teaser . PHP_EOL . $crimeEvent->parsed_content;
        $userMessageContent = strip_tags($userMessageContent);

        $this->line("Hittade händelse " .  $crimeEvent->parsed_date . ': ' . $crimeEvent->parsed_title);
        $this->newLine();
        // echo "chat instructions:\n" . $this->getChatInstruction() . "\n";exit;
        $this->info('Skickar följande text till Open AI:');
        $this->line($userMessageContent);

        $result = $client->chat()->create([
            'model' => 'gpt-3.5-turbo',
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
        $this->line($result->choices[0]->message->content);
        // dd('$result', $result);
        // echo $result->choices[0]->message->content;
    }
}
