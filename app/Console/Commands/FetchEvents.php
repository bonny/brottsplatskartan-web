<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\FeedController;
use App\Http\Controllers\FeedParserController;
use App\CrimeEvent;
use App\highways_ignored;
use App;

class FetchEvents extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'crimeevents:fetch';

    private $feedController;
    private $feedParser;

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetches the latest events from Polisen.se';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(FeedController $FeedController, FeedParserController $FeedParser)
    {
        parent::__construct();

        // Default command line time limit i 0 = forever.
        // We limit this because something on the server is hanging, perhaps this..
        set_time_limit(45);

        $this->feedController = $FeedController;
        $this->feedParser = $FeedParser;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {


        $this->info('Ok, let\'s go!');
        $this->line('Fetching events...');

        // updateFeedsFromPolisen
        $updatedFeedsInfo = $this->feedController->updateFeedsFromPolisen();

        $this->line("Added " . $updatedFeedsInfo["numItemsAdded"] . " items");
        $this->line("Skipped " . $updatedFeedsInfo["numItemsAlreadyAdded"] . " already added items");


        // find items missing locations and add
        $itemsNotScannedForLocations = CrimeEvent::where('scanned_for_locations', 0)->get();

        $this->line("Found " . $itemsNotScannedForLocations->count() . " items with locations missing");

        foreach ($itemsNotScannedForLocations as $oneItem) {
            $this->line("Getting locations for $oneItem->title");
            $this->feedController->parseItem($oneItem->getKey());
        }


        // find items not geocoded and geocode them
        $itemsNotGeocoded = CrimeEvent::where([
            'scanned_for_locations' => 1,
            'geocoded' => 0
        ])->get();

        $this->line("Found " . $itemsNotGeocoded->count() . " items not geocoded");

        foreach ($itemsNotGeocoded as $oneItem) {
            $this->line("Getting geocode info for $oneItem->title");
            $this->feedController->geocodeItem($oneItem->getKey());
        }


        $this->info('Done!');

    }
}
