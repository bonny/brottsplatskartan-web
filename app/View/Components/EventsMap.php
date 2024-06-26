<?php

namespace App\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class EventsMap extends Component
{
    /**
     * Create a new component instance.
     */
    public function __construct(
        public string $mapSize = 'normal'
        )
    {
        //
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        return view('components.events-map');
    }
}
