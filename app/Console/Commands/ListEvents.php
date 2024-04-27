<?php

namespace App\Console\Commands;

use App\CrimeEvent;
use App\Helper;
use App\Models\CrimeView;
use Illuminate\Console\Command;

class ListEvents extends Command {
    /**
     * Signature for the command.
     * 
     * @var string
     */
    protected $signature = 'crimeevents:list {--order=parsed_date} {--count=10}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Lista händelser';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle() {
        $this->line('Senaste händelserna:');

        $order = $this->option('order');
        $count = $this->option('count');

        $valid_orders = ['parsed_date', 'id', 'most_viewed', 'most_viewed_recently'];
        if (!in_array($order, $valid_orders)) {
            $this->error('Invalid order option. Valid options are: ' . implode(', ', $valid_orders));
            return Command::FAILURE;
        }

        if ( in_array($order, ['parsed_date', 'id']) ) {
            $events = CrimeEvent::select(['parsed_date', 'id', 'title'])
            ->orderByDesc($order)
            ->limit($count)
            ->get();
        } elseif ($order === 'most_viewed') {
            $mostViewed = Helper::getMostViewedEvents(limit: $count);
            // getMostViewedEventsRecently
            $events = $mostViewed->map(function (CrimeView $crimeView) {
                return $crimeView->crimeEvent;
            });            
        } elseif ($order === 'most_viewed_recently') {
            $mostViewed = Helper::getMostViewedEventsRecently(minutes: 25, limit: $count);
            $events = $mostViewed->map(function (CrimeView $crimeView) {
                return $crimeView->crimeEvent;
            });
        }

        if ( $order === 'most_viewed_recently' || $order === 'most_viewed') {
            // Hide all attributes or they will be shown in the table.
            collect($events->first()->getAttributes())->each(function ($value, $key) use ($events) {
                $events->makeHidden($key);
            });
            
            // Add back just the columns we want to show.
            $events->makeVisible(['parsed_date', 'id', 'title']);
        }

        $this->table(['Parsed date', 'ID', 'Title'], $events);
        return Command::SUCCESS;
    }
}
