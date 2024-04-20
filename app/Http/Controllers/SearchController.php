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
                'canonicalLink' => route('adsenseSearch'),
                'userSearches' => $this->getSearches(true),
            ]
        );
    }

    /**
     * Hämta sökningar som användare gjort.
     * 
     * @param bool $only_with_hits Om bara sökningar med träffar ska hämtas.
     * @return array
     */
    public static function getSearches($only_with_hits = false) {
        /** @var array */
        $searches = \Setting::get('searches3', []);

        if ($only_with_hits) {
            $searches = array_filter($searches, function ($search) {
                return $search['hits'] > 0;
            });
        }

        return $searches;
    }
}
