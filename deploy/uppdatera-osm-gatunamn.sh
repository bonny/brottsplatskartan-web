#!/usr/bin/env bash
# Lägger till nya gatunamn från Geofabriks Sverige-extrakt i
# resources/openstreetmap/highways_sorted_unique.txt och genererar om
# swedish-cities-sorted-unique.txt.
# Se docs/openstreetmap-gatunamn.md för bakgrund och hur resultatet granskas.
#
# Kör från repo-roten: ./deploy/uppdatera-osm-gatunamn.sh
# Hoppa över nedladdningen (återanvänd befintlig pbf): SKIP_DOWNLOAD=1
set -euo pipefail

cd "$(dirname "$0")/.."
ROOT="$(pwd)"
WORK="$ROOT/tmp-osm-gatunamn"
OUT="$ROOT/resources/openstreetmap"
IMAGE="bpk-osmium:local"
PBF_URL="https://download.geofabrik.de/europe/sweden-latest.osm.pbf"

mkdir -p "$WORK/download"

if [[ "${SKIP_DOWNLOAD:-0}" != "1" ]]; then
    curl -fsSL -o "$WORK/download/sweden-latest.osm.pbf" "$PBF_URL"
    curl -fsSL -o "$WORK/download/sweden-latest.osm.pbf.md5" "$PBF_URL.md5"
    (cd "$WORK/download" && md5 -r sweden-latest.osm.pbf | cut -d' ' -f1 | grep -qxF "$(cut -d' ' -f1 sweden-latest.osm.pbf.md5)")
fi

# osmium-tool i en liten Debian-image, så inget behöver installeras på hosten.
printf 'FROM debian:bookworm-slim\nRUN apt-get update && apt-get install -y --no-install-recommends osmium-tool && rm -rf /var/lib/apt/lists/*\n' \
    | docker build -q -t "$IMAGE" - >/dev/null

osmium() {
    docker run --rm -v "$WORK":/data -w /data "$IMAGE" osmium "$@"
}

# Bara ways med highway=*. Den gamla osmosis-körningen
#   osmosis --read-pbf ... --tf accept-ways highway=* --write-xml
# släppte även igenom alla noder och relationer (butiker, hållplatser,
# busslinjer). I dagens OSM ger de massor av felträffar på vanliga ord
# (finns, ambulans, kvinna, mellan …), så de tas inte med längre.
# -R = ta inte med refererade noder.
osmium tags-filter -R -O -o highways.osm download/sweden-latest.osm.pbf w/highway
# Motsvarar: --tf accept-nodes place=city,town,village --tf reject-relations --tf reject-ways
osmium tags-filter -R -O -o places.osm download/sweden-latest.osm.pbf n/place=city,town,village

# Avkoda XML-entiteterna (&amp; sist), ta bort tomma rader, sortera.
clean_names() {
    sed -e 's/&lt;/</g' -e 's/&gt;/>/g' -e 's/&quot;/"/g' -e "s/&apos;/'/g" \
        -e 's/&#x[0-9A-Fa-f]*;/ /g' -e 's/&amp;/\&/g' \
        | grep -v '^[[:space:]]*$' \
        | LC_ALL=C sort -u
}

# Plocka ut värdet på taggen "name".
extract_names() {
    grep -h 'k="name"' "$@" | cut -d'"' -f4 | clean_names
}

# Nya gatunamn tas bara med om de innehåller mellanslag eller slutar på en
# typisk gatuändelse. Enordsnamn utan ändelse är i OSM ofta vanliga ord
# (Okänd, Norra, Hållplats, Rondell, Avfarten, Transport …).
GATUANDELSE='(väg|vägen|gata|gatan|gränd|gränden|stig|stigen|allé|allén|backe|backen|torg|torget|plan|planen|led|leden|gång|gången|kaj|kajen|bro|bron|park|parken|esplanad|esplanaden|promenad|promenaden|stråk|stråket|slinga|slingan|ringen|vall|vallen|tunnel|tunneln|kvarn|platsen)$'

# Gatunamn som klarar filtret ovan men ändå ger felträffar i polistexter
# (uppmätt mot 2 000 prod-händelser 2026-10-07).
STOPPORD=(motorvägen 'gång i' 'gårds väg')

stoppord_args=()
for ord in "${STOPPORD[@]}"; do
    stoppord_args+=(-e "$ord")
done

extract_names "$WORK/highways.osm" \
    | grep -E " |$GATUANDELSE" \
    | LC_ALL=en_US.UTF-8 grep -vixF "${stoppord_args[@]}" \
    > "$WORK/nya-gatunamn.txt"

# Nya gatunamn läggs till den befintliga listan; inget tas bort. Den gamla
# listan innehåller ortnamn, län och platser som inte går att återskapa ur
# highway-ways (umeå, södermalm, östergötlands län …).
cat "$OUT/highways_sorted_unique.txt" "$WORK/nya-gatunamn.txt" | clean_names > "$WORK/highways.txt"
mv "$WORK/highways.txt" "$OUT/highways_sorted_unique.txt"

extract_names "$WORK/places.osm" > "$OUT/swedish-cities-sorted-unique.txt"

wc -l "$OUT/highways_sorted_unique.txt" "$OUT/swedish-cities-sorted-unique.txt"
