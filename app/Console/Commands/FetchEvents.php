<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\FeedController;
use App\Http\Controllers\FeedParserController;
use App\CrimeEvent;
use App\highways_ignored;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App;
use DB;
use App\Services\ContentFilterService;

class FetchEvents extends Command
{
    /** Max antal geokodningsförsök per händelse (#109.4). */
    private const MAX_GEOKODFORSOK = 10;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'crimeevents:fetch';

    private $feedController;

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
        $this->line('Fetching events...');

        $updatedFeedsInfo = $this->feedController->updateFeedsFromPolisen();

        $this->line("Added " . $updatedFeedsInfo["numItemsAdded"] . " items");
        $this->line("Skipped " . $updatedFeedsInfo["numItemsAlreadyAdded"] . " already added items");

        // Händelser som Polisen ändrat sedan importen (#109.6).
        foreach ($updatedFeedsInfo["itemsChanged"] as $changedId => $apiItem) {
            $this->line("Polisen har ändrat händelse {$changedId}, tolkar om");
            try {
                $this->feedController->uppdateraFranApi($changedId, $apiItem);
            } catch (\Exception $e) {
                Log::warning('Kunde inte tolka om ändrad händelse', ['crime_event_id' => $changedId, 'fel' => $e->getMessage()]);
            }
        }
        
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
            // Tak på försök per händelse (#109.4): körs var 12:e minut i 15
            // dagar, så en händelse som aldrig går att geokoda gav annars upp
            // till ~1 800 Google-anrop. Räknaren ligger i cachen — töms den
            // blir det bara några extra försök.
            $forsokKey = 'geokodforsok:' . $oneItem->getKey();
            Cache::add($forsokKey, 0, now()->addDays(16));
            $forsok = Cache::increment($forsokKey);
            if ($forsok > self::MAX_GEOKODFORSOK) {
                if ($forsok === self::MAX_GEOKODFORSOK + 1) {
                    Log::warning('Ger upp geokodning efter ' . self::MAX_GEOKODFORSOK . ' försök', ['crime_event_id' => $oneItem->getKey()]);
                }
                continue;
            }

            $this->line("Getting geocode info for $oneItem->title, id " . $oneItem->getKey());
            $geocodeResult = $this->feedController->geocodeItem($oneItem->getKey());
            if ($geocodeResult['error']) {
                $this->error("Error during geocodeItem():\n" . $geocodeResult['error_message']);
            } else {
                $this->info("Geocoded using url: " . $geocodeResult['geocodeUrl']);
            }

            // $bar->advance();
        }

        // $bar->finish();
        // End geocode.

        // Kontrollera och markera händelser som inte ska vara publika
        $this->info('Kontrollerar händelser för publicitetsstatus...');
        $contentFilterService = new ContentFilterService();
        $filterResult = $contentFilterService->markEventsAsNonPublic(1); // Bara händelser från idag
        
        if ($filterResult['updated_count'] > 0) {
            $this->info("Markerade {$filterResult['updated_count']} händelser som icke-publika");
        } else {
            $this->info('Inga händelser behövde markeras som icke-publika');
        }

        $this->info('Done!');
    }
}
