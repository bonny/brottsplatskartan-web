**Status:** klar 2026-04-28 — `/api/eventsMap` accepterar `?city=` och `?lan=`, lookback 30d med filter, cache-key per parameter; city.blade.php skickar filter via nya komponent-props
**Senast uppdaterad:** 2026-04-28

# Todo #44 — EventsMap-API ska kunna filtreras per plats

## Sammanfattning

Live-kartan på Tier 1-stadsidor (t.ex. `/uppsala`) hämtar globalt
event-data via `/api/eventsMap` utan platsfilter, och centreras
sedan på staden via `data-events-map-lat-lng` + `zoom`. Resultatet:
på Uppsala-sidan syns ofta bara 0–2 markers eftersom bara de globala
events som råkar ligga inom synlig bbox visas, medan listan i
sidebaren samtidigt visar 3+ Uppsala-events. Användaren upplever
det som "bara en händelse på kartan" trots att flera finns.

## Bakgrund

- `app/Http/Controllers/ApiEventsMapController.php` — tar inga
  parametrar; returnerar senaste 500 events från senaste 3 dagarna,
  cachat 5 min.
- `resources/views/components/events-map.blade.php` — bara
  lat/lng/zoom/size som data-attribut, ingen plats.
- `public/js/events-map.js:219` — `fetch("/api/eventsMap")` utan
  query-string.

På Uppsala-sidan (zoom 11, lat-lng 59.86,17.64) syns bara 1–2
events i bbox av 173 globalt. Vid mindre städer / län kan det bli
0 markers även om listan visar händelser.

## Förslag

1. **API-parameter** — låt `ApiEventsMapController` ta `?city=`,
   `?lan=`, eller `?plats=` som filter. Match mot
   `administrative_area_level_2` (kommun) för city/plats och
   `administrative_area_level_1` (län) för lan. Cache-key bygger
   in parametern.
2. **Längre lookback när platsfilter används** — t.ex. 30 dagar
   istället för 3, så små städer faktiskt har markers att visa.
   Drop limit till 200 eller behåll 500.
3. **Propagera filter via blade-komponent** — `<x-events-map>`
   accepterar ett `:location-filter` som lägger
   `data-events-map-location` + `data-events-map-location-type`
   på containern. JS läser och bygger fetch-URL.
4. **Uppdatera anrop från city/single-plats/single-lan-vyer** —
   skicka korrekt slug + typ.
5. **Sverigekartan / startsidan** — fortsätt utan filter (default).

## Risker

- **Cache-explosion** — varje (typ, slug)-kombination får egen
  cache-rad. Med ~5 Tier 1 + ~290 platser + 21 län = 316 rader
  i Redis. Med 5min TTL och små JSON-blobs (max 500 events) blir
  det totalt några MB max. OK.
- **Performance på obeskrivna platser** — om query saknar index
  på `administrative_area_level_2` kan filter bli långsamt vid
  cache-miss. Kolla index. (Indextäckning verifieras i #38-arbetet.)
- **Lookback 30d vs 3d skapar stora datamängder för Stockholm
  m.fl. stora städer** — limit 500 ska räcka, men sannolik trim
  behövs (t.ex. limit 200 men med ordering på senaste först).

## Confidence

Hög — välavgränsat ändringsblock (1 controller + 1 component +
1 JS-fil + 3 vyer). ~2-3h jobb inkl. testning.

## Beroenden

Inga starka. Kan göras parallellt med #41 (årskalender) och #42
(månadsnav). Inga dataförändringar krävs.
