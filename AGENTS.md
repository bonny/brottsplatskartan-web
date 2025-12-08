# CLAUDE.md

Denna fil tillhandahåller vägledning för Claude Code (claude.ai/code) vid arbete med kod i denna repository.

## API-dokumentation

För komplett API-dokumentation, se **[API.md](docs/API.md)**.

**Snabbreferens:**

-   `/api/events` - Hämta händelser med filtrering
-   `/api/eventsMap` - Händelser för kartvisning (cachad, optimerad)
-   `/api/event/{id}` - Enskild händelse
-   `/api/eventsNearby` - Händelser nära koordinat
-   `/api/areas` - Lista över län

Alla endpoints returnerar JSON. Se API.md för fullständiga exempel och parametrar.

## Projektöversikt

Brottsplatskartan är en svensk webbapplikation för visualisering av polishändelser från Polisens officiella webbplats. Applikationen aggregerar och presenterar brottsdata genom interaktiv kartvisualisering med fokus på geografisk representation och realtidsuppdateringar.

**Viktigt**: Denna applikation är utvecklad för svenskspråkiga användare. All användargenererat innehåll, felmeddelanden, kodkommentarer och dokumentation ska författas på svenska.

## Kommunikationsriktlinjer

-   Kommunicera uteslutande på svenska under utvecklingsarbetet
-   Använd tydligt och professionellt språk i all koddokumentation
-   Följ svenska terminologi för brottstyper och geografiska begrepp

## Teknisk arkitektur

### Utvecklingsmiljö

-   **Backend-ramverk**: Laravel 12 (PHP 8.2+)
-   **Databashantering**: MariaDB
-   **Cache**: Redis
-   **Response Cache**: Spatie Laravel Response Cache
-   **Kartvisualisering**: Leaflet.js

## Utvecklingsarbetsflöde

### Lokal utvecklingsmiljö

```bash
# Starta lokal utvecklingsserver
./artisan serve
# Applikationen är tillgänglig på http://localhost:8000
```

### Datahantering och import

```bash
# Importera aktuella polishändelser från RSS-flöden
./artisan crimeevents:fetch

# Importera nyhetsartiklar från TextTV
./artisan texttv:fetch
```

### Pakethantering och beroenden

```bash
# Uppdatera PHP-beroenden via Composer
# Vi har inte Redis lokalt, så vi ignorerar detta.
composer update <paketnamn> --ignore-platform-req=ext-redis
```

## Systemarkitektur

### Datamodeller (Models)

-   **`CrimeEvent`** - Huvudmodell för hantering av brottshändelser och polisrapporter
-   **`VMAAlert`** - Datamodell för nationella varnings- och informationssystem
-   **`Place`** - Geografisk platsdata och koordinathantering
-   **`Locations`** - Mappning av geografiska områden (städer, kommuner, län)
-   **`Dictionary`** - Kategorisering och klassificering av brottstyper

### Kontrollenheter (Controllers)

-   **`StartController`** - Hantering av startsida och primära användarvyer
-   **`PlatsController`** - Platsspecifik brottsdata och geografisk filtrering
-   **`CityController`** - Stadsspecifika sidor och kommundata
-   **`LanController`** - Länsövergripande data och statistik
-   **`ApiController`** - RESTful API-endpoints för extern dataåtkomst
-   **`VMAAlertsController`** - Hantering av nationella varningar och alerts

### Kärnfunktionalitet

-   **Realtidsaggregering** av brottsdata från Polisens officiella RSS-flöden
-   **Interaktiv kartvisualisering** med responsiv Leaflet.js-implementation
-   **Avancerad geografisk filtrering** baserat på städer, län och specifika platser
-   **Nyhetsintegration** med TextTV för kompletterande innehåll
-   **Mobilresponsiv design** med Progressive Web App (PWA) funktionalitet

### Datakällor och integration

-   **Polisens RSS-flöden**: [Lokala RSS-flöden](https://polisen.se/Aktuellt/RSS/Lokala-RSS-floden/)
-   **TextTV-nyhetsintegration**: Automatiserad hämtning av relevanta nyhetsartiklar
-   **OpenStreetMap**: Geografisk kartdata och geokodning

### Frontend-arkitektur

```
Frontend Asset Pipeline:
resources/js/app.js        → public/js/app.js        (Huvud JavaScript-bundle)
resources/sass/app.scss    → public/css/app.css      (Huvudstilark)
public/js/events-map.js                             (Kartspecifik funktionalitet)
```

### Databasdesign

**Brottshändelser** struktureras med följande dataattribut:

-   **Geografiska koordinater** (latitud/longitud för exakt positionering)
-   **Administrativa områdesnivåer** (kommun, län, region)
-   **Parsad platsinformation** (strukturerad adress- och platsdata)
-   **Brottskategorisering** (standardiserad klassificering enligt Polisens taxonomy)
-   **Temporal data** (tidsstämplar för händelse och rapportering)
-   **Engagement-mätning** (visningsstatistik för populärt innehåll)

### Prestanda och cachning

-   **Response Cache** - Spatie Laravel Response Cache för hela HTTP-responses (2-30 min TTL)
-   **Redis** - Query cache och sessionshantering
-   **Databassökningscachning** för optimering av geografiska uppslagningar
-   **Asset-versionering** via Laravel Mix manifest för efficient browser-cachning

## Terminologi och konventioner

### Brottskategorier

| **Svenska termer** | **Engelska motsvarigheter** | **Beskrivning**                            |
| ------------------ | --------------------------- | ------------------------------------------ |
| Inbrott            | Burglary                    | Olagligt intrång i byggnad eller fordon    |
| Stöld              | Theft                       | Olovligt tillgrepp av egendom              |
| Rån                | Robbery                     | Stöld med våld eller hot om våld           |
| Misshandel         | Assault                     | Fysiskt våld mot person                    |
| Trafikolycka       | Traffic accident            | Olycka i trafiken med personskada          |
| Narkotikabrott     | Drug offense                | Brott relaterat till narkotiska substanser |

### Geografisk nomenklatur

| **Svenska termer** | **Engelska motsvarigheter** | **Administrativ nivå**    |
| ------------------ | --------------------------- | ------------------------- |
| Län                | County                      | Regional nivå             |
| Kommun             | Municipality                | Kommunal nivå             |
| Stad               | City                        | Urban enhet               |
| Plats              | Location/Place              | Specifik geografisk punkt |

## Produktionsmiljö och deployment

### Automatiserad deployment via Dokku

**Produktionsinfrastruktur**:

-   **Plattform**: Dokku-baserad deployment till `brottsplatskartan.se`
-   **Operativsystem**: Ubuntu 22.04.5 LTS
-   **Hosting**: Digital Ocean (8 GB Memory / 160 GB Disk / FRA1)
-   **CI/CD Pipeline**: GitHub Actions för automatiserad deployment
-   **Trigger**: Automatisk deployment vid push till `main`-branch

### Deployment-arbetsflöde

**Automatisk deployment:**

1. Push till `main`-branch
2. GitHub Actions triggar automatisk deployment
3. Dokku deployar senaste koden till `brottsplatskartan.se`

**Post-deployment åtgärder:**
När deployment är klart kan manuella kommandon behöva köras på servern:

```bash
# Logga in på produktionsservern
ssh <server>

# Kör kommandon via Dokku
dokku run brottsplatskartan php artisan migrate
dokku run brottsplatskartan php artisan config:cache
dokku run brottsplatskartan php artisan view:cache
```

**Vanliga post-deployment kommandon:**

-   **Databasmigrationer**: `dokku run brottsplatskartan php artisan migrate`
-   **Cache-clearing**: `dokku run brottsplatskartan php artisan cache:clear`
-   **Config-cache**: `dokku run brottsplatskartan php artisan config:cache`
-   **Rensa icke-publika händelser**: `dokku run brottsplatskartan php artisan crimeevents:check-publicity --apply --since=365`

### Produktionskonfiguration

**Kritiska miljövariabler:**

-   `APP_ENV=production` - Produktionsmiljö-flagga
-   `APP_DEBUG=false` - Avaktivera debug-läge för säkerhet
-   `APP_URL` - Kanonisk produktions-URL
-   **Databaskonfiguration**: MySQL-anslutningsparametrar
-   **Redis-inställningar**: Cache-server konfiguration
-   **Mail-konfiguration**: SMTP-inställningar för systemnotifikationer

### Produktionsserver - Användbara kommandon

**Redis-hantering:**

```bash
# Anslut till Redis CLI
dokku redis:connect brottsplatskartan

# Visa Redis-info och statistik
dokku redis:info brottsplatskartan

# Redis-logs
dokku redis:logs brottsplatskartan
dokku redis:logs brottsplatskartan -t  # följ i realtid

# Exportera Redis-data (backup)
dokku redis:export brottsplatskartan
```

**Redis CLI-kommandon (när ansluten med `redis:connect`):**

```bash
# Monitorera alla Redis-operationer i realtid (bästa sättet att verifiera caching!)
MONITOR

# Lista cache-nycklar
KEYS laravel_cache:api:*
KEYS laravel_cache:api:events:*

# Visa cache-värde
GET "laravel_cache:api:events:area=Stockholms län:location=:type=:page=1:limit=10"

# Visa TTL (time to live) för en nyckel
TTL "laravel_cache:api:events:area=Stockholms län:location=:type=:page=1:limit=10"

# Visa statistik
INFO stats
INFO keyspace

# Räkna antal nycklar
DBSIZE

# Avsluta
exit
```

**Verifiera cache-implementering på produktion:**

```bash
# Workflow för att verifiera att API-caching fungerar:

# 1. Rensa cache
dokku run brottsplatskartan php artisan cache:clear

# 2. Terminal 1: Starta Redis MONITOR
dokku redis:connect brottsplatskartan
> MONITOR

# 3. Terminal 2: Gör API-anrop
curl "https://brottsplatskartan.se/api/events?area=Stockholms+län&limit=5"

# 4. I MONITOR ser du SETEX (cache skapas med TTL)
# 5. Gör samma anrop igen (inom 2 min)
curl "https://brottsplatskartan.se/api/events?area=Stockholms+län&limit=5"

# 6. I MONITOR ser du nu GET (cache-träff!)
```

**Snabb cache-verifiering:**

```bash
# Ett-rads kommandon för att kolla cache
dokku redis:connect brottsplatskartan <<< "KEYS laravel_cache:api:events:*"
dokku redis:connect brottsplatskartan <<< "INFO stats"
dokku redis:connect brottsplatskartan <<< "DBSIZE"
```

**Laravel Debugbar på produktion:**

Debugbar aktiveras via cookie (implementerat i `app/Http/Middleware/DebugBarMaybeEnable.php`):

```javascript
// Aktivera debugbar (i webbläsarens Console):
document.cookie = "show-debugbar=1; path=/; max-age=86400";
// Ladda om sidan

// Stäng av debugbar:
document.cookie = "show-debugbar=; path=/; max-age=0";
```

✅ Säkert - bara du som sätter cookien ser debugbar
✅ Ingen kod-ändring behövs i produktion
✅ Fungerar direkt utan att ändra APP_DEBUG

**Applikationsloggar:**

```bash
# Visa senaste loggarna
dokku logs brottsplatskartan

# Följ loggar i realtid
dokku logs brottsplatskartan -t

# Filtrera loggar
dokku logs brottsplatskartan --tail 100 | grep "Cache"
dokku logs brottsplatskartan --tail 100 | grep "ERROR"
```

**MariaDB-hantering:**

```bash
# Anslut till MariaDB
dokku mariadb:connect brottsplatskartan
```

**MariaDB-kommandon (när ansluten med `mariadb:connect`):**

```sql
-- Visa aktiva queries (för att identifiera upprepade COUNT queries)
SHOW PROCESSLIST;

-- Analysera query-prestanda
EXPLAIN SELECT date_created_at as dateYMD, count(*) as dateCount
FROM crime_events
WHERE date_created_at < CURDATE();

-- Avsluta
exit;
```

**Applikationskommandon:**

```bash
# Kör artisan-kommandon
dokku run brottsplatskartan php artisan cache:clear
dokku run brottsplatskartan php artisan responsecache:clear
dokku run brottsplatskartan php artisan config:cache
dokku run brottsplatskartan php artisan migrate
dokku run brottsplatskartan php artisan tinker

# Kör anpassade kommandon
dokku run brottsplatskartan php artisan crimeevents:fetch
dokku run brottsplatskartan php artisan crimeevents:check-publicity --apply --since=365

# Öppna bash-session
dokku run brottsplatskartan bash
```

**Container-hantering:**

```bash
# Visa app-info
dokku apps:info brottsplatskartan

# Visa processer
dokku ps:report brottsplatskartan

# Starta om appen
dokku ps:restart brottsplatskartan

# Skala upp/ner
dokku ps:scale brottsplatskartan web=2

# Visa resursutnyttjande
dokku ps:top brottsplatskartan
```

## Utvecklingsriktlinjer

### Kodkvalitet och dokumentation

-   **Funktionskommentarer**: Implementera kortfattade, beskrivande kommentarer för alla funktioner
-   **Kodens läsbarhet**: Prioritera tydlig och välstrukturerad kod
-   **Svenska terminologi**: Konsekvent användning av svenska termer i kommentarer och dokumentation

### Projekthanteringsverktyg

-   **GitHub CLI**: Använd `gh` kommandot för effektiv hantering av GitHub-issues och pull requests
-   **Git Branch-hantering**: När du jobbar med GitHub och dess CLI-verktyg så se till att skapa en ny branch när vi börjar arbeta med ett nytt issue

### GitHub-projektet

**Projekt-URL**: https://github.com/bonny/brottsplatskartan-web/

**Hantera issues med GitHub CLI:**

```bash
# Lista öppna issues
gh issue list

# Lista alla issues (även stängda)
gh issue list --state all

# Visa detaljer för ett specifikt issue
gh issue view <issue-nummer>

# Skapa nytt issue
gh issue create
```

**Övervaka GitHub Actions:**

```bash
# Lista senaste workflow-körningar
gh run list

# Lista tillgängliga workflows
gh workflow list

# Visa detaljer för en specifik körning
gh run view <run-id>

# Visa logg för en körning
gh run view <run-id> --log

# Följ en pågående körning i realtid
gh run watch
```

## Händelsefiltrering och innehållshantering

### ContentFilterService

Systemet använder `ContentFilterService` för att automatiskt identifiera och dölja icke-relevanta händelser:

**Filtertyper:**

-   **Presstalesperson-meddelanden**: Händelser med information om presstalespersoners tjänstgöringstider
-   **Pressnummer-information**: Meddelanden om polisens pressnummer och tillgänglighet

**Automatisk filtrering:**

-   Körs automatiskt vid import av nya händelser (`crimeevents:fetch`)
-   Använder Global Scope för att endast visa publika händelser på sajten
-   Händelser markeras som `is_public = false` istället för att raderas

**Manuell hantering:**

```bash
# Kontrollera vilka händelser som skulle döljas (dry-run)
php artisan crimeevents:check-publicity --since=365

# Applicera filtrering för befintliga händelser
php artisan crimeevents:check-publicity --apply --since=365

# På produktionsservern
dokku run brottsplatskartan php artisan crimeevents:check-publicity --apply --since=365
```

### Övrigt/blandat

-   Lagra aldrig API-nycklar eller auth tokens i readme-filer.
