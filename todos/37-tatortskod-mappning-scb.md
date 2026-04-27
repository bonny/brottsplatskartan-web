**Status:** aktiv — kod klar lokalt (steg 1–5), kvar prod-deploy + manuell import
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

1. ~~Mät storlek~~ ✓ (2026-04-27, prod): 331 unika platsnamn, 296 kommuner, 26 län, 328k events
2. ~~Skapa tabeller + import-kommando~~ ✓ — migration `2026_04_27_220147` + 3 artisan-cmd
3. ~~Auto-match~~ ✓ — 99.1% (327/330) lokalt. Strict-then-fuzzy match-strategi
4. ~~Manuell granskning av longtail~~ ✓ — bara 3 omappade och alla är 1-event-skräp
5. ~~Lägg till `getPopulation()` på `Place`~~ ✓ — `App\PlacePopulation::lookup()` + `crimesPerThousand()` med 24h Redis-cache
6. **Kvar:** prod-deploy + manuell import (en gång)
7. Avblockera #27 Lager 2

## Implementation 2026-04-27

### Tabeller (migration `2026_04_27_220147_create_scb_population_tables`)

- `scb_tatorter` — 2017 rader från SCB Geopackage (CC0, 2023)
- `scb_kommuner` — 290 rader från SCB-API (CC0, 2024)
- `place_population` — bpk-namn → tätort/kommun/län-koder. `bpk_place_name` har `utf8mb4_bin`-collation för att särskilja accent-känsliga dubletter ("Habo" vs "Håbo")

### Artisan-kommandon

```bash
docker compose exec app php artisan scb:import-tatorter           # 49 MB Geopackage
docker compose exec app php artisan scb:import-kommuner --year=2024
docker compose exec app php artisan place-population:auto-map     # auto-mappa
```

### Match-prioritet

1. Strikt tätort (case-foldat, accent-bevarat)
2. Strikt kommun
3. Fuzzy tätort (accent-okänsligt) — får `notes='Fuzzy-match'`
4. Fuzzy kommun
5. Län-fallback (för "Västerbottens län"-fall)

### Lokal verifiering

| Plats               | Källa      | Befolkning     |
| ------------------- | ---------- | -------------- |
| Stockholm           | scb_tatort | 1 652 895      |
| Uppsala             | scb_tatort | 174 982        |
| Habo                | scb_tatort | 9 499          |
| Håbo                | scb_kommun | 22 973         |
| Solna               | scb_kommun | 85 789         |
| Västerbottens län   | scb_lan    | 281 138        |

Brott per 1000 inv. (Uppsala): 5571 events / 174982 = 31.84

## Prod-import (kvar att göra)

Efter deploy, kör en gång på prod-servern:

```bash
ssh deploy@brottsplatskartan.se
cd /opt/brottsplatskartan
docker compose exec app php artisan scb:import-tatorter
docker compose exec app php artisan scb:import-kommuner --year=2024
docker compose exec app php artisan place-population:auto-map
```

Kommandona är idempotenta — säkra att köra om vid behov.

## Mätning 2026-04-27 (prod)

**Distribution av `parsed_title_location`:**

| Antal events | Antal platser |
| ------------ | ------------- |
| ≥1000        | 76            |
| 500–999      | 73            |
| 100–499      | 161           |
| 10–99        | 17            |
| <10          | 3             |
| **Totalt**   | **331**       |

**Topp-30** är blandning av städer (Stockholm, Malmö, Umeå, Göteborg, ...)
och län-namn ("Västerbottens län", "Norrbottens län" m.fl.) där Polisen
inte specificerat ort. Län-fall mappas till hela länets befolkning.

**Duplikater att hantera:** "Västernorrland län" + "Västernorrlands län"
finns båda i datan (typo från Polisens RSS).

**Konsekvens:** scopen är mycket mindre än ursprungligen tänkt
(331 platser, inte 2000+). Manuellt arbete bör kunna göras på en halvdag.

## Synergi

Detta jobb är värt att göra även om #27 inte rullas ut — befolknings-
data + per-1000-statistik kan användas i adminvyer för att utvärdera
om en plats är "tunn" och bör konsolideras eller noindex:as (se #29).
