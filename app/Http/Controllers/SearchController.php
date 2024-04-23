<?php

namespace App\Http\Controllers;

use Creitive\Breadcrumbs\Breadcrumbs;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

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
        $searches = Collection::make( \Setting::get('searches3', []) );
       
        // Ta bort för korta sökningar.
        $searches = $searches->reject(function ($vals, $key) {
            return strlen($key) < 3;
        });

        // Ta bort sökningar utan datum (gamla setting, försvinner automatiskt).
        $searches = $searches->reject(function ($search) {
            return !isset($search['last']);
        });

        if ($only_with_hits) {
            $searches = $searches->filter(function ($search) {
                return $search['hits'] > 0;
            });
        }

        return $searches;
    }
}
