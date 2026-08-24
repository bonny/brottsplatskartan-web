# AGENTS.md

Vägledning för Claude Code (claude.ai/code) vid arbete med koden i detta repo.

## Projektöversikt

Brottsplatskartan är en svensk webbapplikation för visualisering av polishändelser
från Polisens officiella webbplats. Aggregerar och presenterar brottsdata via
interaktiv karta med fokus på geografisk representation.

**Viktigt:** All användargenererat innehåll, felmeddelanden, kodkommentarer och
dokumentation skrivs **på svenska**.

## API-dokumentation

Se **[docs/API.md](docs/API.md)** för komplett API. Endpoints definieras i
`routes/api.php`.

## Analytics (GA4 + Search Console)

Brottsplatskartans data nås via två MCP-servrar registrerade per dator
(`--scope user`, inte i repo). Använd för datadrivna SEO-, cache- och
UX-beslut.

- **`analytics-mcp`** — GA4-data (sessions, landingPages, deviceCategory)
- **`mcp-gsc`** — Search Console-data (queries, indexering, sitemap, position)
- **GA4 property-ID:** `305258979`
- **GSC site_url:** `https://brottsplatskartan.se/`
- **Setup på ny dator:** [todos/done/08-ga-mcp.md](todos/done/08-ga-mcp.md) (GA4) + [todos/done/26-gsc-mcp.md](todos/done/26-gsc-mcp.md) (GSC)
- **Exempel-queries + insikter:** **[docs/analytics.md](docs/analytics.md)**

Verifiera anslutning: `claude mcp list | grep -E "analytics-mcp|mcp-gsc"`.

## Teknisk stack

Laravel-app i Docker. Se `composer.json`, `package.json` och `compose.yaml`
för versioner och tjänster.

Två saker som inte syns i manifesten:

- **Kartvisualisering (frontend)** använder externa OSM-tiles.
- **Kartbilder (backend)** genereras av vår egen tileserver-gl-container
  på `kartbilder.brottsplatskartan.se`.

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

### Köra engångs-PHP mot appen — två tinker-fällor

`php artisan tinker --execute` och `tinker < fil.php` går genom psysh, som
har två begränsningar som lätt äter en halvtimme:

1. **psysh parsar rad för rad** och faller på flerradiga `try`/`catch`
   (`Cannot use try without catch or finally`).
2. **Radvis `echo` inuti `foreach` kommer tillbaka mangleat** — psysh ekar
   källraderna blandat med utdata.

Hooken `check-prod-tinker.sh` blockerar skriv-PHP, men **bara mot prod**:
båda registreringarna i `.claude/settings.local.json` är `if`-låsta till
`ssh deploy@brottsplatskartan.se '...tinker --execute=*`. Lokal tinker med
`file_put_contents` går igenom — verifierat 2026-08-24 för både `--execute`
och stdin-formen.

För allt som är mer än en enkel läsfråga: skriv ett fristående skript som
bootar Laravel själv och kör det med `php`, inte `tinker`.

```php
<?php // /tmp/skript.php
require '/var/www/html/vendor/autoload.php';
$app = require_once '/var/www/html/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// ... vanlig PHP här, inga psysh-begränsningar ...
echo json_encode($resultat, JSON_UNESCAPED_UNICODE);
```

```bash
docker compose cp /tmp/skript.php app:/tmp/skript.php
docker compose exec -T app php /tmp/skript.php
```

Behöver du ändå tinker och vill ha strukturerad output tillbaka: echo:a en
enda sträng med markörer (`###J###...###E###`) och plocka ut den med `grep -o`.

### Composer

```bash
# Ny dependency
docker compose exec -u root app composer require <paket>

# Uppdatera
docker compose exec -u root app composer update <paketnamn>
```

## Systemarkitektur

Modeller ligger i `app/Models/`, controllers i `app/Http/Controllers/`.

### Datakällor

- **Polisens JSON-API** — `https://polisen.se/api/events`. Se
  [docs/polisen-api.md](docs/polisen-api.md) för datafält, rate-limits
  och hur vi använder det.
- **TextTV** — kompletterande nyhetstext
- **OpenStreetMap** — geografisk tile-data

### Frontend

Byggs med Laravel Mix — se `webpack.mix.js` för källor och utdata.

### Prestanda

Response cache via Spatie (Redis), plus query-caching för geografiska
uppslag. Se
[docs/spatie-response-cache-implementation.md](docs/spatie-response-cache-implementation.md).

### AI-kostnad

Fem agenter i `app/Ai/Agents/` anropar Anthropic via `laravel/ai`. Innan du
utreder prompt-caching eller batchning: läs
**[docs/ai-kostnad.md](docs/ai-kostnad.md)** — båda är redan utredda och
avfärdade på mätning, och filen förklarar varför så vi slipper göra om det.

## Terminologi

### Brottskategorier

| Svenska        | Engelska         | Beskrivning                             |
| -------------- | ---------------- | --------------------------------------- |
| Inbrott        | Burglary         | Olagligt intrång i byggnad eller fordon |
| Stöld          | Theft            | Olovligt tillgrepp av egendom           |
| Rån            | Robbery          | Stöld med våld eller hot                |
| Misshandel     | Assault          | Fysiskt våld mot person                 |
| Trafikolycka   | Traffic accident | Olycka med personskada                  |
| Narkotikabrott | Drug offense     | Brott relaterat till narkotika          |

### Geografisk nomenklatur

| Svenska | Engelska       | Nivå           |
| ------- | -------------- | -------------- |
| Län     | County         | Regional       |
| Kommun  | Municipality   | Kommunal       |
| Stad    | City           | Urban          |
| Plats   | Location/Place | Specifik punkt |

## Produktionsmiljö (Hetzner)

Servern körs på Hetzner Cloud, koden ligger i `/opt/brottsplatskartan/`, och
`git push main` deployar automatiskt via GitHub Actions.

Fullständig driftmanual — deploy, rollback, SSH-kommandon, DB-backup,
env-ändringar, debugbar, provisionering — finns i skillen
**`prod-ops`** ([.claude/skills/prod-ops/SKILL.md](.claude/skills/prod-ops/SKILL.md)).

Två saker som måste sitta i ryggmärgen innan du rör prod:

- **Ta alltid backup före skrivande ändringar:** `./deploy/backup-prod-db.sh`
  innan backfill, `migrate` eller `UPDATE` mot prod.
- **`docker compose restart app` läser INTE om `.env`.** Vid env-ändring krävs
  `docker compose up -d app` (recreate), och `php artisan config:cache` måste
  köras EFTER det — annars bakas det gamla värdet in. Läs `prod-ops` för hela
  sekvensen.

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

- Kortfattade funktionskommentarer när _varför_ inte är uppenbart
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

Issues och GitHub Actions hanteras med `gh`.

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
