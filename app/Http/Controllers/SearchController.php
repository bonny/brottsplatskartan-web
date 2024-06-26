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
                'userSearches' => $this->getSearches(only_with_hits_more_than: 0, only_with_count_more_than: 1),
            ]
        );
    }

    /**
     * Hämta sökningar som användare gjort.
     * 
     * @param bool $only_with_hits_more_than Om bara sökningar med träffar ska hämtas.
     * @return array
     */
    public static function getSearches($only_with_hits_more_than = 0, $only_with_count_more_than = 0) {
        $searches = Collection::make( \Setting::get('searches3', []) );
       
        // Ta bort för korta sökningar.
        $searches = $searches->reject(function ($vals, $key) {
            return mb_strlen($key) < 4;
        });

        // Ta bort sökningar utan datum (gamla setting, försvinner automatiskt).
        $searches = $searches->reject(function ($search) {
            return !isset($search['last']);
        });

        // Ta bort sökningar som innehåller årtal.
        $searches = $searches->reject(function ($search, $key) {
            return preg_match('/\b\d{4}\b/', $key);
        });

        // Ta bort sökningar som innehåller månadsnamn.
        $searches = $searches->reject(function ($search, $key) {
            return preg_match('/\b(januari|februari|mars|april|maj|juni|juli|augusti|september|oktober|november|december)\b/i', $key);
        });

        // Ta bort sökningar som endast är siffror.
        $searches = $searches->reject(function ($search, $key) {
            return preg_match('/^\d+$/', $key);
        });

        // Sortera.
        $searches = $searches->sortByDesc('last')->sortByDesc('count');

        // Ta bort lite mer:
        // - sökningar som är relativa tider, t.ex. "idag", "igår".
        // - sökningar som är datum, t.ex. "16 april", "16 april 2024"

        if ($only_with_hits_more_than) {
            $searches = $searches->filter(function ($search) use ($only_with_hits_more_than) {
                return $search['hits'] > $only_with_hits_more_than;
            });
        }

        if ($only_with_count_more_than) {
            $searches = $searches->filter(function ($search) use ($only_with_count_more_than) {
                return $search['count'] > $only_with_count_more_than;
            });
        }

        return $searches;
    }
}
