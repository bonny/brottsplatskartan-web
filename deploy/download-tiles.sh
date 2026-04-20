#!/bin/bash
# Ladda ner mbtiles för tileservern.
# Filen är 1.21 GB och ligger i Hetzner Object Storage (Helsinki).
# Används både lokalt och i provisioneringen av Hetzner-servern.

set -euo pipefail

TILES_DIR="$(dirname "$0")/tileserver"
TILES_FILE="$TILES_DIR/2017-07-03_europe_sweden.mbtiles"
TILES_URL="https://brottsplatskartan.hel1.your-objectstorage.com/tiles/2017-07-03_europe_sweden.mbtiles"

mkdir -p "$TILES_DIR"

if [ -f "$TILES_FILE" ]; then
	echo "→ $TILES_FILE finns redan ($(du -h "$TILES_FILE" | cut -f1)). Skippar."
	exit 0
fi

echo "→ Laddar ner mbtiles från Hetzner Object Storage (~1.21 GB)..."
curl -fL --progress-bar -o "$TILES_FILE" "$TILES_URL"
echo "✅ Klart: $TILES_FILE ($(du -h "$TILES_FILE" | cut -f1))"
