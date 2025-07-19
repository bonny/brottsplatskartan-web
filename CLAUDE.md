# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Projektöversikt

Brottsplatskartan är en svensk brottskartewebbplats som visar polishändelser från Polisens webbplats i ett användarvänligt format med kartvisualisering. Sajten aggregerar och presenterar brottsdata med fokus på geografisk representation och senaste händelser.

**Viktigt**: Detta är en svenskspråkig webbplats. All användargenererat innehåll, felmeddelanden, kommentarer och dokumentation ska vara på svenska.

## Språkliga riktlinjer

- Använd svenska när vi pratar.

## Teknikstack

- **Backend**: Laravel 12 (PHP 8.2+)
- **Frontend**: Vue.js 2.6, Bootstrap 5
- **Build Tool**: Laravel Mix (Webpack wrapper)
- **Databas**: MySQL med Redis-cachning
- **Kartor**: Leaflet.js
- **Styling**: Sass/SCSS

## Utvecklingskommandon

### Lokal utveckling
```bash
# Starta utvecklingsserver
./artisan serve
# Besök http://localhost:8000

# Bevaka ändringar i assets under utveckling
npm run watch

# Bygg assets för utveckling
npm run dev

# Bygg assets för produktion
npm run production
```

### Dataimportkommandon
```bash
# Hämta polishändelser
./artisan crimeevents:fetch

# Hämta TextTV-nyhetsartiklar
./artisan texttv:fetch
```

### Pakethantering
```bash
# Uppdatera composer-paket (notera Redis-kravhantering)
composer update <paketnamn> --ignore-platform-req=ext-redis

# Installera npm-beroenden
npm install
```

### Testning
```bash
# Kör PHPUnit-tester
./vendor/bin/phpunit

# Alternativt testkommando
php artisan test
```

## Nyckelarkitekturkomponenter

### Modeller
- `CrimeEvent` - Huvudmodell för brottshändelser
- `VMAAlert` - Data för varningssystem
- `Place` - Geografisk platsdata
- `Locations` - Stad/område-mappningar
- `Dictionary` - Kategorisering av brottstyper

### Controllers
- `StartController` - Startsida och huvudvyer
- `PlatsController` - Platsspecifik brottsdata
- `CityController` - Stadsspecifika sidor
- `LanController` - Länsdata
- `ApiController` - API-endpoints för dataåtkomst
- `VMAAlertsController` - Nödvarningar

### Nyckelfunktioner
- Realtidsaggregering av brottsdata från Polisens RSS-flöden
- Interaktiv kartvisualisering med Leaflet
- Geografisk filtrering efter städer, län och specifika platser
- Integration med TextTV för nyhetsinnehåll
- Mobilresponsiv design med PWA-funktioner

### Datakällor
- Polisens RSS-flöden (https://polisen.se/Aktuellt/RSS/Lokala-RSS-floden/)
- TextTV-nyhetsintegration
- OpenStreetMap för geografisk data

### Frontend Assets
- Huvud JS-bundle: `resources/js/app.js` → `public/js/app.js`
- Styles: `resources/sass/app.scss` → `public/css/app.css`
- Vue-komponenter i `resources/views/components/`
- Kartspecifik JS: `public/js/events-map.js`

### Databasstruktur
Brottshändelser lagras med:
- Geografiska koordinater (lat/lng)
- Administrativa områdesnivåer
- Parsade platsnamn
- Brottskategorisering
- Tidsstämpeldata
- Visningsspårning för populärt innehåll

### Cachningsstrategi
- Redis används för sessionslagring och cachning
- Databassökningscachning för geografiska uppslagningar
- Asset-versionering via Laravel Mix manifest

## Svenska termer och konventioner

### Brottstyper (crime types)
- Inbrott - Burglary
- Stöld - Theft
- Rån - Robbery
- Misshandel - Assault
- Trafikolycka - Traffic accident
- Narkotikabrott - Drug offense

### Geografiska termer
- Län - County
- Kommun - Municipality
- Stad - City
- Plats - Location/Place

## Deployment och Produktion

### Produktionsmiljö

**Dokku (Automatisk deployment)**
- GitHub Actions deployment till `brottsplatskartan.se`
- Triggas automatiskt vid push till `main`-branch
- SSL-terminering och gzip-komprimering
- Nginx-konfiguration med felhantering

### Deployment-process

Deployment sker automatiskt när kod pushas till `main`-branch:
1. GitHub Actions triggas vid push
2. Kod deployar till Dokku-server via SSH
3. Assets byggs automatiskt i produktionsmiljön
4. Nginx startas om med ny konfiguration

### Lokal utvecklingsmiljö med Docker

```bash
# Starta med Laravel Sail
./vendor/bin/sail up -d

# Alternativt via alias (om konfigurerat)
sail up -d

# Tjänster som startas:
# - Laravel app (port 80)
# - MySQL 8.0 (port 3306) 
# - Redis (port 6379)
```

### Miljövariabler för produktion

Viktiga miljövariabler som behöver konfigureras:
- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL` - produktions-URL
- Databaskonfiguration för MySQL
- Redis-konfiguration för cachning
- Mail-inställningar för notifikationer

### Build-process

1. **Frontend assets**: Laravel Mix kompilerar JS/CSS med `npm run production`
2. **Composer**: PHP-beroenden installeras
3. **Database migrations**: Kör automatiskt vid deployment
4. **Asset optimization**: Minifiering och versioning

## Utvecklingsanteckningar

- Toppen, lägg till korta kommentarer till funktionerna så de är lätta att förstå.