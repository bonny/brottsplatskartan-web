#!/bin/bash
# Deploy brottsplatskartan på Hetzner-servern.
# Körs från /opt/brottsplatskartan/ av deploy-usern (via GHA eller manuellt).

set -euo pipefail

cd /opt/brottsplatskartan

# Explicit -f compose.yaml hindrar att compose.override.yaml (dev-config)
# laddas automatiskt på servern.
DC="docker compose -f compose.yaml"

echo "→ git pull ($(git rev-parse --abbrev-ref HEAD))"
PREV_SHA=$(git rev-parse HEAD)
git pull --ff-only
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

# AUTORUN fixar config/route/view cache vid restart
echo "→ docker compose restart app scheduler"
$DC restart app scheduler

# Rensa Spatie response cache (Redis). Annars serveras gamla cachade
# svar tills TTL (2–30 min) löper ut — irriterande när man just deployat
# en Blade-fix.
echo "→ responsecache:clear"
$DC exec -T app php artisan responsecache:clear || true

echo "✅ Deploy klart ($NEW_SHA)"
