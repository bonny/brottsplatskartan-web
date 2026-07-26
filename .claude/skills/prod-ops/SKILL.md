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
3. `deploy.sh` kör: `git pull` → villkorlig `composer install` (om lock ändrats) → villkorlig `artisan migrate` (om nya migrationer) → `docker compose restart app`
4. AUTORUN i containern kör `storage:link` + cache-warmup

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
