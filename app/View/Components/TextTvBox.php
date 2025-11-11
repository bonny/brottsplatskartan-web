<?php

namespace App\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use Setting;
use Cache;

class TextTVBox extends Component {
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

        // Cacha TextTV-data i 10 minuter eftersom den uppdateras ungefär var 10:e minut
        $latestNews = Cache::remember('texttv-last-updated-cached', 10 * MINUTE_IN_SECONDS, function() {
            return Setting::get('texttv-last-updated', []);
        });

        $mostRead = Cache::remember('texttv-most-read-cached', 10 * MINUTE_IN_SECONDS, function() {
            return Setting::get('texttv-most-read', []);
        });

        return view('components.text-tv-box', ['latestNews' => $latestNews, 'mostRead' => $mostRead]);
    }
}
