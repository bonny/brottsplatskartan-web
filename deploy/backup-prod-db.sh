#!/usr/bin/env bash
#
# Tar en full backup av produktions-DB:n och skriver den lokalt till
# backups/prod-YYYY-MM-DD-HHMMSS.sql.gz. Alla tabeller ingår
# (inklusive users, sessions, etc.) eftersom det är en faktisk backup,
# inte en utvecklingscopy. Använd fetch-prod-db-to-local-db.sh om du
# vill skriva över lokal DB istället.
#
# Användning (från repo-roten):
#
#   ./deploy/backup-prod-db.sh
#
# Filer placeras i backups/ som är gitignored (innehåller PII).
# Permissions sätts till 600 så bara ägaren kan läsa.
#
# Miljö-overrides:
#   REMOTE_HOST=deploy@brottsplatskartan.se
#   REMOTE_DIR=/opt/brottsplatskartan

set -euo pipefail

REMOTE_HOST="${REMOTE_HOST:-deploy@brottsplatskartan.se}"
REMOTE_DIR="${REMOTE_DIR:-/opt/brottsplatskartan}"

cd "$(dirname "$0")/.."

mkdir -p backups
TIMESTAMP="$(date +%Y-%m-%d-%H%M%S)"
OUTFILE="backups/prod-${TIMESTAMP}.sql.gz"

echo
echo "Backup prod DB → $OUTFILE"
echo "  Remote: $REMOTE_HOST:$REMOTE_DIR"
echo

# Lösenord via MYSQL_PWD så det inte syns i ps på prod.
REMOTE_CMD=$(cat <<'REMOTE'
set -euo pipefail
cd "$REMOTE_DIR"

DB_DATABASE=$(awk -F= '$1=="DB_DATABASE"{sub(/^[^=]+=/,"");gsub(/^"|"$|^'\''|'\''$/,"");print;exit}' .env)
DB_ROOT_PASSWORD=$(awk -F= '$1=="DB_ROOT_PASSWORD"{sub(/^[^=]+=/,"");gsub(/^"|"$|^'\''|'\''$/,"");print;exit}' .env)

export MYSQL_PWD="$DB_ROOT_PASSWORD"

docker compose exec -T -e MYSQL_PWD mariadb mariadb-dump \
    --single-transaction \
    --skip-lock-tables \
    --quick \
    --default-character-set=utf8mb4 \
    --no-tablespaces \
    -u root \
    "$DB_DATABASE" \
    | gzip --fast
REMOTE
)

# Skriv till tempfil först, byt namn vid success — undvik halv-fil.
TMPFILE="${OUTFILE}.partial"
umask 077

# shellcheck disable=SC2029  # avsiktlig: REMOTE_DIR expanderas lokalt, skickas som literal till servern
ssh "$REMOTE_HOST" "REMOTE_DIR='$REMOTE_DIR' bash -s" <<< "$REMOTE_CMD" > "$TMPFILE"

# Sanity: gzip-filen ska kunna packas upp.
if ! gzip -t "$TMPFILE" 2>/dev/null; then
    echo "Fel: backup-filen är inte en giltig gzip. Avbryter." >&2
    rm -f "$TMPFILE"
    exit 1
fi

mv "$TMPFILE" "$OUTFILE"
chmod 600 "$OUTFILE"

SIZE_BYTES=$(wc -c < "$OUTFILE")
SIZE_HUMAN=$(awk -v b="$SIZE_BYTES" 'BEGIN{
    units="B KB MB GB"; split(units,u," ");
    i=1; while (b>=1024 && i<4){ b=b/1024; i++ }
    printf "%.1f %s", b, u[i]
}')

echo
echo "Klar. Backup: $OUTFILE ($SIZE_HUMAN)"
echo
echo "Återställa lokalt:"
echo "  gunzip -c $OUTFILE | docker compose exec -T mariadb mariadb -u root -p\"\$(grep ^DB_ROOT_PASSWORD .env | cut -d= -f2)\" \\"
echo "      \"\$(grep ^DB_DATABASE .env | cut -d= -f2)\""
