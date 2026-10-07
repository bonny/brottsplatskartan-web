#!/usr/bin/env python3
"""
Länsgeometri för Brottsplatskartan (todo #78). Två steg, kör från repo-roten:

  python3 deploy/lansgeometri.py granser
      Hämtar Sveriges 21 länsgränser från OpenStreetMap (Nominatim) och
      sparar dem förenklade i resources/geo/lansgranser.geojson.
      properties.name har samma form som Helper::getAllLan(), t.ex.
      "Skåne län". OBS: OSM:s länsgränser inkluderar havsområdet ut till
      territorialgränsen (Gotland ~15 000 km² mot ~3 100 km² land).

  python3 deploy/lansgeometri.py cirklar <handelsekoordinater.json>
      Räknar fram en "områdescirkel" per län och sparar den i
      resources/geo/lanscirklar.json. Används av StaticMapUrlBuilder::
      areaUrl() för sammanfattningar. Indata: {"<län>": [[lat, lng], ...]}
      med ett års händelsekoordinater, exporterad från prod — se
      docs/lansgeometri.md för kommandot.

Mitt = polygonens ytviktade tyngdpunkt (länets geometriska mitt). Radie =
avståndet från mitten som täcker 90 % av länets händelseplatser (minst
MIN_RADIE_M). Varför inte bara ytan: en cirkel med länets yta blir mest
hav för kustlän. Varför inte median av platserna som mitt: den dras mot
storstaden, så Norrbottens cirkel missade Kiruna och Gotlands södra ön.
Varje unik plats räknas en gång, så länets standardpunkt (händelser som
bara geokodats till länet) inte väger tungt.

Data © OpenStreetMap-bidragsgivare, ODbL. Nominatims användarvillkor:
max 1 anrop/s och en identifierande User-Agent — skriptet följer dem.
"""

import json
import math
import sys
import time
import urllib.parse
import urllib.request

LAN = [
    "Blekinge län", "Dalarnas län", "Gotlands län", "Gävleborgs län",
    "Hallands län", "Jämtlands län", "Jönköpings län", "Kalmar län",
    "Kronobergs län", "Norrbottens län", "Skåne län", "Stockholms län",
    "Södermanlands län", "Uppsala län", "Värmlands län", "Västerbottens län",
    "Västernorrlands län", "Västmanlands län", "Västra Götalands län",
    "Örebro län", "Östergötlands län",
]

GRANSER_FIL = "resources/geo/lansgranser.geojson"
CIRKLAR_FIL = "resources/geo/lanscirklar.json"

# ~0,005° ≈ 300–500 m. Tillräckligt för kartbilder och punkt-i-polygon,
# och håller filen liten (~85 kB).
FORENKLING_GRADER = 0.005
USER_AGENT = "brottsplatskartan.se lansgeometri (par.thernstrom@gmail.com)"
JORDRADIE_M = 6371008.8
TACKNING = 0.90
MIN_RADIE_M = 15000


def hamta_grans(lan):
    url = "https://nominatim.openstreetmap.org/search?" + urllib.parse.urlencode({
        "q": f"{lan}, Sverige",
        "format": "json",
        "countrycodes": "se",
        "polygon_geojson": 1,
        "polygon_threshold": FORENKLING_GRADER,
        "limit": 5,
    })
    req = urllib.request.Request(url, headers={"User-Agent": USER_AGENT})
    with urllib.request.urlopen(req, timeout=60) as svar:
        traffar = json.load(svar)

    # Länet är en administrativ relation; förkasta orter/kommuner med
    # liknande namn.
    for t in traffar:
        if t.get("osm_type") == "relation" and t.get("type") == "administrative" \
                and t["geojson"]["type"] in ("Polygon", "MultiPolygon"):
            return t
    raise SystemExit(f"Hittade ingen länsgräns för {lan!r}: {[t.get('display_name') for t in traffar]}")


def avrunda(geometri):
    def ring(r):
        return [[round(x, 5), round(y, 5)] for x, y in r]
    if geometri["type"] == "Polygon":
        return {"type": "Polygon", "coordinates": [ring(r) for r in geometri["coordinates"]]}
    return {"type": "MultiPolygon", "coordinates": [[ring(r) for r in p] for p in geometri["coordinates"]]}


def granser():
    features = []
    for lan in LAN:
        t = hamta_grans(lan)
        features.append({
            "type": "Feature",
            "properties": {"name": lan, "osm_relation_id": int(t["osm_id"])},
            "geometry": avrunda(t["geojson"]),
        })
        print(f"{lan:22} relation {t['osm_id']}")
        time.sleep(1.1)

    with open(GRANSER_FIL, "w", encoding="utf-8") as f:
        json.dump({
            "type": "FeatureCollection",
            "attribution": "© OpenStreetMap-bidragsgivare, ODbL",
            "features": features,
        }, f, ensure_ascii=False, separators=(",", ":"))
        f.write("\n")


def polygoner(geometri):
    return [geometri["coordinates"]] if geometri["type"] == "Polygon" else geometri["coordinates"]


def i_ring(lng, lat, ring):
    inne = False
    for (x1, y1), (x2, y2) in zip(ring, ring[1:] + ring[:1]):
        if (y1 > lat) != (y2 > lat) and lng < (x2 - x1) * (lat - y1) / (y2 - y1) + x1:
            inne = not inne
    return inne


def i_lan(lat, lng, geometri):
    for poly in polygoner(geometri):
        if i_ring(lng, lat, poly[0]) and not any(i_ring(lng, lat, hal) for hal in poly[1:]):
            return True
    return False


def tyngdpunkt(geometri):
    """Ytviktad tyngdpunkt (lat, lng) över alla yttre ringar, via lokal
    ekvirektangulär projektion. Hål ignoreras — försumbart för län."""
    ringar = [poly[0] for poly in polygoner(geometri)]
    lat0 = math.radians(sum(y for r in ringar for _, y in r) / sum(len(r) for r in ringar))
    kx = math.cos(lat0)

    total_a = cx = cy = 0.0
    for r in ringar:
        pts = [(x * kx, y) for x, y in r]
        a = sx = sy = 0.0
        for (x1, y1), (x2, y2) in zip(pts, pts[1:] + pts[:1]):
            k = x1 * y2 - x2 * y1
            a += k / 2
            sx += (x1 + x2) * k / 6
            sy += (y1 + y2) * k / 6
        # Nominatim garanterar inte samma ringriktning för alla delar av en
        # MultiPolygon; en ö med motsatt riktning skulle annars dras ifrån.
        if a < 0:
            a, sx, sy = -a, -sx, -sy
        total_a += a
        cx += sx
        cy += sy

    return cy / total_a, (cx / total_a) / kx


def avstand_m(a, b):
    dlat = math.radians(b[0] - a[0])
    dlng = math.radians(b[1] - a[1])
    h = math.sin(dlat / 2) ** 2 + math.cos(math.radians(a[0])) * math.cos(math.radians(b[0])) * math.sin(dlng / 2) ** 2
    return 2 * JORDRADIE_M * math.asin(math.sqrt(h))


def cirklar(koordinatfil):
    with open(GRANSER_FIL, encoding="utf-8") as f:
        geo = {ft["properties"]["name"]: ft["geometry"] for ft in json.load(f)["features"]}
    with open(koordinatfil, encoding="utf-8") as f:
        koordinater = json.load(f)

    resultat = {}
    for lan in LAN:
        # Felgeokodade punkter utanför länet skulle dra iväg mitten.
        pts = [(la, lo) for la, lo in koordinater.get(lan, []) if i_lan(la, lo, geo[lan])]
        if len(pts) < 20:
            raise SystemExit(f"För få platser inom {lan} ({len(pts)}) — kontrollera exporten")
        mitt = tyngdpunkt(geo[lan])
        avstand = sorted(avstand_m(mitt, p) for p in pts)
        radie = max(MIN_RADIE_M, avstand[int(TACKNING * (len(avstand) - 1))])
        resultat[lan] = {"lat": round(mitt[0], 4), "lng": round(mitt[1], 4), "radie_m": int(round(radie, -3))}
        print(f"{lan:22} {len(pts):6} platser  mitt {mitt[0]:.3f},{mitt[1]:.3f}  r={radie / 1000:.0f} km")

    with open(CIRKLAR_FIL, "w", encoding="utf-8") as f:
        json.dump(resultat, f, ensure_ascii=False, indent=2)
        f.write("\n")


if __name__ == "__main__":
    if len(sys.argv) >= 2 and sys.argv[1] == "granser":
        granser()
    elif len(sys.argv) == 3 and sys.argv[1] == "cirklar":
        cirklar(sys.argv[2])
    else:
        raise SystemExit(__doc__)
