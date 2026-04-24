#!/usr/bin/env bash
# Backup gamla Dokku-servern (dokku.eskapism.se) till lokal Mac.
#
# Backar upp:
#   - MariaDB-dump av mariadb-brottsplatskartan (gammal BPK-DB)
#   - Orphan storage-mappar (eskapism-multisite-uploads + simple-history-uploads)
#
# Appar (antonblomqvist.se, simple-fields) skippas — källkoden finns på GitHub.
#
# Kör från Mac:
#   bash deploy/backup-dokku-eskapism.sh
#
# Kräver:
#   - SSH-access till dokku.eskapism.se (se ~/.ssh/config)

set -euo pipefail

SSH_HOST="dokku.eskapism.se"
DATESTAMP=$(date +%Y-%m-%d)
DEST="$HOME/Backups/dokku-eskapism-$DATESTAMP"

mkdir -p "$DEST"
echo "→ Backup-destination: $DEST"

echo ""
echo "→ 1/2  Dumpar MariaDB (mariadb-brottsplatskartan)…"
ssh "$SSH_HOST" 'dokku mariadb:export mariadb-brottsplatskartan' \
    | gzip > "$DEST/mariadb-brottsplatskartan.sql.gz"
echo "   $(du -h "$DEST/mariadb-brottsplatskartan.sql.gz" | cut -f1) sparat."

echo ""
echo "→ 2/2  Tar:ar orphan storage-mappar…"
ssh "$SSH_HOST" '
    cd /var/lib/dokku/data/storage
    tar czf - eskapism-multisite-uploads simple-history-uploads
' > "$DEST/storage.tar.gz"
echo "   $(du -h "$DEST/storage.tar.gz" | cut -f1) sparat."

echo ""
echo "→ Verifiering:"
echo ""
echo "   DB-dump (första rader):"
zcat "$DEST/mariadb-brottsplatskartan.sql.gz" | head -3 | sed 's/^/     /'
INSERTS=$(zcat "$DEST/mariadb-brottsplatskartan.sql.gz" | grep -c "^INSERT INTO" || true)
echo "   INSERT-statements i dumpen: $INSERTS"
echo ""
echo "   Storage-arkiv (första filerna):"
tar tzf "$DEST/storage.tar.gz" | head -5 | sed 's/^/     /'
echo ""
echo "✓ Klart. Backup ligger i $DEST"
echo ""
echo "   Rekommendation: kopiera till extern disk eller Hetzner Object Storage."
echo "   Exempel: rclone copy \"$DEST\" \"Brottsplatskartan Hetzner:brottsplatskartan/dokku-backup-$DATESTAMP/\""
