<?php

namespace App\Http\Controllers;

use Creitive\Breadcrumbs\Breadcrumbs;
use Illuminate\Http\Request;

/**
 * Söksidan
 */
class SearchController extends Controller {
    /**
     * Visa söksidan på URL /sok-blåljushändelser.
     */
    public function adsenseSearch(Request $request) {
        return view(
            'adsenseSearch',
            [
                'pageTitle' => 'Sök blåljushändelser',
                'canonicalLink' => route('adsenseSearch')
            ]
        );
    }
}
