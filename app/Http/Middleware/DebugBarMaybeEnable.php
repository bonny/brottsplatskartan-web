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
        //if (Cookie::has('debugbar')) {
        if (\Auth::check() && $request->has('debugbar-enable')) {
            \Debugbar::enable();
        }

        return $next($request);
    }
}
