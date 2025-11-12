# Spatie Laravel Response Cache - Implementation

## Översikt

Spatie Response Cache cachear kompletta HTTP-responses på middleware-nivå för maximala prestandavinster.

### Multi-Tier Caching Architecture

```
┌─────────────────────────────────────┐
│   REQUEST                           │
└──────────────┬──────────────────────┘
               │
               ▼
┌─────────────────────────────────────┐
│   Response Cache (Yttre lager)      │ ← ~5ms (hela HTTP response)
│   TTL: 2-30 min                     │
└──────────────┬──────────────────────┘
               │ MISS
               ▼
┌─────────────────────────────────────┐
│   Controller Logic                  │
└──────────────┬──────────────────────┘
               │
               ▼
┌─────────────────────────────────────┐
│   Query Cache (Inre lager)          │ ← ~50ms (databas-resultat)
│   TTL: 5-120 min                    │
└──────────────┬──────────────────────┘
               │ MISS
               ▼
┌─────────────────────────────────────┐
│   Database                          │ ← ~500ms (full query)
└─────────────────────────────────────┘
```

**VIKTIGT:** Båda cache-lagren behövs! Ta INTE bort `Cache::remember()` - response cache och query cache kompletterar varandra.

---

## Installation & Konfiguration

### 1. Installera Paketet

```bash
composer require spatie/laravel-responsecache
php artisan vendor:publish --tag="responsecache-config"
```

### 2. Environment Variables (.env)

```bash
CACHE_DRIVER=redis
RESPONSE_CACHE_DRIVER=redis
RESPONSE_CACHE_ADD_TIME_HEADER=true
CACHE_BYPASS_HEADER_NAME=X-Response-Cache-Bypass
CACHE_BYPASS_HEADER_VALUE=$(openssl rand -hex 32)  # Generera säkert token
```

### 3. Config (config/responsecache.php)

Uppdatera dessa rader:

```php
'cache_profile' => \App\CacheProfiles\BrottsplatskartanCacheProfile::class,
'hasher' => \App\ResponseCache\CustomRequestHasher::class,
'cache_store' => env('RESPONSE_CACHE_DRIVER', 'redis'),
'add_cache_time_header' => env('RESPONSE_CACHE_ADD_TIME_HEADER', true),
```

### 4. Middleware (app/Http/Kernel.php)

Lägg till SIST i `web` middleware-gruppen:

```php
protected $middlewareGroups = [
    'web' => [
        // ... andra middleware
        \Spatie\ResponseCache\Middlewares\CacheResponse::class,  // SIST!
    ],
];
```

---

## Custom Cache Profile

**Fil:** `app/CacheProfiles/BrottsplatskartanCacheProfile.php`

Bestämmer cache-livstider baserat på URL. Se aktuell kod för implementation.

### Cache-tider Översikt

| Sidtyp | Cache-tid | Motivering |
|--------|-----------|------------|
| Startsida (`/`) | 2 minuter | Ofta uppdaterad med nya händelser |
| VMA alerts (`/vma`) | 2 minuter | Kritisk info, måste vara färsk |
| Historiska datum (>7 dagar gamla) | 7 dagar | Gammal data ändras aldrig |
| API events (`/api/events`) | 10 minuter | Balans mellan prestanda och fräschhet |
| Standard | 30 minuter | Säker fallback |

---

## Custom Request Hasher

**Fil:** `app/ResponseCache/CustomRequestHasher.php`

Filtrerar bort query-parametrar (`?t=`, `?_=`, `?nocache=`, `?timestamp=`) som inte ska påverka cachen.

**Varför viktigt?**
- Utan: `/?t=123` och `/?t=456` skapar separata cache-entries
- Med: Båda delar samma cache-entry (effektivare)

---

## Cache-Busting & Invalidering

### Metod 1: HTTP Header (för debugging)

```bash
curl -H "X-Response-Cache-Bypass: <token>" https://brottsplatskartan.se/
```

### Metod 2: Rensa Cache

```bash
# Lokalt
php artisan responsecache:clear

# Produktion
dokku run brottsplatskartan php artisan responsecache:clear
```

### Metod 3: Selektiv Invalidering

```php
use Spatie\ResponseCache\Facades\ResponseCache;

// Rensa specifika URLs
ResponseCache::selectCachedItems()
    ->forUrls('/', '/stockholm', '/lan/stockholm')
    ->forget();
```

### Integration med crimeevents:fetch

Lägg till i `app/Console/Commands/CrimeEventsFetch.php`:

```php
use Spatie\ResponseCache\Facades\ResponseCache;

public function handle()
{
    // Importera händelser...

    // Rensa response cache
    ResponseCache::clear();

    $this->info('Cache cleared');
}
```

---

## Testing & Verifiering

### Test 1: Verifiera Cache Headers

```bash
# Första requesten (cachar)
curl -I https://brottsplatskartan.se/

# Andra requesten (läser från cache)
curl -I https://brottsplatskartan.se/
# Förväntat: "laravel-responsecache: cached on <timestamp>"
```

### Test 2: Query Parameter Filtering

```bash
curl -I "https://brottsplatskartan.se/?t=123"
curl -I "https://brottsplatskartan.se/?t=456"
# Båda ska visa samma cached timestamp
```

### Test 3: Browser DevTools

1. Öppna DevTools (F12) → Network
2. Ladda sidan
3. Kolla Response Headers
4. Leta efter `laravel-responsecache: cached on ...`

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
# Kolla: keyspace_hits / (keyspace_hits + keyspace_misses)

# Response cache keys
KEYS responsecache:*

# Inspektera specifik cache + TTL
GET "responsecache:https://brottsplatskartan.se/:"
TTL "responsecache:https://brottsplatskartan.se/:"
```

### Performance Metrics

| Scenario | Response Time | Förklaring |
|----------|---------------|------------|
| Response Cache HIT | 5-10ms | Ingen PHP körs |
| Response Cache MISS + Query Cache HIT | 50-100ms | PHP körs, queries cachade |
| Response Cache MISS + Query Cache MISS | 500-1000ms | Full database query |

**Målsättning:**
- 95% requests: Response Cache HIT (<10ms)
- 4% requests: Query Cache HIT (<100ms)
- 1% requests: Full query (<1000ms)

---

## Deployment

### Lokal Development

```bash
# Skapa katalog för custom klasser
mkdir -p app/CacheProfiles app/ResponseCache

# Skapa BrottsplatskartanCacheProfile.php och CustomRequestHasher.php
# (Se faktiska filer för kod)

# Uppdatera .env
echo "CACHE_DRIVER=redis" >> .env
echo "RESPONSE_CACHE_DRIVER=redis" >> .env
echo "RESPONSE_CACHE_ADD_TIME_HEADER=true" >> .env

# Cacha config
php artisan config:cache

# Testa
php artisan serve
curl -I http://localhost:8000/
```

### Produktion (Dokku)

```bash
# Push till main (GitHub Actions deployar automatiskt)
git push origin main

# SSH till servern
ssh <server>

# Sätt environment variables
dokku config:set brottsplatskartan \
  CACHE_DRIVER=redis \
  RESPONSE_CACHE_DRIVER=redis \
  RESPONSE_CACHE_ADD_TIME_HEADER=true \
  CACHE_BYPASS_HEADER_NAME=X-Response-Cache-Bypass \
  CACHE_BYPASS_HEADER_VALUE=$(openssl rand -hex 32)

# Rensa och optimera
dokku run brottsplatskartan php artisan config:cache
dokku run brottsplatskartan php artisan responsecache:clear

# Verifiera
curl -I https://brottsplatskartan.se/  # Första (cachar)
curl -I https://brottsplatskartan.se/  # Andra (från cache)
```

### Post-Deployment Checklist

- [ ] Response cache fungerar (header syns efter andra requesten)
- [ ] Query-parametrar filtreras korrekt (samma cache entry)
- [ ] Cache bypass fungerar (med header)
- [ ] Redis har tillräckligt minne (512MB+)

---

## Best Practices

### 1. Behåll Cache::remember()

Response cache och query cache kompletterar varandra:

```php
// BÅDA lagren behövs!

// Response Cache (yttre): 2-30 min, ~5ms vid hit
// Query Cache (inre): 5-120 min, ~50ms vid hit

// Utan Query Cache:
Response MISS → DB query (500ms) ← LÅNGSAMT!

// Med Query Cache:
Response MISS → Cache HIT (50ms) ← SNABBT!
```

### 2. Olika TTL-strategier

```php
// Response cache: 2 minuter (från CacheProfile)
// Query cache: 10 minuter (hårdare i koden)
$data = Cache::remember($key, 10 * MINUTE_IN_SECONDS, function() {
    // Tung query
});

// Resultat:
// Min 0-2: Response cache träffar
// Min 2-10: Response miss, men query cache träffar (snabbt!)
// Min 10+: Båda missar (acceptabelt, sällan)
```

### 3. Selektiv Caching

```php
// BRA: Historiska sidor (data ändras aldrig)
if ($date->diffInDays(now()) > 7) {
    return now()->addDays(7);
}

// DÅLIGT: Real-time data
if ($request->is('live-feed')) {
    return false; // Cacha inte!
}
```

---

## Sammanfattning

### Fördelar
- ✅ 50-80% snabbare response times vid cache hit
- ✅ Minskar CPU-belastning och databas-queries
- ✅ Kan hantera högre trafikvolymer

### Begränsningar
- ⚠️ Kräver aktiv cache-invalidering
- ⚠️ Risk för inaktuell data om misslyckad invalidering

### Implementation Status (Brottsplatskartan)
1. ✅ Grundläggande konfiguration
2. ✅ `BrottsplatskartanCacheProfile` (cache-livstider)
3. ✅ `CustomRequestHasher` (filtrerar query-parametrar)
4. ✅ Redis som cache driver
5. ✅ Deployment till produktion

### Performance-resultat
- Cache HIT: ~1-5ms
- Cache MISS + Query cache HIT: ~50ms
- Historiska sidor: 7 dagars cache
- Query-parametrar skapar inte separata entries

---

## Support

**Spatie Laravel Response Cache:**
- GitHub: https://github.com/spatie/laravel-responsecache
- Dokumentation: https://spatie.be/docs/laravel-responsecache

**Brottsplatskartan:**
- AGENTS.md: Produktionsserver-kommandon
- API.md: API-dokumentation
