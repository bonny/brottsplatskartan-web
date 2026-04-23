# Uppdatera mbtiles (vektor-tiles för tileserver-gl)

Run-book för att regenerera `.mbtiles`-filen som tileservern serverar från.
Nuvarande fil genereras med **Planetiler** från OSM-data via Geofabrik.

## När körs detta?

Vid behov — kanske 1–2 gånger per år. Kartdatan blir inaktuell när nya
områden byggs (Hagastaden, Slussen, Kiruna nya stadsdel osv.). Pipelinen
är helt gratis och reproducerbar.

Senaste uppdatering: se filnamnet i `deploy/download-tiles.sh`.

## Förutsättningar

- ~5 GB ledigt diskutrymme (PBF + cache + output)
- Java 21+ (`brew install openjdk`). Docker fungerar också men behöver
  minst 12 GB RAM allokerat i Docker Desktop, annars OOMKiller vid
  `water_polygons`-fasen. Java native på host är enklast.
- `rclone` konfigurerat med Hetzner Object Storage (remote-namn
  `Brottsplatskartan Hetzner`). Se `rclone config`.
- Arbetsmapp: `tmp-maps/` i projektroten (gitignored).

## Pipeline

### 1. Ladda ner PBF och planetiler.jar

```bash
mkdir -p tmp-maps/data && cd tmp-maps

# Senaste Sverige-extract (~770 MB)
curl -L -o data/sweden-latest.osm.pbf \
  https://download.geofabrik.de/europe/sweden-latest.osm.pbf

# Planetiler (Java-jar, ~90 MB)
curl -L -o planetiler.jar \
  https://github.com/onthegomap/planetiler/releases/latest/download/planetiler.jar
```

### 2. Generera ny mbtiles

Kör från `tmp-maps/` (så att `data/sources/` hamnar rätt):

```bash
DATESTAMP=$(date +%Y-%m-%d)

/opt/homebrew/opt/openjdk/bin/java -Xmx6g -jar planetiler.jar \
  --maxzoom=15 \
  --osm-path=data/sweden-latest.osm.pbf \
  --output=data/sweden-${DATESTAMP}.mbtiles \
  --force
```

**Viktigt:**
- `--maxzoom=15` — matchar vad `CrimeEvent.php` begär från tileservern.
  Default är 14 och gör z15-bilder suddiga.
- Första körningen laddar ner ~1 GB extra (water polygons,
  natural earth, lake centerlines) till `data/sources/`. Cachas
  mellan körningar.
- Körtid: ~4 min på M-Mac med 6 GB heap.
- Output: ~2.4 GB med z15, ~1.3 GB med z14.

### 3. Testa lokalt

```bash
# Flytta gamla filen, kopiera in nya
mv deploy/tileserver/*.mbtiles deploy/tileserver/old.mbtiles.bak
cp tmp-maps/data/sweden-${DATESTAMP}.mbtiles \
   deploy/tileserver/sweden-${DATESTAMP}.mbtiles

# Starta om lokal tileserver
docker compose restart tileserver

# Vänta tills den svarar och verifiera maxzoom=15
until curl -sf -o /dev/null http://127.0.0.1:8351/styles.json; do sleep 2; done
curl -s http://127.0.0.1:8351/data.json | grep -oE '"maxzoom":[0-9]+'
```

### 4. Visuell jämförelse

Använd `tmp-maps/fetch-tiles.sh` för att hämta samma 12 referens-URL:er
från prod (before) och lokal tileserver (after):

```bash
cd tmp-maps
BASE_URL=https://kartbilder.brottsplatskartan.se OUTDIR=before ./fetch-tiles.sh
BASE_URL=http://127.0.0.1:8351 OUTDIR=after ./fetch-tiles.sh
open compare.html
```

Gå igenom: inga renderingsfel, nya områden syns bättre, inga dramatiska
regressioner i färg/typografi.

Om zoom-15-bilder ser suddiga ut — kör om med `--maxzoom=15` (ska redan
stå i steg 2).

### 5. Ladda upp till Hetzner Object Storage

```bash
rclone copy tmp-maps/data/sweden-${DATESTAMP}.mbtiles \
  "Brottsplatskartan Hetzner:brottsplatskartan/tiles/" \
  --progress --stats=10s
```

Verifiera:
```bash
rclone lsl "Brottsplatskartan Hetzner:brottsplatskartan/tiles/"
curl -sI "https://brottsplatskartan.hel1.your-objectstorage.com/tiles/sweden-${DATESTAMP}.mbtiles"
```

Status 200 + rätt `content-length` → publikt nåbar.

### 6. Uppdatera `deploy/download-tiles.sh`

Byt `TILES_FILE` och `TILES_URL` till nya filnamnet. Filen uppdaterar
även rensning så att gamla `.mbtiles` tas bort från
`deploy/tileserver/` när ny har laddats ner.

### 7. Deploy

```bash
git add deploy/download-tiles.sh
git commit -m "Uppdatera mbtiles till ${DATESTAMP} (Planetiler, z0-15)"
git push origin main
```

`deploy.sh` ser diff:en på `download-tiles.sh`, kör `download-tiles.sh`,
startar om tileserver + rensar response-cache. GHA-action triggar
automatiskt.

### 8. Verifiera prod

```bash
curl -sI "https://kartbilder.brottsplatskartan.se/styles/basic-preview/static/18.068,59.330,13/640x340.jpg" | head -5
```

- `X-Cache-Status: MISS` första gången (nginx-tiles-cachen är tom för
  nya filen)
- Öppna några event-sidor i incognito → bekräfta att kartbilder ser
  uppdaterade ut

### 9. Städa

Efter ~1 vecka i prod utan problem:

```bash
# Ta bort gammal fil från Object Storage
rclone delete "Brottsplatskartan Hetzner:brottsplatskartan/tiles/2017-07-03_europe_sweden.mbtiles"

# Ta bort lokala backup
rm deploy/tileserver/old.mbtiles.bak
rm -rf tmp-maps/data/sources tmp-maps/data/tmp
# (tmp-maps/ är gitignored — får ligga eller tas bort helt)
```

## Rollback

Om något är fel efter deploy:

```bash
# Revert commit
git revert <commit-sha>
git push origin main
```

`download-tiles.sh` pekar då tillbaka på gamla URL:en. Deploy.sh hämtar
gamla filen igen (om den fortfarande finns i bucketen) och startar om
tileserver. Rensning i download-tiles.sh tar bort nya filen.

Om gamla filen redan raderats från Object Storage: ladda upp den från
backup (om sparad lokalt) eller regenerera från en äldre PBF.

## Felsökning

**OOM (exit 137) i Planetiler via Docker**
Docker Desktop default (7.75 GB) räcker inte. Antingen öka till 12+ GB
i Settings → Resources, eller kör Java native på host (rekommenderat).

**`data/sources/lake_centerline.shp.zip does not exist`**
Kör med `--download` första gången, eller verifiera att cwd matchar
så att relativ sökväg `data/sources/` är korrekt.

**Tileserver serverar gammal data efter deploy**
Kör manuellt på servern:
```bash
docker compose exec tileserver ls /data
docker compose restart tileserver
```
Om gamla `.mbtiles` ligger kvar: download-tiles.sh ska rensa automatiskt.
Verifiera `find deploy/tileserver -name '*.mbtiles'` och radera manuellt
om nödvändigt.

**Attribution-uppdatering?**
Planetiler sätter attribution till "© OpenMapTiles © OpenStreetMap
contributors" i mbtiles-metadata. Nuvarande frontend-attribution i
`resources/views/page.blade.php` matchar redan.

## Referenser

- Planetiler: https://github.com/onthegomap/planetiler
- Geofabrik extracts: https://download.geofabrik.de/europe/
- OpenMapTiles schema: https://openmaptiles.org/schema/
- tileserver-gl docs: https://tileserver.readthedocs.io/
