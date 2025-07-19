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

        // Skip database queries when running console commands or if database is not available
        if (!app()->runningInConsole()) {
            try {
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
                \View::share('shared_latest_events', \App\Helper::getLatestEventsByParsedDate(10));
            } catch (\Exception $e) {
                // Database not available - set empty defaults
                \View::share('shared_lan_with_stats', []);
                \View::share('shared_notification_bar_contents', '');
                \View::share('inbrott_undersidor', []);
                \View::share('shared_vma_alerts', []);
                \View::share('shared_vma_current_alerts', []);
                \View::share('shared_archived_vma_alerts', []);
                \View::share('shared_most_viewed_events', []);
                \View::share('shared_latest_events', []);
            }
        }
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
