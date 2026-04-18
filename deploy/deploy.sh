#!/bin/bash
# Deploy brottsplatskartan på Hetzner-servern.
# Körs från /opt/brottsplatskartan/ av deploy-usern (via GHA eller manuellt).

set -euo pipefail

cd /opt/brottsplatskartan

echo "→ git pull"
PREV_SHA=$(git rev-parse HEAD)
git pull --ff-only origin main
NEW_SHA=$(git rev-parse HEAD)

if [ "$PREV_SHA" = "$NEW_SHA" ]; then
	echo "→ Inga nya commits, inget att göra"
	exit 0
fi

echo "→ Deploy $PREV_SHA → $NEW_SHA"

# Kör composer install bara om composer.lock ändrats
if ! git diff "$PREV_SHA" "$NEW_SHA" --quiet -- composer.lock composer.json; then
	echo "→ composer install (composer.lock ändrades)"
	docker compose run --rm --no-deps app composer install --no-dev --optimize-autoloader --no-interaction
else
	echo "→ Skippar composer install (ingen ändring)"
fi

# Kör migrations om något nytt finns i database/migrations/
if ! git diff "$PREV_SHA" "$NEW_SHA" --quiet -- database/migrations/; then
	echo "→ artisan migrate (nya migrationer)"
	docker compose exec -T app php artisan migrate --force
else
	echo "→ Inga nya migrationer"
fi

# AUTORUN fixar config/route/view cache vid restart
echo "→ docker compose restart app"
docker compose restart app

echo "✅ Deploy klart ($NEW_SHA)"
