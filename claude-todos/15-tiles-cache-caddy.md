# Todo #15 — Server-side cache för kartbilder (tiles)

## Sammanfattning

Efter flytten till Hetzner sätter Caddy bara `Cache-Control: public,
max-age=604800, immutable` på `kartbilder.brottsplatskartan.se` — det
cachar i browsern, men det finns ingen server-side cache. Tileservern
(`maptiler/tileserver-gl` v4.4.4) rasteriserar varje cache-miss via
MapLibre Native + headless GL, vilket är CPU-tungt och långsamt. Förut
var nginx hårt konfigurerat att cacha tiles på disk, den biten tappades
vid migrationen.

Målet: lägg tillbaka persistent server-side tile-cache så att första
besökaren per tile träffar varm cache istället för tileserver-gl.

## Nuläge

- `deploy/Caddyfile` rad 23–28: endast response-header, ingen server-cache
- `compose.yaml` rad 86–92: `tileserver-gl` kör default-config mot
  ~1.2 GB mbtiles (Sverige, 2017)
- Tileserver-gl saknar inbyggd persistent raster-cache och warmer —
  ingen förändring där sedan det senast verifierades
- Rapporterad upplevelse: tileservern är **långsam** på cache-miss

## Alternativ (research 2026-04-23)

### A. Varnish som sidecar ⭐ rekommenderat

- Officiell `varnish:7.6`-image, aktivt underhållen
- Topologi: `caddy → varnish:80 → tileserver:8080`
- 20 raders VCL: cacha 200 OK i 7 dagar, pass-through för resten
- Disk-backad cache (`-s file,/var/lib/varnish/cache,5g`) — inte
  crash-safe men irrelevant för tiles (återvärms av sig själv)
- **Ingen custom Caddy-build.** En extra service i compose.yaml och
  en liten VCL-fil

### B. caddy-cache-handler (Souin)

- `github.com/caddyserver/cache-handler` (wrappar Souin), aktivt 2025
- Backends: in-memory, Badger/NutsDB (disk), Redis
- Kräver **custom Caddy-image** via `xcaddy` (~8 rader Dockerfile)
- "Ren Caddy"-lösning utan sidecar, men större config-yta än Varnish
  och fler kantfall (Vary/ETag)

### C. nginx som reverse-proxy istället för Caddy

- Beprövad `proxy_cache` med LRU på disk
- **För invasivt** — vi vill inte byta ut Caddy för en subdomän

### D. tileserver-gl intern cache

- Saknas. Finns långtidsöppna issues, inget levererat
- Enda interna mitigering: serva vektortiles (`.pbf`) direkt istället
  för raster — kräver frontend-byte (Leaflet → MapLibre GL JS) och
  ligger utanför scope för den här todo:n

### E. Pre-rendera raster till disk

- `mbutil` / `tl copy` för att bake:a ut PNG/JPEG i förväg
- Inte en cache utan precomputation; stor diskåtgång för alla zoom-nivåer
- Kan övervägas som komplement men löser inte dynamisk last generellt

## Rekommendation (uppdaterad 2026-04-23 efter review + storleksberäkning)

### Historik

- **Första förslaget (Varnish-sidecar):** underkänt av review — overkill
  för skalan, `-s file` avrådes av upstream, VCL cachade 404/5xx i 7 d.
- **Andra förslaget (förgenerera alla zoom 6–14 till disk):** underkänt
  av storleksberäkning. Rimlig Sverige-BBOX (lat 55–69, lon 11–24) ger
  grovt:
  - z12 ≈ 40 k tiles ≈ 1 GB
  - z13 ≈ 160 k tiles ≈ 4 GB
  - z14 ≈ 640 k tiles ≈ 16 GB
  - z15 ≈ 2.5 M tiles ≈ 60+ GB

  Zoom 6–14 totalt ~20 GB, zoom 15 tillkommer rejält. För mycket på en
  CX33 med 80 GB som också kör DB/Redis/app.

### Aktuell rekommendation: nginx-sidecar med proxy_cache ⭐

Cache + LRU-eviction är rätt abstraktion: bara tiles som efterfrågas
hamnar på disk, gamla vräks ut automatiskt. Hett material (storstäder,
populära händelsesidor) stannar varmt; svansen får kalla misses
någon gång ibland.

1. Lägg till `nginx:alpine` som sidecar i `compose.yaml`
2. Config (ungefär 15 rader):
   ```nginx
   proxy_cache_path /var/cache/nginx/tiles
       levels=1:2 keys_zone=tiles:10m max_size=2g
       inactive=30d use_temp_path=off;

   server {
       listen 80;
       location / {
           proxy_pass http://tileserver:8080;
           proxy_cache tiles;
           proxy_cache_valid 200 7d;
           proxy_cache_valid 404 1m;
           proxy_cache_use_stale error timeout updating;
           add_header X-Cache-Status $upstream_cache_status;
       }
   }
   ```
3. Ändra `deploy/Caddyfile`: `kartbilder.brottsplatskartan.se` →
   `reverse_proxy nginx-tiles:80` istället för `tileserver:8080`
4. Named volume för cachen (persistent över container-restart)
5. Invalidering vid mbtiles-byte (todo #4):
   `docker compose exec nginx-tiles rm -rf /var/cache/nginx/tiles/* && docker compose exec nginx-tiles nginx -s reload`

**Fördelar:** bounded disk (`max_size=2g`), LRU eviction automatisk,
mogen disk-backend, trivial invalidering, `X-Cache-Status`-header för
debug.

### Valfritt komplement: förgenerera låga zoom (6–11)

Låga zoom-nivåer är små (totalt ~250 MB för zoom 6–11) och träffas ofta
(översiktskartor på start- och länsidor). Kan förgenereras en gång och
serveras direkt via Caddy `file_server` utanför nginx-cachen. Sparar
cache-plats för de dyra höga zoom-nivåerna.

Detta är optimering — börja utan och mät först.

### Varför inte Varnish (research 2026-04-23)

Avgörande punkt: **Varnish `-s file` är officiellt deprecated.**
Varnish-teamet avråder själva — fragmentering, dåligt LRU under
minnespress, risk för korruption. Persistent on-disk-cache i open
source Varnish kräver i praktiken betald Enterprise (Massive Storage
Engine).

Övriga skäl mot Varnish för det här use-caset:

- **Operativt:** VCL + `varnishlog`/`varnishstat`/`varnishadm` är
  overkill för GET-only binär trafik utan cookies
- **Restart:** malloc-cache förloras vid varje deploy → full
  re-warming från tileserver-gl
- **Prestanda:** Varnish vinner benchmarks på >50k req/s in-memory,
  men på CX33 bakom Caddy+TLS är skillnaden omätbar — nätverket /
  tileserver-gl är flaskhalsen långt innan sidecaren

**Scenarier där Varnish vore bättre (gäller inte oss):** komplex
invalidering med ban-tags, ESI, request-coalescing, grace-mönster,
>20k req/s per nod.

Pär har kört nginx `proxy_cache` för tiles tidigare med gott resultat
— inget nytt att lära, beprövat val.

### Acceptanskriterier

- [ ] Andra anropet för samma tile returnerar från cache (mät via
      `Age`-header eller svarstid < 50 ms)
- [ ] Tileserver-gl-containern ser drastiskt färre requests (verifiera
      via `docker compose logs tileserver`) — eller tas bort helt i
      alternativ A
- [ ] Overhead på Hetzner CX33: disk-åtgång <5 GB

### Acceptanskriterier

- [ ] Andra anropet för samma tile returnerar `Age: >0` (server-cache-hit)
- [ ] Tileserver-gl-containern ser drastiskt färre requests (verifiera
      via `docker compose logs tileserver`)
- [ ] Svarstider på cache-miss oförändrade, cache-hit <50 ms från server
- [ ] Overhead på Hetzner CX33: cache-volym <5 GB, Varnish RAM <512 MB

## Risker / öppna frågor

- Cache-invalidering när mbtiles uppdateras (todo #4): sätt en
  cache-key prefix som byts vid deploy, eller kör `varnishadm ban` i
  deploy-scriptet
- Varnish gör inte TLS — inget problem eftersom Caddy terminerar SSL
- Om Varnish-containern kraschar försvinner cachen (file-backend är
  inte crash-safe) — acceptabelt för tiles

## Koppling till andra todos

- **#4 (mbtiles-uppdatera):** vid byte av mbtiles måste cachen bustas
- Ingen annan direkt koppling
