<?php

namespace App\Providers;

use Carbon\Carbon;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider {
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot() {
        // https://stackoverflow.com/questions/24426423/laravel-generate-secure-https-url-from-route
        if (\App::environment() === 'production') {
            \URL::forceScheme('https');
        }

        // @TODO: Don't run the code below when we don't have any db connction
        // because we can install things using composer and so on.

        // Lan listing is used in header nav so share it here for easy access.
        // https://laravel.com/docs/9.x/views#sharing-data-with-all-views
        $lan = \App\Helper::getAllLanWithStats();
        \View::share('shared_lan_with_stats', $lan);

        // Contents in notification bar = red banner on top of all pages.
        $notificationBarContents = \Setting::get('notification-bar-contents');
        \View::share('shared_notification_bar_contents', trim($notificationBarContents));

        $inbrottUndersidor = \App\Helper::getInbrottNavItems();
        \View::share('inbrott_undersidor', $inbrottUndersidor);

        \View::share('shared_vma_alerts', \App\Helper::getVMAAlerts());
        \View::share('shared_vma_current_alerts', \App\Helper::getCurrentVMAAlerts());
        \View::share('shared_archived_vma_alerts', \App\Helper::getArchivedVMAAlerts());

        // Mest lästa.
        \View::share('shared_most_viewed_events', \App\Helper::getMostViewedEvents(Carbon::now(), 10));

        // Nyaste.
        \View::share('shared_latest_events', \App\Helper::getLatestEventsByPubdate());
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register() {
        //
    }
}
