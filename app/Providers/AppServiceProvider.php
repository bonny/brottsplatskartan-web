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
        if (\App::environment() === 'production') {
            \URL::forceScheme('https');
        }

        // @TODO: Don't run the code below when we don't have any db connction
        // because we can install things using composer and so on.
        
        // Lan listing is used in header nav so share it here for easy access.
        // https://laravel.com/docs/5.5/views#passing-data-to-views
        $lan = \App\Helper::getAllLanWithStats();
        \View::share('shared_lan_with_stats', $lan);

        // Contents in notification bar = red banner on top of all pages.
        $notificationBarContents = \Setting::get('notification-bar-contents');
        \View::share('shared_notification_bar_contents', trim($notificationBarContents));

        $inbrottUndersidor = \App\Helper::getInbrottNavItems();
        \View::share('inbrott_undersidor', $inbrottUndersidor);
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
