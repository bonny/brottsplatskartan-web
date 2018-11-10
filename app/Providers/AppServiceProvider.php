<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // https://stackoverflow.com/questions/24426423/laravel-generate-secure-https-url-from-route
        \URL::forceScheme('https');

        // Lan listing is used in header nav so share it here for easy access.
        // https://laravel.com/docs/5.5/views#passing-data-to-views
        $lan = \App\Helper::getAllLanWithStats();
        \View::share('shared_lan_with_stats', $lan);

        $notificationBarContents = \Setting::get('notification-bar-contents');
        \View::share('shared_notification_bar_contents', trim($notificationBarContents));
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }
}
