<?php

namespace App\ResponseCache;

use Illuminate\Http\Request;
use Spatie\ResponseCache\Hasher\DefaultHasher;

class CustomRequestHasher extends DefaultHasher
{
    /**
     * Normalisera request URI och ta bort query params som inte ska påverka cache
     *
     * Override:ar parent för att filtrera bort query parameters som t, _, nocache, timestamp
     * så att de inte skapar separata cache-entries.
     */
    protected function getNormalizedRequestUri(Request $request): string
    {
        // Ta bort query params som inte ska påverka cache
        $query = $request->query->all();
        $ignoreParams = ['t', '_', 'nocache', 'timestamp'];

        foreach ($ignoreParams as $param) {
            unset($query[$param]);
        }

        // Bygg query string (samma logik som parent)
        $queryString = '';
        if (!empty($query)) {
            $queryString = '?' . http_build_query($query);
        }

        return $request->getBaseUrl() . $request->getPathInfo() . $queryString;
    }
}
