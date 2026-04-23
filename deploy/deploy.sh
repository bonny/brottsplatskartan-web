#!/bin/bash
# Deploy brottsplatskartan på Hetzner-servern.
# Körs från /opt/brottsplatskartan/ av deploy-usern (via GHA eller manuellt).

set -euo pipefail

cd /opt/brottsplatskartan

# Explicit -f compose.yaml hindrar att compose.override.yaml (dev-config)
# laddas automatiskt på servern.
DC="docker compose -f compose.yaml"

# Prod kör alltid main. Tvinga checkout så driftiga branches eller
# borttagna remote-branches inte kan paja deployen.
PREV_SHA=$(git rev-parse HEAD 2>/dev/null || echo "none")
echo "→ git fetch + checkout main"
git fetch origin --prune
git checkout -B main origin/main
NEW_SHA=$(git rev-parse HEAD)

if [ "$PREV_SHA" = "$NEW_SHA" ]; then
	echo "→ Inga nya commits, inget att göra"
	exit 0
fi

echo "→ Deploy $PREV_SHA → $NEW_SHA"

# Kör composer install bara om composer.lock ändrats.
# Körs som root pga named volume-perms, AUTORUN av för att slippa
# storage:link före vendor/ finns. Chownar tillbaka till www-data.
if ! git diff "$PREV_SHA" "$NEW_SHA" --quiet -- composer.lock composer.json; then
	echo "→ composer install (composer.lock ändrades)"
	$DC run --rm --no-deps -u root -e AUTORUN_ENABLED=false app \
		sh -c 'composer install --no-dev --optimize-autoloader --no-interaction && chown -R www-data:www-data /var/www/html/vendor /var/www/html/bootstrap/cache'
else
	echo "→ Skippar composer install (ingen ändring)"
fi

# Kör migrations om något nytt finns i database/migrations/
if ! git diff "$PREV_SHA" "$NEW_SHA" --quiet -- database/migrations/; then
	echo "→ artisan migrate (nya migrationer)"
	$DC exec -T app php artisan migrate --force
else
	echo "→ Inga nya migrationer"
fi

# Starta nya/ändrade services. Idempotent — skapar bara containers som
# saknas eller har ändrad config, rör inte resten. Fångar upp fallet
# där deploy.sh själv uppdateras men nya services i compose.yaml
# redan är committade.
echo "→ docker compose up -d"
$DC up -d --remove-orphans

# Restart Caddy (Caddyfile är bind-mount → up -d recreatar inte
# containern när filen ändrats, och 'caddy reload' har visat sig
# opålitligt i vår setup). Hård restart tar <1s och är säker.
echo "→ docker compose restart caddy"
$DC restart caddy

# Restart nginx-tiles alltid (samma logik som caddy — bind-mount +
# reload har visat sig opålitligt).
echo "→ docker compose restart nginx-tiles"
$DC restart nginx-tiles

# AUTORUN fixar config/route/view cache vid restart
echo "→ docker compose restart app scheduler"
$DC restart app scheduler

# Rensa Spatie response cache (Redis). Annars serveras gamla cachade
# svar tills TTL (2–30 min) löper ut — irriterande när man just deployat
# en Blade-fix.
echo "→ responsecache:clear"
$DC exec -T app php artisan responsecache:clear || true

echo "✅ Deploy klart ($NEW_SHA)"
