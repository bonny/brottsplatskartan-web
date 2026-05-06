**Status:** aktiv (väntar på #50 soak-end 2026-05-10)
**Senast uppdaterad:** 2026-05-06
**Relaterad till:** #50 (Trafikverket — samma layer-mönster), #51 (research-katalog som denna bryts ut från)

# Todo #69 — SMHI vädervarningar som kart-layer

## Sammanfattning

Lägg till SMHI:s konsekvensbaserade vädervarningar (IBW) som togglebar
GeoJSON-layer på huvudkartan. Ingen egen route, ingen /vma-refaktor —
ren overlay som följer mönstret från #50 Trafikverket Fas 1.

Scope **medvetet snävt** efter SEO/annons/UX-granskning 2026-05-06:
paraply-route `/varningar` avfärdades pga brand-mismatch (sänker brott/
plats-clusters topical authority), dåligt Adsense-RPM på kris-trafik,
och säkerhetsrisk i att blanda VMA (livshotande) med vardagliga
vädervarningar (halka, vattenbrist).

## Bakgrund

- **API verifierat live 2026-05-06:**
  `https://opendata-download-warnings.smhi.se/ibww/api/version/1/warning.json`
- **Data:** 5 aktiva varningar idag (4 × vattenbrist + 1 × brandrisk),
  GeoJSON-polygon inbäddad direkt (EPSG:4326), bilingual sv/en, severity
  RED/ORANGE/YELLOW/MESSAGE, 16 event-typer.
- **Licens:** CC-BY 4.0 SE — cache + kommersiell användning OK,
  attribution till SMHI krävs.
- **Mönster:** identiskt med #50 (egen layer, schemalagd fetch, GeoJSON,
  layer-toggle) — inget arkitekturarbete utöver implementation.

## Förslag (scope A — ren layer)

### Datalager

1. Ny modell `WeatherWarning` + migration:
    - `id` (PK), `smhi_warning_area_id` (unik, kommer från API)
    - `event_code`, `event_sv`
    - `level` (enum: RED, ORANGE, YELLOW, MESSAGE)
    - `area_name`, `affected_areas` (JSON, län/region-IDs)
    - `geometry` (JSON, GeoJSON Feature)
    - `descriptions` (JSON, händelsebeskrivning + "Vad ska jag tänka på?")
    - `approximate_start`, `published`
    - `last_seen_at` (för soft-delete vid uteblivna upserts)
2. Artisan-kommando `smhi:fetch-warnings` — hämtar `/warning.json`,
   upsert på `smhi_warning_area_id`, markerar varningar som inte sågs
   denna körning som inaktiva (`last_seen_at` < senaste fetch).
3. Scheduler i `app/Console/Kernel.php`: var 15:e minut.

### API + frontend

4. Endpoint `/api/weatherWarnings` → GeoJSON FeatureCollection.
   Response-cache 5 min (Spatie). Bara aktiva varningar
   (`last_seen_at >= now - 30min`).
5. `events-map.js`: ny Leaflet GeoJSON-layer.
    - Toggle i layer-control bredvid Trafikverket
    - Color: `RED=#dc2626`, `ORANGE=#ea580c`, `YELLOW=#eab308`, `MESSAGE=#71717a`
    - Stroke + 0.25 fill-opacity (polygoner kan vara stora — låg opacity)
6. Click-popup: `event_sv` + severity-badge + `descriptions` (Händelse +
   "Vad ska jag tänka på?"). Länk "Mer info hos SMHI" till
   `smhi.se/vadret/varningar-i-sverige`.
7. Attribution-rad i karta-footer eller om-sidan: "Varningsdata från
   SMHI (CC-BY 4.0)".

### Conditional UX

8. Liten badge på startsidans karta när det finns RED/ORANGE-varningar
   aktivt: "⚠ 2 aktiva vädervarningar". Klick scrollar till
   layer-toggle. Visas inte vid endast MESSAGE/YELLOW (för bullrigt —
   det finns nästan alltid MESSAGE-varningar).

### Vad som uttryckligen INTE ingår

- **Ingen `/varningar`-paraply-route.** /vma rörs inte.
- **Ingen /väder-route.** SMHI/klart.se/yr.no äger den nischen.
- **Ingen integration med VMAAlert-modellen.** Separat tabell, separat
  layer (kan slå samman senare om behov uppstår — men inte nu).
- **Ingen per-län-notis** ("Aktiv vädervarning i {län}" på län-/plats-
  sidor) i denna todo. Värt att utvärdera _efter_ layer är live, men
  riskerar SEO-utspädning så kräver egen analys + GSC-mätning innan.
- **Ingen polygon-simplifiering** i v1. Mät prestanda först, optimera
  bara om mätning visar problem (Douglas-Peucker via PostGIS eller
  geo-libs på serversidan om så).

## Risker

- **Polygon-storlek på mobil:** Gotland brandrisk = 822 punkter
  MultiPolygon. Hela `/warning.json` är ~80 KB just nu. Vid storm-läge
  kan det blåsa upp. Mitigering: response-cache + stäng layer som
  default (användaren togglar på).
- **Stale-detektion:** SMHI har ingen `valid_to`. Vi tolkar "saknas i
  nästa fetch" som "inaktiv". Risk om scheduler kraschar och vi visar
  utdaterade varningar — soft-delete-windowed på `last_seen_at` skyddar.
- **CC-BY-attribution:** måste finnas synligt. Lätt att glömma vid
  layer-toggle — placera attribution direkt i Leaflet attribution-line.
- **Ingen rate-limit dokumenterad** men SMHI varnar i allmänna villkor
  för "obvious misuse". 15-minuters fetch är riskfritt.
- **CAP-XML-endpointen var 503 vid verifiering 2026-05-06** — vi
  använder dock inte CAP, bara JSON-endpointen som var stabil.

## Beroenden

- **#50 Fas 1 soak till 2026-05-10.** Vänta tills den är ren — då vet
  vi att layer-mönstret är prod-stabilt och kan kopiera utan
  överraskningar. Två live-feeds samtidigt under första veckan är
  onödig risk.

## Confidence

**Hög.** API verifierat live (warning.json + metadata.json båda 200),
data-shape stämmer mot #51:s skiss, GeoJSON-polygoner är direkt
Leaflet-renderbara, licens permissiv, mönstret 1:1 från #50. Effort:
**~1 dag** för MVP (modell + fetch + endpoint + layer + popup +
attribution). Inget AI behövs, ingen ny dependency.

## Avfärdade alternativ (för spårbarhet)

- **B) `/varningar`-paraply** (ersätt /vma med 301 + gemensam
  Alert-modell). Avfärdad efter SEO/annons/UX-granskning 2026-05-06:
  brand-mismatch späder ut topical authority, kris-trafik har lågt
  Adsense-CPM och stör auction-optimering, och blandning av VMA med
  vardagsvarningar sänker upplevd severitet av riktiga VMA. Granskning
  finns i konversationen 2026-05-06.
- **C) Inkludera Krisinformation.se v2** (#51 källa C). Avfärdad: deras
  geografi-data är ofta `null` i praktiken, riskerar att blockera
  SMHI-leveransen.
- **Egen `/väder`-route.** Avfärdad: smhi.se/klart.se/yr.no äger den
  nischen totalt, hopplös SEO-konkurrens.

## Inte i scope

- /vma-modifikationer
- Per-län-notiser eller startsidan-banner utöver den lilla badge:n
- Räddningstjänst-RSS (separat todo om/när #51 källa B prioriteras)
