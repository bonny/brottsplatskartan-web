**Status:** aktiv (idé — research saknas)
**Senast uppdaterad:** 2026-04-30
**Källa:** Inbox Brottsplatskartan (2026-04-30)

# Todo #55 — Kortare/snyggare URL:er för statiska kartbilder

## Sammanfattning

> Bild-urlar (för kartorna) blir väldigt långa. Kan vi göra dom snygga och
> mer SEO-vänliga. Använda egen url shortener?

## Bakgrund

`StaticMapUrlBuilder::circleUrl()` bygger URL:er av typen:

```
https://kartbilder.brottsplatskartan.se/styles/basic-preview/static/auto/617x463.jpg
  ?latlng=1
  &padding=0.35
  &path=fill:rgba(220,38,38,0.07)|stroke:...|width:0|59.123,18.456|59.124,18.457|...(48 punkter)
  &path=...(2 till lager)
```

Längd typiskt **2–4 KB per URL** med 3 cirkellager (48 punkter vardera).
Varje event-kort på en lista renderar en sån URL i `<img src>` →
HTML-bytes växer fort på listsidor (15–30 events × 4 KB = 60–120 KB
extra HTML, mest path-koordinater).

Risker:

- **HTML-payload-tyngd** påverkar CWV (LCP, TTFB).
- **OG/Twitter-share** av enskilda events skickar URL:n vidare —
  ser fult ut i preview.
- **Image search:** Google ser path-strängen som filnamn, ger sannolikt
  ingen hjälp i image-sökresultat.

## Förslag

**Alternativ A — proxy-route med kort URL (rekommenderas):**

- Ny route: `GET /k/{event_id}-{w}x{h}` →
  `KartbildController::show()` bygger long-URL via `StaticMapUrlBuilder`
  och **proxy:ar bytes** från tileserver till klient.
- Cachas i Spatie Response Cache med lång TTL (24h+).
- HTML-payload sjunker dramatiskt; cache-hit-rate på tileserver oförändrad.
- SEO: URL `/k/8821334-617x463.jpg` är ren och beskrivande.

**Alternativ B — redirect (302) till tileserver:**

- Som A men 302 istället för proxy. Snabbare implementation men varje
  bild kostar en extra HTTP-rundan. Slipper proxy-bandbreddskostnad
  men spräcker LCP.

**Alternativ C — short hash, slå upp i tabell:**

- `kartbild_urls (hash, long_url)` med `/k/{hash}.jpg`. Mer flexibelt
  men kräver migration + lagring. Onödigt komplex för en deterministisk
  long-URL som kan rekonstrueras från `event_id`.

**Rekommendation: A.** Bytes-vinst i HTML är värt en thin proxy-route.
Fall tillbaka till B om proxy visar sig CPU-dyr.

## Risker

- **Tileserver-bandbredd dubbelräknas** vid proxy (request → app →
  tileserver → app → klient). Mätperiod 30d post-launch — om
  Hetzner egress sticker iväg, byt till alt B.
- **Cache-invalidation** vid stilbyte (cirkel-färg etc.) — bumpa
  `STATIC_MAP_VERSION`-konstant och inkludera i cache-key.

## Confidence

**Medel-hög.** Tekniken är trivial; risken är att vinsten är liten på
LCP eftersom bilderna är `loading="lazy"` (verifiera först).

## Beroenden

- Bygger på #20 (kartbilder-med-cirklar), #15 (tiles-cache).

## Nästa steg

1. Mät: total HTML-bytes på `/lan/stockholms-lan/handelser/2026/04` —
   nu vs. om alla `<img src>` byts mot `/k/{id}-617x463.jpg`.
   Beslutsunderlag.
2. Mät: hur många kartbild-URL:er är `loading="lazy"` (förmodligen
   alla utom första). Det dämpar HTML-tyngd-effekten på LCP.
3. Om vinst > 50 KB / sida och CWV-budget tajt: bygg alt A.
