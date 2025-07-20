# CLAUDE.md

Denna fil tillhandahåller vägledning för Claude Code (claude.ai/code) vid arbete med kod i denna repository.

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
-   **Databashantering**: MySQL 8.0 med Redis-cachning
-   **Kartvisualisering**: Leaflet.js

## Utvecklingsarbetsflöde

### Lokal utvecklingsmiljö

```bash
# Starta lokal utvecklingsserver
./artisan serve
# Applikationen är tillgänglig på http://localhost:8000

# Aktivera automatisk kompilering av frontend-assets
npm run watch

# Kompilera assets för utveckling
npm run dev

# Kompilera och optimera assets för produktion
npm run production
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
# Observera: Redis-tillägget kan kräva särskild hantering
composer update <paketnamn> --ignore-platform-req=ext-redis

# Installera eller uppdatera Node.js-beroenden
npm install
```

### Kvalitetssäkring och testning

```bash
# Kör fullständig testsvit med PHPUnit
./vendor/bin/phpunit

# Alternativt testkommando via Laravel Artisan
php artisan test
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

-   **Redis-implementation** för sessionshantering och högpresterande cachning
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

## Utvecklingsriktlinjer

### Kodkvalitet och dokumentation

-   **Funktionskommentarer**: Implementera kortfattade, beskrivande kommentarer för alla funktioner
-   **Kodens läsbarhet**: Prioritera tydlig och välstrukturerad kod
-   **Svenska terminologi**: Konsekvent användning av svenska termer i kommentarer och dokumentation

### Projekthanteringsverktyg

-   **GitHub CLI**: Använd `gh` kommandot för effektiv hantering av GitHub-issues och pull requests

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
