# Lokal utveckling med Docker Compose

Samma stack som på Hetzner, men utan Caddy/SSL. Snabb filsync via
named volumes för `vendor/`, `node_modules/` och `storage/framework/*`.

## Engångssetup

```bash
# 1. Kopiera env-mallen och fyll i (eller låt Laravel generera key)
cp deploy/.env.local.example .env

# 2. Starta stacken
docker compose up -d

# 3. Installera dependencies (första gången – hamnar i named volume)
docker compose exec app composer install
docker compose exec app npm install
docker compose exec app npm run build

# 4. Generera APP_KEY (om det är blankt i .env)
docker compose exec app php artisan key:generate

# 5. Migrera databasen
docker compose exec app php artisan migrate

# 6. (Valfritt) importera produktionsdump för realistisk testdata
scp root@brottsplatskartan.se:/tmp/bpk.sql.gz .
zcat bpk.sql.gz | docker compose exec -T mariadb mysql -u root -plocal-dev-root-password brottsplatskartan
```

Sajten finns nu på <http://localhost:8080>.

## Dagligt flöde

```bash
# Starta
docker compose up -d

# Läs logs i realtid
docker compose logs -f app

# Kör artisan
docker compose exec app php artisan migrate
docker compose exec app php artisan tinker

# Kör composer
docker compose exec app composer require <paket>

# Stoppa
docker compose down
```

## Ansluta med GUI-verktyg

Från docker-compose.override.yml exposas:

- **MariaDB** på `127.0.0.1:3306` (TablePlus, Sequel Ace, DBeaver)
- **Redis** på `127.0.0.1:6379` (RedisInsight, TablePlus)
- **Tileservern** på <http://localhost:8181>

Credentials matchar det du satte i `.env`.

## Varför named volumes för vendor/ och storage?

På macOS är bind-mounts ~3x långsammare än native filsystem (VirtioFS har
förbättrat det men är fortfarande overhead). Kataloger som skrivs ofta och
inte behöver synkas tillbaka till host (vendor, node_modules, cache, logs)
läggs därför i named volumes — mycket snabbare.

Du redigerar alltså koden på host som vanligt, men `composer install` och
Laravel-caches sker inne i containern utan att tråla genom VirtioFS.

## Prestanda fortfarande segt?

Prova **OrbStack** (<https://orbstack.dev>) istället för Docker Desktop.
Drop-in-ersättare, samma kommandon, betydligt snabbare på macOS. Gratis
för personligt bruk.

## Produktionsläge lokalt

Om du vill testa hela prod-setupen (inkl. Caddy med lokala certs):

```bash
docker compose -f docker-compose.yml up -d
```

Ignorera då docker-compose.override.yml. Du behöver också lägga till
`127.0.0.1 brottsplatskartan.se kartbilder.brottsplatskartan.se` i
`/etc/hosts` och konfigurera Caddy för självsignerat cert. Oftast
överflödigt — standard `docker compose up` räcker.
