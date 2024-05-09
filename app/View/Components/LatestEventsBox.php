<?php

namespace App\View\Components;

use App\Helper;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class LatestEventsBox extends Component
{
    /**
     * Create a new component instance.
     */
    public function __construct()
    {
        
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        $latestEvents = Helper::getLatestEventsByParsedDate(5);
        return view('components.latest-events-box')->with('latestEvents', $latestEvents);
    }
}
