---
name: prod-health
description: "Kolla produktionsserverns hälsa — CPU, minne, disk, Docker-containrar och Redis — via SSH till Hetzner. Använd när användaren frågar saker som 'hur mår prod', 'kolla cpu på prod', 'redis health', 'är allt OK på servern', 'docker stats prod'. Subkommandon: (tomt)/all, cpu, mem, disk, docker, redis."
---

# /prod-health — produktionsserver-hälsa

Snabb hälsokoll mot prod (Hetzner CX33, `deploy@brottsplatskartan.se`,
kod i `/opt/brottsplatskartan/`). Allt är read-only — inga ändringar.

## Tolka argumentet

Argumentet efter `/prod-health` (ord 1) avgör vad som körs:

| Argument                | Visar                                                          |
| ----------------------- | -------------------------------------------------------------- |
| (tomt) / `all`          | Sammanfattning av allt nedan                                   |
| `cpu`                   | Load + topprocesser                                            |
| `mem` / `memory`        | RAM-användning + swap                                          |
| `disk`                  | Diskutrymme                                                    |
| `docker` / `containers` | `docker compose ps` + `docker stats --no-stream`               |
| `redis`                 | `php artisan redis:health` (rikare output, tabell + varningar) |

## Kommandon

Alla körs över SSH. Alla är read-only. Om en permission-prompt dyker upp,
godkänn — eller (bättre) lägg permanent regel i `.claude/settings.local.json`.

### cpu

```bash
ssh deploy@brottsplatskartan.se "uptime && echo --- && top -bn1 | head -15"
```

Tolka: load average över 4 (CX33 har 4 vCPU) = ihållande mättning.
%Cpu(s)-raden visar fördelning user/sys/idle/wa. Hög `wa` = disk-I/O-bunden.

### mem

```bash
ssh deploy@brottsplatskartan.se "free -h"
```

Tolka: `available` är viktigare än `free` (Linux räknar buff/cache som
återanvändbart). Swap > 0 på en server utan swap-fil betyder fel-konfig;
servern har idag 0 MB swap, så swap-raden ska vara helt 0.

### disk

```bash
ssh deploy@brottsplatskartan.se "df -h / && echo --- && du -sh /opt/brottsplatskartan 2>/dev/null"
```

Tolka: `/` på 80 GB SSD. Över 80 % är värt att flagga (tile-data + Docker
images kan svälla). `/opt/brottsplatskartan` visar projektets storlek.

### docker

```bash
ssh deploy@brottsplatskartan.se "cd /opt/brottsplatskartan && docker compose ps && echo --- && docker stats --no-stream"
```

Tolka:

- Alla containrar ska vara `running` / `Up`. Saknas en eller står på
  `Exited`/`Restarting` → kolla logs för den.
- `docker stats` visar CPU%, MEM USAGE/LIMIT per container.
- Förväntad RAM-fördelning grovt (CX33 har 8 GB total):
    - `redis` ~ 0.2–1 GB (cap 3 GB)
    - `mariadb` ~ 0.3 GB
    - `app` ~ 0.1–0.3 GB per php-fpm-worker
    - `caddy`, `scheduler`, `tileserver` små

### redis

```bash
ssh deploy@brottsplatskartan.se "cd /opt/brottsplatskartan && docker compose exec -T app php artisan redis:health"
```

Tolka utdata:

- **Använt nu / Maxmemory** — under 80 % är OK.
- **Peak / max** — peak nära max betyder att eviction kan börja vid trafiktoppar.
- **Evicted keys** — > 0 betyder Redis har börjat slänga data; ofta ett
  tecken på att maxmemory är för litet eller TTL för långa.
- **Hit rate** — > 90 % bra. Sjunker det efter en `cache:clear` är det
  normalt en stund.
- **Fragmentation ratio** — 1.0–1.5 normalt. > 1.5 kan indikera
  minnesfragmentering; > 5 är illa.
- **Uptime** — om mycket lägre än serverns systemuptime betyder det att
  Redis-containern startat om. Värt att fråga varför (`docker compose logs redis`).

## all / (tomt)

Kör allt i **en enda SSH-anslutning** för att minimera handshake-overhead.
Tunga jobb (`docker stats`, `redis-cli INFO`, `du -sh`) startas i
bakgrunden direkt så de överlappar med de snabba sekventiella anropen.
`redis-cli` använder `REDISCLI_AUTH` (env-var i redis-containern, satt i
`compose.yaml`) — inget lösenord behövs i shell-anropet.

```bash
ssh deploy@brottsplatskartan.se '
  cd /opt/brottsplatskartan

  # Starta tunga jobb i bakgrund så de överlappar med de snabba nedan.
  # docker stats sätter golvet (~2 s, inneboende sampling).
  docker stats --no-stream > /tmp/_bpk_stats.txt &
  docker exec -i brottsplatskartan-redis-1 redis-cli INFO memory stats server > /tmp/_bpk_redis.txt &
  du -sh /opt/brottsplatskartan > /tmp/_bpk_du.txt 2>/dev/null &

  uptime
  echo === MEM ===
  free -h
  echo === DISK ===
  df -h /
  echo === DOCKER PS ===
  docker ps -a --filter label=com.docker.compose.project=brottsplatskartan --format "table {{.Names}}\t{{.Status}}"
  echo === CACHE-FLUSH ===
  cat storage/app/cache-meta/last-cache-flush 2>/dev/null; echo
  cat storage/app/cache-meta/last-responsecache-flush 2>/dev/null; echo

  wait
  echo === PROJECT SIZE ===
  cat /tmp/_bpk_du.txt
  echo === DOCKER STATS ===
  cat /tmp/_bpk_stats.txt
  echo === REDIS INFO ===
  cat /tmp/_bpk_redis.txt
  rm -f /tmp/_bpk_stats.txt /tmp/_bpk_redis.txt /tmp/_bpk_du.txt
'
```

Varför detta mönster:

- **En SSH-handshake** istället för flera separata anslutningar.
- **Bakgrundsjobben startas först** så `docker stats` (~2 s-golvet)
  överlappar med uptime/free/df/cache-flush-läsningarna istället för
  att dyka upp efteråt. Total tid ≈ 2 s (begränsat av docker stats).
- **`docker exec` istället för `docker compose exec`** för redis-anropet
  — slipper compose-yaml-parsen (~300 ms).
- **Cache-flush läses direkt från host** — `storage/` är bind-monterad
  (`./:/var/www/html` i compose.yaml), så ingen `docker exec` behövs
  (~500 ms).
- **`docker ps --filter` istället för `docker compose ps`** — samma
  resonemang, slipper compose-parsen.
- **Inget Laravel-boot** — rå `redis-cli` istället för `php artisan
redis:health`. Skillen tolkar `INFO`-output själv (se nedan).
- **`SSH ControlMaster`** (i `~/.ssh/config`) sparar handshake vid
  upprepade körningar inom en timme.

### Tolka rå `INFO`-output i `all`

`INFO memory stats server` returnerar `key:value`-rader. Plocka:

| Nyckel                    | Vad det är          |
| ------------------------- | ------------------- |
| `used_memory`             | Använt nu (bytes)   |
| `used_memory_peak`        | Peak (bytes)        |
| `maxmemory`               | Tak (bytes)         |
| `mem_fragmentation_ratio` | Fragmentation       |
| `evicted_keys`            | Evicted sedan start |
| `keyspace_hits`           | Cache-träffar       |
| `keyspace_misses`         | Cache-missar        |
| `uptime_in_seconds`       | Redis-uptime        |
| `redis_version`           | Version             |

Räkna:

- **Hit rate:** `keyspace_hits / (keyspace_hits + keyspace_misses) * 100`
- **Peak / max:** `used_memory_peak / maxmemory * 100`

Trösklar (samma som `php artisan redis:health` använder):

- Peak/max > 80 % → överväg höja taket
- `evicted_keys` > 0 → cache slits ut, taket för litet eller TTL för långa
- `mem_fragmentation_ratio` > 1.5 (när `used_memory` > 100 MB) → fragmentering
- `mem_fragmentation_ratio` < 0.95 (när `used_memory` > 100 MB) → möjlig swap
- Redis-uptime ≪ system-uptime → containern har startat om nyligen

`CACHE-FLUSH`-blocket levererar två ISO8601-strängar (eller tomma rader
om aldrig flushat). Visa relativ tid ("för 1 timme sedan") i sammanfattningen.

Sammanfatta i slutet: "Allt OK" eller lista avvikelser (load > 4,
mem available < 1 GB, disk > 80 %, container nere, Redis evictions, etc.).

För `all` räcker det med en kondenserad version per sektion — inte hela
top-output. Visa det viktigaste:

- CPU: load + de 3 högsta processerna
- Mem: total/used/available + swap
- Disk: `/` raden
- Docker: bara containrar som **inte** är `Up`/`running` (om alla är OK, säg det)
- Redis: minne använt / max + evictions + hit rate

## Konventioner

- **Aldrig modifiera** något på servern via denna skill. Inga
  `cache:clear`, `restart`, eller liknande. För skrivande operationer:
  använd `deploy/`-skripten och låt användaren köra.
- **Säg vad du gjorde** — visa kommandot du körde innan/under output, så
  användaren kan reproducera manuellt.
- **Tolka, sammanfatta inte bara dumpa** — du har sett hela output, ge
  användaren slutsatsen ("RAM mår bra, 60 % ledigt") inte bara siffrorna.
- **Avvikelser = flagga** — om något ser ovanligt ut (load 6 på 4 vCPU,
  swap > 0, container Restarting, evictions > 0) säg det tydligt.

## Edge cases

- **SSH timeout** — säg det och fråga om servern är nere; rekommendera
  Hetzner Cloud Console.
- **Permission denied av sandbox** — be användaren godkänna eller lägga
  in permission-regel.
- **Container nere** — visa `docker compose logs --tail 50 <namn>` som
  nästa steg, men kör det inte automatiskt.
