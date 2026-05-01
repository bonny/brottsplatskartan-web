**Status:** sammanslagen 2026-05-01 — innehåll fört tillbaka in i #61 som "Commit 1". Egen todo motiverades initialt av "mindre blast radius per deploy", men efter review (subagent 2026-05-01) konstaterades att hela leveransen är ~6 rader (Dockerfile + compose-edit) och därför inte gör nytta som separat 7d-soak-cykel. #61 hanterar nu båda stegen som två commits i samma deploy.
**Senast uppdaterad:** 2026-05-01

# Todo #65 — Egen Caddy-image via xcaddy (build på servern)

## Sammanfattning

Bygg en egen Caddy-image **på Hetzner-servern** via `xcaddy` med
[`caddy-cache-handler`](https://github.com/caddyserver/cache-handler)
(Souin) inbakat. **Ingen funktionell ändring initialt** — modulen är
inkluderad men inte aktiverad i Caddyfile. Soakas i prod 7d innan #61
slår på cachen och ersätter `nginx-tiles`.

Bryts ut från #61 så att infrastrukturändringen (egen build) shippas
separat från cache-cutover. Mindre blast radius per deploy.

Bygger på servern istället för i CI eller lokalt: ingen registry-setup,
inga PATs, inga extra logins. Risken med server-build (att en trasig
binär swappar in) elimineras med ett `caddy validate`-pre-flight-steg
i `deploy.sh` som testar att binären startar och kan parsa configen
**innan** den nya containern tar över.

## Bakgrund

Caddy körs idag som `caddy:2-alpine` direkt från Docker Hub. För att
kunna lägga till moduler (cache-handler i #61, ev. andra framöver)
behöver vi en egen build via xcaddy.

Tre build-strategier övervägdes:

- **GitHub Actions → GHCR:** robustast men kräver workflow-underhåll.
- **Lokal build → push till registry:** kräver `docker login` på
  två maskiner + PAT-rotation.
- **Build på servern:** valt. Inget extra att underhålla.
  Pre-flight-validering täcker den största risken.

CX33 (4 vCPU / 8 GB) klarar xcaddy-build på ~30–60s. Build:en triggas
bara när `deploy/Dockerfile.caddy` ändras (Docker compose layer-cache).

## Förslag

### 1. `deploy/Dockerfile.caddy`

Pinnad både på Caddy-version och cache-handler-modul-version så
builds är reproducerbara. Konkreta versioner verifieras vid
implementation (senaste stabila vid build-tillfället).

```dockerfile
FROM caddy:2.8.4-builder-alpine AS builder
RUN xcaddy build v2.8.4 \
    --with github.com/caddyserver/cache-handler@v0.14.0

FROM caddy:2.8.4-alpine
COPY --from=builder /usr/bin/caddy /usr/bin/caddy
```

### 2. `compose.yaml`

Byt:

```yaml
caddy:
    image: caddy:2-alpine
```

mot:

```yaml
caddy:
    build:
        context: .
        dockerfile: deploy/Dockerfile.caddy
```

Övriga fält oförändrade.

### 3. `deploy/deploy.sh` — bygg + validera + health-check

**Före** `docker compose up -d`, lägg till:

```bash
# Egen Caddy-image: bygg explicit och pre-validera så vi inte swappar
# in en trasig binär. caddy validate exercis:ar module-loading +
# config-parsing utan att binda portar.
echo "→ docker compose build caddy"
$DC build caddy

echo "→ caddy validate (pre-flight)"
$DC run --rm --no-deps caddy \
    caddy validate --config /etc/caddy/Caddyfile --adapter caddyfile
```

`set -euo pipefail` i toppen av deploy.sh gör att build- eller
validate-fel avbryter deployen **innan** containern swappas. Gamla
Caddy fortsätter köra ostörd.

**Efter** `restart caddy`, lägg till:

```bash
echo "→ caddy health-check"
for i in 1 2 3 4 5; do
    if curl -fsS -o /dev/null -m 5 https://brottsplatskartan.se/; then
        echo "  caddy ok"
        break
    fi
    if [ "$i" = "5" ]; then
        echo "  caddy svarar inte — kör manuell rollback (git revert + deploy.sh)"
        exit 1
    fi
    sleep 2
done
```

(Roten används som health-endpoint — `/up` finns inte i denna Laravel-
installation, verifierat 2026-05-01: `/` → 200, `/up` → 404.)

## Migration

1. Skriv `deploy/Dockerfile.caddy` med pinnade versioner.
2. Uppdatera `compose.yaml` (`image:` → `build:`).
3. Lägg till build + validate + health-check i `deploy.sh`.
4. **Lokal smoke-test:** `docker compose build caddy`,
   `docker compose run --rm --no-deps caddy caddy validate ...`,
   `docker compose up -d caddy`, curl mot `brottsplatskartan.test:8350`.
5. Verifiera att `caddy list-modules | grep cache` visar
   `http.handlers.cache`.
6. Caddyfile oförändrad — ingen cache-funktionalitet aktiveras.
7. Commit + push → GHA SSH:ar in → deploy.sh bygger på servern.
8. 7d soak.
9. När ok → #61 aktiverar cache-handler i Caddyfile + plockar bort
   `nginx-tiles`.

## Risker

- **Build fail på servern.** `set -e` avbryter deploy. Gamla Caddy
  fortsätter köra. ✅ Inget kvar att laga.
- **Build OK men binären panic:ar vid start.** Fångas av
  `caddy validate` innan swap. Testar både att binären exekverar
  och att alla moduler laddas. ✅
- **Build OK + validate OK men runtime-fel under load.** Fångas av
  health-check efter restart. Vid fail: `git revert` på compose.yaml
  / Dockerfile + ny `deploy.sh`. Window är bounded (~10s) men inte
  noll. Acceptabel risk för engångsuppgraderingen.
- **Build-tid på CX33.** ~30–60s första gången, sen cachat så länge
  Dockerfile inte ändras. Lägger till motsvarande tid till deploys
  som rör Dockerfile.
- **Floating tag i Dockerfile:n.** Om `caddy:2.8.4-alpine` faktiskt
  byts ut (sällsynt — Docker Hub tar inte bort versioner) blir
  builds icke-deterministiska. Valfri fix: pinna till digest via
  `caddy:2.8.4-alpine@sha256:...`. Inte värt extra friktion än.
- **Disk för Docker-builder-cache.** Multi-stage builder-stage är
  ~1 GB. Docker GC:ar mellanlager mellan builds. Om disk växer
  oroväckande: `docker builder prune` månadsvis.

## Vinster

- Möjliggör #61 (cache-handler) utan att den behöver hantera build-
  pipeline-risken samtidigt.
- Inget extra att underhålla — ingen registry, inga tokens.
- `caddy validate` + health-check fångar de stora kategorier av fel
  som annars skulle nått prod (binärfel, config-fel, runtime-fel).
- Öppnar för framtida Caddy-moduler (rate-limit, geoip, m.fl.).

## Confidence

**Hög.** xcaddy + multi-stage är standard. `caddy validate` är ett
officiellt subkommando designat för just det här (CI-validering före
swap). Health-check stänger det sista risk-fönstret.

## Beroenden

- **Blockerar:** #61 (cache-handler / nginx-tiles-cutover) — #61
  antar att egen image redan är i drift.

## Nästa steg

1. Skriv `deploy/Dockerfile.caddy` med pinnade versioner.
2. Byt `image:` → `build:` i `compose.yaml`.
3. Uppdatera `deploy/deploy.sh` med build + validate + health-check.
4. Lokal smoke-test.
5. Commit + push → prod deployar.
6. 7d soak.

## Övriga noteringar (för #61 / följdarbete)

- `depends_on: [app, tileserver, nginx-tiles]` i compose.yaml måste
  uppdateras när #61 plockar bort `nginx-tiles`-tjänsten. Inte #65:s
  problem men flagga vid #61-implementation.
- Bind-mount `/etc/caddy/sites.d/` för co-hostade sajter (statiska
  sajter via egen GHA) — verifiera vid lokal smoke-test att den
  fortfarande funkar med egen build. Bör vara transparent eftersom
  `/usr/bin/caddy`-entrypoint är samma.
- `caddy-data`-volymen (Let's Encrypt-state) bevaras vid restart.
- Pre-flight `caddy validate` + health-check i deploy.sh är generella
  förbättringar — kommer även #61 till godo när cache-handler-config
  läggs till i Caddyfile.
