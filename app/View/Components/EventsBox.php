<?php

namespace App\View\Components;

use App\Helper;
use App\Models\CrimeView;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class EventsBox extends Component {
    /**
     * Create a new component instance.
     */
    public function __construct() {
        //
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string {

        return function (array $data) {

            $eventsType = $data['attributes']['type'] ?? 'latest';

            if ($eventsType === 'latest') {
                $title = 'Senaste händelserna';
                $containerId = 'senaste';
                $moreEventsLink = route('handelser');
                $events = Helper::getLatestEventsByParsedDate(10);
            } elseif ($eventsType === 'trending') {
                $title = 'Mest lästa händelserna';
                $containerId = 'mest-last';
                $moreEventsLink = route('mostRead');
                $events = Helper::getMostViewedEventsRecently();
                $events = $events->map(function (CrimeView $crimeView) {
                    return $crimeView->crimeEvent;
                });

                // Eager-loada locations för att undvika N+1 queries
                // Efter mappning försvinner eager-loaded relationer
                $events->load('locations');

            } else {
                return null;
            }

            $showReloadLink = $data['attributes']->get('show-reload-link', 'true') === 'true';

            return view('components.events-box')
                ->with('events', $events)
                ->with('containerId', $containerId)
                ->with('eventsType', $eventsType)
                ->with('title', $title)
                ->with('moreEventsLink', $moreEventsLink)
                ->with('showReloadLink', $showReloadLink);
        };
    }
}
