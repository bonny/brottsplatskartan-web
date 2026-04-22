# AGENTS.md

Vägledning för Claude Code (claude.ai/code) vid arbete med koden i detta repo.

## Projektöversikt

Brottsplatskartan är en svensk webbapplikation för visualisering av polishändelser
från Polisens officiella webbplats. Aggregerar och presenterar brottsdata via
interaktiv karta med fokus på geografisk representation.

**Viktigt:** All användargenererat innehåll, felmeddelanden, kodkommentarer och
dokumentation skrivs **på svenska**.

## API-dokumentation

Se **[docs/API.md](docs/API.md)** för komplett API.

Snabbreferens:

- `/api/events` — Hämta händelser med filtrering
- `/api/eventsMap` — Händelser för kartvisning (cachad)
- `/api/event/{id}` — Enskild händelse
- `/api/eventsNearby` — Händelser nära koordinat
- `/api/areas` — Lista över län

## Teknisk stack

- **Ramverk:** Laravel 12 (PHP 8.2+)
- **App-image:** `serversideup/php:8.4-fpm-nginx` + extra PHP-extensions (bcmath, exif, gd)
- **Databas:** MariaDB 11
- **Cache & sessions:** Redis 8 med `maxmemory-policy allkeys-lru`
- **Response cache:** Spatie Laravel Response Cache (via Redis)
- **Reverse proxy:** Caddy (auto-SSL via Let's Encrypt)
- **Kartvisualisering (frontend):** Leaflet.js + extern OSM tiles
- **Kartbilder (backend):** egen tileserver-gl-container (`kartbilder.brottsplatskartan.se`)

## Lokal utvecklingsmiljö

Se **[deploy/local-dev.md](deploy/local-dev.md)** för full guide.

Kortversion:

```bash
docker compose up -d
open http://brottsplatskartan.test:8350
```

Portar lokalt: app 8350, tileserver 8351, MariaDB 33012, Redis 63012.

### Rensa cache vid ändringar i Blade/config

```bash
docker compose exec app php artisan cache:clear
docker compose exec app php artisan view:clear
docker compose exec app php artisan config:clear
```

### Vanliga artisan-kommandon

```bash
docker compose exec app php artisan crimeevents:fetch
docker compose exec app php artisan app:importera-texttv
docker compose exec app php artisan migrate
docker compose exec app php artisan tinker
```

### Composer

```bash
# Ny dependency
docker compose exec -u root app composer require <paket>

# Uppdatera
docker compose exec -u root app composer update <paketnamn>
```

## Systemarkitektur

### Datamodeller

- `CrimeEvent` — huvudmodell för brottshändelser
- `VMAAlert` — nationella varnings- och informationsmeddelanden
- `Place` — geografisk platsdata och koordinater
- `Locations` — mappning av orter/kommuner/län
- `Dictionary` — kategorisering av brottstyper

### Controllers

- `StartController` — startsida och primära vyer
- `PlatsController` — platsspecifik data
- `CityController` — stadssidor
- `LanController` — länsövergripande data
- `ApiController` — REST API
- `VMAAlertsController` — varningsmeddelanden

### Datakällor

- **Polisens RSS-flöden** — https://polisen.se/Aktuellt/RSS/Lokala-RSS-floden/
- **TextTV** — kompletterande nyhetstext
- **OpenStreetMap** — geografisk tile-data

### Frontend

```
resources/js/app.js        → public/js/app.js        (JS-bundle)
resources/sass/app.scss    → public/css/app.css      (stilar)
public/js/events-map.js                              (karta)
```

### Databasstruktur för brottshändelser

- Geografiska koordinater (lat/lng)
- Administrativa nivåer (kommun, län, region)
- Parsad platsinformation
- Brottskategorisering (enligt Polisens taxonomi)
- Temporal data (tidsstämplar)
- Engagement-statistik

### Prestanda

- **Response Cache** — Spatie, 2–30 min TTL
- **Redis** — query cache + sessions
- **Query-caching** för geografiska uppslag

## Terminologi

### Brottskategorier

| Svenska | Engelska | Beskrivning |
|---|---|---|
| Inbrott | Burglary | Olagligt intrång i byggnad eller fordon |
| Stöld | Theft | Olovligt tillgrepp av egendom |
| Rån | Robbery | Stöld med våld eller hot |
| Misshandel | Assault | Fysiskt våld mot person |
| Trafikolycka | Traffic accident | Olycka med personskada |
| Narkotikabrott | Drug offense | Brott relaterat till narkotika |

### Geografisk nomenklatur

| Svenska | Engelska | Nivå |
|---|---|---|
| Län | County | Regional |
| Kommun | Municipality | Kommunal |
| Stad | City | Urban |
| Plats | Location/Place | Specifik punkt |

## Produktionsmiljö (Hetzner)

- **Plattform:** Hetzner Cloud (EU)
- **Server:** CX33 (x86 AMD, 4 vCPU / 8 GB / 80 GB), Debian 13 (Trixie), Helsinki
- **Deploy-stack:** Docker Compose (`compose.yaml` + egen `Dockerfile.app`)
- **Reverse proxy:** Caddy med auto-Let's Encrypt
- **Kod-plats:** `/opt/brottsplatskartan/`
- **CI/CD:** GitHub Actions (`.github/workflows/deploy-hetzner.yml`) → SSH → `deploy/deploy.sh`
- **Trigger:** `git push main` deployar automatiskt

### Deploy-flöde

1. `git push origin main`
2. GitHub Actions triggar → SSH till Hetzner
3. `deploy.sh` kör: `git pull` → villkorlig `composer install` (om lock ändrats) → villkorlig `artisan migrate` (om nya migrationer) → `docker compose restart app`
4. AUTORUN i containern kör `storage:link` + cache-warmup

### Manuell deploy

```bash
ssh deploy@brottsplatskartan.se /opt/brottsplatskartan/deploy/deploy.sh
```

### Rollback

```bash
ssh deploy@brottsplatskartan.se 'cd /opt/brottsplatskartan && git reset --hard HEAD~1 && ./deploy/deploy.sh'
```

### Produktionsserver – kommandon

```bash
ssh deploy@brottsplatskartan.se
cd /opt/brottsplatskartan

# Artisan
docker compose exec app php artisan migrate
docker compose exec app php artisan cache:clear
docker compose exec app php artisan responsecache:clear
docker compose exec app php artisan crimeevents:check-publicity --apply --since=365

# Logs
docker compose logs -f app
docker compose logs --tail 100 app | grep ERROR

# Redis CLI
docker compose exec redis redis-cli -a "$REDIS_PASSWORD"
# I redis-cli:
#   DBSIZE
#   KEYS laravelresponsecache-*
#   MONITOR

# MariaDB CLI
docker compose exec mariadb mariadb -u root -p"$DB_ROOT_PASSWORD" brottsplatskartan

# Container-hantering
docker compose ps
docker compose restart app
docker compose down && docker compose up -d
```

### Provisionering av ny server

Se **[deploy/provision.md](deploy/provision.md)**.

### Produktions-env

`.env` ligger i `/opt/brottsplatskartan/.env` på servern (chmod 600, ägd av `deploy`).
Mall: `deploy/.env.example`. Alla secrets hanteras där — aldrig i git.

Kritiska variabler:

- `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://brottsplatskartan.se`
- DB: `DB_HOST=mariadb`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `DB_ROOT_PASSWORD`
- Redis: `REDIS_HOST=redis`, `REDIS_PASSWORD`
- Cache: `CACHE_DRIVER=redis`, `RESPONSE_CACHE_DRIVER=redis`, `SESSION_DRIVER=redis`
- API-nycklar: `CLAUDE_API_KEY`, `GOOGLE_API_KEY`, m.fl.

### Laravel Debugbar i produktion

Debugbar aktiveras via cookie (`app/Http/Middleware/DebugBarMaybeEnable.php`):

```javascript
// Aktivera
document.cookie = "show-debugbar=1; path=/; max-age=86400";
// Inaktivera
document.cookie = "show-debugbar=; path=/; max-age=0";
```

Bara den som satt cookien ser debugbar. Kräver ingen ändring av `APP_DEBUG`.

## Scheduler

Allt schemaläggs i `app/Console/Kernel.php`. Körs av en dedikerad
`scheduler`-container som kör `php artisan schedule:work` — ingen
host-cron behövs.

Se `app/Console/Kernel.php` för aktiva jobb.

Kontrollera att schedulern lever:
```bash
docker compose ps scheduler
docker compose logs -f scheduler
```

## Utvecklingsriktlinjer

- Kortfattade funktionskommentarer när *varför* inte är uppenbart
- Prioritera tydlig, välstrukturerad kod
- Konsekvent svensk terminologi i kommentarer och dokumentation

### Statisk analys efter kodändringar

Efter PHP-ändringar ska `composer analyse` (Larastan/PHPStan level 5) köras
lokalt innan commit. Baseline på kända fel ligger i `phpstan-baseline.neon`
— nya fel ska antingen fixas eller (om motiverat) läggas till i baseline.

```bash
docker compose exec app composer analyse
```

Ingen CI kör detta — disciplin lokalt gäller.

## GitHub-projektet

**URL:** https://github.com/bonny/brottsplatskartan-web/

### Issues via `gh`

```bash
gh issue list              # öppna issues
gh issue list --state all  # alla
gh issue view <nr>
gh issue create
```

### Övervaka GitHub Actions

```bash
gh run list
gh run view <run-id>
gh run view <run-id> --log
gh run watch
```

## Händelsefiltrering (ContentFilterService)

Filtrerar bort icke-relevanta händelser (presstalesperson-info, pressnummer):

- Körs automatiskt vid `crimeevents:fetch`
- Global Scope döljer icke-publika händelser (inte raderas)
- Händelser markeras `is_public = false`

Manuell körning:

```bash
# Dry-run
docker compose exec app php artisan crimeevents:check-publicity --since=365

# Applicera
docker compose exec app php artisan crimeevents:check-publicity --apply --since=365
```

## Övrigt

- Lagra aldrig API-nycklar eller auth tokens i readme-filer.
