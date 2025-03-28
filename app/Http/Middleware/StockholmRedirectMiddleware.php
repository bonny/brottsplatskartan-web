<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class StockholmRedirectMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $path = urldecode($request->path());
        
        // List of patterns to match for Stockholm redirects
        $patterns = [
            'plats/stockholms-län',
            'plats/stockholm-stockholms-län',
            'plats/stockholm',
            'sida/stockholm',
            'lan/stockholms-lan',
            'plats/stockholm-city',
            'plats/stockholms-län-stockholms-län',
            'plats/södra-stockholm-stockholms-län',
            'lan/Stockholms län'
        ];

        // Check if the current path matches any of our patterns
        foreach ($patterns as $pattern) {
            // Match direct paths
            if (stripos($path, $pattern) === 0) {
                return redirect()->route('city', ['city' => 'stockholm'], 301);
            }
            
            // Match pagination paths by checking if the path starts with our pattern and contains /handelser/
            if (stripos($path, $pattern) === 0 && strpos($path, '/handelser/') !== false) {
                return redirect()->route('city', ['city' => 'stockholm'], 301);
            }
        }

        return $next($request);
    }
} 