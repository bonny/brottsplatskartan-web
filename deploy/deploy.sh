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

# Kör download-tiles.sh (idempotent — hoppar över om filen redan finns).
# Om ny fil hämtades: restart tileserver längre ner.
TILES_CHANGED=0
echo "→ download-tiles.sh"
./deploy/download-tiles.sh | tee /tmp/download-tiles.log
if grep -q "Laddar ner" /tmp/download-tiles.log; then
	TILES_CHANGED=1
fi

# Starta nya/ändrade services. Idempotent — skapar bara containers som
# saknas eller har ändrad config, rör inte resten. Fångar upp fallet
# där deploy.sh själv uppdateras men nya services i compose.yaml
# redan är committade.
echo "→ docker compose up -d"
$DC up -d --remove-orphans

if [ "$TILES_CHANGED" = "1" ]; then
	echo "→ docker compose restart tileserver (ny mbtiles)"
	$DC restart tileserver
fi

# Caddy startas om BARA när dess konfiguration faktiskt ändrats.
#
# Tidigare kördes restart vid varje deploy. Caddy terminerar all trafik,
# så under omstarten svarar sajten inte alls — connection refused, inte
# 502. Uppmätt i Caddy-loggen: 2–13 s per deploy, 16 avbrott på sju dygn
# (2026-07-31). Kommentaren som stod här påstod "<1s", vilket inte stämde.
#
# Caddyfile är bind-mount, så 'up -d' ovan recreatar inte containern när
# filen ändrats — därför behövs restart när den gör det. Två källor kan
# ändra konfigurationen:
#
#   1. deploy/Caddyfile i det här repot.
#   2. /opt/caddy-sites.d/*.caddy, som deployas av static-sites-repots
#      egen GitHub Action. Den rör inte vår container, så den ovillkorliga
#      omstarten var i praktiken det som fick nya snippets att slå igenom.
#      Checksumman nedan bevarar den effekten utan att kosta nertid varje
#      deploy (snippets ändras ett par gånger om året).
CADDY_SITES_STATE=/opt/brottsplatskartan/.caddy-sites.sha256
CADDY_SITES_SUM=$( { find /opt/caddy-sites.d -maxdepth 1 -name '*.caddy' -type f \
	-exec sha256sum {} + 2>/dev/null || true; } | sort | sha256sum | cut -d' ' -f1)

CADDY_NEEDS_RESTART=0
CADDY_REASON=""
if [ "$PREV_SHA" = "none" ]; then
	CADDY_NEEDS_RESTART=1
	CADDY_REASON="okänt föregående commit"
elif ! git diff "$PREV_SHA" "$NEW_SHA" --quiet -- deploy/Caddyfile; then
	CADDY_NEEDS_RESTART=1
	CADDY_REASON="deploy/Caddyfile ändrad"
elif [ "$CADDY_SITES_SUM" != "$(cat "$CADDY_SITES_STATE" 2>/dev/null || true)" ]; then
	CADDY_NEEDS_RESTART=1
	CADDY_REASON="/opt/caddy-sites.d ändrad"
fi

if [ "$CADDY_NEEDS_RESTART" = "1" ]; then
	echo "→ docker compose restart caddy ($CADDY_REASON)"
	$DC restart caddy
else
	echo "→ Skippar caddy-restart (ingen konfigändring — noll nertid)"
fi
printf '%s\n' "$CADDY_SITES_SUM" >"$CADDY_SITES_STATE"

# Samma logik för nginx-tiles: bind-mountad config, reload opålitlig.
# Enda källan är repot, så ingen checksumma behövs.
if [ "$PREV_SHA" = "none" ] || ! git diff "$PREV_SHA" "$NEW_SHA" --quiet -- deploy/nginx-tiles.conf; then
	echo "→ docker compose restart nginx-tiles (config ändrad)"
	$DC restart nginx-tiles
else
	echo "→ Skippar nginx-tiles-restart (ingen konfigändring)"
fi

# AUTORUN fixar config/route/view cache vid restart
echo "→ docker compose restart app scheduler"
$DC restart app scheduler

# Skriv deploy-info som sidfoten läser. Körs i app-containern så fil-
# ägarskap blir www-data, samma som resten av storage/app/. Tiden sätts
# till Europe/Stockholm för att matcha app-timezone.
echo "→ skriver storage/app/deploy.json"
SUBJECT=$(git log -1 --format='%s')
SHORT=$(git rev-parse --short HEAD)
NOW=$(TZ=Europe/Stockholm date -Iseconds)
$DC exec -T \
	-e DEPLOY_SHA="$NEW_SHA" \
	-e DEPLOY_SHORT="$SHORT" \
	-e DEPLOY_SUBJECT="$SUBJECT" \
	-e DEPLOY_AT="$NOW" \
	app php -r '
		file_put_contents("storage/app/deploy.json", json_encode([
			"sha"         => getenv("DEPLOY_SHA"),
			"short_sha"   => getenv("DEPLOY_SHORT"),
			"subject"     => getenv("DEPLOY_SUBJECT"),
			"deployed_at" => getenv("DEPLOY_AT"),
		], JSON_UNESCAPED_UNICODE));
	'

# Rensa Spatie response cache (Redis). Annars serveras gamla cachade
# svar tills TTL (2–30 min) löper ut — irriterande när man just deployat
# en Blade-fix.
echo "→ responsecache:clear"
$DC exec -T app php artisan responsecache:clear || true

echo "✅ Deploy klart ($NEW_SHA)"
