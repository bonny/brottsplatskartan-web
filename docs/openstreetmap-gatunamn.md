# Gatunamns- och ortlistan från OpenStreetMap

`resources/openstreetmap/highways_sorted_unique.txt` är listan som
`FeedParserController::findLocations()` matchar händelsetexterna mot för att
hitta gator och platser (en, två och tre ord i följd). Filen läses av
`loadHighways()`, som trimmar, gör om till gemener, tar bort poster på tre
tecken eller kortare och sedan drar ifrån `highways_ignored` och lägger till
`highways_added` från databasen. Ordning, skiftläge och dubbletter i filen
spelar alltså ingen roll för koden.

`swedish-cities-sorted-unique.txt` används inte av koden i dag
(`loadCities()` är bortkommenterad) men genereras om samtidigt.

## Uppdatera

Kräver Docker och ungefär 3 GB ledigt disk. Tar ett par minuter.

```bash
./deploy/uppdatera-osm-gatunamn.sh
# Återanvänd redan nedladdad pbf:
SKIP_DOWNLOAD=1 ./deploy/uppdatera-osm-gatunamn.sh
```

Skriptet:

1. Laddar ner `sweden-latest.osm.pbf` från Geofabrik till den gitignorerade
   mappen `tmp-osm-gatunamn/` och kontrollerar md5-summan.
2. Bygger en liten image (`bpk-osmium:local`, Debian + `osmium-tool`), så
   inget behöver installeras på hosten.
3. Plockar med `osmium tags-filter` ut alla ways med `highway=*` och tar ut
   deras `name`. Entiteter som `&amp;` och `&apos;` avkodas.
4. Behåller bara namn som innehåller mellanslag eller slutar på en
   gatuändelse (`väg`, `gatan`, `gränd`, `allé`, `torget` m.fl.), och tar bort
   en kort stoppordslista (`motorvägen`, `gång i`, `gårds väg`).
5. Lägger till de namnen i den befintliga listan (union, inget tas bort) och
   sorterar med `LC_ALL=C sort -u`.
6. Genererar om ortlistan från noder med `place=city,town,village`.

## Varför bara tillägg, och bara gator

Den ursprungliga metoden (Obsidian-anteckningen "svenska gatunamn och adresser
från openstreetmap") var

```bash
osmosis --read-pbf sweden-latest.osm.pbf --tf accept-ways highway=\* --write-xml highways.osm
grep 'k="name"' highways.osm | cut -d\" -f4 | sort | uniq
```

`--tf accept-ways` filtrerar bara ways. Noder och relationer släpps igenom
orörda, så den gamla listan innehåller också namn på alla namngivna noder och
relationer: orter, län, stadsdelar, hållplatser, butiker och busslinjer.

Att köra om samma sak mot dagens OSM (2026-10-07) gav 330 907 rader. En
simulering av `findLocations()` mot de 2 000 senaste prod-händelserna, med
prods `highways_ignored` och `highways_added`, gav då 3 955 → 7 498 träffar
och nya träffar i 1 607 händelser. Nästan alla var vanliga ord som numera finns
som nodnamn: `finns` 395, `ambulans` 310, `kvinna` 305, `mellan` 264, `brand` 142.

Bara highway-ways (108 000 namn) blev i stället 3 955 → 1 371 träffar,
eftersom orterna och länen försvinner (`umeå` 74, `skellefteå` 58,
`östergötlands län` 33 …).

Därför: gamla listan plus nya gatunamn från highway-ways, filtrerade enligt
punkt 4. Simuleringen ger då 3 955 → 3 982 träffar, 27 händelser med nya
träffar (alla riktiga gator, t.ex. `thulinvägen`, `heffners allé`,
`travbanegränd`) och inga förlorade träffar. Utan ändelsefiltret kom
enordsnamn som `Okänd` (77), `Okänt` (40), `Norra` (36), `Hållplats` och
`Rondell` med.

Nackdelen med union är att gator som har bytt namn eller tagits bort i OSM
ligger kvar. Det kostar bara lite minne och tid; falska träffar på dem är
osannolika.

## Granska resultatet

```bash
git diff --shortstat resources/openstreetmap/
comm -13 <(git show HEAD:resources/openstreetmap/highways_sorted_unique.txt | LC_ALL=C sort -u) \
  resources/openstreetmap/highways_sorted_unique.txt | grep -v ' ' | awk 'NR % 200 == 0'
```

Titta särskilt på nya enordsnamn. Ser något ut som ett vanligt ord som kan
stå i en polistext, lägg till det i `STOPPORD` i skriptet (eller i
`highways_ignored` om det redan är i prod).

## Efter deploy

Ingen cache behöver rensas. Listan läses från disk en gång per process och
sparas bara i en instansvariabel (`$this->highwayItems`). Schedulern kör
`crimeevents:fetch` som en ny process varje gång, så nästa hämtning använder
den nya listan. Händelser som redan har sökts igenom (`scanned_for_locations`)
söks inte om.
