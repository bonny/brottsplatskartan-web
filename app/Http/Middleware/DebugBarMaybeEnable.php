<?php

/**
 * https://github.com/barryvdh/laravel-debugbar/issues/34
 */

namespace App\Http\Middleware;

use Closure;

class DebugBarMaybeEnable
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        // This works both for regular pages/routes
        // but also for debug bar urls like https://brottsplatskartan.localhost/_debugbar/assets/stylesheets?v=1520325331
        // if (\Cookie::has('show-debugbar')) {
        //     \Debugbar::enable();
        // }

        return $next($request);
    }
}
