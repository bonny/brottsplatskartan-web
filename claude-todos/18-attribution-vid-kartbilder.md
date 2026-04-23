# Todo #18 — Granska och ev. förbättra OSM-attribution vid statiska kartbilder

## Sammanfattning

ODbL (OpenStreetMap-licensen) kräver synlig attribution "© OpenStreetMap
contributors" där OSM-data visas. Idag finns attributionen på två ställen:

1. **Om-sida** (`resources/views/page.blade.php:139-141`):
   "Kartbilderna kommer från OpenMapTiles: © OpenMapTiles © OpenStreetMap contributors"
2. **Leaflet-kartor** (`public/js/events-map.js:386`, `sverigekartan-iframe.blade.php:182`):
   inline attribution-kontroll "© OSM" / "© OpenStreetMap"

**Möjlig brist:** De statiska JPG:erna som visas på event-kort
(`crimeevent/card.blade.php`, `list-item.blade.php`, `event-map-far.blade.php`,
single-event-sidor) har **ingen synlig attribution i närheten av själva bilden**.
Användare som ser en bild men aldrig besöker om-sidan får ingen attribution.

## Vad ODbL faktiskt kräver

ODbL 4.3: "reasonable means" — strikt tolkning vill se attribution i/nära
datan. Gängse praxis är tillräckligt om det finns en länk från sidan där
datan visas till en attribution-sida. Leaflet-standarden (inline-overlay)
är den mest ordentliga lösningen.

Nuvarande läge är **gråzon, inte klar brist** — det finns attribution men
den är inte så nära som Leaflet-kartorna har.

## Möjliga åtgärder (välj en)

1. **Gör inget.** Länken på om-sidan är "reasonable means". Risk: ingen praktisk.
2. **Liten bildtext under varje statisk kartbild** — t.ex. "Karta: © OSM contributors"
   i 11px grå text under `.Event__mapImage`. Lägg in i `crimeevent/card.blade.php`
   + varianter.
3. **CSS-overlay på bilden själv** — hörntext med position:absolute ovanpå
   JPG:en. Gör det mer Leaflet-likt men kräver CSS-justering per storlek.

## Rekommendation

Alt 2 är enklast och tydligast. Påverkar inte layout nämnvärt. Kan göras
som del av #4 (mbtiles-uppgradering) eftersom attributionen då också
behöver uppdateras om openmaptiles.com-referensen ändras.

## Prio

**Låg.** Ingen akut licensrisk. Gör i samband med #4 eller som standalone-fix
om någon har 15 min över.
