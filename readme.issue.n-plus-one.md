# N+1 Query Problem - API Endpoints

## Problem

API-endpoints hade N+1 query-problem där `locations`-relationen inte eager-loadades. Detta ledde till att varje CrimeEvent i en loop triggade en separat SQL-query för att hämta dess locations.

### Identifierat via slow request log

Analys av PHP-FPM slow request log visade att följande endpoints var långsamma:

1. **ApiController::events()** - 20 förekomster
2. **ApiEventsMapController::index()** - 15 förekomster
3. **CrimeEvent::getLocationString()** - 15 förekomster (anropas från ovanstre)

## Orsak

### Exempel: ApiController

**Före:**
```php
$events = CrimeEvent::orderBy("created_at", "desc")
    ->where(...)
    ->paginate(20); // Hämtar 20 events

// Senare i koden, för varje event:
foreach ($events as $event) {
    $event->getLocationString(); // Triggrar lazy load av $this->locations
}
```

**Resultat:**
- 1 query för att hämta 20 events
- 1 query för pagination count
- 20 queries för att hämta locations (1 per event)
- **Totalt: 22 queries**

### Exempel: ApiEventsMapController

**Före:**
```php
CrimeEvent::orderBy("created_at", "desc")
    ->limit(500)
    ->get(); // Hämtar 500 events

// Senare i loopen:
foreach ($events as $event) {
    $event->getLocationString(); // Lazy load
    $event->getPermalink();      // Lazy load (använder också locations)
}
```

**Resultat:**
- 1 query för att hämta 500 events
- 500 queries för locations
- **Totalt: 501 queries** (varje 5:e minut när cache uppdateras)

## Lösning

Lägg till `->with('locations')` för att eager-load relationen i en enda query.

### Efter:

```php
$events = CrimeEvent::orderBy("created_at", "desc")
    ->with('locations') // Eager load locations
    ->paginate(20);
```

**Resultat:**
- 1 query för att hämta 20 events
- 1 query för pagination count
- 1 query för att hämta alla locations för dessa events
- **Totalt: 3 queries** ✅

## Ändringar

### 1. ApiController.php (rad 160)

```php
// Eager load locations för att undvika N+1 query problem
$events = $events->with('locations')->paginate($limit);
```

**Impact:**
- Endpoint: `/api/1.0/events`
- Används av: Frontend karta, API-konsumenter
- Förbättring: Från 22+ queries till 3 queries per request

### 2. ApiEventsMapController.php (rad 20)

```php
return CrimeEvent::orderBy("created_at", "desc")
    ->where('created_at', '>=', now()->subDays($daysBack))
    ->with('locations') // Eager load för att undvika N+1 query problem
    ->limit(500)
    ->get();
```

**Impact:**
- Endpoint: `/api/events-map` (antas baserat på controller-namnet)
- Cache: 5 minuter
- Förbättring: Från 501 queries till 2 queries (varje 5:e minut)

## Påverkan

### CPU-belastning

Före ändringarna visade `htop` på produktionsservern:
- mariadb-processer använde ofta 40-80% CPU
- Många php-fpm pool worker processer i slow request state

### Förväntad förbättring

**Databas:**
- 85-95% färre queries för dessa endpoints
- Mindre CPU-användning på MariaDB
- Snabbare response times

**PHP-FPM:**
- Kortare exekveringstid per request
- Färre slow requests
- Fler tillgängliga workers

## Verifiering

### Före (från slow-queries.log analys)

```
ApiController.php:159 - 20 slow requests
ApiEventsMapController.php:40 - 15 slow requests
CrimeEvent.php:484 (getLocationString) - 15 slow requests
```

### Test efter deployment

För att verifiera fixarna, kör följande i Laravel Tinker på produktionsservern:

```php
// Aktivera query logging
DB::enableQueryLog();

// Testa ApiController-liknande query
$events = \App\CrimeEvent::orderBy('created_at', 'desc')
    ->with('locations')
    ->limit(20)
    ->get();

// Loopar genom och anropar getLocationString()
foreach ($events as $event) {
    $event->getLocationString();
}

// Visa antal queries
count(DB::getQueryLog()); // Ska vara ~2 istället för ~21
```

## Relaterade problem

Detta är separata problem från de datum-index fixes som gjordes i `readme.issue.slow-queries.md`. Båda bidrog till hög databas-belastning:

1. **Datum-index problem** → Full table scans på date queries
2. **N+1 problem** → Hundratals onödiga queries per request

Tillsammans orsakade dessa mycket hög CPU-användning på MariaDB.

## Nästa steg efter deployment

1. ⏳ Deploya ändringarna till produktion
2. ⏳ Rensa cache: `dokku run brottsplatskartan php artisan cache:clear`
3. ⏳ Monitorera MariaDB CPU-användning i `htop`
4. ⏳ Kolla PHP-FPM slow request log efter några timmar
5. ⏳ Verifiera färre queries med query logging

## Referenser

- [Laravel Eager Loading Documentation](https://laravel.com/docs/eloquent-relationships#eager-loading)
- [N+1 Query Problem Explained](https://stackoverflow.com/questions/97197/what-is-the-n1-selects-problem-in-orm-object-relational-mapping)
- Original slow request log: `slow-queries.log`

## Sammanfattning

**Problemanalys från slow-queries.log:**
- Totalt 1046 rader i loggen
- 46 förekomster av API controllers
- Vanligaste: ApiController (20x), CrimeEvent::getLocationString (15x)

**Fixes:**
- 2 endpoints fixade
- Potentiell reduktion: 90%+ färre queries för dessa endpoints
- Förväntat: Dramatisk minskning av MariaDB CPU-användning

**Status:** ✅ Fixat och klart för deployment
