---
name: prod-ops
description: Drift av produktionsservern på Hetzner — deploy, rollback, artisan/docker/redis/mariadb-kommandon via SSH, backup av prod-DB, ändra env-variabler, aktivera Laravel Debugbar, provisionering och mbtiles-uppdatering. Använd när användaren pratar om att deploya, rulla tillbaka, köra något på servern, ändra prod-env, ta backup av prod-databasen eller felsöka produktion.
---

# Produktionsdrift (Hetzner)

- **Plattform:** Hetzner Cloud (EU)
- **Server:** CX33 (x86 AMD, 4 vCPU / 8 GB / 80 GB), Debian 13 (Trixie), Helsinki
- **Deploy-stack:** Docker Compose (`compose.yaml` + egen `Dockerfile.app`)
- **Reverse proxy:** Caddy med auto-Let's Encrypt
- **Kod-plats:** `/opt/brottsplatskartan/`
- **CI/CD:** GitHub Actions (`.github/workflows/deploy-hetzner.yml`) → SSH → `deploy/deploy.sh`
- **Trigger:** `git push main` deployar automatiskt

## Deploy-flöde

1. `git push origin main`
2. GitHub Actions triggar → SSH till Hetzner
3. `deploy.sh` kör: `git fetch/checkout main` → villkorlig `composer install`
   (om lock ändrats) → villkorlig `artisan migrate` (om nya migrationer) →
   `docker compose up -d` → **villkorlig** `restart caddy` +
   `restart nginx-tiles` (bara vid konfigändring, se nästa avsnitt) →
   `restart app scheduler` → skriver `deploy.json` → `responsecache:clear`
4. AUTORUN i containern kör `storage:link` + cache-warmup

### Efter deploy: två saker som ser ut som fel men inte är det

Verifierat 2026-08-24 (#102). Rulla **inte** tillbaka på något av det här
utan att först kolla tidsstämplarna mot deployens fönster.

**1. Fatala fel i loggen under `composer install`.** När lockfilen ändrats
byts `vendor/` ut under fötterna på körande php-fpm-processer. Det ger en
skur av `Class not found`, `Call to undefined method` och
`headers already sent` — alla inom samma fåtal sekunder.

Så här skiljer du transient från äkta:

```bash
# 1. Ligger alla fel i ett fönster på några sekunder? Då är det vendor-swappen.
ssh deploy@brottsplatskartan.se 'cd /opt/brottsplatskartan && docker compose exec -T app sh -c \
  "ls -t storage/logs/*.log | head -1 | xargs grep -hE \"ERROR|CRITICAL\"" ' | \
  sed -E "s/^(\[[^]]+\]).*/\1/" | uniq -c

# 2. Ändrades paketet som klagar överhuvudtaget?
git diff HEAD~1 composer.lock | grep -A2 '"name": "<paketet>"'

# 3. Kör kommandot som föll igen — går det rent nu är det avklarat.
```

Exempel: `Call to undefined method GuzzleHttp\Psr7\Response::assertProtocolVersion()`
såg ut som en trasig uppgradering, men `guzzlehttp/psr7` var **oförändrad**
2.13.1 före och efter — felen låg i ett 4-sekundersfönster och `vma_alerts:import`
körde rent direkt efteråt.

**2. Sidor tar 10–60 s de första minuterna.** `deploy.sh` avslutar med
`responsecache:clear`, så _hela_ cachen är tom samtidigt som app-containern
just startat om (kall opcache). Requests köar bakom varandras omrenderingar.

Uppmätt efter en deploy: `/stockholm` 56 s → load 4,05. Fem minuter senare:
kallrendering 0,22 s, load 2,73. **Äkta kallrendering är 0,2–0,3 s** — allt
över det är kön, inte sidan.

Vänta ut det och mät om innan du felsöker:

```bash
# Följ load och kallrendering ihop — de sjunker tillsammans.
for i in 1 2 3; do
  L=$(ssh deploy@brottsplatskartan.se 'cut -d" " -f1 /proc/loadavg')
  T=$(curl -s -o /dev/null -w '%{time_total}' "https://brottsplatskartan.se/malmo?v=$RANDOM$i")
  echo "load=$L kallrendering=${T}s"
done
```

`?v=` fungerar för att tvinga fram kallrendering — `?nocache=` gör det
**inte**, den ligger i `ignored_query_parameters` (config/responsecache.php)
och ger samma cache-nyckel som utan parameter.

### ⚠️ Prod loggar i svensk tid, GitHub Actions i UTC

`ai_usage_logs`, `storage/logs/` och `date` på servern är Europe/Stockholm
(UTC+2 sommartid). Deploy-tidpunkten från `gh run` är UTC. Två timmars
förskjutning gör att man lätt läser _före_-data som _efter_-data och tror
att en ändring inte slog igenom. Jämför alltid mot:

```bash
ssh deploy@brottsplatskartan.se 'date -u "+UTC: %H:%M"; TZ=Europe/Stockholm date "+Lokal: %H:%M"'
```

### Nertid vid deploy — åtgärdat 2026-07-31

Fram till `c468ccd` startade `deploy.sh` om Caddy **ovillkorligt** vid
varje deploy. Caddy terminerar all trafik, så sajten svarade inte alls
under omstarten — connection refused, inte 502. Uppmätt i Caddy-loggen:
**2–13 s per deploy, 16 avbrott på sju dygn**.

Numera startas Caddy och nginx-tiles om **bara när deras konfiguration
ändrats** (`deploy/Caddyfile`, `deploy/nginx-tiles.conf`, eller snippets i
`/opt/caddy-sites.d` — det sista via en checksumma i
`/opt/brottsplatskartan/.caddy-sites.sha256`). En vanlig kod- eller
docs-deploy ger därför **noll** nertid från Caddy.

Deployen loggar vilket den valde:

```
→ Skippar caddy-restart (ingen konfigändring — noll nertid)
→ docker compose restart caddy (deploy/Caddyfile ändrad)
```

Kvar att veta:

- `restart app scheduler` körs fortfarande alltid — nödvändigt för att ny
  PHP-kod ska laddas. Det ger kortvariga 502:or via Caddy, inte
  connection refused.
- `responsecache:clear` körs sist, så cachen är kall efter varje deploy
  och första trafiken går rakt på DB.

Mät alltid i Caddy-loggen innan du litar på en siffra om nertid — se
[docs/loggar.md](../../../docs/loggar.md), receptet för nertidsfönster.

### ⚠️ Ändringar i `deploy.sh` gäller först vid NÄSTA deploy

`deploy.sh` uppdaterar sig själv mitt i sin egen körning: GitHub Actions
startar den version som ligger på servern, och scriptets `git checkout`
byter sedan ut filen. Git skriver en ny fil och byter namn (ny inode), så
den körande bash-processen behåller sin filedeskriptor mot den **gamla**
filen och kör den klart.

Konsekvens: en deploy som ändrar `deploy.sh` kör fortfarande gammal logik.
Den nya gäller från deployen därefter. Verifierat 2026-07-31 (`2fdbc89`)
— loggen visade gamla meddelanden trots att nya scriptet låg på disk.

Slutsatsen när du felsöker: läs alltid **loggen** från körningen, inte bara
filen på disk. De kan visa olika saker precis efter en ändring av scriptet.

(Att git byter inode är för övrigt tur — hade filen skrivits över på plats
kunde bash läst vidare från fel byteoffset i den nya koden.)

## Manuell deploy

```bash
ssh deploy@brottsplatskartan.se /opt/brottsplatskartan/deploy/deploy.sh
```

## Rollback

```bash
ssh deploy@brottsplatskartan.se 'cd /opt/brottsplatskartan && git reset --hard HEAD~1 && ./deploy/deploy.sh'
```

## Produktionsserver – kommandon

```bash
ssh deploy@brottsplatskartan.se
cd /opt/brottsplatskartan

# Artisan
docker compose exec app php artisan migrate
docker compose exec app php artisan cache:clear
docker compose exec app php artisan responsecache:clear
docker compose exec app php artisan crimeevents:check-publicity --apply --since=365

# Logs
docker compose logs -f app
docker compose logs --tail 100 app | grep ERROR

# Redis CLI
docker compose exec redis redis-cli -a "$REDIS_PASSWORD"
# I redis-cli:
#   DBSIZE
#   KEYS laravelresponsecache-*
#   MONITOR

# MariaDB CLI
docker compose exec mariadb mariadb -u root -p"$DB_ROOT_PASSWORD" brottsplatskartan

# Container-hantering
docker compose ps
docker compose restart app
docker compose down && docker compose up -d
```

## Backup av prod-DB till lokal fil

Kör alltid detta innan backfill, migrate eller UPDATE mot prod.

```bash
./deploy/backup-prod-db.sh
```

Dumpar full prod-DB till `backups/prod-YYYY-MM-DD-HHMMSS.sql.gz`
(gitignored, chmod 600 — innehåller PII från `users` och liknande).
Använd `deploy/fetch-prod-db-to-local-db.sh` istället när du vill ersätta
lokal dev-DB direkt utan mellanfil.

## Loggar och trafik-/bot-analys

Se **[docs/loggar.md](docs/loggar.md)** för var access-/felloggarna ligger
(app-containerns nginx-stdout, ej fil), loggformatet (riktig klient-IP loggas
**sist** på raden eftersom Caddy står framför) och färdiga `awk`-recept för att
ranka topp-IP:er och user-agents senaste timmen.

## Felsöka Redis-minne (vad äter taket?)

`artisan redis:health` ger översikt. När den varnar för eviction räcker inte
nyckel*antal* — mät **bytes per prefix**, annars drar man fel slutsats
(småcacher är många men bidrar marginellt).

```bash
cd /opt/brottsplatskartan
set -a; . ./.env; set +a

# 1. Sampla nycklar (SCAN, ej KEYS — KEYS blockerar servern)
docker compose exec -T redis sh -c \
  "redis-cli -a \"$REDIS_PASSWORD\" --no-auth-warning --scan --count 1000" \
  | head -20000 > /tmp/bpk_keys.txt

# 2. Mät MEMORY USAGE per nyckel, gruppera på normaliserat prefix
awk '{ printf "MEMORY USAGE \"%s\"\n", $0 }' /tmp/bpk_keys.txt \
  | docker compose exec -T redis redis-cli -a "$REDIS_PASSWORD" --no-auth-warning \
  > /tmp/bpk_sizes.txt
paste -d' ' /tmp/bpk_keys.txt /tmp/bpk_sizes.txt \
  | awk '{ k=$1; gsub(/[0-9a-f]{16,}.*/, "<HASH>", k); gsub(/[0-9]+/, "<N>", k);
           n[k]++; s[k]+=$2 }
         END { for (i in n) printf "%-55s %7d %9.1f MB %8.1f KB\n",
                 substr(i,1,55), n[i], s[i]/1048576, s[i]/n[i]/1024 }' \
  | sort -k3 -rn | head -20
```

### ⚠️ `docker compose exec -T` äter loopens stdin

I en `while read` -loop slukar `docker compose exec -T` resten av indata och
loopen kör bara **ett** varv. Symptomet är att du får en enda rad utdata och
tror att kommandot misslyckades. Lägg alltid `</dev/null` på exec:en:

```bash
while read -r key; do
  docker compose exec -T redis redis-cli … GET "$key" </dev/null
done < /tmp/nycklar.txt
```

Samma fälla gäller `docker compose exec` i alla loopar, inte bara Redis.

Andra fallgropar: `bc` finns **inte** i containrarna — räkna med `awk`.
MEMORY USAGE, SCAN, TTL och GET är read-only och säkra att köra mot prod.

## Provisionering av ny server

Se **[deploy/provision.md](deploy/provision.md)**.

## Uppdatera mbtiles (kartdata)

Se **[deploy/update-tiles.md](deploy/update-tiles.md)** för Planetiler-pipelinen.
Körs vid behov (~1–2 ggr/år) när kartdatan blir för gammal. Gratis och reproducerbart.

## Produktions-env

`.env` ligger i `/opt/brottsplatskartan/.env` på servern (chmod 600, ägd av `deploy`).
Mall: `deploy/.env.example`. Alla secrets hanteras där — aldrig i git.

Kritiska variabler:

- `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://brottsplatskartan.se`
- DB: `DB_HOST=mariadb`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `DB_ROOT_PASSWORD`
- Redis: `REDIS_HOST=redis`, `REDIS_PASSWORD`
- Cache: `CACHE_DRIVER=redis`, `RESPONSE_CACHE_DRIVER=redis`, `SESSION_DRIVER=redis`
- API-nycklar: `CLAUDE_API_KEY`, `GOOGLE_API_KEY`, m.fl.

### Ändra en env-variabel på prod (rätt ordning)

Containern får env via `env_file: .env` i `compose.yaml` (laddas vid
container-**start**), och config är `config:cache`:ad. Två fallgropar gör att
en naiv ändring inte slår igenom:

1. **`docker compose restart app` läser INTE om `env_file`** — den startar om
   samma container med oförändrade env-variabler. Du måste **recreate**:a med
   `docker compose up -d app`.
2. **`config:cache` på den gamla containern bakar in det gamla värdet** —
   config läser `env()` ur containerns process-miljö, som ännu har gamla
   värdet tills containern recreate:ats. Kör `config:cache` FÖRST efter `up -d`.

Korrekt sekvens (exempel: `MONTHLY_VIEWS_PILOT`):

```bash
ssh deploy@brottsplatskartan.se
cd /opt/brottsplatskartan
cp -p .env ".env.bak-$(date +%Y%m%d-%H%M%S)"          # backup först
sed -i 's|^MONTHLY_VIEWS_PILOT=.*|MONTHLY_VIEWS_PILOT="all"|' .env
docker compose up -d app                               # recreate → laddar om env_file
docker compose exec -T app printenv MONTHLY_VIEWS_PILOT # verifiera: nya värdet
docker compose exec -T app php artisan config:cache     # rebuild MED nya env:et
docker compose exec -T app php artisan responsecache:clear
docker compose exec -T app php artisan config:show <key> # bekräfta effektivt värde
```

Obs: `up -d app` kan recreate:a beroende-containrar (t.ex. redis) → cache
kallstartar (harmlös perf-blip). `responsecache:clear` ensam kan trigga
`check-prod-tinker.sh`-hooken i en kedja — kör den som eget kommando.

## Laravel Debugbar i produktion

Debugbar aktiveras via cookie (`app/Http/Middleware/DebugBarMaybeEnable.php`):

```javascript
// Aktivera
document.cookie = "show-debugbar=1; path=/; max-age=86400";
// Inaktivera
document.cookie = "show-debugbar=; path=/; max-age=0";
```

Bara den som satt cookien ser debugbar. Kräver ingen ändring av `APP_DEBUG`.
