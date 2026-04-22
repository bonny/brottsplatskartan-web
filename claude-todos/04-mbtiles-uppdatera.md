# Todo #4 — Uppdatera mbtiles från 2017

## Sammanfattning

Nuvarande kartdata kommer från en färdig mbtiles-fil från **openmaptiles.com**
(datumstämpel 2017-07-03) som används av `maptiler/tileserver-gl` för att generera
statiska kartbilder åt händelsesidor. OpenMapTiles tar idag ~500 USD/år för
prenumeration på färdiga extracts. För Sverige räcker det långt att generera
en egen mbtiles från Geofabrik-extract med **Planetiler** — det tar ~5–15 min
och resultatet är schema-kompatibelt med `basic-preview`-stilen som
tileserver-gl levererar out-of-the-box. Ingen kodändring i Laravel krävs;
enda filen som uppdateras är själva `.mbtiles` och URL:en i
`deploy/download-tiles.sh`.

## Nuläge — hur det fungerar idag

### Filen
- `deploy/tileserver/2017-07-03_europe_sweden.mbtiles` (~1.21 GB)
- Gitignored (`/deploy/tileserver/*.mbtiles`)
- Ligger i Hetzner Object Storage:
  `https://brottsplatskartan.hel1.your-objectstorage.com/tiles/2017-07-03_europe_sweden.mbtiles`
- Laddas ner via `deploy/download-tiles.sh` (idempotent)

### Ursprung (från `../brottsplatskartan-tileserver/readme.md`)
- Filen köptes/laddades ned färdig från **openmaptiles.com**
  (`downloads/tileset/osm/europe/sweden/`)
- Ingen egen pipeline finns — det fanns alltså aldrig något build-script att återanvända
- Tidigare körde tileservern på Dokku, nu på Docker Compose på Hetzner

### Container
```yaml
# compose.yaml
tileserver:
  image: maptiler/tileserver-gl:v4.4.4
  command: ["-p", "8080", "--verbose"]
  volumes: [./deploy/tileserver:/data:ro]
```

Tileserver-gl auto-detekterar `.mbtiles` i `/data` och serverar dem med
**default-konfig**. Ingen `config.json` eller custom style finns i repot.
Default-stilen heter `basic-preview` och använder **OpenMapTiles vector
schema** (layers: `water`, `landuse`, `transportation`, `place`, `poi` osv.).

### Hur sajten använder tileservern
`app/CrimeEvent.php` genererar URL:er mot **static image-endpoint**:
```
https://kartbilder.brottsplatskartan.se/styles/basic-preview/static/auto/{w}x{h}.jpg?path=...
https://kartbilder.brottsplatskartan.se/styles/basic-preview/static/{lng},{lat},{z}/{w}x{h}.jpg?path=...
```

Ingen Leaflet-integration mot vår tileserver — Leaflet på fronten använder
externa OSM-tiles (`resources/views/page.blade.php` bekräftar attribution
"Kartbilderna kommer från OpenMapTiles"). Vår tileserver renderar bara
**statiska JPG:er** som embeddas på event-kort.

### Konsekvens för kompatibilitet
Eftersom vi bara använder **default-stilen** (`basic-preview`) och den
stilen är inbyggd i tileserver-gl-imagen, måste en ny mbtiles-fil följa
**OpenMapTiles vector tile schema**. Alla nämnda pipeline-verktyg
(Planetiler default profile, tilemaker med openmaptiles-config, tippecanoe
med eget schema) kan producera det, men bara Planetiler och tilemaker
gör det "out of the box".

## Pipeline-alternativ

| Verktyg | Schema | Tid (Sverige) | RAM | Disk ut | Kommentar |
|---|---|---|---|---|---|
| **Planetiler** | OpenMapTiles (native) | ~5–10 min | 4–8 GB | ~1.2–1.8 GB | Rekommenderas. Snabbast, Java-jar, enkel CLI |
| **Tilemaker** | OpenMapTiles via config | ~30–60 min | 8–16 GB | ~1.2–1.8 GB | C++, äldre alternativ, mer konfigurerbar |
| **Tippecanoe** | Eget/custom (GeoJSON in) | långt och krångligt | — | — | Fel verktyg här — gjort för egen data, inte OSM-vägar |

### Planetiler (rekommenderat)
- https://github.com/onthegomap/planetiler
- Jar-fil, Java 21+, Docker-image tillgänglig
- Default-profilen `openmaptiles` genererar exakt det schema som
  `basic-preview` förväntar sig
- Sverige (~1.5 GB pbf) körs lokalt på min-sekundnivå
- Producerar `.mbtiles` direkt

### Tilemaker
- https://github.com/systemed/tilemaker
- Mer etablerat i OSM-communityt men långsammare och mer manuell konfig
- Kräver nedladdning av `process.lua` + `config.json` för openmaptiles-schema
- Passar bättre om man vill göra anpassningar i schemat

### Tippecanoe
- Inte relevant här — bygger tiles från egen GeoJSON, inte från OSM-strukturer.
  Skulle kräva att man först konverterar OSM→GeoJSON och sedan bygger eget
  schema + style. Kasta tid.

## Konkret plan

### 1. Generera ny mbtiles lokalt med Planetiler
```bash
# Ladda ner senaste Sverige-extract (~1.5 GB)
curl -L -o /tmp/sweden-latest.osm.pbf \
  https://download.geofabrik.de/europe/sweden-latest.osm.pbf

# Kör Planetiler via Docker (ingen Java-installation krävs)
docker run --rm -v /tmp:/data \
  ghcr.io/onthegomap/planetiler:latest \
  --osm-path=/data/sweden-latest.osm.pbf \
  --output=/data/sweden-latest.mbtiles \
  --force

# Resultat: /tmp/sweden-latest.mbtiles (~1.2–1.8 GB)
```

### 2. Testa lokalt mot tileserver-gl
```bash
# Kopiera in till deploy/tileserver/ och döp om den gamla tillfälligt
cp /tmp/sweden-latest.mbtiles deploy/tileserver/$(date +%Y-%m-%d)_sweden.mbtiles
mv deploy/tileserver/2017-07-03_europe_sweden.mbtiles \
   deploy/tileserver/2017-07-03_europe_sweden.mbtiles.bak

docker compose up -d tileserver
open http://kartbilder.brottsplatskartan.test:8351

# Testa en faktisk event-URL lokalt
curl -I "http://kartbilder.brottsplatskartan.test:8351/styles/basic-preview/static/18.068,59.330,12/640x340.jpg"
```

Verifiera visuellt:
- `/styles/basic-preview/` laddar utan 500/404
- Static image-endpoints returnerar kartbild (inte bara havsblått)
- Zoomnivåer 5–15 fungerar (nivåerna vi använder i `CrimeEvent.php`)

### 3. Ladda upp till Hetzner Object Storage
```bash
# Med rclone (förutsätter hetzner-config i ~/.config/rclone/rclone.conf)
rclone copy deploy/tileserver/2026-04-21_sweden.mbtiles \
  hetzner-s3:brottsplatskartan/tiles/

# Eller aws-cli (S3-kompatibelt endpoint)
aws s3 cp deploy/tileserver/2026-04-21_sweden.mbtiles \
  s3://brottsplatskartan/tiles/ \
  --endpoint-url https://hel1.your-objectstorage.com
```

### 4. Uppdatera download-script
```bash
# deploy/download-tiles.sh
TILES_FILE="$TILES_DIR/2026-04-21_sweden.mbtiles"
TILES_URL="https://brottsplatskartan.hel1.your-objectstorage.com/tiles/2026-04-21_sweden.mbtiles"
```

### 5. Deploy till produktion
```bash
ssh deploy@brottsplatskartan.se
cd /opt/brottsplatskartan
git pull
./deploy/download-tiles.sh               # laddar ner nya
rm deploy/tileserver/2017-07-03_*.mbtiles  # ta bort gamla
docker compose restart tileserver
docker compose exec app php artisan responsecache:clear  # event-kort har cachat URLer
```

Gamla filen i Object Storage kan ligga kvar tills man är säker på den nya.

## Risker

| Risk | Sannolikhet | Mitigering |
|---|---|---|
| Default `basic-preview`-stilen matchar inte nya mbtiles schema | Låg | Planetiler openmaptiles-profil är schema-kompatibel; testa lokalt |
| Kartbilder ser visuellt annorlunda ut (färger, teckensnitt) | Medel | Stilen är samma — datan är nyare. Jämförbilder sida vid sida före deploy |
| Filstorleken blir betydligt större → långsam nedladdning vid provision | Låg | Planetiler producerar liknande storlek för Sverige. Kontrollera innan upload |
| Response cache har hårdkodade URL:er mot kartbilder | Ingen | URL:erna är dynamiska (`getStaticImageSrc()`) och har samma path-format |
| Downtime vid byte i prod | Mycket låg | `docker compose restart tileserver` tar ~2 sek. Event-kort har egen Caddy-cache, så ev. fallbacks syns nästan inte |
| Dataförlust om upload misslyckas | Låg | Behåll gamla filen i bucket tills nya är verifierad |

## Fördelar

- **Aktuella vägar** — 9 års nya vägar, cykelbanor, kvartersnamn
- **Nya adresser och bebyggelseområden** — t.ex. Hagastaden, Barkarbystaden,
  nya Slussen i Stockholm m.fl. som inte finns i 2017-datan
- **Fria från openmaptiles.com-prenumeration** (de tar idag betalt för
  färdiga extracts — att generera själv är helt gratis)
- **Reproducerbar pipeline** — kan köras igen när som helst
- **Möjlighet till återkommande uppdateringar** — t.ex. årligen via
  GitHub Action om man vill

## Att validera före start (från review 2026-04-22)

1. **OpenMapTiles-kostnaden** ($500/år) — hela motivationen vilar på att det
   faktiskt kostar. Dubbelkolla aktuell prisstruktur innan arbete påbörjas.
   Om det fortfarande finns gratis-alternativ från OpenMapTiles försvinner
   en stor del av nyttan.

2. **Protomaps daily builds** som alternativ
   (https://maps.protomaps.com/builds) — färdiga globala PMTiles, gratis CDN.
   Kräver serverbyte från `tileserver-gl` till PMTiles-kompatibel server,
   så inte "enklast" givet nuvarande setup, men värt att nämna.

3. **Attribution** — efter byte från openmaptiles.com-data till egen
   Planetiler-build måste `layouts/web.blade.php` / `page.blade.php` uppdateras
   till "© OpenStreetMap contributors" (ODbL-krav).

4. **maxzoom-beslut låst** — z=15 behövs (`CrimeEvent.php` använder zoom 5–15
   för statiska bilder). Inte något att öppna med `--maxzoom=14`.

5. **Jämförelse-script** — konkret diff-metod: hämta samma 10 event-URL:er
   från gammal (backup på port) vs ny tileserver, spara sida vid sida i
   `/tmp/tiles-compare/` och granska visuellt. Bygg script innan deploy.

6. **Tidsuppskattning på CX33** — om build körs på servern istället för
   lokalt: räkna med 15–30 min snarare än 5–10 (endast 8 GB RAM).

## Öppna frågor

1. **Tyskland då?** Gamla tileserver-repot refererar även till tyska OSM-tiles
   (`openmaptiles.com/.../germany/`). Används tysk data någonstans idag?
   Grep mot `germany|tyskland|deutschland` i koden säger nej — kan ignoreras.

2. **Ska nya filen heta `sweden-latest.mbtiles` (alltid senaste) eller
   datumstämplas?** Datumstämpling gör rollback enkel men kräver manuell
   URL-uppdatering. `sweden-latest.mbtiles` är enklare men otydligt vilken
   version som körs. **Förslag:** datumstämpel, matchar nuvarande konvention.

3. **Automatisera via GitHub Action?** Planetiler tar ~10 min på standard
   GH-runner. Kan köras kvartalsvis och pusha direkt till Object Storage.
   Låg prio — nyttan av månatliga uppdateringar är liten.

4. **Egen stil senare?** Default `basic-preview` är funktionell men ganska
   spartansk. Om man vill ha egen färgsättning/typografi kan en anpassad
   `style.json` läggas i `deploy/tileserver/styles/` och refereras via
   `config.json`. Utanför denna todos scope.

5. **Storlek på ny fil?** Om Planetiler-output för Sverige blir mycket
   större än 1.2 GB (t.ex. 3+ GB) bör man överväga Z-level-trimning
   (`--maxzoom=14` räcker för våra behov, default är 15).

## Status / nästa steg

- [ ] **Verifiera OpenMapTiles-kostnaden först** — annars ifrågasätts nyttan
- [ ] Bestäm attributionstext och plats för uppdatering
- [ ] Skriv jämförelse-script (10 event-URL:er, sida vid sida)
- [ ] Testa Planetiler lokalt på senaste Sverige-PBF → generera mbtiles
- [ ] Spinna upp lokal tileserver med nya filen → jämför kartbilder med produktion
- [ ] Stämma av att `basic-preview`-stilen renderar korrekt på zoom 5–15
- [ ] Ladda upp till Hetzner Object Storage under nytt datum-prefix
- [ ] Uppdatera `deploy/download-tiles.sh` (TILES_FILE + TILES_URL)
- [ ] Deploy, verifiera, rensa responsecache
- [ ] Ta bort gamla filen från Object Storage efter 1–2 veckors drift
- [ ] Dokumentera pipeline i `deploy/update-tiles.md` (eller direkt i `AGENTS.md`)

**Prio:** Låg. Gamla tiles fungerar, men uppdatering ger mätbar UX-vinst
för nya områden. Lämplig när större deploy-arbete annars är tyst.
