**Status:** klar 2026-10-07 — gamla listan ∪ filtrerade gatunamn från OSM (+42 179), 27 av 2 000 events får nya träffar, alla riktiga gator. Skript: `deploy/uppdatera-osm-gatunamn.sh`
**Senast uppdaterad:** 2026-10-07
**Källa:** Inbox Brottsplatskartan (2026-10-04)

# Todo #106 — Uppdatera gatunamnslistan från OpenStreetMap

## Sammanfattning

Kolla [[svenska gatunamn och adresser från openstreetmap]]
Om det fortsatt är ett fungerande sätt att få ut gator och platsnamn. Och om det f funkar borde vi köra det igen nu och uppdatera listan i koden.

**Status 2026-08-21: kvar.** Listan i `resources/openstreetmap/` är oförändrad sedan flytten.

## Bakgrund

Obsidian-anteckningen "svenska gatunamn och adresser från openstreetmap"
beskriver hur listan togs fram.

## Förslag

1. Läs anteckningen, kontrollera att metoden fortfarande fungerar.
2. Kör om och diffa mot nuvarande lista i `resources/openstreetmap/`.

## Status 2026-10-07: gatunamnslistan utökad

Metoden fungerar fortfarande, men med osmium i Docker i stället för osmosis,
och med ändrat urval. Reproducerbart skript:
`deploy/uppdatera-osm-gatunamn.sh`, dokumentation och mätningar i
[docs/openstreetmap-gatunamn.md](../docs/openstreetmap-gatunamn.md).

Källa: Geofabrik `sweden-261006.osm.pbf` (2026-10-06).

Den gamla osmosis-raden (`--tf accept-ways highway=*`) filtrerade bara ways,
så alla namngivna noder och relationer följde med (orter, län, butiker,
hållplatser, busslinjer). Att köra om exakt det mot dagens OSM gick inte att
skeppa: simulerat mot 2 000 prod-händelser gav det 3 955 → 7 498 träffar,
nästan bara vanliga ord (`finns`, `ambulans`, `kvinna`, `mellan`, `brand`).
Bara highway-ways gav 3 955 → 1 371, eftersom orter och län försvann.

Valt: gamla listan plus nya gatunamn från highway-ways. Enordsnamn tas bara
med om de slutar på en gatuändelse (`väg`, `gatan`, `gränd` …), plus en kort
stoppordslista i skriptet.

| Variant (simulerad mot 2 000 prod-händelser) | Träffar       | Händelser med nya träffar | Förlorade träffar |
| -------------------------------------------- | ------------- | ------------------------- | ----------------- |
| Full omkörning (noder + relationer + ways)   | 3 955 → 7 498 | 1 607                     | 32                |
| A: bara highway-ways                         | 3 955 → 1 371 | 233                       | 2 831             |
| B: gamla ∪ highway-ways                      | 3 955 → 4 202 | 233                       | 0                 |
| Valt: gamla ∪ filtrerade highway-ways        | 3 955 → 3 982 | 27                        | 0                 |

De 27 nya träffarna är alla riktiga gator (`thulinvägen`, `heffners allé`,
`travbanegränd`, `simlinge byaväg` …).

| Fil                                | Före    | Efter   |
| ---------------------------------- | ------- | ------- |
| `highways_sorted_unique.txt`       | 146 185 | 188 363 |
| `swedish-cities-sorted-unique.txt` | 2 735   | 2 818   |

42 179 nya namn i gatulistan, inget borttaget (en rad ändrad när `&#xA;`
avkodades). Alla XML-entiteter avkodas nu och sorteringen är `LC_ALL=C`.
Ortlistan är helt omgenererad (256 borttagna småbyar, 339 nya) men används
inte av koden.

Ingen cache att rensa efter deploy, listan läses per process.

## Risker

- Gator som bytt namn eller tagits bort i OSM ligger kvar, eftersom listan
  bara växer. Ofarligt i praktiken.
- `findLocations()` gör linjär `in_array` mot listan: 600 anrop tar 0,13 s
  mot 0,10 s förut. Byt till `isset` på en `array_flip`:ad lista om det blir
  ett problem.

## Confidence

medel
