**Status:** klar 2026-05-05 — Fas 1 + Fas 2 deployat 2026-04-29; 6d soak ren (100 % polisen_id + 100 % geocoded sedan 2026-04-30, inga rate-limit-fel). RSS-referenser i kommentarer/blade städade.
**Senast uppdaterad:** 2026-05-05

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

**Polisens JSON-API:** <https://polisen.se/api/events> (klicka för att se 500
senaste händelserna direkt). Officiellt sedan ~2017, ingen API-nyckel.

- Dokumentation: <https://polisen.se/om-polisen/om-webbplatsen/oppna-data/api-over-polisens-handelser/>
- Regler: <https://polisen.se/om-polisen/om-webbplatsen/oppna-data/regler-for-oppna-data/>
- Filter-parametrar: `?DateTime=2026-04`, `?locationname=Stockholm;Järfälla`, `?type=Misshandel;Rån`

| Aspekt      | RSS (idag)       | JSON-API                                          |
| ----------- | ---------------- | ------------------------------------------------- |
| ID          | bara guid/url    | numeriskt `id`                                    |
| Brottstyp   | inbäddat i titel | separat `type`-fält                               |
| Plats       | bara i titel     | `location.name` (län-nivå)                        |
| Koordinater | saknas           | `location.gps = "lat,lng"`                        |
| Rate-limit  | odokumenterat    | min 10s/anrop, max 60/h, max 1440/dygn → HTTP 429 |

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

### Fas 2 — Geocoding-förbättring (klar 2026-04-29)

Implementerat:

- Ny kolumn `polisen_location_name` (län-namn från `location.name`) — fångas
  i import. Alltid län-nivå, till skillnad från `parsed_title_location`
  som ibland är stad.
- `getGeocodeURL` lägger till `, {polisen_location_name}` i Google-querysträngen
  om länet inte redan finns där.
- Viewport-bias via `&bounds=sw_lat,sw_lng|ne_lat,ne_lng` — ~50 km bbox runt
  `polisen_gps_lat/lng`. Icke-restriktiv → biasa, inte begränsa.
- Backfill av tidigare JSON-importerade rader gjord lokalt; gamla RSS-rader
  har inga av fälten och påverkas inte (de geokodades redan).

Eventuell uppföljning:

- Backfill-task för gamla felgeokodade events (Kumla→Stockholm-fallet) —
  separat scope, behöver mätperiod först
- `geocodeItemFallbackVersion` använder fortfarande gamla query-formatet —
  kan harmoniseras om vi ser att fallback-vägen träffas ofta i loggarna

## Risker

- **Rate-limiting odokumenterad** — börja konservativt (60s polling)
- **Polisens summary kan uppdateras retroaktivt** — kontrollera om existerande
  uppdateringslogik (`parseItemContentAndUpdateIfChanges`) fortfarande triggar
- **Backwards compat på md5** — håll kvar `md5(permalink)` så vi inte importerar dubletter
- Backfill av gamla events utanför scope — endast nya events får full nytta

## Confidence

medel — Fas 1 är välavgränsad. Fas 2 har fler okända (Google Geocoding bias-API
kräver test) men inga blockerare.
