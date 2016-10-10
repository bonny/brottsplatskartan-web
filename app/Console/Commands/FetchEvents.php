<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\FeedController;

class FetchEvents extends Command
{
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

        $this->info('Ok, lets go!');
        $this->line('Fetching events...');

        // updateFeedsFromPolisen
        $updatedFeedsInfo = $this->feedController->updateFeedsFromPolisen();

        $this->line("Added " . $updatedFeedsInfo["numItemsAdded"] . " items");
        $this->line("Skipped " . $updatedFeedsInfo["numItemsAlreadyAdded"] . " already added items");

        if ($updatedFeedsInfo["itemsAdded"]) {

            $this->info("Finding locations in items added");
            
            foreach ( $updatedFeedsInfo["itemsAdded"] as $item ) {

                $this->feedController->parseItem($item->getKey());

                #print_r($item->locations);

            }

        }

        $this->info('Done!');

    }
}
