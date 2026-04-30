<?php

namespace App\Providers;

use Illuminate\Cache\Events\CacheFlushed;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;
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

        // Logga tidpunkten för cache-rensningar till fil. Tidigare lagrades
        // detta i Redis, men med allkeys-lru evicterades meta-nycklarna i
        // takt med övriga keys när cache närmade sig maxmemory.
        Event::listen(CacheFlushed::class, function (): void {
            self::writeFlushTimestamp('last-cache-flush');
        });

        Event::listen(ClearedResponseCacheEvent::class, function (): void {
            self::writeFlushTimestamp('last-responsecache-flush');
        });
    }

    public static function flushTimestampPath(string $name): string
    {
        return storage_path('app/cache-meta/' . $name);
    }

    private static function writeFlushTimestamp(string $name): void
    {
        $path = self::flushTimestampPath($name);
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        @file_put_contents($path, now()->toIso8601String());
    }
}
