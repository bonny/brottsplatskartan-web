# Länsgeometri: gränser och områdescirklar

Två filer i `resources/geo/`, genererade av `deploy/lansgeometri.py`
(todo #78). Läses av `App\Lansgeometri`.

| Fil                   | Innehåll                                                   | Används av                                                                                          |
| --------------------- | ---------------------------------------------------------- | --------------------------------------------------------------------------------------------------- |
| `lansgranser.geojson` | Sveriges 21 länsgränser som polygoner, förenklade (~85 kB) | Ingen kod än — sparad för framtida bruk (punkt-i-polygon, länskartor). Används av `cirklar`-steget  |
| `lanscirklar.json`    | Per län en mittpunkt och radie för en "områdescirkel"      | `StaticMapUrlBuilder::areaUrl()` (kartbilder för sammanfattningar), `/api/eventsMap` (pinnens läge) |

Nyckeln är länsnamnet som det står i `administrative_area_level_1`, t.ex.
`"Skåne län"` — samma form som `Helper::getAllLan()`.

Data © OpenStreetMap-bidragsgivare, ODbL.

## Gränserna

```bash
python3 deploy/lansgeometri.py granser
```

Hämtar varje län från Nominatim (administrativ relation, förenklad till
~0,005°) med 1 anrop/s. Gränser ändras sällan — kör om bara om något län
ser fel ut.

**OBS: gränserna inkluderar havsområdet** ut till territorialgränsen.
Gotland blir ~15 000 km² mot ~3 100 km² land, Stockholm ~16 700 mot
~6 500. Rätt för "vilket län ligger punkten i", missvisande om man ritar
länet som en yta på en karta utan att klippa mot land.

## Områdescirklarna

Cirkeln ska visa "ungefär det här länet" på kartbilder för
sammanfattningar ("Sammanfattning natt" m.fl.), vars koordinat bara är
Polisens samordningspunkt.

- **Mitt** = polygonens ytviktade tyngdpunkt.
- **Radie** = avståndet från mitten som täcker 90 % av länets unika
  händelseplatser (minst 15 km).

Varför inte enklare:

- _Cirkel med länets yta_ blir mest hav för kustlän (se ovan).
- _Median av händelseplatserna som mitt_ dras mot storstaden — Norrbottens
  cirkel hamnade runt Luleå och missade Kiruna, Gotlands missade södra ön.
- _Alla händelser i stället för unika platser_: länets standardpunkt
  (händelser som bara geokodats till länet) och dolda pressinfo-händelser
  (`is_public = 0`, 47 000 "Övrigt" på en punkt i Västra Götaland) drog
  cirklarna. Exporten tar därför bara publika händelser och unika platser.

Kör om när händelsemönstret ändrats märkbart (t.ex. en gång om året):

```bash
# 1. Exportera ett års unika händelseplatser per län från prod (read-only).
mkdir -p tmp-78
ssh deploy@brottsplatskartan.se "cd /opt/brottsplatskartan && docker compose exec -T app php artisan tinker --execute='echo \"###J###\".json_encode(DB::table(\"crime_events\")->where(\"is_public\",1)->where(\"created_at\",\">\",now()->subYear())->whereNotNull(\"location_lat\")->whereNotNull(\"administrative_area_level_1\")->where(\"parsed_title\",\"not like\",\"Sammanfattning%\")->selectRaw(\"administrative_area_level_1 a, round(location_lat,3) la, round(location_lng,3) lo\")->distinct()->get()->groupBy(\"a\")->map(fn(\$g)=>\$g->map(fn(\$r)=>[(float)\$r->la,(float)\$r->lo])->values()), JSON_UNESCAPED_UNICODE).\"###E###\";'" \
  | grep -o '###J###.*###E###' | sed 's/^###J###//; s/###E###$//' > tmp-78/handelsekoordinater.json

# 2. Räkna cirklarna.
python3 deploy/lansgeometri.py cirklar tmp-78/handelsekoordinater.json
```

Efter deploy av nya cirklar: kartbilds-301:orna cachas `immutable` i ett
år, så ändrade cirklar syns bara för nya besökare om URL:en byts. Byt
läges-namnet (`omrade` → nytt namn, behåll det gamla som alias i
`KartbildController`) om ändringen är stor.
