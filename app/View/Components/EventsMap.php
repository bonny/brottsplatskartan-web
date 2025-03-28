<?php

namespace App\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class EventsMap extends Component {
    /**
     * Create a new component instance.
     */
    public function __construct(
        public string $mapSize = 'normal',
        public bool $showMapTitle = true,
        public array $latLng = [59, 15],
        public int $mapZoom = 6,
    ) {
        //
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string {
        return view('components.events-map');
    }
}
