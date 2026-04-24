# 05 — Uppgradering Laravel 12 → 13 + Spatie Response Cache 7.7 → 8.x (SWR)

**Status:** KLAR (2026-04-21). Deployad till Hetzner test.
**Senast uppdaterad:** 2026-04-21

## Resultat

Genomfört samma dag som planen togs fram. Alla fyra delsteg (A–D)
plus SWR-aktivering och larastan-byte landade i flytt-till-hetzner-
branchen:

- `424d267` Uppgradera till Laravel 13 + Spatie Response Cache 8
- `672a1a5` Aktivera SWR globalt via FlexibleCacheResponse + graceInSeconds
- `5a3e290` Byt abandoned nunomaduro/larastan mot larastan/larastan

Verifierat i browser + Redis på hetzner.brottsplatskartan.se. Inga
PHPStan-regressioner (74 errors vs 75 före uppgraderingen).

Kvarvarande uppföljning — flyttad till separata uppgifter:

- Dokumentera `php artisan pail` i AGENTS.md
- Utvärdera Laravel 13 AI SDK som ersättning för claude-php/claude-php-sdk
- Ev. bumpa predis/predis 1 → 3, phpunit 11 → 12

---

## Ursprunglig plan (behålls för historik)

## Sammanfattning

Syftet är två saker:

1. **Spatie Response Cache 8.x** ger stale-while-revalidate (SWR), vilket
   tar bort köerna av samtidiga cache-miss-förfrågningar på populära
   sidor (start, län, stad) och minskar p95-latensen.
2. **Laravel 13** håller ramverket aktuellt (säkerhet, bug fixes,
   framtida paketstöd). Kräver PHP 8.3+ (troligen 8.4) — vi kör redan
   `serversideup/php:8.4-fpm-nginx`.

Blockerare från tidigare utredning (`todo.md` rad 251+):

| Paket                            | L13-stöd | Åtgärd                                     |
| -------------------------------- | -------- | ------------------------------------------ |
| `rap2hpoutre/laravel-log-viewer` | Nej      | Ersätt med `laravel/pail` + ta bort route  |
| `willvincent/feeds`              | Nej      | Ersätt med SimplePie direkt (tunn wrapper) |
| `claude-php/claude-php-sdk`      | Oklar    | Avvakta — inget akut byte krävs            |

### Prioordning

1. Byt ut `willvincent/feeds` → SimplePie direkt (liten, isolerad ändring).
2. Byt ut `rap2hpoutre/laravel-log-viewer` → `laravel/pail` (trivial).
3. Uppgradera Spatie Response Cache 7.7 → 8.x, implementera SWR-konfig.
4. Uppgradera Laravel 12 → 13 (kör `laravel/upgrade`-vägen).
5. Utvärdera Claude-SDK-byte som separat, senare todo.

## Dependency-matris

Baserat på `composer.json` och `composer.lock`:

| Paket                                  | Nuvarande       | L13-stöd            | Åtgärd                               |
| -------------------------------------- | --------------- | ------------------- | ------------------------------------ |
| `laravel/framework`                    | ^12.0           | —                   | Uppgradera till ^13.0                |
| `laravel/sanctum`                      | ^4.0            | Ja (kontrollera ^5) | Bumpa om ny major finns              |
| `laravel/tinker`                       | ^2.7            | Ja                  | Inget                                |
| `laravel/ui`                           | ^4.0            | Kontrollera ^5      | Ev. bumpa                            |
| `laravel/helpers`                      | ^1.1            | Ja                  | Inget                                |
| `spatie/laravel-responsecache`         | ^7.7            | 8.x                 | Uppgradera, aktivera SWR             |
| `spatie/laravel-feed`                  | ^4.0            | Kontrollera ^5      | Ev. bumpa                            |
| `spatie/laravel-ignition`              | ^2.0            | Kontrollera ^3      | Ev. bumpa                            |
| `barryvdh/laravel-debugbar`            | ^3.2            | Kontrollera         | Ev. bumpa                            |
| `rap2hpoutre/laravel-log-viewer`       | ^2.1 (v2.5.0)   | **Nej**             | **Ta bort**, använd `laravel/pail`   |
| `willvincent/feeds`                    | ^2.3.0 (v2.7.0) | **Nej**             | **Ta bort**, använd SimplePie direkt |
| `claude-php/claude-php-sdk`            | ^0.5.1          | OK (ramverksfritt)  | Behåll, utvärdera senare             |
| `creitive/breadcrumbs`                 | ^3.0            | Kontrollera         | Test                                 |
| `duzun/hquery`                         | ^3.0.3          | Ramverksfritt       | OK                                   |
| `ezyang/htmlpurifier`                  | ^4.17           | Ramverksfritt       | OK                                   |
| `guzzlehttp/guzzle`                    | ^7.4.2          | Ja                  | OK                                   |
| `league/geotools`                      | ^1.1.0          | Ramverksfritt       | OK                                   |
| `predis/predis`                        | ^1.1            | Ja (men ^2 finns)   | Ev. bumpa                            |
| `stevegrunwell/time-constants`         | ^1.1            | Ramverksfritt       | OK                                   |
| `symfony/browser-kit`                  | ^6.3            | —                   | Ev. bumpa till ^7 matchar övrigt     |
| `symfony/http-client`                  | ^7.0            | OK                  | OK                                   |
| `unisharp/laravel-settings`            | ^2.0            | **Kontrollera**     | Risk — paket uppdateras sällan       |
| **dev:** `barryvdh/laravel-ide-helper` | ^3.0            | Kontrollera         | Ev. bumpa                            |
| **dev:** `nunomaduro/larastan`         | ^3.0            | Kontrollera ^4      | Bumpa                                |
| **dev:** `phpunit/phpunit`             | ^11.0           | Kontrollera ^12     | Ev. bumpa                            |

Verifiera L13-stöd paket för paket via `composer why-not laravel/framework:^13.0` innan uppgradering körs.

## Plan per blockerare

### A. `willvincent/feeds` → SimplePie direkt

Paketet är en extremt tunn Laravel-wrapper runt SimplePie (1 fasad,
1 service provider). SimplePie följer redan som underberoende i
`composer.lock`.

**Användning i kodbasen:** Endast **ett** anrop.

- `app/Http/Controllers/FeedController.php:382` — `\Feeds::make($url, 0, true, $options)`.

Konfiguration som måste flyttas över (cachelagring som SimplePie-fasaden
sköter idag):

- Cache-katalog / cache-duration — kontrollera `config/feeds.php` om
  sådan finns (default ~/storage/framework/cache).

**Refaktorering:**

```php
// app/Http/Controllers/FeedController.php
use SimplePie\SimplePie;

$feed = new SimplePie();
$feed->set_feed_url($this->RssURL);
$feed->force_feed(true);
$feed->set_curl_options([CURLOPT_SSL_VERIFYPEER => false]);
$feed->enable_cache(true);
$feed->set_cache_location(storage_path('framework/cache/simplepie'));
$feed->set_cache_duration(3600);
$feed->init();
$feed->handle_content_type();
$feed_items = $feed->get_items();
```

**Steg:**

1. Skapa `storage/framework/cache/simplepie/` (lägg till i `.gitignore` vid behov).
2. Refaktorera `FeedController::updateFeedsFromPolisen()`.
3. Ta bort `willvincent\Feeds\FeedsServiceProvider` + `Feeds`-alias ur `config/app.php` (rad 186 och 242).
4. `composer remove willvincent/feeds`.
5. Testa `php artisan crimeevents:fetch` lokalt mot live-feed.

**Arbetsinsats:** 1–2 timmar.

### B. `rap2hpoutre/laravel-log-viewer` → `laravel/pail`

Används endast av `/logs`-route i `routes/web.php:735-738` (auth-skyddad).

Pail är ett CLI-verktyg som streamar loggar i realtid — inte en webbvy.
Ersättning:

- **Option 1 (rekommenderad):** Ta bort `/logs`-routen helt. Använd
  `docker compose logs -f app` eller `docker compose exec app php artisan pail` vid felsökning.
- **Option 2:** Om webbvy behövs, utvärdera `opcodesio/log-viewer`
  (aktivt underhållet, L13-klart).

**Steg:**

1. Ta bort route i `routes/web.php`.
2. Ta bort provider `Rap2hpoutre\LaravelLogViewer\LaravelLogViewerServiceProvider` ur `config/app.php:191`.
3. `composer require laravel/pail --dev`.
4. `composer remove rap2hpoutre/laravel-log-viewer`.
5. Dokumentera `artisan pail` i AGENTS.md under "Produktionsserver – kommandon".

**Arbetsinsats:** 30 min.

### C. Spatie Response Cache 7.7 → 8.x + SWR

8.x lägger till SWR: cache serverar stale-svar omedelbart och triggar
bakgrundsrevalidering. Detta löser "thundering herd" vid cache-miss.

**Påverkan:**

- `config/responsecache.php` — ny `stale_while_revalidate` + ev.
  `stale_if_error`-nycklar tillkommer.
- `app/CacheProfiles/BrottsplatskartanCacheProfile.php` — ev. nytt
  interface/metod för att ange SWR-TTL per route.
- `app/ResponseCache/CustomRequestHasher.php` — kontrollera att interface
  inte ändrats.
- Middleware `\Spatie\ResponseCache\Middlewares\CacheResponse` oförändrat namn.

**SWR-konfig skiss:**

```php
// config/responsecache.php — nytillägg
'cache_lifetime_in_seconds' => (int) env('RESPONSE_CACHE_LIFETIME', 60 * 30), // fresh: 30 min

'stale_while_revalidate_in_seconds' => (int) env('RESPONSE_CACHE_SWR', 60 * 60 * 6), // stale: upp till 6 h efter fresh gått ut

'stale_if_error_in_seconds' => (int) env('RESPONSE_CACHE_SIE', 60 * 60 * 24), // serva stale vid 5xx upp till 24 h
```

Per-route TTL kan sättas via cacheprofil:

```php
public function cacheRequestUntil(Request $request): ?CarbonInterval
{
    // Startsida: kort fresh, lång stale
    if ($request->is('/')) {
        return CarbonInterval::minutes(5);
    }
    return CarbonInterval::minutes(30);
}
```

**Bakgrundsrevalidering:**

SWR i Spatie 8.x använder en queued job för att varma om cachen. Kräver
queue worker — vi kör redan scheduler-container, lägg till en `queue`-container
eller låt schedulern hantera queuen via `queue:work`. Kontrollera upstream-docs
innan deploy.

**Steg:**

1. `composer require spatie/laravel-responsecache:^8.0` (efter att L13 är ute, annars parallellt).
2. Läs upgrade guide, uppdatera `config/responsecache.php`.
3. Uppdatera `BrottsplatskartanCacheProfile` om interface ändrats.
4. Testa lokalt: `ab -n 100 -c 20 http://brottsplatskartan.test:8350/` och kolla att svar är `X-ResponseCache: HIT` + stale-header.
5. Lägg till queue worker i `compose.yaml` om krävs.

**Arbetsinsats:** 3–5 timmar inkl. queue-setup och verifiering.

### D. Laravel 12 → 13 core

1. Kör `composer require laravel/framework:^13.0 --with-all-dependencies --dry-run` för att se vilka paket som blockerar.
2. Följ officiell upgrade guide (brytande ändringar på Eloquent, middleware-registrering, validation, etc.).
3. Kontrollera `bootstrap/app.php` (L11+-struktur) för nya config-callbacks.
4. Kör `php artisan test` + smoke-tester: startsida, län-sida, API-endpoints, feed-fetch, AI-summary.
5. Bump PHP-version i `composer.json` (`"php": "^8.3"` eller `^8.4`).

**Arbetsinsats:** 4–8 timmar inkl. felsökning.

### E. `claude-php/claude-php-sdk`

Paketet är ramverksfritt (bara Guzzle+PSR). Ingen akut blockerare för L13.

Laravel 13:s officiella AI-SDK (om den släpps tid nog) kan ersätta detta
**senare**. Omfattning: 2 anropsställen.

- `app/Services/AISummaryService.php:20` — `new ClaudePhp(apiKey: ...)` + `$this->claude->messages()->create([...])`.
- `app/Console/Commands/CreateAISummary.php:56` — samma mönster (troligen duplikat som kan refaktoreras).

**Rekommendation:** Flyttas till **separat todo** efter L13-uppgradering. Gör INTE parallellt — minska antalet rörliga delar.

**Arbetsinsats (framtida):** 2–3 timmar.

## Risker

| Risk                                                     | Sannolikhet | Mitigering                                                                                    |
| -------------------------------------------------------- | ----------- | --------------------------------------------------------------------------------------------- |
| Feed-parsing trasig efter SimplePie-byte                 | Medel       | Testa mot live-RSS i staging; behåll backup av gammal kod i git                               |
| AI-summaries slutar fungera                              | Låg         | SDK rörs inte i denna uppgradering                                                            |
| Stale cache under Response Cache-switch                  | Medel       | `artisan responsecache:clear` direkt efter deploy; verifiera att hasher-klassen är kompatibel |
| `unisharp/laravel-settings` saknar L13-stöd              | Hög         | Utvärdera alternativ (`spatie/laravel-settings`) innan L13-bump                               |
| Middleware-registrering flyttad till `bootstrap/app.php` | Hög         | Följ upgrade guide steg för steg                                                              |
| Debugbar / Ignition inkompatibla                         | Låg         | Senaste minor-versioner släpps snabbt efter L13                                               |
| SWR kräver queue worker som inte finns                   | Medel       | Utöka `compose.yaml` med queue-container innan aktivering                                     |

## Fördelar

- SWR eliminerar latensspikar vid cache-utgång på hårt trafikerade sidor.
- Laravel 13 håller säkerhet + community-support aktuell.
- Färre nischberoenden (log-viewer, feeds-wrapper) → mindre underhållsskuld.
- `laravel/pail` är officiellt och underhållet.
- Möjliggör framtida byte till Laravel AI SDK.

## Öppna frågor

1. Vilken PHP-version kräver L13 exakt — 8.3 eller 8.4? (Lås minst `^8.3`.)
2. Stöder `unisharp/laravel-settings` L13? Om inte — migrera till `spatie/laravel-settings`?
3. Behövs `/logs`-webbvyn alls, eller räcker `docker compose logs` + `pail`?
4. Ska queue-container läggas till för SWR-revalidering, eller hänga på schedulern?
5. Kan `CreateAISummary`-kommandot refaktoreras att använda `AISummaryService` (DRY) i samma veva?
6. Finns det ytterligare Spatie-paket (laravel-feed, laravel-ignition) som kräver major-bump samtidigt?

## Tidsuppskattning

| Delsteg                         | Tid                             |
| ------------------------------- | ------------------------------- |
| A. Byt feeds → SimplePie        | 1–2 h                           |
| B. Byt log-viewer → Pail        | 0,5 h                           |
| C. Response Cache 8.x + SWR     | 3–5 h                           |
| D. Laravel 13 core-uppgradering | 4–8 h                           |
| Testning + staging-deploy       | 2–4 h                           |
| **Totalt**                      | **11–20 h** (≈ 2–3 arbetsdagar) |

## Nästa steg

1. Kör `composer outdated --direct` lokalt för att få färska versionsdata.
2. Verifiera L13-stöd för `unisharp/laravel-settings`, `spatie/laravel-feed`, `spatie/laravel-ignition`, `creitive/breadcrumbs`, `laravel/ui`.
3. Skapa branch `upgrade/laravel-13`.
4. Börja med steg A (feeds), merge separat — isolerad, låg risk.
5. Fortsätt med B (pail), merge separat.
6. Efter det: C + D i samma PR (de är sammanflätade pga beroendegraf).
7. Deploya till staging om sådan finns; annars noggrann lokal smoke-test innan prod.
