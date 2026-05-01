**Status:** avfärdad 2026-05-01 — ingen konkret driver. #55 (det enda som listades som "kräver edge-cache") använder Alt B (301-redirect), inte proxy, och cachas tillräckligt av browser-`immutable` + Spatie Response Cache. Caddy-ombyggnad skulle lägga RAM-pressure på CX33 (Redis äter redan 3 GB av 8) och introducera cold-cache vid varje deploy. nginx-tiles funkar. Plockas tillbaka om/när en endpoint faktiskt behöver edge-cache och det är värt RAM-/deploy-kostnaden.
**Senast uppdaterad:** 2026-05-01

# Todo #61 — Bygg om Caddy med cache-handler, ersätt nginx-tiles

## Sammanfattning

Bygg en egen Caddy-image med
[`caddy-cache-handler`](https://github.com/caddyserver/cache-handler)
(Souin) inbakat. Aktivera in-memory-cache i Caddyfile och flytta
tile-cache från `nginx-tiles`-sidecar till Caddy direkt. En process
mindre, en config-syntax mindre, delad cache-pool mellan tiles och
framtida endpoints (#55 `/k/*`, ev. `/api/eventsMap`, sitemaps).

In-memory (Souin-default) räcker — CX33 har gott om RAM, tiles är små,
cold cache vid restart är harmless eftersom tileserver-gl är lokal och
mbtiles redan ligger på disk. On-disk-backend (badger/redis) kan läggas
till senare om mätning visar behov. YAGNI tills dess.

## Bakgrund

Nuvarande stack:

- `caddy:2-alpine` — reverse-proxy + Let's Encrypt. Ingen HTTP-cache.
- `nginx-tiles` (sidecar) — `proxy_cache` med 2 GB on-disk LRU för
  tileserver-gl-svar. Driftad via [todo #15](done/15-tiles-cache-caddy.md).

Caddy core saknar cache. För att kunna cacha andra endpoints (#55) måste
vi antingen lägga till ännu en sidecar, lita på Spatie Response Cache
(bootar Laravel per request), eller bygga om Caddy med cache-handler.
Det sista är minst topologi.

## Förslag

Ett commit som bygger om Caddy och plockar bort `nginx-tiles` i samma
deploy. Diffen är liten nog (~30 rader) att läsbarheten inte vinner på
separation, och båda ändringar deployas ändå i samma `deploy.sh`-körning.

### `deploy/Dockerfile.caddy`

```dockerfile
FROM caddy:2-builder-alpine AS builder
RUN xcaddy build \
    --with github.com/caddyserver/cache-handler

FROM caddy:2-alpine
COPY --from=builder /usr/bin/caddy /usr/bin/caddy
```

### `compose.yaml`

```yaml
caddy:
    build:
        context: .
        dockerfile: deploy/Dockerfile.caddy
    # ports/volumes/Caddyfile-mount oförändrade
    depends_on: [app, tileserver] # nginx-tiles bortplockad


# Bort: nginx-tiles-tjänsten + nginx-tiles-cache-volymen
```

### `deploy/Caddyfile`

```caddy
{
    cache {
        ttl 168h
        stale 1h
    }
}

brottsplatskartan.se {
    # ... befintlig config oförändrad
    reverse_proxy app:8080
}

kartbilder.brottsplatskartan.se {
    encode gzip zstd
    cache { ttl 168h }
    header Cache-Control "public, max-age=604800, immutable"
    reverse_proxy tileserver:8080   # direkt, inte via nginx-tiles
}
```

Souin-defaults motsvarar nginx-tiles-beteendet (single-flight,
background-update, RFC 9211 `Cache-Status`). Se
[cache-handler README](https://github.com/caddyserver/cache-handler)
för parameter-detaljer.

## Migration

1. Lokal smoke-test: `docker compose build caddy && docker compose up -d`,
   curl mot `kartbilder.brottsplatskartan.test:8350/...` — kolla
   `Cache-Status: hit` på andra request.
2. Verifiera att co-hostade sajter (`/etc/caddy/sites.d/*.caddy`)
   fortfarande funkar.
3. Push commit → `deploy.sh` bygger och deployar på servern.
4. 24h soak: kontrollera `Cache-Status: hit`-rate, RAM-användning på
   caddy-containern, tileserver-load.
5. När ok → #55 (kortare kartbild-URLs) fri att starta.

## Risker

- **Trasig build/binär.** `set -e` i `deploy.sh` avbryter innan swap så
  gamla Caddy fortsätter köra. Lokal smoke-test innan push fångar
  resten.
- **Cold cache vid cutover.** `nginx-tiles-cache`-volymen tappas →
  första timmarna: alla tile-requests cold tills cachen fyllts. Inte
  farligt (mbtiles är lokala) men gör cutoveren utanför trafiktoppar.
  Samma sak gäller vid framtida Caddy-restarts eftersom in-memory inte
  överlever container-restart.

## Vinster

- En process mindre (`nginx-tiles`: ~30 MB RAM + en till container att
  uppdatera).
- En config-syntax (Caddyfile) istället för Caddyfile + nginx.conf.
- Delad cache-pool mellan tiles och framtida endpoints (#55, ev.
  `/api/eventsMap`, sitemaps).
- Konsekvent `Cache-Status`-header över hela stacken (RFC 9211).
- Frigör #55 till en ren feature-todo utan infra-blocking.

## Confidence

**Hög.** cache-handler är beprövad (Souin är de-facto Caddy-cache och
underhållen sedan 2020). Liten diff, lokal smoke-test, `set -e`-skydd.

## Beroenden

- Bygger på #15 (nginx-tiles existerar idag).
- **Blockerar:** #55 (kortare kartbild-URLs) — `/k/*`-routen vill ha
  cache-handler igång först. _(24h soak räcker för att frigöra #55.)_

## Nästa steg

1. Skriv `deploy/Dockerfile.caddy`.
2. Uppdatera `compose.yaml` (build + ta bort nginx-tiles).
3. Uppdatera `deploy/Caddyfile` (cache-block + direktroute till
   tileserver).
4. Lokal smoke-test (build, modul-check, `Cache-Status: hit` på
   tile-request).
5. Push, 24h soak.
