# Claude TODO - Brottsplatskartan

Statusdokument för pågående förbättringsarbete.

---

## Översikt av uppdrag

1. **Minska antalet URLer/vyer** - Response cache tar för stor plats
2. **SEO-review** - Sajten tjänar pengar på annonser, måste ranka högt på Google
3. **Konsolidera blade-templates** - Framförallt event-kort på startsidan

---

## 1. Minska URLer och cache-påverkan

### Datavolym i databasen
| Data | Antal |
|------|-------|
| Totalt händelser | 296,878 |
| Unika dagar | 2,849 (~10 år) |
| Unika orter | 330 |
| Unika län | 21 (+ 2 dubletter) |
| Unika brottstyper | 119 |
| Locations-poster | 687,858 |

### Cache-konfiguration (Spatie Response Cache)
| Route | TTL |
|-------|-----|
| `/` (startsida) | 2 min |
| `/vma`, `/api/vma` | 2 min |
| `/handelser/{date}` (>7 dagar gamla) | 7 dagar |
| `/api/events` | 10 min |
| Övriga routes | 30 min |

### 🔴 PROBLEMOMRÅDEN - Potentiella cache-entries

| Route | Beräkning | Antal entries | Allvarlighetsgrad |
|-------|-----------|---------------|-------------------|
| `/plats/{plats}/handelser/{date}` | 330 orter × 2849 dagar | **~940,000** | 🔴 KRITISK |
| `/lan/{lan}/handelser/{date}` | 21 län × 2849 dagar | **~60,000** | 🟠 HÖG |
| `/handelser/{date}` | 2849 dagar | **~2,849** | 🟢 OK (lång TTL) |
| `/plats/{plats}` | 330 orter | **~330** | 🟢 OK |
| `/typ/{typ}` | 119 typer × pagination | **~500+** | 🟡 MEDEL |
| `/lan/{lan}` | 21 län | **~21** | 🟢 OK |
| `/{city}` | fallback, okänt antal | **?** | 🟡 UNDERSÖK |

**Total potentiell cache-storlek: >1,000,000 entries**

### 🎯 Rekommendationer

#### 1. Ta bort `/plats/{plats}/handelser/{date}` (KRITISK)
- **Skapar ~940,000 cache-entries!**
- Användare kan nå samma data via `/handelser/{date}` + filtrera
- Alternativ: Lägg till `noindex` + exkludera från cache

#### 2. Ta bort `/lan/{lan}/handelser/{date}` (HÖG)
- **Skapar ~60,000 cache-entries**
- Samma lösning: `/handelser/{date}` är tillräckligt

#### 3. Granska `/{city}` catch-all routen
- Verkar överlappa med `/plats/{plats}`
- Kan skapa duplicerade cache-entries

#### 4. Lägg till cache-exkludering för datum-routes
Om routes ska behållas, exkludera dem från response cache:
```php
// I BrottsplatskartanCacheProfile
public function shouldCacheRequest(Request $request): bool
{
    // Exkludera datum-kombinationer med plats/län
    if ($request->is('plats/*/handelser/*') || $request->is('lan/*/handelser/*')) {
        return false;
    }
    return parent::shouldCacheRequest($request);
}
```

### Status
- [x] Analysera datavolym i databasen
- [x] Identifiera vilka routes som skapar flest cache-entries
- [x] Beräkna potentiell cache-storlek
- [ ] **AVVAKTAR** - Beslut om vilka routes som ska tas bort/ändras

---

## 2. SEO-review

### Status
- [ ] Granska meta-taggar (title, description)
- [ ] Kontrollera canonical URLs
- [ ] Schema.org markup (JSON-LD)
- [ ] Intern länkning
- [ ] Page speed / Core Web Vitals
- [ ] Mobile-first indexering
- [ ] robots.txt och sitemap

---

## 3. Blade-templates - Event-kort

### Nuläge: 8 olika event-kort-templates

| Template | Används av | Rader | Beskrivning |
|----------|------------|-------|-------------|
| `crimeevent.blade.php` | Detaljsidor, design | ~175 | Original, stort kort med karta |
| `crimeevent_v2.blade.php` | ? | ~115 | Nyare två-kolumn version |
| `crimeevent-small.blade.php` | Listor | ~60 | Miniatyr med thumbnail |
| `crimeevent-city.blade.php` | Stadssidor | ~20 | Likt small |
| `crimeevent-mapless.blade.php` | ? | ~30 | Utan karta |
| `crimeevent-hero.blade.php` | Startsida (topp 3) | ~23 | Stor hero |
| `crimeevent-hero-second.blade.php` | Startsida (row 2) | ~20 | Medium hero |
| `crimeevent-helicopter.blade.php` | Helikoptersidan | ~45 | Specialversion |

### Startsidans struktur (`events-heroes.blade.php`)
1. **3 st stora heroes** → `crimeevent-hero`
2. **6 st medium (2x3 rutnät)** → `crimeevent-hero-second`
3. **8 st små i lista** → `crimeevent-small`

### Designsidan (`/design`)
✅ **KLAR** - Visar nu alla 9 kortvarianter:
1. `parts.crimeevent` (single=true)
2. `parts.crimeevent` (overview=true)
3. `parts.crimeevent_v2` (overview=true)
4. `parts.crimeevent-hero`
5. `parts.crimeevent-hero-second`
6. `parts.crimeevent-small` (detailed=true)
7. `parts.crimeevent-small` (detailed=false)
8. `parts.crimeevent-city`
9. `parts.crimeevent-mapless`
10. `parts.crimeevent-helicopter`

### Förslag för konsolidering
1. ~~**Uppdatera designsidan** att visa ALLA kort-varianter~~ ✅
2. **Identifiera duplicering** mellan `crimeevent-city` och `crimeevent-small`
3. **Bestäm strategi** för `crimeevent` vs `crimeevent_v2`

### Status
- [x] Inventera alla event-templates
- [x] Uppdatera designsidan med alla varianter
- [ ] Besluta vilka kort att behålla

### Ändringar gjorda
- Uppdaterade `/design`-sidan (`resources/views/design.blade.php`) för att visa alla korttyper
- Varje kort visas i en sektion med:
  - Template-namn (t.ex. `parts.crimeevent-hero`)
  - Beskrivning av var det används
  - Live-rendering av kortet
- La till dokumentation i `AGENTS.md` om att rensa cache vid blade-ändringar

---

## Nästa steg

**Status: AVVAKTAR**

Alla uppgifter pausade tills vidare. Färdiga analyser:

1. ✅ **Designsidan** - Visar nu alla kortvarianter på `/design`
2. ✅ **Cache-analys** - Identifierade problemroutes (se sektion 1)
3. ⏸️ **SEO-review** - Ej påbörjad
4. ⏸️ **Blade-konsolidering** - Väntar på beslut om vilka kort som ska behållas

---

## 4. Uppdatera mbtiles till nyare version

Nuvarande fil: `2017-07-03_europe_sweden.mbtiles` (~1.21 GB, från 2017).
Ligger i Hetzner Object Storage, laddas ner via `deploy/download-tiles.sh`.

**Behöver undersökas:**
- Hur genererades filen från början? (OSM-extract + tippecanoe? Planetiler?
  Annan pipeline?)
- Finns script/dokumentation från första gången någonstans? Kolla
  `../brottsplatskartan-tileserver` och nvALT.
- Vilket stylesheet/schema använder tileserver-gl för att rendera? Ny
  mbtiles kan behöva matchande style.
- Hur ofta uppdateras OSM-data tillräckligt mycket för att motivera
  en ny extract? (Vägar i Sverige ändras långsamt — kanske vart 2–3:e år.)

**Pipeline-skiss (att verifiera):**
1. Ladda ner senaste Sverige-extract från Geofabrik
   (`https://download.geofabrik.de/europe/sweden-latest.osm.pbf`)
2. Konvertera till mbtiles med `planetiler` eller `tilemaker`
3. Ladda upp till Hetzner Object Storage-bucket `brottsplatskartan/tiles/`
4. Uppdatera `TILES_FILE` + `TILES_URL` i `deploy/download-tiles.sh`
5. Deploy — containern plockar upp nya filen

### Status
- [ ] Hitta/dokumentera ursprunglig pipeline
- [ ] Testa ny extract med planetiler eller tilemaker
- [ ] Verifiera att tileserver-gl:s default-style fungerar med ny mbtiles
- [ ] Committa dokumentation till `deploy/update-tiles.md` eller liknande

---

## 5. Uppgradera Laravel 12 → 13 + spatie/laravel-responsecache 7.7 → 8.x

**Att göra EFTER Hetzner-cutover** — inte innan, för att undvika
rörliga delar mitt i flytten. Båda uppgraderingarna görs bäst samtidigt.

### Laravel 13 (släppt 17 mars 2026)

- Minimum PHP 8.3 (vi kör 8.4 ✅)
- "Relatively minor upgrade in terms of effort"
- 18 mån bug fix + 2 år security fix
- Intressanta nya features:
  - `Cache::touch()` för att förlänga TTL utan att re-store
  - First-party AI SDK (kunde ersätta manuella Claude/OpenAI-anrop)
  - Utökade PHP attributes (`#[Middleware]`, `#[Tries]`, `#[Backoff]` etc.)
  - Queue routing (`Queue::route(...)`)
  - Semantic/vector search (PostgreSQL + pgvector) — irrelevant för oss

### spatie/laravel-responsecache 8.x (släppt feb 2025)

Version 8.0 lade till **flexible caching**: stale responses serveras
direkt medan cachen regenereras i bakgrunden. SWR-mönstret men på
response-cache-nivå (hela HTML-svaret).

Löser konkret problem: `/stockholm` är seg ibland när outer response
cache expirerar (30 min TTL) och användaren måste vänta på hela
regenereringen (geo-spatial query + stats-aggregering + Blade).

### Krav (vi uppfyller)

- PHP 8.4+ ✅
- Spatie ResponseCache 8.2+ stöder Laravel 13 ✅

### Migration

1. `composer require laravel/framework:^13.0 spatie/laravel-responsecache:^8.2`
2. Följ Laravel 13 upgrade guide (få breaking changes)
3. Uppdatera `BrottsplatskartanCacheProfile` för SWR-API (fresh/stale-fönster)
4. Lokalt test: verifiera att stale-svar serveras inom grace-perioden
5. Deploy

### Förväntad vinst

- `/stockholm`, `/lan/*`, `/plats/*`, startsidan: nästan aldrig kall cache
- Dagens "30 min TTL utan SWR" → 25 min fresh + 5-15 min stale-window
- Eliminerar ~3s väntetider som nu drabbar enstaka användare var 30:e minut
- Bonus: `Cache::touch()` kan ersätta en del manuell cache-logik

### Paketkompatibilitet (koll mot `composer why-not laravel/framework ^13.0`)

| Paket | L13-stöd | Åtgärd |
|---|---|---|
| barryvdh/laravel-debugbar | ✅ i 4.2.7+ | Uppdatera |
| laravel/tinker | ✅ i 3.0.2+ | Uppdatera |
| spatie/laravel-responsecache | ✅ i 8.2+ | Uppdatera (redan planerat) |
| **rap2hpoutre/laravel-log-viewer** | ❌ | Ersätt med `laravel/pail` eller ta bort |
| **willvincent/feeds** | ❌ | Ersätt med SimplePie direkt, eller vänta |

### Status
- [ ] Vänta till efter cutover (DO-server avstängd)
- [ ] Besluta: vänta på willvincent/feeds eller ersätt med SimplePie?
- [ ] Ta bort eller ersätt rap2hpoutre/laravel-log-viewer
- [ ] Läs Laravel 13 upgrade guide + Spatie 8.x release notes
- [ ] Implementera + testa lokalt
- [ ] Deploy och verifiera

### Uppföljning: ersätt rap2hpoutre/laravel-log-viewer med Laravel Pail

`laravel/pail` är det officiella alternativet i Laravel-ekosystemet.
Används via artisan direkt: `php artisan pail` → realtids-log-stream
i terminalen med filter/search. Fungerar mot alla log-drivers
(single, daily, stderr m.fl.).

**Migration:**
1. `composer require laravel/pail --dev`
2. Ta bort rap2hpoutre/laravel-log-viewer från composer.json
3. Ta bort routen i `routes/web.php:736-737`
4. Dokumentera nya flödet i AGENTS.md:
   ```bash
   docker compose -f compose.yaml exec app php artisan pail
   ```

Pail har inget webb-UI (tmpt — bara terminal). För tillfällena när
man vill se logs från browser: använd `tail` direkt eller Telescope.
Men troligen aldrig behövt i praktiken.

### Uppföljning: ersätt egen Claude-SDK med Laravel 13:s AI-primitiver

Nuvarande setup:
- `claude-php/claude-php-sdk` (composer-dependency)
- `app/Services/AISummaryService.php` — wrapper
- `app/Console/Commands/CreateAISummary.php` — scheduled command
- `app/CrimeEvent.php` refererar Claude-API

Laravel 13 (mars 2026) lade till **first-party AI SDK** — en enhetlig
API för text generation, tool-calling, embeddings osv. över flera
providers (Anthropic, OpenAI m.fl.).

**Potentiella vinster:**
- En abstraktion över providers → lättare att byta framtida modell
- Officiellt paket — underhållsansvar hos Laravel-teamet
- Inbyggt i Laravel → mindre glue-kod
- Testbarhet via fakes/mocks (om SDK:n följer Laravel-mönstret)

**Oklart att utreda:**
- Stöder SDK:n Claude (Anthropic) eller bara OpenAI-kompatibla modeller?
- Har den samma kapabilitet (streaming, tool-use, vision m.fl.)?
- Är den mogen nog (v1 i mars 2026) eller värd att vänta en release eller två?

### Uppföljning: hantera willvincent/feeds

Paketet underhålls långsamt (~årlig uppdatering). Kritiskt för
RSS-import från polisen.se. Tre vägar:

**A. Ersätt med SimplePie direkt**
willvincent/feeds är en tunn Laravel-wrapper över SimplePie. Skriv
om `FeedController::parseFeed()` för att anropa SimplePie direkt
(~20-50 rader). Bort med mellanlagret, behåll samma funktionalitet.

**B. Forka paketet**
Skapa `bonny/feeds` fork på GitHub, uppdatera composer-constraints,
använd via `repositories`-section i composer.json. Låg insats men
skapar teknisk skuld.

**C. Vänta på official uppdatering**
Öppna issue/PR på upstream. Risk: kan ta 6-12 mån, blockerar hela
Laravel 13-uppgraderingen under tiden.

**Rekommenderad:** A. Paketet är så tunt att en direkt SimplePie-lösning
är snabbast långsiktigt.

---

## 6. Flytta "Brottsstatistik"-ruta från startsidan → egen /statistik-sida

Nuvarande: stapeldiagram på startsidan "Antal rapporterade händelser från
Polisen per dag i Sverige, 14 dagar tillbaka" (genereras av
`Helper::getStatsChartHtml('home')`).

**Problem:**
- Ger inte stort mervärde för en första-besökare på startsidan
- Tar plats från mer relevant innehåll (senaste händelser, karta)
- Statistik i sig är intressant men förtjänar egen yta för djupdykning

**Förslag:**
- Ta bort statistik-rutan från startsidan
- Skapa `/statistik` med:
  - Nuvarande 14-dagars-graf för hela Sverige (högst upp)
  - Motsvarande per län (små grafer i grid)
  - Ev. top 10 brottstyper senaste veckan
  - Karta med värmeindex över landet
  - Lista med rekord (högsta/lägsta-dagar)
- Länka till /statistik från footer eller nav

**Tekniskt:**
- `getStatsChartHtml('home')` finns redan i `Helper.php` — återanvänd
- Route: `Route::get('/statistik', [StatisticsController::class, 'index'])`
- Ny controller `StatisticsController`
- Ny vy `resources/views/statistics/index.blade.php`
- SEO: meta-titel "Brottsstatistik för Sverige — Brottsplatskartan"

### Status
- [ ] Designa statistik-sidans layout
- [ ] Skapa StatisticsController + route + vy
- [ ] Flytta befintlig graf från startsidan
- [ ] Lägg till länk i footer/nav
- [ ] SEO (meta, sitemap, canonical)

---

## 7. Fixa PHPStan-errors och lägga till i CI

**Status:** PHPStan (Larastan 3.x) är installerat och konfigurerat på
level 5, men inte i CI och errors är inte åtgärdade. Composer-script
finns nu: `composer analyse`.

### Att göra

- [ ] Kör `composer analyse` och triage errors
  - [ ] Fixa de enklaste direkt
  - [ ] Svårare → baseline med `composer analyse:baseline`
- [ ] Lägg till GitHub Actions-workflow som kör phpstan på PR/push
  ```yaml
  # .github/workflows/phpstan.yml
  name: PHPStan
  on: [push, pull_request]
  jobs:
    phpstan:
      runs-on: ubuntu-latest
      steps:
        - uses: actions/checkout@v4
        - uses: shivammathur/setup-php@v2
          with: { php-version: 8.4 }
        - run: composer install --no-interaction
        - run: composer analyse
  ```
- [ ] Överväg att öka level 5 → 6 eller 7 över tid
- [ ] Lägg till Laravel Pint (`composer require laravel/pint --dev`)
  som code formatter + composer-script `"format": "pint"`

### Varför senare

Kräver dedikerad tid att gå igenom varningarna utan att distraheras
av migrationsarbetet. Bättre efter cutover när vi inte också jagar
migration-buggar.

---

## 8. Konfigurera Google Analytics MCP-server i Claude Code

Ger Claude Code direkt access till GA4-data under utvecklings-sessions.
Kraftfullt för datadrivna beslut om:

- **Cache pre-warm:** vilka sidor är faktiskt mest besökta? (topp 20 istället för alla 330)
- **Todo 1 (cache-entries):** identifiera routes som ingen besöker → ta bort
- **Todo 2 (SEO):** prioritera optimeringar efter trafik
- **Todo 6 (/statistik):** se om nuvarande stats-ruta faktiskt används
- Generellt: "vilka sidor är mina top 20?", "bounce rate per typ?", m.fl.

### Setup

Repo: https://github.com/googleanalytics/google-analytics-mcp

1. `pipx install google-analytics-mcp` (eller `pip install`)
2. Skapa service account i Google Cloud Console med GA4 Data API-access
3. Ladda ner JSON-credentials → spara säkert (inte i git)
4. Ge service account "Viewer"-access i GA4-propertyn
5. Lägg till i Claude Code config (`~/.claude/claude_code_config.json`):
   ```json
   {
     "mcpServers": {
       "google-analytics": {
         "command": "uvx",
         "args": ["google-analytics-mcp"],
         "env": {
           "GOOGLE_APPLICATION_CREDENTIALS": "/path/to/sa-key.json",
           "GA_PROPERTY_ID": "<ga4-property-id>"
         }
       }
     }
   }
   ```
6. Starta om Claude Code

### Status

- [ ] Skapa service account + JSON-nyckel
- [ ] Installera + konfigurera MCP-servern
- [ ] Testa: be Claude ranka topp-URL:er senaste 30 dagarna
- [ ] Använd data för att besluta cache pre-warm-URL:er (se todo #1)

### Alternativ (quick)

Om MCP-setup känns krångligt: exportera CSV från GA4 manuellt → mata
Claude direkt. Enstaka analyser, ingen löpande integration.

---

## 9. Extern DB-backup via Hetzner Object Storage (eller S3)

**Nuläge:** bara Hetzner-snapshots (hela diskens backup, dagligen, 7 dagar
retention). Räcker för katastrofåterställning men:

- Ligger på samma leverantör
- Kan inte inspekteras (binär snapshot)
- Kräver ~5 min att boota från backup för att hämta databas-data
- Om *hela Hetzner-kontot* skulle tas över / stängas ned = inget

**Mål:** Dagliga SQL-dumps till **extern** Object Storage så vi har:

- Läsbar/sökbar dump
- Off-site redundans
- Snabb partial-restore (bara DB, utan att röra servern)

### Implementering

1. Skapa bucket i Hetzner Object Storage (Helsinki eller annan region)
2. Generera access key (gärna skrivbegränsad till specifik bucket)
3. Installera `rclone` eller `aws-cli` i en dedikerad backup-container
   (eller på host, om det är enklare)
4. Script som i cron-jobb:
   - Dumpar DB via `mariadb-dump --single-transaction ... | gzip`
   - Uppladdar till `s3://brottsplatskartan-backups/db/YYYY-MM-DD.sql.gz`
   - Raderar lokala dumpar efter upload (spar disk)
   - Roterar bort dumpar äldre än 30 dagar i bucket (lifecycle policy)

### Alternativ: annan leverantör

För geografisk redundans kan backupen gå till helt annan leverantör
än Hetzner:
- **Backblaze B2** (billigast för backup-data)
- **AWS S3** (mest standard, men dyrast)
- **Wasabi** (S3-kompatibel, $6/TB/månad flat)

Fördelen: om Hetzner-kontot av någon anledning försvinner har vi backup
på oberoende plats.

### Status

- [ ] Besluta: Hetzner Object Storage (enklast) eller extern leverantör?
- [ ] Skapa bucket + access keys
- [ ] Implementera backup-script + cron
- [ ] Testa återställning från dump (viktigt — backup som inte testats är ingen backup)
- [ ] Dokumentera i `deploy/provision.md`

### Prioritet

Låg innan cutover. Efter cutover — gör detta inom första månaden då
det är ett verkligt skydd mot människliga fel.

---

*Senast uppdaterad: 2026-04-21*
