<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\FeedController;
use App\Http\Controllers\FeedParserController;
use App\CrimeEvent;
use App\highways_ignored;
use Carbon\Carbon;
use App;
use DB;

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

        $updatedFeedsInfo = $this->feedController->updateFeedsFromPolisen();

        $this->line("Added " . $updatedFeedsInfo["numItemsAdded"] . " items");
        $this->line("Skipped " . $updatedFeedsInfo["numItemsAlreadyAdded"] . " already added items");

        // Find items missing locations and add
        $itemsNotScannedForLocations = CrimeEvent::where('scanned_for_locations', 0)->get();

        $this->info("Found " . $itemsNotScannedForLocations->count() . " items with locations missing");
        $this->info("Checking these for locations in text");

        // $bar = $this->output->createProgressBar($itemsNotScannedForLocations->count());

        foreach ($itemsNotScannedForLocations as $oneItem) {
            $this->line("Parse item $oneItem->title, id $oneItem->id");

            try {
                $this->feedController->parseItem($oneItem->getKey());
            } catch (\Exception $e) {
                $this->info('Got exception');
                $this->info($e);
            }

            // $bar->advance();
        }

        // $bar->finish();

        // End add locations.

        // Find items not geocoded and geocode them
        $itemsNotGeocoded = CrimeEvent::where([
            ['scanned_for_locations', '=', 1],
            ['geocoded', '=', 0]
        ])
        // Do not include to old items, because we don't want to try to encode them forever
        // Indexkoll: Kollat 24 Apr 2018 och använde index
        ->whereDate('created_at', '>', Carbon::now()->subDays(15))
        ->get();

        $this->info("Found " . $itemsNotGeocoded->count() . " items not geocoded");
        // $bar = $this->output->createProgressBar($itemsNotGeocoded->count());

        foreach ($itemsNotGeocoded as $oneItem) {
            $this->line("Getting geocode info for $oneItem->title, id " . $oneItem->getKey());
            $geocodeResult = $this->feedController->geocodeItem($oneItem->getKey());
            if ($geocodeResult['error']) {
                $this->error("Error during geocodeItem():\n" . $geocodeResult['error_message']);
            }

            // $bar->advance();
        }

        // $bar->finish();
        // End geocode.

        $this->info('Done!');
    }
}
