**Status:** aktiv
**Senast uppdaterad:** 2026-06-13

# Todo #89 — Trafikverket-detaljsida: bättre slug + karta

## Sammanfattning

`/trafik/{id}`-permalinks (Trafikverket-pilot, [#50](done/50-trafikverket-trafikinformation-live.md))
har två förbättringar att göra:

1. **Slug istället för bar id.** Idag är URL:en `/trafik/36271` — bara
   Trafikverkets interna id, ingen kontext för användare eller sök. Borde vara
   t.ex. `/trafik/{type}-{road}-{plats}-{id}` eller liknande
   (jfr. event-slugen `rattfylleri-2440`).
2. **Karta.** Vi har redan `lat`/`lng` på `Event`-modellen (visas idag bara som
   råa koordinater i en `<li>`). Borde rendera en Leaflet-karta med pin på
   platsen, precis som single-event-sidan.

Exempel: <https://brottsplatskartan.se/trafik/36271>

## Bakgrund

- Detaljvyn: `resources/views/trafik-detail.blade.php`, route `trafik.show`
  i `routes/web.php` (~rad 891).
- Sidan är indexerbar (per #50-beslut). Bättre slug → bättre SEO + delbarhet.
- Koordinater finns redan (`$event->lat`, `$event->lng`), renderas idag bara
  som text (`trafik-detail.blade.php` ~rad 61).
- Meta description fixades 2026-06-13 (separat) — den här todon handlar om
  slug + karta.

## Förslag

### Slug
- Generera en SEO-slug av `message_type` + `road_number`/`location_descriptor`
  + id, t.ex. `/trafik/trafikmeddelande-e4-vasterbottens-lan-36271`.
- Behåll id:t i slutet så lookup är snabb och robust (samma mönster som
  CrimeEvent-permalinks). 301:a bar `/trafik/{id}` → kanonisk slug-URL för att
  inte tappa redan indexerade/länkade URL:er.
- Uppdatera `route('trafik.show', ...)`-anrop i listan + canonical.

### Karta
- Återanvänd Leaflet-uppsättningen från single-event-vyn (eller statisk
  kartbild via tileserver-gl, jfr `getKortKartbildUrl`) med pin på lat/lng.
- Överväg statisk kartbild för CWV/LCP (ingen JS) om en interaktiv karta är
  overkill för en pilot-detaljsida.

## Risker

- Pilot-vy, låg trafik — väg insatsen mot nyttan (#50 är fortf. utvärdering).
- Slug-byte kräver 301 från gamla `/trafik/{id}` så inga indexerade URL:er
  404:ar.

## Confidence

medel — tydlig förbättring och låg teknisk risk (återanvänder befintliga
mönster), men nyttan begränsad av att det är en lågtrafikerad pilot. Bör
prioriteras mot #50 Fas 2-beslutet.
