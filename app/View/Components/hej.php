<?php

namespace App\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use Setting;

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

        $latestNews = Setting::get('texttv-last-updated', []);
        $mostRead = Setting::get('texttv-most-read', []);

        return view('components.text-tv-box', ['latestNews' => $latestNews, 'mostRead' => $mostRead]);
    }
}
