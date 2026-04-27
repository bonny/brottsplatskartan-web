**Status:** aktiv (förberedande blocker för #27 Lager 2)
**Senast uppdaterad:** 2026-04-27
**Relaterad till:** #27 (rikare innehåll), #25 (månadsvyer)

# Todo #37 — Tätortskod-mappning (SCB) för "brott per 1000 invånare"

## Sammanfattning

För att räkna ut **brott per 1000 invånare** på ortssidor (CrimeGrade-
modellen — den enda validerade vinnar-metriken enligt #27-research)
behöver Brottsplatskartans plats-namn paras ihop med SCB:s tätortskoder
+ kommunkoder. Det finns inget gemensamt nyckelfält → manuell mappning
+ fallback-logik krävs. Räkna 1–2 dagars arbete.

Detta är formell blockerare för **#27 Lager 2** (befolkningsfakta,
brott/1000 inv., jämförelsetabell mot grannstäder).

## Bakgrund

### Datakällor (verifierade 2026-04-27)

- **Kommunbefolkning** (`api.scb.se`): JSON-API, anonymt, 4-siffrig
  kommunkod → folkmängd. Verifierat: Uppsala kommun `0380` =
  248 016 inv. 2024.
- **Tätorter** (`geodata.scb.se`): GeoPackage 51 MB, ~2 000 tätorter
  med polygon + befolkning + namn. Senaste 2023, CC0-licens.

### Varför mappning är manuellt jobb

Polisens RSS ger fri text som platsnamn:
- `"Stockholm city"` (inte en tätort i SCB:s register)
- `"Södermalm"` (stadsdel, inte separat SCB-tätort)
- `"Bromma"` (både stadsdel i Stockholms tätort OCH SCB-tätort)
- `"Visby"` (matchar SCB-tätort direkt)
- `"Hejsanhoppsangränd"` (gata — finns inte i SCB)

SCB:s tätorter heter t.ex. `"Stockholm"`, `"Uppsala"`, `"Bromma kyrkby"`.
Inget gemensamt fält. För Brottsplatskartans ~2 000+ unika platsnamn
behövs en mappnings-tabell + fallback-strategi.

## Förslag

### 1. Skapa tabell `place_population`

```sql
CREATE TABLE place_population (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  bpk_place_name VARCHAR(255) NOT NULL UNIQUE,
  scb_tatort_code VARCHAR(20) NULL,         -- t.ex. "T0014" (Stockholm)
  scb_kommun_code VARCHAR(4) NULL,          -- t.ex. "0180" (Stockholm kommun)
  population INT UNSIGNED NULL,
  population_year YEAR NULL,
  source ENUM('scb_tatort','scb_kommun','manual','none') NOT NULL,
  notes TEXT NULL,
  updated_at TIMESTAMP NULL,
  KEY idx_kommun (scb_kommun_code),
  KEY idx_tatort (scb_tatort_code)
);
```

### 2. Importera SCB-data en gång (artisan-kommando)

```bash
docker compose exec app php artisan scb:import-tatorter
```

- Ladda ner `Tatorter_2023.gpkg`
- Läs in via PHP SQLite3-extension (GeoPackage = SQLite-fil)
- Spara till hjälptabell `scb_tatorter` (kod, namn, befolkning, polygon)
- Idempotent — säker att köra om

### 3. Auto-mappa exakta matchningar

Skript som matchar Brottsplatskartans befintliga platsnamn mot
SCB-tätorter:
- **Exakt match** (case-insensitive, normaliserad åäö) → `source=scb_tatort`
- **Match på kommunnamn** (när platsnamnet = kommun) → `source=scb_kommun`
- **Geo-match via koordinater** (om event har lat/lng inom tätortspolygon)
  → `source=scb_tatort`
- Resten → `source=none`, manuell granskning

### 4. Manuell granskning + fallback

För platser som inte fick auto-match:
- Stadsdelar i större städer → mappa till stadens tätortskod (t.ex.
  "Södermalm" → Stockholm-tätorten)
- Småorter <200 inv. → använd kommunkod istället
- Skräp ("polismyndigheten", "okänd plats") → `source=none`,
  döljs i UI

Verktyg: enkel CLI eller admin-vy för granskning. Estimat: 1 dag manuellt
arbete för 100–200 vanligaste platserna (longtail kan stå utan data
utan att ramen havererar).

### 5. Helper på `Place`-modellen

```php
$place->getPopulation(); // returns int|null
$place->getCrimesPerThousand(); // brott senaste 12 mån / pop * 1000
$place->getNeighbors(int $count = 5); // 5 grannstäder för jämförelsetabell
```

Cachas i Redis 24h.

## Risker

- **Stadsdelar dubbelräknas.** Om "Södermalm" mappas till Stockholm-
  tätorten och "Stockholm" också gör det, så delar de samma befolkning
  — brott/1000 blir lägre på Stockholm men inte på Södermalm. Lös
  genom att bara visa metriken på _kommun_- och _tätorts_-nivå, inte
  stadsdelsnivå.
- **Definition av "brott senaste 12 mån".** Inkluderar vi alla händelser
  eller bara de som faktiskt är brott (inte trafikolyckor, sammanfattningar)?
  Sannolikt ska sammanfattningar exkluderas. Definiera i todo.
- **Tätortsdata är 2023, befolkning är 2024.** Mismatch på 1 år är
  acceptabelt — visar vi "2023" i UI:t räcker det.
- **Polygon-matching kan vara dyr.** 2 000 polygoner × ~150 000 events
  via spatial join. Kör som engångsjobb, cacha resultatet.

## Confidence

**Hög** — datakällorna är verifierade tillgängliga (testade via curl),
schemat är straightforward, manuell granskning är tråkig men inte
osäker. Enda osäkerheten är hur många platser som behöver manuell
mappning — beror på fördelningen av longtail i `crime_events.location`.
Bör mätas tidigt med en `SELECT location, COUNT(*)`-query.

## Implementationsordning

1. Mät storlek: `SELECT location_string, COUNT(*) FROM crime_events GROUP BY 1 ORDER BY 2 DESC LIMIT 500`
2. Skapa tabeller + import-kommando
3. Auto-match top 100 platser
4. Manuell granskning av nästa 200
5. Lägg till `getPopulation()` på `Place`
6. Avblockera #27 Lager 2

## Synergi

Detta jobb är värt att göra även om #27 inte rullas ut — befolknings-
data + per-1000-statistik kan användas i adminvyer för att utvärdera
om en plats är "tunn" och bör konsolideras eller noindex:as (se #29).
