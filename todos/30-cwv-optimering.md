**Status:** aktiv (Fas A+B+C deployade 2026-04-26 — kvar: AdSense-config + Fas D)
**Senast uppdaterad:** 2026-04-26
**Härledd från:** #11 SEO-audit (CWV-baseline 2026-04-26)

# Todo #30 — CWV-optimering Fas 1

## Resultat på prod efter Fas A+B+C + self-host (2026-04-26)

| Metric          | Baseline | Efter | Δ          |
| --------------- | -------: | ----: | ---------- |
| Perf-poäng      |       51 |    80 | +29        |
| LCP             |     9.6s | 1.57s | -84 %      |
| FCP             |     4.1s | 1.57s | -62 %      |
| CLS             |    0.243 | 0.236 | oförändrat |
| Render-blocking |       12 |     0 | -12        |

CLS är fortfarande POOR — orsakas av AdSense Auto Ads, kräver
config-ändring i AdSense-dashboard (se Fas A nedan), inte kod.

Kommitar:

- `4fc169e` Fas A: defer scroll-snap-slider + DOMContentLoaded-wrapper
- `aaee7d4` Fas B: defer Leaflet-bundle + page-specifik charts.css
- `79b45b5` Fas C: byta interaktiv map-tilelayer från OSM till
  egna tileservern (WebP, ~70 % mindre/tile)
- `e0b6195` Self-hosta Leaflet + plugins (eliminerar 2 externa DNS)

## Varför

CWV-baseline (Lighthouse mobile, 2026-04-26) visar att **6 av 8 mätta
URL:er har POOR LCP** (>4.0s) och **5 av 8 har POOR CLS** (>0.25).
Startsidan `/` är värst med performance-poäng 51 och LCP 9.6s.

Google CWV är ranking-signal sedan 2021. POOR-status drar ner ranking
direkt — och 80% av vår trafik är mobil där det är värst.

## Mål

| Metric            | Idag (median) |      Target |
| ----------------- | ------------: | ----------: |
| Performance-poäng |            62 |   ≥ 70 alla |
| LCP               |          7.0s | < 4.0s alla |
| CLS               |         0.243 | < 0.10 alla |
| FCP               |          4.1s | < 2.5s alla |
| TBT               |          25ms |  (redan OK) |

## Topp-flaskhalsar (från Lighthouse opportunities)

### 1. Reduce unused JavaScript (6/8 sidor, ~1600-2180ms saving)

Sannolika syndare:

- **AdSense**-bibliotek — laddar mycket även när annonser inte syns
- **GA4** — gtag.js är hyfsat lättviktigt men fortfarande blockerande
- **Leaflet** + plugins — ingår även på sidor utan kartor (?)
- **Vendor-bundles** — Bootstrap, jQuery m.fl.

**Action:**

- Audit `public/js/app.js` för oanvänd kod
- `defer`/`async` på externa script-taggar i `layouts/web.blade.php`
- Code-split: ladda Leaflet bara när karta finns på sidan
- Lazy-load AdSense-script tills användaren scrollar nära ad-slot

### 2. Eliminate render-blocking resources (5/8 sidor, ~600-1300ms saving)

CSS i `<head>` blockar render. Specifikt:

- Tailwind/Bootstrap-bundle är troligen för stor
- Externa fonts (om vi har sådana)

**Action:**

- Inline kritisk CSS för above-fold-content
- Ladda resten av CSS asynkront via `preload` + `onload`
- Mät vad som faktiskt renderas i viewport för varje sidtyp

### 3. Serve images in next-gen formats (~780-870ms saving på `/` och `/stockholm`)

Sannolika syndare:

- Hero-bilder + thumbnails som JPG/PNG
- Kartbilder från tileservern (om de inte är WebP)

**Action:**

- AVIF + WebP för thumbnails via `<picture>`-tag
- Fallback till JPG för äldre browsers
- Ev. on-the-fly konvertering via en image proxy
- Verifiera att `kartbilder.brottsplatskartan.se`-tileservern serverar
  WebP/AVIF för rasterized maps

### 4. CLS-fixar (5/8 sidor, ~0.20-0.32 CLS-värde)

Layout shift orsakas av:

- Bilder utan explicit `width`/`height`
- Dynamiskt injicerade ad-units utan reserverad plats
- Fonts som byter (FOIT/FOUT)

**Action:**

- Audit alla `<img>` — säkerställ explicit width/height eller
  `aspect-ratio` CSS
- Reservera utrymme för ads med `min-height` på containern
- `font-display: swap` + lokal font-fallback med matched metrics

## Implementation-ordning

Strikt sekventiell — mät efter varje fas så vi kan rollback om något
gör värre.

### Fas A: CLS-fix (KRÄVER AdSense-config-ändring, inte kod)

**Diagnos 2026-04-26 (startsidan):**

| Miljö                | CLS       | LCP   | FCP   | Perf |
| -------------------- | --------- | ----- | ----- | ---- |
| Prod (med AdSense)   | **0.243** | 9.6 s | 4.1 s | 51   |
| Lokalt (utan AdSense) | **0**     | 5.9 s | 2.4 s | 72   |

Lighthouse-tracen visar EN enda layout-shift: `<article>` (3:e
hero-eventet) flyttas ~590 px under render. Lokalt utan AdSense är CLS
exakt 0 — alltså ingen kod-fix räcker. Hela CLS kommer från AdSense
Auto Ads som injicerar in-page-annonser mellan map-widgeten och hero-
eventen.

**Verifierat kod-side OK:**

- Alla `<img>` har explicit `width`/`height` (90×90 list, 50×50
  timeline, 640×340 hero) ✓
- `.fill { width: 100%; height: auto }` funkar tillsammans med HTML-
  attributen — moderna browsers räknar `aspect-ratio` från attributen ✓
- `.EventsMap { height: 70dvh }` reserverar plats ✓
- `.NotificationBar` är `display: none` om inget innehåll ✓
- Font-stack är 100 % systemfonts (`-apple-system, BlinkMacSystemFont,
  ...`) — inga `@font-face`, ingen FOIT/FOUT ✓

**Två vägar för faktisk fix:**

a. **Stäng av in-page Auto Ads i AdSense-dashboard** (rekommenderas).
   Logga in på AdSense → Sites → brottsplatskartan.se → Auto ads →
   slå av "In-page ads", behåll "Anchor" + "Vignette". Anchor-ads är
   `position: fixed` och skapar inte CLS. Förlust: kan vara mindre
   ad-impressions, men CLS-straffet är värre för ranking.

b. **Manuell ad-placement** (mer kontroll, mer jobb). Skapa
   `<ins class="adsbygoogle">`-slots i Blade med fast `min-height`-
   container. Inaktivera Auto ads helt. Gör att vi kan välja position
   ovanför fold för viewability men reservera höjd så ingen CLS sker.

Default-väg: börja med (a). Mät RPM före/efter via AdSense+GA4. Om
intäkterna sjunker > 10 %, gå till (b) på topp-trafikerade sidtyper
(`/`, `/handelser`, `/lan/*`, `/<stad>`).

**Acceptanskriterium:** CLS < 0.10 på prod (alla sidor).

### Fas B: Render-blocking + JS-defer (2-3 dagar)

5. Inline kritisk CSS för layouts/web.blade.php
6. `defer` på alla `<script>` utom kritiska (GA4 init räcker async)
7. Lazy-load AdSense efter LCP

**Acceptanskriterium:** FCP < 2.5s på alla sidor.

### Fas C: Image-formats (1-2 dagar)

8. WebP/AVIF för thumbnails
9. Verifiera tileserver-output

**Acceptanskriterium:** LCP < 4.0s på alla sidor.

### Fas D: JS-bundle-reduktion (2-3 dagar)

Sista och svåraste — kräver mer arbete.

10. Code-split Leaflet
11. Tree-shake övriga bibliotek
12. Mät bundle size före/efter

**Acceptanskriterium:** Performance ≥ 70 på alla sidor.

## Risker

- **AdSense viewability** — om vi lazy-loadar för aggressivt sjunker
  CPM. Mät RPM före/efter.
- **JS-fixar kan bryta interactivity** — t.ex. om Leaflet inte
  laddas i tid på map-sidor. Testa på alla sidtyper.
- **Inline-kritisk-CSS kan bli stor** — skydda HTML-storlek. Bör vara
  < 14 KB inline (TCP slow start).
- **WebP-konvertering på externa kartbilder** — om tileservern inte
  serverar WebP måste vi konvertera on-the-fly eller pre-bake.

## Mätning

Efter varje fas: kör om Lighthouse mot samma 8 URL:er
(`tmp-cwv/`-mappen har baseline). Jämför mot mål-tabellen.

Efter ALLA faser: mät RPM/session via GA4 + AdSense att vi inte
tappat intäkter.

## Beroenden

- **#27** kommer lägga till trend-grafer + heatmaps som påverkar CWV
  direkt. Synka — vi vill inte fixa LCP nu och sedan tillbaka-
  introducera problem via #27.
- **#31 TTFB-anomali** — påverkar specifikt `/lan/Värmlands län`.
  Separat men samma mätrunda.

## Tid

1-2 veckor totalt (Fas A + B + C + D + mätning mellan).

## Status

Designfas. Kan börja på Fas A direkt eftersom CLS-fixar är låg risk
och oberoende av övriga arkitektur-todos.
