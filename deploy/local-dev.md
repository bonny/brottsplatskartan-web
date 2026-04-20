# Lokal utveckling med Docker Compose

Samma stack som på Hetzner: `serversideup/php:8.4-fpm-nginx` (+ egna
PHP-extensions), MariaDB 11, Redis 8, tileserver-gl. Lokalt används
`compose.override.yaml` som laddas automatiskt och gör stacken
dev-vänlig utan att röra prod-filen.

## Portar (för att inte krocka med andra projekt)

| Service | Port |
|---|---|
| App | 8350 |
| Tileserver (profil) | 8351 |
| MariaDB | 33012 |
| Redis | 63012 |

Registrerat i `nvALT/Earth People/web projects local ports.md`.

## .test-domäner via dnsmasq (Valet-installerat)

Valet installerar `dnsmasq` som löser alla `*.test` till `127.0.0.1`.
Du behöver **ingen Valet-proxy** — bara använda `.test`-hostname med
port direkt. Undviker konflikt med port 80/443 som används av annat
projekt.

`.env` lokalt:

```env
APP_URL=http://brottsplatskartan.test:8350
TILESERVER_URL=http://kartbilder.brottsplatskartan.test:8351/
```

Sajt på <http://brottsplatskartan.test:8350>.

HTTPS lokalt är överflödigt — cutover-rehearsal mot riktig Hetzner-server
fångar HTTPS-specifika buggar innan prod-flytt.

### HSTS-problem?

Om Chrome auto-redirectar till `https://` och vägrar HTTP:

1. `chrome://net-internals/#hsts`
2. "Delete domain security policies" → `brottsplatskartan.test` → Delete
3. Samma för `kartbilder.brottsplatskartan.test`
4. Ny flik → `http://brottsplatskartan.test:8350` fungerar igen

## Engångssetup

```bash
# 1. Kopiera env-mallen och fyll i egna värden
cp deploy/.env.local.example .env

# 2. Bygg + starta stacken (app-image byggs första gången med Dockerfile.app)
docker compose up -d --build

# 3. Installera composer-dependencies
#    GOTCHA: named volume för vendor/ ägs av root initialt, och AUTORUN
#    kör storage:link som kräver vendor/. Därför: -u root + AUTORUN_ENABLED=false.
docker compose run --rm --no-deps -u root -e AUTORUN_ENABLED=false app \
  sh -c 'composer install && chown -R www-data:www-data /var/www/html/vendor /var/www/html/bootstrap/cache /var/www/html/storage'

# 4. Node-paket + build (för frontend-assets)
docker compose exec app npm install
docker compose exec app npm run build

# 5. Generera APP_KEY (om det är blankt i .env)
docker compose exec app php artisan key:generate

# 6. Migrera databasen
docker compose exec app php artisan migrate

# 7. (Valfritt) importera produktionsdump för realistisk testdata
scp root@brottsplatskartan.se:/tmp/bpk.sql.gz .
zcat bpk.sql.gz | docker compose exec -T mariadb mysql -u root -plocal-dev-root-password brottsplatskartan
```

Sajten finns nu på <http://brottsplatskartan.test:8350>.

## Dagligt flöde

```bash
# Starta
docker compose up -d

# Läs logs
docker compose logs -f app

# Artisan
docker compose exec app php artisan migrate
docker compose exec app php artisan tinker

# Composer (enklast att köra som root för att undvika perm-issues)
docker compose exec -u root app composer require <paket>
docker compose exec -u root app composer update

# Npm
docker compose exec app npm run dev

# Stoppa (behåller data i named volumes)
docker compose down

# Stoppa + radera data (DESTRUKTIVT)
docker compose down -v
```

## Varför containern loopar / krashar?

Troliga orsaker + fix:

**"Failed opening required '/var/www/html/vendor/autoload.php'"**
→ `composer install` har inte körts. Kör engångsettan ovan.

**"Permission denied" i vendor/ eller storage/**
→ Named volume ägs av root. Kör: `docker compose exec -u root app chown -R www-data:www-data /var/www/html/vendor /var/www/html/storage /var/www/html/bootstrap/cache`

**Port-konflikt vid uppstart**
→ Kör `docker compose down` först, sen `up -d`. Om det inte hjälper:
  `docker ps -a --format '{{.Names}}' | grep brottsplatskartan | xargs docker rm -f`
  `docker network prune -f`

**"Ports are not available" (något annat håller porten)**
→ `lsof -iTCP:33012 -sTCP:LISTEN` för att se vem. Byt ev. port i
  `compose.override.yaml` + `.env` + port-registret.

## Ansluta med GUI-verktyg

| Verktyg | Värd | Port | Credentials |
|---|---|---|---|
| TablePlus / Sequel Ace | 127.0.0.1 | 33012 | `.env` DB_USERNAME/DB_PASSWORD |
| RedisInsight / TablePlus (Redis) | 127.0.0.1 | 63012 | `.env` REDIS_PASSWORD |

## Tileservern lokalt (valfritt)

Tileservern genererar kartbilder för händelsesidor. **Du behöver inte den
för 95% av dev-arbetet** — sajten fungerar utan, bara att `<img>` för
kartbilden blir trasig.

Vill du ändå köra:

```bash
# 1. Ladda ner mbtiles (1.21 GB, idempotent)
./deploy/download-tiles.sh

# 2. Starta stacken INKL. tileservern
docker compose --profile tileserver up -d

# 3. Kolla att den svarar
curl http://kartbilder.brottsplatskartan.test:8351
```

Stäng av: `docker compose stop tileserver`

Mbtiles är gitignored, förorenar inte repot.

## Bind-mount

Hela projektet bind-mountas från host → container (`./:/var/www/html`).
Det innebär att `vendor/`, `node_modules/`, `storage/` m.fl. ligger på
ditt filsystem och är läsbara av din IDE (viktigt för autocomplete,
PHPStan, Pint).

Named volumes används bara för **binär data som inte behöver ses på
host** — MariaDB-filer, Redis-dumps, Caddy-certifikat.

### Om prestandan ändå är seg

Prova **OrbStack** (<https://orbstack.dev>) — drop-in-ersättare för
Docker Desktop med betydligt snabbare filsync. Samma kommandon, inget
att ändra i configen. Gratis för personligt bruk.

## Bygga om app-imagen

Vi har en egen `Dockerfile.app` som extendar `serversideup/php` med
`bcmath`, `exif` och `gd` (behövs av `league/geotools` och
bildmanipulation).

När du ändrar `Dockerfile.app`:

```bash
docker compose build app
docker compose up -d
```

## Varför config cache är AV lokalt

I `compose.override.yaml`:

```yaml
AUTORUN_LARAVEL_CONFIG_CACHE: "false"
AUTORUN_LARAVEL_ROUTE_CACHE: "false"
AUTORUN_LARAVEL_VIEW_CACHE: "false"
AUTORUN_LARAVEL_EVENT_CACHE: "false"
```

Annars kräver varje ändring i `config/*.php` eller `.env` en
`php artisan config:clear`. Lokalt vill vi att allt läses fräscht.

På prod är alla dessa `true` för snabbare request-hantering.

## Produktionsläge lokalt (sällan)

Om du vill testa full prod-setup (inkl. Caddy + SSL):

```bash
# Ignorera override.yml
docker compose -f compose.yaml up -d
```

Kräver också att port 80/443 är fri. Oftast överflödigt — dev mot
`http://brottsplatskartan.test:8350` är tillräckligt.
