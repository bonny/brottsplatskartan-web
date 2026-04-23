#!/bin/bash
# Ladda ner mbtiles för tileservern.
# Filen är ~2.4 GB och ligger i Hetzner Object Storage (Helsinki).
# Används både lokalt och i provisioneringen av Hetzner-servern.
#
# Uppdateras via deploy/update-tiles.md (Planetiler-pipeline).

set -euo pipefail

TILES_DIR="$(dirname "$0")/tileserver"
TILES_FILE="$TILES_DIR/sweden-2026-04-23.mbtiles"
TILES_URL="https://brottsplatskartan.hel1.your-objectstorage.com/tiles/sweden-2026-04-23.mbtiles"

mkdir -p "$TILES_DIR"

if [ -f "$TILES_FILE" ]; then
	echo "→ $TILES_FILE finns redan ($(du -h "$TILES_FILE" | cut -f1)). Skippar."
	exit 0
fi

echo "→ Laddar ner mbtiles från Hetzner Object Storage (~2.4 GB)..."
curl -fL --progress-bar -o "$TILES_FILE" "$TILES_URL"
echo "✅ Klart: $TILES_FILE ($(du -h "$TILES_FILE" | cut -f1))"

# Rensa gamla .mbtiles-filer (tileserver-gl auto-detektar alla .mbtiles i /data
# och kan servera fel fil om gammal version ligger kvar)
find "$TILES_DIR" -maxdepth 1 -name "*.mbtiles" -not -name "$(basename "$TILES_FILE")" -print -delete
