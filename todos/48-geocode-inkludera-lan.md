**Status:** aktiv — Fas 1 (RSS → JSON-API) pågår
**Senast uppdaterad:** 2026-04-29

# Todo #48 — Polisens JSON-API + bättre geocoding

Importerad från GitHub-issue [#6](https://github.com/bonny/brottsplatskartan-web/issues/6) (gammal — 2018).

Reviderad 2026-04-29 efter kodgranskning + research av RSS vs JSON-API.

## Sammanfattning

Två sammanhängande förbättringar:

1. **Migrera datakälla** RSS → Polisens JSON-API
2. **Förbättra geocoding** för orter som finns i flera län (t.ex. "partille") via viewport-bias och län-fallback

## Bakgrund (verifierad 2026-04-29)

**Idag:**

- Importen hämtar RSS från `polisen.se/aktuellt/rss/hela-landet/handelser-i-hela-landet/`
  via SimplePie (`FeedController::updateFeedsFromPolisen`, rad 380)
- Geocoding sker via Google Maps Geocoding API med `country:SE`, **utan län** i query
  (`FeedController::geocodeItem`, rad 83). Polisens lat/lng används inte alls.
- Exempel på fel: `trafikolycka-orebro-kumla-103228` hamnade under `stockholms-lan/`.

**Polisens JSON-API** (`https://polisen.se/api/events`) — officiellt sedan ~2017,
ingen API-nyckel, dokumenterat på polisen.se. Strukturen oförändrad sedan dess.

| Aspekt      | RSS (idag)       | JSON-API                              |
| ----------- | ---------------- | ------------------------------------- |
| ID          | bara guid/url    | numeriskt `id`                        |
| Brottstyp   | inbäddat i titel | separat `type`-fält                   |
| Plats       | bara i titel     | `location.name` (län-nivå)            |
| Koordinater | saknas           | `location.gps = "lat,lng"`            |
| Rate-limit  | odokumenterat    | odokumenterat — community: max ~1/min |

**Viktig nyans:** `location.gps` i JSON-API är **mittpunkt för län/kommun**, inte
event-precis. Räcker för viewport-bias men inte för kart-pin. Den event-precisa
geokodningen måste fortsatt ske via Google Geocoding utifrån ortsnamnet.

## Plan

### Fas 1 — Migrera RSS → JSON-API

Värde oavsett geocoding: stabilt `id`, separat `type`-fält, strukturerad
`location.name` (län), grov `location.gps` att senare använda för viewport-bias.

- Ny metod i `FeedController` som hämtar `polisen.se/api/events` via Laravel HTTP-klient
- Mappa fält: `name` → title, `summary` → description, `url` → permalink (prefix
  `https://polisen.se`), `datetime` → pubdate, `id` → ny kolumn `polisen_id`
- Bevara nuvarande dedup (`md5(permalink)`) för bakåtkompatibilitet
- Cache 60s (motsvarar nuvarande SimplePie-cache)
- Pensionera SimplePie-importen (kan tas bort när Fas 1 är stabilt soak:ad)

### Fas 2 — Geocoding-förbättring

Kräver Fas 1 (för att ha `location.gps`).

- Använd `location.gps` som `bounds`-parameter i Google Geocoding-anropet
  (Viewport Biasing → bättre träff för "partille")
- Lägg till `, {län}` i query-strängen som extra signal
- Eventuell backfill-task för gamla felgeokodade events (separat scope)

## Risker

- **Rate-limiting odokumenterad** — börja konservativt (60s polling)
- **Polisens summary kan uppdateras retroaktivt** — kontrollera om existerande
  uppdateringslogik (`parseItemContentAndUpdateIfChanges`) fortfarande triggar
- **Backwards compat på md5** — håll kvar `md5(permalink)` så vi inte importerar dubletter
- Backfill av gamla events utanför scope — endast nya events får full nytta

## Confidence

medel — Fas 1 är välavgränsad. Fas 2 har fler okända (Google Geocoding bias-API
kräver test) men inga blockerare.
