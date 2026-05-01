**Status:** ✅ Klar 2026-05-01. Alt D (densitet-kapning) + Alt B (301 redirect via `/k/v1/`) + StripCookies-mw live i prod. Alla blade-callers bytta — event-page: 0 long-URLs kvar i HTML, listsida: 50 thumbnails à ~57 bytes (var ~850 bytes). OG/Twitter-meta + Schema.org JSON-LD-images går också via `/k/v1/`. Verifierad end-to-end inkl. FB-UA-spoofing.
**Senast uppdaterad:** 2026-05-01
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
HTML-bytes växer fort på listsidor.

### Mätning på `/stockholm/handelser/2026/04` (live, 2026-04-30)

| Metric                          | Värde                          |
| ------------------------------- | ------------------------------ |
| Total HTML (raw)                | 1 008 668 bytes (~1 MB)        |
| Kartbilder-URL:er (src+srcset)  | 204 st                         |
| Bytes i URL:erna                | 807 024 (**80 % av all HTML**) |
| Snitt-URL-längd                 | 3 956 bytes                    |
| Gzip-9 (faktisk transfer)       | 79 439 bytes                   |
| Gzip efter `/k/X`-ersättning    | 22 226 bytes                   |
| **Gzip-besparing per pageview** | **~57 KB transfer, -72 %**     |

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

**Alternativ D — kapa path-density per storlek (komplement, ingen ny route):**

- Idag får alla storlekar (även 160×160-thumbnails) **3 lager × 48
  punkter** i `edgeFadedCirclePaths()`. På en 160px-thumb syns ingen
  gradient — 1 lager × 24 punkter räcker visuellt. Kapar URL från ~4 KB
  till ~600 bytes utan ny infra.
- Snabb-fix: ny parameter på `circleUrl()` (`$density = 'low' | 'high'`),
  per-storlek-default i blade-callers.

**Rekommendation: D först (ren win, ingen infra), sedan B (301 +
`immutable` + Spatie Response Cache).** Alt B är billigare än A eftersom
long-URL:n är deterministisk från `event_id` → permanent browser-cache
funkar och PHP-FPM-workern slipper proxa bytes. 301-responsen cachas
av Spatie i Redis (befintlig stack); browser-cachen tar resten. Alt A
bara om B visar problem med image-search eller cache-thrash.

### Varför inte Alt A (proxy)

- Bandbreddsdubblering (Hetzner egress räcker, men onödigt).
- PHP-FPM-worker låst i ~50–200 ms per fresh request → kan köa under
  bot-bursts.
- Spatie response-cache räddar 2:a hit, men 1:a hit per unik URL kostar
  full worker-tråd.

### CPU-impact per /k/-hit (Alt B)

| Steg                                  | Tid       |
| ------------------------------------- | --------- |
| PHP-FPM + Laravel boot                | ~10–15 ms |
| `CrimeEvent::find($id)` (PK + cache)  | ~1 ms     |
| `circlePath()` × 3 × 48 (144 cos/sin) | ~0.5 ms   |
| **Total per fresh hit**               | ~12–17 ms |

Med Spatie Response Cache (Redis, befintlig) på `/k/*`-routen:
returnerande besökare och crawls hittar 301:an i cachen → ingen full
Laravel-render. Browser-cachen (`immutable`) tar resten — samma user
träffar aldrig servern igen för samma bild. CPU-ökning under cold-cache
crawl-bursts: ~1–3 %. Acceptabelt på CX33.

## URL-format (Alt B)

```
/k/{event_id}-{w}x{h}.jpg
/k/{event_id}-{w}x{h}@2x.jpg
/k/{event_id}-far-{w}x{h}.jpg
/k/{event_id}-far-{w}x{h}@2x.jpg
```

Ingen DB-tabell — long-URL:n är deterministisk från `event_id`, storlek och
`mode` (close/far). Controller rekonstruerar via befintlig
`StaticMapUrlBuilder`. Ingen migration, ingen hash-mappning.

### Slug i URL: avfärdat efter GSC-mätning (2026-05-01)

Övervägt format: `/k/{id}-stockholm-sodermalm-{w}x{h}.jpg` för att ge
Google Image Search en plats-/sökord-signal i bild-URL:n.

Avfärdat efter GSC-data 90d (2026-01-31 → 2026-04-30):

| Source | Clicks   | Impressions | Avg position |
| ------ | -------- | ----------- | ------------ |
| WEB    | ~143 000 | ~2.6 M      | ~10–11       |
| IMAGE  | ~470     | ~180 000    | **~45**      |

Image-search är **0.33 % av clicks** (470 / 143 000). Top-queries är
event-specifika (`ida falkenberg försvunnen`, `explosion älgö`) där
page-URL:n redan bär plats + händelsetyp. Slug i bild-URL skulle vara en
tredjehands-signal när Google redan har en förstahands-signal i page-URL +
alt-text. Realistisk lyft: ~25 extra clicks/90d. Inte värt arkitekturen
(canonical-redirects, slug-drift vid re-geocoding, ~1.5 KB extra
transfer/pageview som äter ~2 % av #55:s besparing).

→ **Investera i `getMapAltText()` istället, se #62.**

### Versionering

`STATIC_MAP_VERSION = 'v2'` i config + URL-prefix: `/k/v2/...`. Vid
stilbyte: bumpa version → browser/edge cache rensas automatiskt eftersom
URL ändras. `immutable` Cache-Control kvarstår.

## Risker

- **Cache-invalidation** vid stilbyte (cirkel-färg etc.) — hanteras via
  version-prefix i URL, se ovan.
- **Cold-cache + crawl-burst** = PHP-FPM får jobba. Spatie Response
  Cache fångar de aktivt requestade URL:erna, men en bot som drar 5000
  okända events i rad kostar ~1–3 % CPU under bursten. Acceptabelt på
  CX33; mät under första veckan post-launch.
- **Migration av statiska metaImage** (`single-event.blade.php` → OG/JSON-LD)
  — befintliga delade länkar med long-URL fortsätter funka oförändrat
  eftersom long-URL:n är giltig parallellt. Bara HTML-render byts till
  `/k/...`-form.

## Confidence

**Medel-hög.** Tekniken är trivial; risken är att vinsten är liten på
LCP eftersom bilderna är `loading="lazy"` (verifiera först).

## Beroenden

- Bygger på #20 (kartbilder-med-cirklar), #15 (tiles-cache).
- _(Tidigare blockerad av #61 / Caddy cache-handler — avfärdat
  2026-05-01: 301-responsen cachas tillräckligt av Spatie + browser-
  immutable.)_

## Nästa steg

1. ✅ **Alt D implementerad 2026-05-01.** `$density='low'` på `circleUrl()` /
   `edgeFadedCirclePaths()` ger 1 lager × 24 punkter. Sätts i
   `list-item.blade.php` (160×160-thumbs). Card-storlekarna (617×463,
   640×320) behåller `'high'`. Lokalt: 4092 → 854 bytes per thumb-URL
   (-79 %). Live i prod 2026-05-01 — verifierad.

2. ✅ **Alt B (301 redirect) implementerad 2026-05-01, shadow-deployad.**
   Route `/k/v1/{spec}.jpg` → `KartbildController::show()` returnerar 301
   med `Cache-Control: public, max-age=31536000, immutable`. Spec-format:
   `(circle|circle-low|near|far)-{id}-{w}x{h}[@2x].jpg`. Skippar
   session/CSRF/debugbar-middleware (Set-Cookie skulle bryta cache).
   Spatie Response Cache fångar 301:an automatiskt
   (`hasCacheableResponseCode()` returnerar true för redirections).

3. ✅ **`StripCookiesForCachableImages` middleware tillagd 2026-05-01.**
   Defense-in-depth utöver routens `withoutMiddleware`-skipp: ligger
   FÖRST i web-gruppen så att den kör SIST på response, fångar cookies
   som framtida paket lägger via mw. Begränsning: cookies satta via
   `RequestHandled`-event-listeners (Debugbar i dev) körs efter
   mw-stacken och fångas inte — men Debugbar är inaktiv i prod.
   Mönstret från Aaron Francis (2025).

4. ✅ **Prod-headers verifierade 2026-05-01.**
   `https://brottsplatskartan.se/k/v1/circle-{id}-617x463.jpg`:
   `HTTP/2 301`, `cache-control: immutable, max-age=31536000, public`,
   `location: https://kartbilder.brottsplatskartan.se/...`,
   **inga `Set-Cookie`-headers**. TTFB ~100 ms (Hetzner Helsinki-latens
    - Laravel boot + Redis-lookup; Spatie Response Cache cachar 301:an).

5. **Nästa: byt blade-callers att rendera `/k/v1/...`-URL:er.**
   `card.blade.php`, `list-item.blade.php`, `events-box.blade.php`,
   `event-map-far.blade.php`, `single-event.blade.php` (metaImage).
   Mät: total HTML-bytes per pageview, gzip-transfer, CWV (CrUX),
   PHP-FPM CPU under bot-burst. Rulla ut blade åt gången, `git revert`
   per commit om regression.

## Web-research findings (2026-05-01)

Sökte best-practice 2026 för cookieless image-routes i Laravel. Fynd:

- **Aaron Francis (2025)** publicerade en guide för "cookieless,
  cache-friendly image proxy" där han **proxar bytes** (inte 301) och
  förlitar sig på Cloudflare framför origin för edge-caching. Cache-
  Control: `public, max-age=2592000, s-maxage=2592000, immutable`.
  Hans cookie-strip-mw är samma mönster som vi använder (registrerad
  först i web-gruppen → kör sist på response).
- **301 vs 302 vs 308:** 301 cachas av CDN:er enligt cache-control
  (samma regler som 200). 308 är "modern 301" som preserverar
  HTTP-method — onödigt för GET-image-URLs.
- **`immutable`-direktivet** är fortfarande standard för långsiktig
  cache; tells caches "no need to revalidate while fresh".

### Vår lösning vs Aarons (skillnader)

|               | Aaron (proxa bytes) | Vi (Alt B, 301)                                 |
| ------------- | ------------------- | ----------------------------------------------- |
| Mönster       | Proxa image-bytes   | 301 redirect till tileservern                   |
| TTL           | 30 dagar            | 1 år                                            |
| `s-maxage`    | Ja                  | Nej (ingen CDN ändå)                            |
| Cache framför | Cloudflare          | Ingen — bara Hetzner Caddy (cachar inte bilder) |
| Cookie-strip  | Middleware          | `withoutMiddleware` + StripCookies-mw           |

### Framtida förbättringar (ej nu, ej blockerande)

1. **Lägg Cloudflare framför `brottsplatskartan.se`** (gratis Free-tier)
   → cold-hits warm-cachas globalt → 0 origin-PHP för repeat-traffic
   från andra users. Större strukturell förändring. Skapa egen todo om
   prod-trafiken växer eller CPU-press syns.
2. **Återöppna #61 (Caddy + cache-handler / Souin)** med disk-storage
   (Badger) istället för Redis — slipper RAM-pressure som var skälet
   till att vi avfärdade. Mindre invasivt än Cloudflare men kräver
   xcaddy-byggd image. YAGNI tills mätning visar behov.
