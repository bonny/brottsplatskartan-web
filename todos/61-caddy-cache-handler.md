**Status:** aktiv (skissad — research klar, implementation saknas)
**Senast uppdaterad:** 2026-04-30

# Todo #61 — Bygg om Caddy med cache-handler, ersätt nginx-tiles

## Sammanfattning

Bygg en egen Caddy-image med
[`caddy-cache-handler`](https://github.com/caddyserver/cache-handler)
(Souin) inbakat och on-disk badger-backend. Flytta tile-cache från
`nginx-tiles`-sidecar till Caddy direkt. En process mindre, en
config-syntax mindre, delad cache-pool mellan tiles och framtida
endpoints (#55 `/k/*`, ev. `/api/eventsMap`, sitemaps).

## Bakgrund

Nuvarande stack:

- `caddy:2-alpine` — reverse-proxy + Let's Encrypt. Ingen HTTP-cache.
- `nginx-tiles` (sidecar) — `proxy_cache` med 2 GB on-disk LRU för
  tileserver-gl-svar. Driftad via [todo #15](done/15-tiles-cache-caddy.md).

Caddy core saknar cache. För att kunna cacha andra endpoints (#55) måste
vi antingen:

1. Lägga till en till sidecar (komplexare topologi)
2. Lita på Spatie Response Cache (bootar Laravel per request)
3. **Bygga om Caddy med cache-handler** (denna todo)

Tre processer som kan cacha (Caddy + nginx-tiles + Spatie) är värre än
en konsoliderad lösning.

## Förslag

### 1. Egen Caddy-image

`deploy/Dockerfile.caddy`:

```dockerfile
FROM caddy:2-builder-alpine AS builder
RUN xcaddy build \
    --with github.com/caddyserver/cache-handler

FROM caddy:2-alpine
COPY --from=builder /usr/bin/caddy /usr/bin/caddy
```

### 2. compose.yaml-ändringar

```yaml
caddy:
    build:
        context: .
        dockerfile: deploy/Dockerfile.caddy
    # ports, volumes som idag, plus:
    volumes:
        - caddy-cache:/var/cache/caddy
    depends_on: [app, tileserver] # nginx-tiles bortplockad

# Bort: nginx-tiles-tjänsten + nginx-tiles-cache-volymen

volumes:
    caddy-cache:
```

### 3. Caddyfile

```caddy
{
    cache {
        badger {
            configuration {
                Dir /var/cache/caddy/badger
                ValueDir /var/cache/caddy/badger
            }
        }
        ttl 168h        # 7d default (matchar nginx-tiles)
        stale 1h
    }
}

brottsplatskartan.se {
    # ... befintlig config
    reverse_proxy app:8080
}

kartbilder.brottsplatskartan.se {
    encode gzip zstd
    cache { ttl 168h }
    header Cache-Control "public, max-age=604800, immutable"
    reverse_proxy tileserver:8080   # direkt, inte via nginx-tiles
}
```

### 4. Migration

1. Bygg + testa imagen lokalt (`docker compose build caddy`).
2. Smoke-test: `curl -I https://kartbilder.brottsplatskartan.test:8350/...`
   → kolla `Cache-Status`-header.
3. Deploy till prod under låg-trafik-fönster.
4. Mätperiod 7d: tile-cache-hit-rate, RAM-användning, disk-tillväxt
   under `/var/cache/caddy/badger/`.
5. När soak ok → `nginx-tiles`-tjänsten + volym tas bort i en följdcommit.

## Mappning nginx-tiles → cache-handler

| nginx-tiles                                   | cache-handler                      |
| --------------------------------------------- | ---------------------------------- |
| `proxy_cache_path … max_size=2g inactive=30d` | `badger` med TTL + GC              |
| `proxy_cache_valid 200 7d`                    | `ttl 168h` per route               |
| `proxy_cache_valid 404 1m`                    | `default_cache_control max-age=60` |
| `proxy_cache_use_stale … updating`            | `stale 1h`                         |
| `proxy_cache_background_update on`            | Default i Souin                    |
| `proxy_cache_lock on` (single-flight)         | Default i Souin                    |
| `add_header X-Cache-Status`                   | `Cache-Status:` (RFC 9211)         |

## Risker

- **Build-fail i CI tappar reverse-proxyn.** Mitigation: testa imagen
  lokalt först, behåll fallback-tag (`caddy:2-alpine`) som rollback.
- **Tileserver-gl upstream Cache-Control.** Nginx ignorerar; Souin
  respekterar by default. Om tileserver svarar `no-cache` måste
  `cache.cdn.strategy hard` eller motsvarande sättas. Verifiera med
  `curl -I tileserver:8080/...` innan cutover.
- **Cold cache vid cutover.** `nginx-tiles-cache`-volymen tappas →
  första veckan: alla tile-requests cold → ökad tileserver-load tills
  cachen fyllts. Inte farligt (mbtiles är cheap att läsa) men gör
  cutoveren utanför trafiktoppar.
- **Badger har ingen hård size-cap.** Styrs via TTL + value-log GC. Mät
  `du -sh /var/cache/caddy/badger` första veckan. Om obegränsad tillväxt:
  lägg `Compaction.NumCompactors` eller kortare TTL.
- **Caddy-uppgrad blir mer arbete.** Egen build betyder att
  `caddy:2-alpine`-version-bumpar måste rekonsilieras med xcaddy.

## Vinster

- **En process mindre** (nginx-tiles: ~30 MB RAM, en till container att
  uppdatera/övervaka).
- **En config-syntax** (Caddyfile) istället för Caddyfile + nginx.conf.
- **Delad cache-pool** mellan tiles och framtida endpoints (#55, ev.
  `/api/eventsMap`, sitemaps).
- **Konsekvent `Cache-Status`-header** över hela stacken (RFC 9211).
- **Frigör #55** till en ren feature-todo utan infra-blocking.

## Confidence

**Medel-hög.** cache-handler är beprövad (Souin är de-facto Caddy-cache
och underhållen sedan 2020). Riskerna är hanterbara med stegvis cutover.
Den största risken är CI-build-pipelinen, lösbar med lokal test.

## Beroenden

- Bygger på #15 (nginx-tiles existerar idag).
- **Blockerar:** #55 (kortare kartbild-URLs) — `/k/*`-routen vill ha
  cache-handler igång först.

## Nästa steg

1. Skriv `deploy/Dockerfile.caddy` + uppdaterad `compose.yaml`.
2. Lokal test: bygg, smoke-test mot `brottsplatskartan.test:8350`.
3. Verifiera tileserver-gl Cache-Control-svar för att avgöra om
   `cache.cdn.strategy hard` behövs.
4. Deploy till prod med fallback-plan.
5. 7d soak — då är #55 fri att starta.
