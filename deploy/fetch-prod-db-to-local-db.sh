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
#   1. SSH till prod, kör mariadb-dump (--single-transaction, --quick).
#   2. Gzip on-the-fly över SSH-pipen.
#   3. Gunzip lokalt, DROP+CREATE DATABASE prolog, pipa in i docker.
#   4. Kontrollera PIPESTATUS för att upptäcka halvimport.
#
# Säkerhet:
#   - Lösenord skickas via MYSQL_PWD, inte -p-flagga, så det inte
#     syns i `ps auxf` under körning.
#   - REMOTE_DIR expanderas lokalt och skickas som literalt värde
#     till servern (heredoc-quotat innehåll).
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

# Parsa .env robust (hanterar eventuella citat runt värden).
read_env() {
    awk -F= -v key="$1" '
        $1 == key {
            sub(/^[^=]+=/, "")
            gsub(/^"|"$|^'\''|'\''$/, "")
            print
            exit
        }
    ' .env
}

LOCAL_DB="$(read_env DB_DATABASE)"
LOCAL_ROOT_PW="$(read_env DB_ROOT_PASSWORD)"

if [ -z "$LOCAL_DB" ] || [ -z "$LOCAL_ROOT_PW" ]; then
    echo "Fel: kunde inte läsa DB_DATABASE eller DB_ROOT_PASSWORD från .env" >&2
    exit 1
fi

echo
echo "Fetch prod DB → lokal DB"
echo "  Remote: $REMOTE_HOST:$REMOTE_DIR"
echo "  Lokal:  docker compose mariadb ($LOCAL_DB)"
echo

if [ "${1:-}" != "--yes" ]; then
    if [ ! -t 0 ]; then
        echo "Fel: non-interactive körning utan --yes. Avbryter." >&2
        exit 1
    fi
    read -r -p "Detta skriver över din lokala DB. Fortsätta? [y/N] " yn
    case "$yn" in
        [Yy]|[Yy][Ee][Ss]) ;;
        *) echo "Avbryter."; exit 1 ;;
    esac
fi

echo
echo "-> Kör dump på $REMOTE_HOST och importerar lokalt..."
echo "   (tar typiskt 1-5 min beroende på DB-storlek)"

# Remote-script: dumpa data + struktur, gzippa stdout.
# Tvåpassmetod: data+struktur för alla icke-exkluderade tabeller först,
# sen bara struktur för PII/session-tabellerna.
#
# Lösenord via MYSQL_PWD så det inte syns i ps.
REMOTE_CMD=$(cat <<'REMOTE'
set -euo pipefail
cd "$REMOTE_DIR"

# Läs DB-creds från serverns .env
DB_DATABASE=$(awk -F= '$1=="DB_DATABASE"{sub(/^[^=]+=/,"");gsub(/^"|"$|^'\''|'\''$/,"");print;exit}' .env)
DB_ROOT_PASSWORD=$(awk -F= '$1=="DB_ROOT_PASSWORD"{sub(/^[^=]+=/,"");gsub(/^"|"$|^'\''|'\''$/,"");print;exit}' .env)

EXCLUDED="users sessions personal_access_tokens cache cache_locks failed_jobs jobs job_batches password_reset_tokens password_resets"
IGNORE_ARGS=""
for t in $EXCLUDED; do
    IGNORE_ARGS="$IGNORE_ARGS --ignore-table=${DB_DATABASE}.${t}"
done

export MYSQL_PWD="$DB_ROOT_PASSWORD"

# Filtrera EXCLUDED till bara de tabeller som faktiskt finns i
# prod-DB:n. Alla listade finns inte alltid (t.ex. sessions när
# SESSION_DRIVER=redis). mariadb-dump kraschar annars i pass 2.
EXISTING_EXCLUDED=""
for t in $EXCLUDED; do
    exists=$(docker compose exec -T -e MYSQL_PWD mariadb mariadb -N -B -u root \
        -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$DB_DATABASE' AND table_name='$t'" \
        2>/dev/null | tr -d '[:space:]')
    if [ "$exists" = "1" ]; then
        EXISTING_EXCLUDED="$EXISTING_EXCLUDED $t"
    fi
done

{
    # Pass 1: data + struktur för alla icke-exkluderade tabeller.
    # --quick streamar rad-för-rad (viktigt för stora tabeller).
    docker compose exec -T -e MYSQL_PWD mariadb mariadb-dump \
        --single-transaction \
        --skip-lock-tables \
        --quick \
        --default-character-set=utf8mb4 \
        --no-tablespaces \
        -u root \
        "$DB_DATABASE" \
        $IGNORE_ARGS

    # Pass 2: bara struktur för PII/session-tabellerna som finns.
    if [ -n "$EXISTING_EXCLUDED" ]; then
        docker compose exec -T -e MYSQL_PWD mariadb mariadb-dump \
            --single-transaction \
            --skip-lock-tables \
            --no-data \
            --default-character-set=utf8mb4 \
            --no-tablespaces \
            -u root \
            "$DB_DATABASE" \
            $EXISTING_EXCLUDED
    fi
} | gzip --fast
REMOTE
)

# Prolog: nolla lokal DB atomärt så misslyckad import lämnar tom DB,
# inte halvimporterad. Appenderas framför den dekomprimerade streamen.
PROLOG="DROP DATABASE IF EXISTS \`$LOCAL_DB\`;
CREATE DATABASE \`$LOCAL_DB\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE \`$LOCAL_DB\`;
"

# shellcheck disable=SC2029  # avsiktlig: REMOTE_DIR expanderas lokalt, skickas som literal till servern
{ printf '%s' "$PROLOG"; ssh "$REMOTE_HOST" "REMOTE_DIR='$REMOTE_DIR' bash -s" <<< "$REMOTE_CMD" | gzip -d; } \
    | MYSQL_PWD="$LOCAL_ROOT_PW" docker compose exec -T -e MYSQL_PWD mariadb mariadb -u root "$LOCAL_DB"

# Kontrollera att inget i pipen dog halvvägs. ${PIPESTATUS[@]} har exit-koder
# för varje del i pipen vänster-till-höger.
status=("${PIPESTATUS[@]}")
for code in "${status[@]}"; do
    if [ "$code" -ne 0 ]; then
        echo "Fel: något i pipen returnerade exit-kod $code. Lokal DB är troligen tom eller halvimporterad." >&2
        echo "Status per steg: ${status[*]}" >&2
        exit 1
    fi
done

echo
echo "Import klar."
echo
echo "Förslag att köra efteråt:"
echo "  docker compose exec app php artisan optimize:clear"
echo "  docker compose exec app php artisan responsecache:clear"
echo "  docker compose exec app php artisan tinker   # skapa lokal admin om du vill logga in"
