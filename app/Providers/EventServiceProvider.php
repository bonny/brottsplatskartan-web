<?php

namespace App\Providers;

use Illuminate\Cache\Events\CacheFlushed;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Redis;
use Spatie\ResponseCache\Events\ClearedResponseCacheEvent;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array<string, array<int, string>>
     */
    protected $listen = [
        'App\Events\SomeEvent' => [
            'App\Listeners\EventListener',
        ],
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        parent::boot();

        // Logga tidpunkten för cache-rensningar i meta-nycklar utanför
        // cache-prefixet, så de överlever själva flushen och kan visas av
        // `redis:health`. Listenern körs EFTER flushen → skriv-ordningen
        // gör att nyckeln alltid finns kvar.
        Event::listen(CacheFlushed::class, function (): void {
            Redis::connection()->set('bpk:meta:last-cache-flush', now()->toIso8601String());
        });

        Event::listen(ClearedResponseCacheEvent::class, function (): void {
            Redis::connection()->set('bpk:meta:last-responsecache-flush', now()->toIso8601String());
        });
    }
}
