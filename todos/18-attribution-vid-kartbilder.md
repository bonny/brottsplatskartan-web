**Status:** avfärdad 2026-04-24
**Senast uppdaterad:** 2026-04-24

# Todo #18 — Attribution vid statiska kartbilder (ODbL)

## Beslut

Avfärdad. Nuvarande attribution på `/sida/om` och inline i Leaflet-kartor
räcker som "reasonable means" enligt ODbL 4.3. Ingen akut licensrisk.

## Bakgrund

ODbL kräver synlig attribution "© OpenStreetMap contributors" där
OSM-data visas. Idag finns attribution på:

1. Om-sidan (`resources/views/page.blade.php:139-141`):
   "Kartbilderna kommer från OpenMapTiles: © OpenMapTiles © OpenStreetMap contributors"
2. Leaflet-kartor (`public/js/events-map.js:386`): inline "© OSM"

De statiska JPG:erna på event-kort saknar attribution i direkt närhet,
men länken till om-sidan från varje sida är "reasonable means".

## Varför avfärdad

- Gråzon, inte klar brist
- Om-sidan + Leaflet-kartornas inline attribution täcker kravet
- Ingen faktisk licensrisk identifierad
- Tid bättre spenderad på #11 (SEO) eller #17 (städning)

Om OSMF någonsin klagar: lägg till 11px "Karta: © OSM contributors"
under `.Event__mapImage` i `crimeevent/card.blade.php`. 15 min jobb.
