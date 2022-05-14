<?php

namespace App\Console\Commands;

use App;
use App\CrimeEvent;
use App\highways_ignored;
use App\Http\Controllers\FeedController;
use App\Http\Controllers\FeedParserController;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CheckForEventsUpdates extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'crimeevents:checkForUpdates';

    private $feedController;

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check if existing events have text updates on Polisen.se';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(FeedController $FeedController)
    {
        parent::__construct();

        $this->feedController = $FeedController;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->info('Ok, let\'s go!');
        $this->line('Fetching existing events that are recent...');

        $hoursBackToCheck = 12;
        $dateSomeTimeAgo = Carbon::now()->subHours($hoursBackToCheck);
        $recentEvents = CrimeEvent::where('created_at', '>=', $dateSomeTimeAgo)->get();

        $this->line("Found " . $recentEvents->count() . " events created within the last $hoursBackToCheck hours");
        $this->line("Checking if any of those have text changes");

        foreach ($recentEvents as $oneRecentEvent) {
            $this->line("Checking updates for $oneRecentEvent->title, id $oneRecentEvent->id");

            $itemContentsWasUpdated = $this->feedController->parseItemContentAndUpdateIfChanges($oneRecentEvent->id);

            Log::info(
                'Item was updated from remote after a while',
                [
                    'itemContentsWasUpdated' => $itemContentsWasUpdated
                ]
            );

            if ($itemContentsWasUpdated === 'CHANGED') {
                $this->feedController->geocodeItem($oneRecentEvent->id);
                Log::debug(
                    'Item was updated from remote after a while',
                    [
                        'id' => $oneRecentEvent->id
                    ]
                );
                // dd('alrajt, geocode item again too');
            }
        }

        $this->info('Done!');
    }
}
