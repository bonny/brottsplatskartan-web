#!/usr/bin/env bash
#
# Hämtar en DB-dump från produktionsservern (Hetzner) och importerar
# direkt till den lokala Docker-MariaDB:n. Ingen mellanfil på disk.
#
# Användning (från repo-roten):
#
#   ./deploy/fetch-prod-db-to-local-db.sh
#   ./deploy/fetch-prod-db-to-local-db.sh --yes    # skippa bekräftelse
#
# Flöde:
#   1. SSH till prod, kör mariadb-dump (--single-transaction, ingen låsning).
#   2. Gzip on-the-fly över SSH-pipen.
#   3. Gunzip lokalt och pipa rakt in i docker compose mariadb.
#
# Exkluderad data (struktur behålls så Laravel inte kraschar):
#   users, sessions, personal_access_tokens, cache, cache_locks,
#   failed_jobs, jobs, job_batches, password_reset_tokens, password_resets
#
# Miljö-overrides:
#   REMOTE_HOST=deploy@brottsplatskartan.se
#   REMOTE_DIR=/opt/brottsplatskartan

set -euo pipefail

REMOTE_HOST="${REMOTE_HOST:-deploy@brottsplatskartan.se}"
REMOTE_DIR="${REMOTE_DIR:-/opt/brottsplatskartan}"

cd "$(dirname "$0")/.."

LOCAL_DB="$(grep ^DB_DATABASE= .env | cut -d= -f2-)"
LOCAL_ROOT_PW="$(grep ^DB_ROOT_PASSWORD= .env | cut -d= -f2-)"

echo
echo "Fetch prod DB → lokal DB"
echo "  Remote: $REMOTE_HOST:$REMOTE_DIR"
echo "  Lokal:  docker compose mariadb ($LOCAL_DB)"
echo

if [ "${1:-}" != "--yes" ]; then
    read -r -p "Detta skriver över din lokala DB. Fortsätta? [y/N] " yn
    case "$yn" in
        [Yy]|[Yy][Ee][Ss]) ;;
        *) echo "Avbryter."; exit 1 ;;
    esac
fi

echo
echo "→ Kör dump på $REMOTE_HOST och importerar lokalt..."
echo "  (tar typiskt 1-5 min beroende på DB-storlek)"

# Remote-script: dumpa data + struktur, gzippa stdout.
# Använder tvåpassmetod: ignorera data för PII-tabeller i första dumpen,
# lägg till struktur-only i andra.
REMOTE_CMD=$(cat <<'REMOTE'
set -euo pipefail
cd "$REMOTE_DIR"

# Läs DB-creds från serverns .env
DB_DATABASE="$(grep ^DB_DATABASE= .env | cut -d= -f2-)"
DB_ROOT_PASSWORD="$(grep ^DB_ROOT_PASSWORD= .env | cut -d= -f2-)"

EXCLUDED="users sessions personal_access_tokens cache cache_locks failed_jobs jobs job_batches password_reset_tokens password_resets"
IGNORE_ARGS=""
for t in $EXCLUDED; do
    IGNORE_ARGS="$IGNORE_ARGS --ignore-table=${DB_DATABASE}.${t}"
done

{
    # Pass 1: data + struktur för alla icke-exkluderade tabeller.
    docker compose exec -T mariadb mariadb-dump \
        --single-transaction \
        --skip-lock-tables \
        --default-character-set=utf8mb4 \
        --no-tablespaces \
        -u root -p"$DB_ROOT_PASSWORD" \
        "$DB_DATABASE" \
        $IGNORE_ARGS

    # Pass 2: bara struktur för PII/session-tabellerna.
    docker compose exec -T mariadb mariadb-dump \
        --single-transaction \
        --skip-lock-tables \
        --no-data \
        --default-character-set=utf8mb4 \
        --no-tablespaces \
        -u root -p"$DB_ROOT_PASSWORD" \
        "$DB_DATABASE" \
        $EXCLUDED
} | gzip -c
REMOTE
)

# shellcheck disable=SC2029  # avsiktlig: REMOTE_DIR expanderas lokalt, skickas som literal till servern
ssh "$REMOTE_HOST" "REMOTE_DIR='$REMOTE_DIR' bash -s" <<< "$REMOTE_CMD" \
    | gzip -d \
    | docker compose exec -T mariadb \
        mariadb -u root -p"$LOCAL_ROOT_PW" "$LOCAL_DB"

echo
echo "✓ Import klar."
echo
echo "Förslag att köra efteråt:"
echo "  docker compose exec app php artisan optimize:clear"
echo "  docker compose exec app php artisan responsecache:clear"
echo "  docker compose exec app php artisan tinker   # skapa lokal admin om du vill logga in"
