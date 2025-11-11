# Spatie Laravel Response Cache - Implementation Guide

## Innehållsförteckning

1. [Översikt](#översikt)
2. [Installation](#installation)
3. [Konfiguration](#konfiguration)
4. [CacheProfile Implementation](#cacheprofile-implementation)
5. [User-Specific Caching](#user-specific-caching)
6. [Cache-Busting](#cache-busting)
7. [Cache Invalidering](#cache-invalidering)
8. [Testing](#testing)
9. [Deployment](#deployment)
10. [Best Practices](#best-practices)
11. [Monitoring](#monitoring)
12. [Felsökning](#felsökning)

---

## Översikt

### Vad är Response Cache?

Spatie Laravel Response Cache cachear kompletta HTTP-responses på middleware-nivå. När en request kommer in och det finns en cachad version, returneras den direkt utan att köra någon PHP-kod (controllers, views, etc.).

### Varför använda Response Cache?

**Nuvarande arkitektur (Query Cache):**
```
Request → Laravel → Controller → Cache::remember() → Redis/DB → View → Response
         ↑ 50-200ms PHP-kod körs varje gång
```

**Med Response Cache:**
```
Request → Middleware → Cache HIT → Response (5-10ms)
         ↑ Ingen PHP-kod körs!

Request → Middleware → Cache MISS → Controller → Cache::remember() → View → Cache → Response
         ↑ Första gången tar lite längre, sedan blixsnabbt
```

### Multi-Tier Caching (Båda lagren tillsammans)

```
┌─────────────────────────────────────┐
│   REQUEST                           │
└──────────────┬──────────────────────┘
               │
               ▼
┌─────────────────────────────────────┐
│   Response Cache (Yttre lager)      │ ◄── Snabbast (hela HTTP response)
│   TTL: 2-30 min (volatilt)          │     ~5ms response time
└──────────────┬──────────────────────┘
               │ MISS
               ▼
┌─────────────────────────────────────┐
│   Controller Logic                  │
└──────────────┬──────────────────────┘
               │
               ▼
┌─────────────────────────────────────┐
│   Query Cache (Inre lager)          │ ◄── Fallback (databas-resultat)
│   TTL: 5-120 min (stabilare)        │     ~50ms response time
└──────────────┬──────────────────────┘
               │ MISS
               ▼
┌─────────────────────────────────────┐
│   Database                          │ ◄── Långsammast (full query)
└─────────────────────────────────────┘     ~500ms response time
```

### Viktigt: BEHÅLL Cache::remember()!

**Response cache och query cache kompletterar varandra - ta INTE bort befintlig Cache::remember() kod!**

**Varför?**

1. **Cache Miss-scenario:** När response cache saknas körs controllers fortfarande, då behövs query cache
2. **Olika TTL-strategier:** Response cache kan vara kort (2 min), query cache längre (10 min)
3. **Partiell caching:** Admin-sidor, AJAX, POST-requests använder inte response cache
4. **Console-kommandon:** Artisan-kommandon använder inte HTTP middleware, bara query cache
5. **Development:** Kan inaktivera response cache lokalt men behålla query cache

---

## Installation

### Steg 1: Installera Paketet

```bash
composer require spatie/laravel-responsecache
```

### Steg 2: Publicera Config

```bash
php artisan vendor:publish --tag="responsecache-config"
```

Detta skapar `config/responsecache.php`.

---

## Konfiguration

### Environment Variables (.env)

Lägg till följande i `.env`:

```bash
# Cache driver (använd redis för bäst prestanda)
CACHE_DRIVER=redis
RESPONSE_CACHE_DRIVER=redis

# Cache bypass header (för debugging och admin)
# Generera säkert token: openssl rand -hex 32
CACHE_BYPASS_HEADER_NAME=X-Response-Cache-Bypass
CACHE_BYPASS_HEADER_VALUE=din-hemliga-bypass-token-här
```

### Config-fil (config/responsecache.php)

```php
<?php

return [
    /*
     * Använd custom cache profile för Brottsplatskartan
     */
    'cache_profile' => \App\CacheProfiles\BrottsplatskartanCacheProfile::class,

    /*
     * Cache store (använd Redis)
     */
    'cache_store' => env('RESPONSE_CACHE_DRIVER', 'redis'),

    /*
     * Cache tag för selektiv rensning
     */
    'cache_tag' => 'responsecache',

    /*
     * Cache bypass header (för admin och debugging)
     */
    'cache_bypass_header' => [
        'name' => env('CACHE_BYPASS_HEADER_NAME', 'X-Response-Cache-Bypass'),
        'value' => env('CACHE_BYPASS_HEADER_VALUE', null),
    ],

    /*
     * Replacers för dynamiskt innehåll (CSRF tokens automatiskt uppdaterade)
     */
    'replacers' => [
        \Spatie\ResponseCache\Replacers\CsrfTokenReplacer::class,
    ],

    /*
     * Fallback cache lifetime (om CacheProfile returnerar null)
     */
    'cache_lifetime_in_seconds' => env('RESPONSE_CACHE_LIFETIME', 60 * 60 * 24 * 7),
];
```

### Middleware Setup (app/Http/Kernel.php)

```php
protected $middlewareGroups = [
    'web' => [
        \App\Http\Middleware\EncryptCookies::class,
        \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
        \Illuminate\Session\Middleware\StartSession::class,
        \Illuminate\View\Middleware\ShareErrorsFromSession::class,
        \App\Http\Middleware\VerifyCsrfToken::class,
        \Illuminate\Routing\Middleware\SubstituteBindings::class,
        \App\Http\Middleware\DebugBarMaybeEnable::class,

        // VIKTIGT: Lägg till Response Cache SIST i web middleware
        \Spatie\ResponseCache\Middlewares\CacheResponse::class,
    ],

    'api' => [
        'throttle:500,1',
        \Illuminate\Routing\Middleware\SubstituteBindings::class,

        // Valfritt: Cache även API responses
        // \Spatie\ResponseCache\Middlewares\CacheResponse::class,
    ],
];

protected $routeMiddleware = [
    'auth' => \App\Http\Middleware\Authenticate::class,
    // ... övriga middleware

    // Lägg till för att kunna inaktivera cache på specifika routes
    'doNotCacheResponse' => \Spatie\ResponseCache\Middlewares\DoNotCacheResponse::class,
];
```

---

## CacheProfile Implementation

### Skapa Custom CacheProfile

**Fil:** `app/CacheProfiles/BrottsplatskartanCacheProfile.php`

**Rekommenderad version (förenklad genom att extendera befintlig klass):**

```php
<?php

namespace App\CacheProfiles;

use DateTime;
use Illuminate\Http\Request;
use Spatie\ResponseCache\CacheProfiles\CacheAllSuccessfulGetRequests;

class BrottsplatskartanCacheProfile extends CacheAllSuccessfulGetRequests
{
    /**
     * Bestäm cache-livstid baserat på URL/route
     */
    public function cacheRequestUntil(Request $request): DateTime
    {
        // Startsida: kort cache (2 minuter)
        if ($request->is('/') || $request->is('')) {
            return now()->addMinutes(2);
        }

        // Historiska datum-sidor: mycket lång cache (7 dagar)
        // Gammal data ändras aldrig, så kan cachas länge
        if ($request->is('handelser/*')) {
            $date = $this->extractDateFromUrl($request->path());
            if ($date && $date->diffInDays(now()) > 7) {
                return now()->addDays(7);
            }
        }

        // Län-översikter: medellång cache (2 timmar)
        if ($request->is('lan/*')) {
            return now()->addHours(2);
        }

        // Stads-sidor: medellång cache (1 timme)
        if ($request->is('plats/*')) {
            return now()->addHours(1);
        }

        // Specifika brottskategorier: kort cache (5 minuter)
        if ($request->is('inbrott') ||
            $request->is('brand') ||
            $request->is('trafikolycka')) {
            return now()->addMinutes(5);
        }

        // API endpoints: varierad cache
        if ($request->is('api/events')) {
            return now()->addMinutes(10);
        }

        if ($request->is('api/statistics/*')) {
            return now()->addHours(6);
        }

        // VMA alerts: mycket kort cache (1 minut)
        if ($request->is('vma') || $request->is('api/vma')) {
            return now()->addMinutes(1);
        }

        // Standard: 30 minuter
        return now()->addMinutes(30);
    }

    /**
     * Avgör om denna request ska cachelagras
     */
    public function shouldCacheRequest(Request $request): bool
    {
        // Tillåt inloggade användare att bypassa cache med ?fresh=1
        if ($request->user() && $request->has('fresh')) {
            return false;
        }

        // Ingen cache för admin-sidor
        if ($request->is('admin/*')) {
            return false;
        }

        // Ingen cache för API endpoints som kräver real-time data
        if ($request->is('api/live/*')) {
            return false;
        }

        // Använd parent-klassens logik för övriga checks
        return parent::shouldCacheRequest($request);
    }

    /**
     * Suffix för att skilja olika användares cache
     *
     * Inloggade användare får varsin cache baserad på user ID
     * Icke-inloggade delar samma cache (tom sträng)
     */
    public function useCacheNameSuffix(Request $request): string
    {
        if ($request->user()) {
            // Använd user ID som suffix för inloggade
            return (string) $request->user()->id;
        }

        // Tom sträng = delad cache för alla gäster
        return '';
    }

    /**
     * Hjälpmetod för att extrahera datum från URL
     */
    private function extractDateFromUrl(string $path): ?\Carbon\Carbon
    {
        // Extrahera datum från URL som "handelser/15-januari-2024"
        if (preg_match('/(\d{1,2})-([a-zåäö]+)-(\d{4})/i', $path, $matches)) {
            try {
                $day = $matches[1];
                $month = $matches[2];
                $year = $matches[3];

                // Konvertera svensk månad till Carbon
                $monthMap = [
                    'januari' => 'January',
                    'februari' => 'February',
                    'mars' => 'March',
                    'april' => 'April',
                    'maj' => 'May',
                    'juni' => 'June',
                    'juli' => 'July',
                    'augusti' => 'August',
                    'september' => 'September',
                    'oktober' => 'October',
                    'november' => 'November',
                    'december' => 'December',
                ];

                $englishMonth = $monthMap[strtolower($month)] ?? $month;
                return \Carbon\Carbon::parse("$day $englishMonth $year");
            } catch (\Exception $e) {
                return null;
            }
        }

        return null;
    }
}
```

### Cache-tider Sammanfattning

| Sidtyp | Cache-tid | Motivering |
|--------|-----------|------------|
| Startsida (`/`) | 2 minuter | Ofta uppdaterad med nya händelser |
| Historiska datum (>7 dagar) | 7 dagar | Gammal data ändras aldrig |
| Län-översikter (`/lan/*`) | 2 timmar | Relativt statisk aggregerad data |
| Plats-sidor (`/plats/*`) | 1 timme | Medelfrekvent uppdateringar |
| Brottskategorier (`/inbrott`, `/brand`) | 5 minuter | Behöver vara relativt aktuella |
| VMA alerts (`/vma`) | 1 minut | Kritisk info, måste vara färsk |
| API events (`/api/events`) | 10 minuter | Balans mellan prestanda och fräschhet |
| API statistik (`/api/statistics/*`) | 6 timmar | Tung beräkning, ändras sällan |
| Standard | 30 minuter | Säker fallback för okända sidor |

---

## User-Specific Caching

### Hur det fungerar

**useCacheNameSuffix()** returnerar en sträng som läggs till i cache-nyckeln:

- **Inloggad användare:** suffix = användar-ID (t.ex. "123")
- **Ej inloggad användare:** suffix = "" (tom sträng, delad cache)

**Cache-nyckel format:**
```
responsecache:{url}:{suffix}
```

**Exempel:**
```
Gäst:          responsecache:https://brottsplatskartan.se/stockholm:
Inloggad (5):  responsecache:https://brottsplatskartan.se/stockholm:5
Inloggad (7):  responsecache:https://brottsplatskartan.se/stockholm:7
```

### Resultat

- ✅ Alla gäster delar samma cache (effektivt)
- ✅ Varje inloggad användare får egen cache
- ✅ User-specific innehåll (dashboard, settings) cachas korrekt
- ✅ Ingen risk för att visa fel användares data

---

## Cache-Busting

### Metod 1: HTTP Header (Rekommenderas för Admin)

**Användning:**
```bash
# cURL
curl -H "X-Response-Cache-Bypass: din-hemliga-bypass-token-här" \
  https://brottsplatskartan.se/

# JavaScript/AJAX
fetch('/api/events', {
    headers: {
        'X-Response-Cache-Bypass': 'din-hemliga-bypass-token-här'
    }
})
```

**Säkerhet:**
- Token sparas i `.env` (CACHE_BYPASS_HEADER_VALUE)
- Generera säkert token: `openssl rand -hex 32`
- Dela ALDRIG token publikt

### Metod 2: Query Parameter (För inloggade användare)

Implementerat i `BrottsplatskartanCacheProfile::shouldCacheRequest()`:

```php
// Tillåt inloggade användare att bypassa med ?fresh=1
if ($request->user() && $request->has('fresh')) {
    return false;
}
```

**Användning:**
```
https://brottsplatskartan.se/?fresh=1
https://brottsplatskartan.se/plats/stockholm?fresh=1
```

**Säkerhet:**
- Endast inloggade kan använda `?fresh=1`
- Gäster kan INTE bypassa cache (undviker abuse)

### Metod 3: Middleware Route-Exclusion

För sidor som ALDRIG ska cachas:

```php
// routes/web.php
Route::get('/admin/dashboard', [AdminController::class, 'index'])
    ->middleware('auth', 'doNotCacheResponse');
```

---

## Cache Invalidering

### När ska cache rensas?

1. **Vid ny händelse-import** (`crimeevents:fetch`)
2. **Vid manuell uppdatering av händelse**
3. **Vid deployment** (optional, för säkerhets skull)
4. **Schemalagd natt-rensning** (optional, housekeeping)

### Metoder

#### 1. Rensa HELA cachen

```php
use Spatie\ResponseCache\Facades\ResponseCache;

ResponseCache::clear();
```

**När använda:**
- Vid deployment
- Vid stora ändringar
- Schemalagd rensning

#### 2. Glöm specifika URLs (fungerar ENDAST utan suffix)

```php
ResponseCache::forget('/');
ResponseCache::forget(['/stockholm', '/lan/stockholm']);
```

**Begränsning:** Fungerar INTE om `useCacheNameSuffix()` används (vilket vi gör).

#### 3. Selektiv invalidering (fungerar MED suffix)

```php
// Invalidera specifika URLs
ResponseCache::selectCachedItems()
    ->forUrls('/stockholm', '/plats/stockholm')
    ->forget();

// Invalidera baserat på URL-pattern
ResponseCache::selectCachedItems()
    ->forUrls(function ($url) {
        return str_contains($url, 'stockholm');
    })
    ->forget();

// Invalidera för specifik användare
ResponseCache::selectCachedItems()
    ->usingSuffix('123') // User ID
    ->forget();
```

### Implementation i Controllers

**Exempel: När ny händelse skapas**

```php
use Spatie\ResponseCache\Facades\ResponseCache;

public function store(Request $request)
{
    $event = CrimeEvent::create($request->all());

    // Invalidera relevanta sidor
    ResponseCache::selectCachedItems()
        ->forUrls(
            '/',
            '/plats/' . $event->location,
            '/lan/' . $event->county
        )
        ->forget();

    return response()->json($event, 201);
}
```

### Scheduled Cache Clearing

**app/Console/Kernel.php:**

```php
protected function schedule(Schedule $schedule)
{
    // Rensa response cache varje natt kl 03:00
    $schedule->call(function () {
        ResponseCache::clear();
    })->daily()->at('03:00');

    // BEHÅLL befintliga scheduled tasks
    $schedule->command('crimeevents:fetch')->everyThirtyMinutes();
    // ...
}
```

### Integration med crimeevents:fetch

**VIKTIGT:** Vid import av nya händelser måste cache rensas.

**Option 1: Rensa allt (enklast)**
```php
// app/Console/Commands/CrimeEventsFetch.php
use Spatie\ResponseCache\Facades\ResponseCache;

public function handle()
{
    // Importera händelser...

    // Rensa response cache
    ResponseCache::clear();

    $this->info('Cache cleared');
}
```

**Option 2: Selektiv rensning (effektivare)**
```php
public function handle()
{
    $importedLocations = []; // Samla platser som uppdaterades

    // Importera händelser...
    foreach ($newEvents as $event) {
        $importedLocations[] = $event->location;
    }

    // Rensa endast berörda sidor
    ResponseCache::selectCachedItems()
        ->forUrls(function ($url) use ($importedLocations) {
            foreach ($importedLocations as $location) {
                if (str_contains($url, $location)) {
                    return true;
                }
            }
            return false;
        })
        ->forget();
}
```

---

## Testing

### Test 1: Verifiera Cache Headers

```bash
# Första requesten (cachar)
curl -I https://brottsplatskartan.se/

# Svar ska INTE innehålla cache header (första gången)

# Andra requesten (läser från cache)
curl -I https://brottsplatskartan.se/

# Svar ska innehålla:
# laravel-responsecache: cached on 2025-01-15 14:23:45
```

### Test 2: Cache Bypass med Header

```bash
curl -I -H "X-Response-Cache-Bypass: din-hemliga-bypass-token-här" \
  https://brottsplatskartan.se/

# Ska INTE ha laravel-responsecache header
```

### Test 3: Manuell Verifiering med Browser

**Chrome DevTools:**
1. Öppna DevTools (F12)
2. Gå till Network-fliken
3. Ladda sidan
4. Klicka på första request
5. Kolla "Response Headers"
6. Leta efter `laravel-responsecache: cached on ...`

**Laravel Debugbar:**
```php
// För att se cache-status i Debugbar
// Aktivera i .env:
DEBUGBAR_ENABLED=true

// Ladda sidan och kolla "Cache" tab
```

---

## Deployment

### Lokal Deployment (Development)

```bash
# 1. Installera paketet
composer require spatie/laravel-responsecache

# 2. Publicera config
php artisan vendor:publish --tag="responsecache-config"

# 3. Skapa CacheProfile (se sektion "CacheProfile Implementation")
mkdir -p app/CacheProfiles
# Skapa filen BrottsplatskartanCacheProfile.php

# 4. Uppdatera config/responsecache.php
# (Ändra 'cache_profile' till BrottsplatskartanCacheProfile::class)

# 5. Uppdatera app/Http/Kernel.php
# (Lägg till CacheResponse middleware)

# 6. Sätt environment variables
# Lägg till i .env:
# CACHE_BYPASS_HEADER_VALUE=$(openssl rand -hex 32)

# 7. Testa
php artisan serve
curl -I http://localhost:8000/
```

### Produktion Deployment (Dokku)

```bash
# Lokalt: Commit och push
git add .
git commit -m "Add Spatie Response Cache"
git push origin main

# GitHub Actions deployar automatiskt till Dokku

# SSH till produktionsservern
ssh <server>

# Sätt environment variables
dokku config:set brottsplatskartan \
  CACHE_DRIVER=redis \
  RESPONSE_CACHE_DRIVER=redis \
  CACHE_BYPASS_HEADER_NAME=X-Response-Cache-Bypass \
  CACHE_BYPASS_HEADER_VALUE=$(openssl rand -hex 32)

# Publicera config (om inte redan gjort)
dokku run brottsplatskartan php artisan vendor:publish --tag="responsecache-config" --force

# Rensa och optimera
dokku run brottsplatskartan php artisan config:cache
dokku run brottsplatskartan php artisan view:cache
dokku run brottsplatskartan php artisan responsecache:clear

# Restart
dokku ps:restart brottsplatskartan

# Verifiera
curl -I https://brottsplatskartan.se/
# Kolla efter "laravel-responsecache" header efter andra requesten
```

### Post-Deployment Checklist

- [ ] Response cache fungerar (curl -I visar header)
- [ ] Cache bypass fungerar (med header)
- [ ] ?fresh=1 fungerar för inloggade användare
- [ ] Redis har tillräckligt minne (512MB+)
- [ ] crimeevents:fetch rensar cache korrekt
- [ ] Olika användare får olika cache
- [ ] Admin-sidor cachas INTE
- [ ] Historiska sidor har lång cache (7 dagar)

---

## Best Practices

### 1. BEHÅLL Cache::remember()

**VIKTIGT:** Ta INTE bort befintlig query-level caching!

Response cache och query cache kompletterar varandra:

```php
// BÅDA lagren behövs!

// Response Cache (yttre lager)
// - Cachear hela HTTP response
// - TTL: 2-30 minuter
// - Snabbast vid cache hit (~5ms)

// Query Cache (inre lager)
// - Cachear databas-resultat
// - TTL: 5-120 minuter
// - Fallback när response cache saknas (~50ms)

// Utan Query Cache:
Response MISS → Controller → DB query (500ms) → View → Response
                                 ↑ LÅNGSAMT varje cache miss!

// Med Query Cache:
Response MISS → Controller → Cache HIT (50ms) → View → Response
                                 ↑ SNABBT även vid cache miss!
```

### 2. Olika TTL-strategier

Använd OLIKA cache-tider för response vs queries:

```php
// StartController.php
// Response cache: 2 minuter (från CacheProfile)
// Query cache: 10 minuter (hårdare i koden)
$mostCommon = Cache::remember($key, 10 * MINUTE_IN_SECONDS, function() {
    // Tung GROUP BY query
});

// Resultat:
// - Minut 0-2: Response cache träffar, query körs aldrig
// - Minut 2-10: Response cache miss, men query cache träffar (snabbt!)
// - Minut 10+: Båda missar, kör full query (acceptabelt, händer sällan)
```

### 3. Selektiv Caching

Cacha INTE allt - var smart:

```php
// BRA: Historiska sidor (data ändras aldrig)
if ($date->diffInDays(now()) > 7) {
    return now()->addDays(7); // 7 dagars cache
}

// BRA: Statiska sidor
if ($request->is('om-oss')) {
    return now()->addDays(30); // 30 dagars cache
}

// DÅLIGT: Real-time data
if ($request->is('live-feed')) {
    return false; // Cacha inte alls!
}
```

### 4. Cache Invalidering Strategier

**Option A: Rensa allt (enklast)**
```php
// Vid crimeevents:fetch
ResponseCache::clear();
```
- ✅ Enklast att implementera
- ✅ Ingen risk att missa något
- ❌ Rensar även orelaterad cache

**Option B: Selektiv rensning (effektivare)**
```php
// Rensa endast berörda platser
ResponseCache::selectCachedItems()
    ->forUrls('/', '/plats/stockholm', '/lan/stockholm')
    ->forget();
```
- ✅ Behåller cache för orelaterade sidor
- ✅ Bättre prestanda
- ❌ Mer komplex logik

**Rekommendation:** Börja med Option A, optimera till Option B om behov finns.

### 5. Monitoring

Övervaka cache-prestanda:

```php
// Redis: Antal cache keys
dokku redis:connect brottsplatskartan
DBSIZE

// Redis: Cache hit/miss rate
INFO stats
# Kolla keyspace_hits vs keyspace_misses

// Laravel: Response time
// Använd Laravel Telescope eller New Relic
```

### 6. Development Best Practices

```php
// Inaktivera response cache lokalt för snabbare utveckling
// .env.local
RESPONSE_CACHE_ENABLED=false

// Eller rensa ofta
php artisan responsecache:clear
```

---

## Monitoring

### Redis Cache Metrics

```bash
# Anslut till Redis
dokku redis:connect brottsplatskartan

# Antal cache keys
DBSIZE

# Cache hit rate
INFO stats
# Kolla:
# keyspace_hits: antal cache hits
# keyspace_misses: antal cache misses
# Hit rate = hits / (hits + misses)

# Cache keys för response cache
KEYS responsecache:*

# Inspektera specifik cache
GET "responsecache:https://brottsplatskartan.se/:"

# TTL för cache
TTL "responsecache:https://brottsplatskartan.se/:"
```

### Performance Metrics

**Förväntade response times:**

| Scenario | Response Time | Förklaring |
|----------|---------------|------------|
| Response Cache HIT | 5-10ms | Blixtsnabbt, ingen PHP körs |
| Response Cache MISS + Query Cache HIT | 50-100ms | PHP körs, men queries cachade |
| Response Cache MISS + Query Cache MISS | 500-1000ms | Full database query |

**Målsättning:**
- 95% av requests: Response Cache HIT (<10ms)
- 4% av requests: Query Cache HIT (<100ms)
- 1% av requests: Full query (<1000ms)

### Laravel Debugbar

```bash
# Aktivera Debugbar
DEBUGBAR_ENABLED=true

# Kolla "Cache" tab för:
# - Antal cache hits/misses
# - Cache keys som används
# - Cache size
```

### Application Performance Monitoring (APM)

Om ni använder New Relic, Scout APM eller liknande:

```php
// Instrumentera response cache
use Spatie\ResponseCache\Facades\ResponseCache;

ResponseCache::macro('clearWithMetrics', function() {
    $start = microtime(true);
    ResponseCache::clear();
    $duration = microtime(true) - $start;

    // Logga till APM
    \Log::info('Response cache cleared', [
        'duration_ms' => $duration * 1000
    ]);
});
```

---

## Artisan Commands

### Tillgängliga Kommandon

```bash
# Rensa hela response cache
php artisan responsecache:clear

# Produktionsserver (Dokku)
dokku run brottsplatskartan php artisan responsecache:clear
```

### Integration med Befintliga Kommandon

```bash
# Efter import av händelser
php artisan crimeevents:fetch
# (Ska automatiskt rensa response cache)

# Efter TextTV-import
php artisan texttv:fetch
# (Överväg att rensa cache här också om TextTV-box cachas)
```

---

## Sammanfattning

### Vad Response Cache Ger

**Fördelar:**
- ✅ 50-80% snabbare response times vid cache hit
- ✅ Minskar CPU-belastning på servern
- ✅ Minskar databas-queries
- ✅ Bättre användarupplevelse (snabbare sidor)
- ✅ Kan hantera högre trafikvolymer

**Begränsningar:**
- ⚠️ Kräver aktiv cache-invalidering
- ⚠️ Mer komplexitet att underhålla
- ⚠️ Risk för inaktuell data om misslyckad invalidering

### Nästa Steg

1. **Implementera grundläggande setup** (1-2 timmar)
   - Installation och konfiguration
   - Skapa BrottsplatskartanCacheProfile
   - Uppdatera Kernel.php

2. **Testa lokalt** (30 minuter)
   - Verifiera cache fungerar
   - Testa user-specific caching
   - Testa cache-busting

3. **Deploya till produktion** (30 minuter)
   - Push till GitHub
   - Sätt environment variables
   - Verifiera i produktion

4. **Övervaka och optimera** (kontinuerligt)
   - Kolla Redis metrics
   - Justera cache TTL
   - Optimera invaliderings-logik

---

## Support och Dokumentation

**Spatie Laravel Response Cache:**
- GitHub: https://github.com/spatie/laravel-responsecache
- Dokumentation: https://spatie.be/docs/laravel-responsecache

**Laravel Caching:**
- Dokumentation: https://laravel.com/docs/cache

**Brottsplatskartan:**
- AGENTS.md: Produktionsserver-kommandon
- CLAUDE.md: Projektvägledning
