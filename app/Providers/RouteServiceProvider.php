<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to the "home" route for your application.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/home';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     *
     * @return void
     */
    public function boot()
    {
        $this->configureRateLimiting();

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }

    /**
     * Configure the rate limiters for the application.
     *
     * @return void
     */
    protected function configureRateLimiting()
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // Egen limiter för tracking-pixlarna (todo #100). Namngiven med
        // flit: `throttle:60,1` utan namn nycklar på `domain|ip` — samma
        // nyckel för ALLA namnlösa throttles, alltså delad med
        // api-gruppens `throttle:500,1`. Pixelkvoten hade då ätits upp av
        // kartans /api/eventsMap-anrop från samma besökare.
        //
        // 120/min är rikligt för en människa (en pixel per sidvisning)
        // men räcker inte för att pumpa "Mest lästa" i någon meningsfull
        // skala. Taket är medvetet högt eftersom mobilsurfare delar
        // utgående IP via CGNAT — för snålt tak tappar äkta mätdata.
        RateLimiter::for('pixel', function (Request $request) {
            return Limit::perMinute(120)->by($request->ip());
        });
    }
}
