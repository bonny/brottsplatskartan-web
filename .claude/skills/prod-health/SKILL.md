---
name: prod-health
description: "Kolla produktionsserverns hälsa — CPU, minne, disk, Docker-containrar och Redis — via SSH till Hetzner. Använd när användaren frågar saker som 'hur mår prod', 'kolla cpu på prod', 'redis health', 'är allt OK på servern', 'docker stats prod'. Subkommandon: (tomt)/all, cpu, mem, disk, docker, redis."
---

# /prod-health — produktionsserver-hälsa

Snabb hälsokoll mot prod (Hetzner CX33, `deploy@brottsplatskartan.se`,
kod i `/opt/brottsplatskartan/`). Allt är read-only — inga ändringar.

## Tolka argumentet

Argumentet efter `/prod-health` (ord 1) avgör vad som körs:

| Argument                | Visar                                            |
| ----------------------- | ------------------------------------------------ |
| (tomt) / `all`          | Sammanfattning av allt nedan                     |
| `cpu`                   | Load + topprocesser                              |
| `mem` / `memory`        | RAM-användning + swap                            |
| `disk`                  | Diskutrymme                                      |
| `docker` / `containers` | `docker compose ps` + `docker stats --no-stream` |
| `redis`                 | `php artisan redis:health`                       |

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
De två långsammaste kommandona (`docker stats` och `redis:health`) körs
parallellt via bakgrundsjobb och temporärfiler — som läses tillbaka efter `wait`.

```bash
ssh deploy@brottsplatskartan.se '
  uptime
  free -h
  df -h / && du -sh /opt/brottsplatskartan 2>/dev/null
  cd /opt/brottsplatskartan
  docker compose ps
  docker stats --no-stream > /tmp/_bpk_stats.txt &
  docker compose exec -T app php artisan redis:health > /tmp/_bpk_redis.txt &
  wait
  cat /tmp/_bpk_stats.txt
  cat /tmp/_bpk_redis.txt
  rm -f /tmp/_bpk_stats.txt /tmp/_bpk_redis.txt
'
```

Varför detta mönster:
- **En SSH-handshake** istället för fem separata anslutningar
- **`docker stats` + `redis:health` parallellt** — de tar ~2s respektive ~3s;
  parallellt = 3s totalt, sekventiellt = 5s totalt
- **Temporärfiler läses tillbaka** efter `wait` så inget output försvinner
- **`rm` i slutet** städar upp — servern påverkas inte permanent

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
