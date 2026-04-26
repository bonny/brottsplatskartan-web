**Status:** aktiv 2026-04-26 (Fas 1 + mest av Fas 2 klar; **CWV-baseline mätt 2026-04-26 — performance-arbete behövs**)
**Senast uppdaterad:** 2026-04-26

# Todo #11 — SEO-audit enligt best practice 2026

_Skapad: 2026-04-21. Ersätter och inkluderar tidigare todo #2 (legacy SEO-review)._

## Status

**Fas 1 KLAR 2026-04-21** (commit `83b3b19` + `27241d5`):

- Sitemap: `spatie/laravel-sitemap`, `sitemap:generate`-command, scheduler var 30 min
- `Sitemap:`-rad i `public/robots.txt`
- Canonical + meta-description-fallback i `layouts/web.blade.php`
- BreadcrumbList JSON-LD i `parts/breadcrumb.blade.php`
- Global Organization + WebSite JSON-LD (med SearchAction) i layout
- Dubbel `<meta name="robots">` sammanslagen
- Alt-text: verifierat att alla `<img>` har alt-attribut (inga saknas)

**Fas 2 KLAR (stor del) 2026-04-21** (commits `ece48de`, `793287d`, `0cf7e1d`):

- Place/City/AdministrativeArea JSON-LD på `single-plats`, `city`, `single-lan`
  via delad partial `parts/place-jsonld.blade.php`.
- H1-audit: startsidans 9 h1 från hero-partials → 0 (acceptabelt), nyckelsidor
  har exakt 1 h1 per sida (`previousPartners` fixad samtidigt).
- ItemList JSON-LD på `/lan` (21), `/typ` (120), `/plats` (330), `/mest-last`
  via delad partial `parts/itemlist-jsonld.blade.php`.
- Dataset JSON-LD på `/statistik` (creator, isBasedOn, license,
  spatialCoverage, keywords).
- Preconnect + dns-prefetch till tileserver, GA4, AdSense i layout head.
- LCP-fix: första hero-bilden `loading="eager"` + `fetchpriority="high"`.

Skippat (motiverat):

- FAQPage på `/sida/om` — prosa, inte Q&A → schema-spam.
- Per-län RSS-feeds — 21 endpoints för marginell SEO-vinst.

Kvar i Fas 2 (kräver beslut / GA4-data / post-cutover):

- ✅ ~~Beslut: `noindex`+canonical eller borttagning av `/plats/*/handelser/{date}`~~
  — **Klart via #1** (cache-exkludering 30d, behåller routerna pga GSC-data)
- ✅ ~~Core Web Vitals efter Hetzner-cutover~~ — **Mätt 2026-04-26**, se sektion nedan
- 🔄 Noindex-strategi för gamla/thin events (`crimeevents:mark-thin`) —
  **flyttad till egen #29** (audit + reducera indexerade pages)
- Internal anchor text-audit (grep efter "läs mer", "klicka här")
- Auto-genererad OG-image per event (typ + plats-overlay)
- Image sitemap (kartbilder per event) — Google Images-trafik
- Utökad NewsArticle-markup (fler bildformat 1x1/4x3/16x9, articleSection,
  inLanguage, dateModified från faktisk updated_at, contentLocation som
  Place istället för GeoCircle) — se skiss nedan i dokumentet

Fas 3 kvarstår enligt plan nedan.

## CWV-baseline (Lighthouse mobile, 2026-04-26)

Mätt via Lighthouse CLI på 8 representativa URL:er (mobile, simulated
throttling). Sortera efter performance-poäng:

| URL                                     |   Perf |     LCP |  FCP |  TBT |      CLS |   SI |
| --------------------------------------- | -----: | ------: | ---: | ---: | -------: | ---: |
| /helikopter                             | **80** |    2.7s | 1.0s | 30ms | 0.323 ❌ | 3.1s |
| /goteborg                               | **79** | 3.2s ⚠️ | 2.1s | 23ms | 0.248 ❌ | 2.5s |
| /handelser/10-april-2026                |     65 | 6.8s ❌ | 4.1s | 28ms | 0.000 ✅ | 5.3s |
| /skane-lan/brand-staffanstorp-...500640 |     62 | 8.5s ❌ | 4.7s | 22ms | 0.000 ✅ | 5.8s |
| /stockholm                              |     60 | 7.4s ❌ | 2.3s | 16ms | 0.240 ❌ | 3.7s |
| /lan/Värmlands län                      |     55 | 6.7s ❌ | 1.0s | 35ms | 0.284 ❌ | 7.1s |
| /malmo                                  |     55 | 8.2s ❌ | 4.6s | 25ms | 0.196 ⚠️ | 4.6s |
| /                                       | **51** | 9.6s ❌ | 4.7s | 20ms | 0.243 ❌ | 4.7s |

**Google CWV-trösklar:** ✅ good · ⚠️ needs improvement · ❌ poor

### Övergripande bedömning

- **6 av 8 URL:er har POOR LCP** (>4.0s). Bara `/goteborg` och
  `/helikopter` är inom acceptabel range.
- **5 av 8 har POOR CLS** (>0.25). Främst `/helikopter` (0.323) och
  `/lan/Värmlands län` (0.284) sticker ut.
- **TBT är utmärkt på alla** (<50ms). Inga JS-blockerings-problem.
- **Startsidan `/` är värst** med performance 51 — vår mest trafikerade
  landningssida (4 619 sessions/30d från Google organisk).
- **Single-event-sidor** har LCP 8.5s — sannolikt kartan eller event-bilden.

### Topp-flaskhalsar (saving potential >500ms)

Fixar listade i ordning av frekvens + impact:

1. **Reduce unused JavaScript** — på 6 av 8 URL:er, ~1600-2180ms saving
   per sida. Sannolikt: AdSense + GA4 + Leaflet + andra bibliotek som
   laddar oanvänd kod. Action: tree-shake, defer, code-split.
2. **Eliminate render-blocking resources** — på 5 av 8 URL:er,
   ~600-1300ms saving. Action: `defer`/`async` på `<script>`, inline
   kritisk CSS, ladda resten asynkront.
3. **Reduce initial server response time** — på `/lan/Värmlands län`,
   **3562ms saving** (TTFB är dålig på vissa sidor). Action: cache check
   eller query-optimering.
4. **Serve images in next-gen formats** — på `/` och `/stockholm`,
   ~780-870ms saving. Action: AVIF/WebP istället för JPG/PNG på
   thumbnails och hero-bilder.
5. **CLS från images utan width/height** eller dynamiskt injicerade
   ad-units. Action: explicit aspect-ratio på bilder + reservera plats
   för ads (`min-height` på ad-container).

### Fas 2 CWV-leverans — ny todo eller sub-tasks?

CWV-fixar är arbete för veckor, inte timmar. Föreslag att bryta ut till
en egen #30-todo så #11 inte blir en mega-todo. Beslut före kod:

- **#30 (förslag): CWV-optimering Fas 1**
    - JS-bundle-reduktion (defer ads, code-split, tree-shake)
    - Render-blocking CSS → kritisk CSS inline + rest async
    - WebP/AVIF för thumbnails
    - CLS-fix: explicit dimensions på bilder + ad-reservation
    - Mål: alla URL:er till performance ≥70, LCP <4s

    Tid: 1-2 veckor

- **#31 (förslag): TTFB-optimering**
    - `/lan/Värmlands län` har 3.5s TTFB — abnormt högt
    - Audit query-prestanda för länsspecifika vyer
    - Cache check + ev. eager loading

    Tid: 2-3 dagar (efter mätning)

### Detaljerad data

Lighthouse-rapporter som JSON sparade i `/tmp/psi/*_mobile.json`
(lokalt). Inte committade i repo (för stora). Kör om mätningen
om data är ≥7 dagar gammal.

## Sammanfattning

Brottsplatskartan har en anständig SEO-grund: unika `<title>`, OG/Twitter-
cards, canonical via blade-sektioner, `lang="sv"`, NewsArticle JSON-LD på
single-event, svensk 404. Men det finns tydliga, relativt billiga gap:

- Ingen `sitemap.xml` över huvud taget.
- `robots.txt` saknar sitemap-referens och crawler-styrning för
  URL-explosion (datum × plats/län).
- JSON-LD finns bara på single-event (inget `BreadcrumbList`, `WebSite`,
  `Organization`, `Place`, `ItemList`).
- Inga defaults i layout → sidor som glömmer sektion hamnar utan
  canonical/description.
- Bildalt-text är inkonsekvent (thumbnails i `crimeevent-small`,
  `crimeevent-previousPartners` och `crimeevent-helicopter` saknar alt).
- Duplicerad `<meta name="robots">`-tagg i layout (två taggar vid
  noindex — Google slår ihop men det är inkonsekvent).
- URL-explosion (`/plats/*/handelser/{date}`, `/lan/*/handelser/{date}`)
  → risk för duplicate/thin content som späder domänauktoritet.
- Inga Core Web Vitals-mätningar gjorda i denna audit (kräver prod-URL
  via PageSpeed Insights / CrUX).

Största kvalitetslyftet kommer från **Fas 1 quick wins** (sitemap,
default-canonical, BreadcrumbList-JSON-LD, Organization/WebSite-JSON-LD,
robots.txt-städning) — dessa tar timmar inte dagar och är svåra att göra
fel.

---

## Nulägesinventering

Checklista med ja/nej/delvis + filhänvisningar.

### Tekniskt

| Punkt                         | Status   | Fil / anmärkning                                                                                                                                      |
| ----------------------------- | -------- | ----------------------------------------------------------------------------------------------------------------------------------------------------- |
| `<title>` per sida            | Ja       | `resources/views/layouts/web.blade.php:64`, unik per vy via `@section('title', …)`                                                                    |
| Titel-längd ≤ ~60 chars       | Delvis   | Vissa lång t.ex. `overview-helicopter.blade.php:4` (97 chars inkl. emoji), `polisstationer.blade.php:9`. `single-plats.blade.php:17` bygger dynamiskt |
| Meta description              | Delvis   | `resources/views/layouts/web.blade.php:20–24` — villkorlig via `@hasSection('metaDescription')`. **Ingen fallback**                                   |
| Unika descriptions per sida   | Delvis   | Finns där `@section` explicit sätts. Övriga sidor: ingen description alls                                                                             |
| Canonical URL                 | Delvis   | `layouts/web.blade.php:14–16` — villkorlig. Ingen fallback till `url()->current()`                                                                    |
| H1 unik per sida              | Delvis   | 126 förekomster fördelat över 55 filer — ej verifierat att varje vy renderar exakt _en_ H1. `design.blade.php` har 10, men är debugvy                 |
| NewsArticle JSON-LD           | Ja       | `app/CrimeEvent.php:1175` + `single-event.blade.php:19`                                                                                               |
| Event JSON-LD                 | Nej      | Används inte — NewsArticle valt istället (rimligt)                                                                                                    |
| Place JSON-LD                 | Nej      | Ingen `Place`/`City`-markup på `single-plats`, `single-lan`, `city.blade.php`                                                                         |
| BreadcrumbList JSON-LD        | Nej      | `parts/breadcrumb.blade.php` renderar bara visuellt                                                                                                   |
| Organization / WebSite        | Nej      | Ingen global Organization/WebSite-markup i layout                                                                                                     |
| robots.txt                    | Delvis   | `public/robots.txt` — finns men saknar `Sitemap:`-rad och styr inga parameter-URL:er                                                                  |
| sitemap.xml                   | **Nej**  | Ingen route, ingen controller, inget paket installerat                                                                                                |
| RSS/Atom                      | Ja       | `spatie/laravel-feed` via `Route::feeds()`, inkluderas i layout rad 18                                                                                |
| Mobile viewport               | Ja       | `layouts/web.blade.php:12`                                                                                                                            |
| `lang="sv"`                   | Ja       | `layouts/web.blade.php:7`                                                                                                                             |
| `max-image-preview:large`     | Ja       | `layouts/web.blade.php:26`                                                                                                                            |
| Noindex-strategi              | Delvis   | `$robotsNoindex`-flagga finns, men ingen duplicate-hantering för datum-URL:er                                                                         |
| Dubbel `<meta name="robots">` | Ja (bug) | Layout skriver två separata taggar när noindex sätts (rad 26 + 71)                                                                                    |
| Core Web Vitals (LCP/INP/CLS) | Ej mätt  | Gör via PageSpeed Insights mot `brottsplatskartan.se` efter Hetzner-cutover                                                                           |

### On-page

| Punkt                                       | Status   | Fil / anmärkning                                                                                                                                                 |
| ------------------------------------------- | -------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Breadcrumbs (visuellt)                      | Ja       | `parts/breadcrumb.blade.php` + `$breadcrumbs`-objekt                                                                                                             |
| Breadcrumb Schema.org                       | Nej      | Saknas — quick win                                                                                                                                               |
| Alt-text på kartbilder                      | Ja       | `parts/crimeevent.blade.php:57,61,66` + `crimeevent_v2` + `events-box` använder `$crimeEvent->getMapAltText()`                                                   |
| Alt-text på thumbnails                      | Delvis   | `crimeevent-small.blade.php:13,25` och `crimeevent-helicopter.blade.php:27`, `crimeevent-previousPartners.blade.php:17` — verifiera att alla har meningsfull alt |
| Alt-text på `page.blade.php` illustrationer | Delvis   | `page.blade.php:155, 161` saknar synlig alt — bara `loading`/`width`/`height`                                                                                    |
| Intern länkning                             | Ja       | `eventsNearby`, `lan-and-cities`, `related-links`, `widget-blog-entries`, `latestEvents`, `mostViewed`                                                           |
| Mobilt layout                               | Antas ok | Ej verifierat i denna audit — kör Lighthouse mobile                                                                                                              |

### Innehåll / analytics

| Punkt                               | Status                                                              |
| ----------------------------------- | ------------------------------------------------------------------- |
| Titel-kvalitet (se todo #10)        | Delvis — "sammanfattning-natt-…-presstalesperson"-mönster urvattnar |
| Evergreen-content                   | Nej — allt är datumbaserat                                          |
| /statistik-sida (#6)                | Planerat, ej byggt                                                  |
| Google Search Console               | Kopplat men ej granskat i denna audit                               |
| GA4                                 | Ja, `G-L1WVBJ39GH` i `layouts/web.blade.php:106`                    |
| GA4 MCP för datadriven prioritering | Planerat (#8)                                                       |

---

## Gap-analys (prioriterad)

### P0 — Kritiskt / billigt

1. **Ingen sitemap.xml.** Google har bara RSS + interna länkar att gå
   på. Att lägga in en sitemap är ~2 timmars jobb och ger direkt
   indexeringshjälp. Använd `spatie/laravel-sitemap`, generera
   nattligen via scheduler.
2. **Ingen `Sitemap:`-rad i robots.txt.** Trivial fix när sitemap finns.
3. **Ingen canonical-fallback.** Sidor utan `@section('canonicalLink')`
   har ingen canonical → riskerar duplicate content när query-params
   (filter, UTM, fbclid) varieras.
4. **BreadcrumbList JSON-LD saknas.** Gratis rich result + hjälper
   Google förstå hierarki. Dra det in i `parts/breadcrumb.blade.php`.
5. **Dubbel `<meta name="robots">`-tagg.** Kombinera till en tagg i
   `layouts/web.blade.php`.

### P1 — Stor påverkan, medelstort arbete

6. **URL-explosion för datum × plats/län.** `~940k` potentiella
   `/plats/*/handelser/{date}`-URLer (från todo #1). Beslut: ta bort
   eller sätt `noindex, follow` + canonical till
   `/handelser/{date}`. Utan det späder duplicate content hela
   domänen.
7. **Default meta-description i layout.** Fallback på en rimlig
   generell description när sidan inte satt egen.
8. **Place/City Schema.org för `single-plats`/`single-lan`/`city`.**
   Hjälper geo-queries (`polishändelser [stad]`).
9. **Organization + WebSite JSON-LD globalt.** Aktiverar sitelinks
   searchbox och ger Google säker entity-mappning.
10. **Thin content på gamla events.** Events äldre än t.ex. 365 dagar
    med < N ord body → `noindex, follow`. Skyddar domänauktoritet,
    rimmar med cache-diskussion i todo #1.

### P2 — Längre horisont

11. **Titel-kvalitet för vaga events (todo #10).** AI-omskrivning av
    presstalesperson-titlar.
12. **Core Web Vitals-mätning + optimering.** Mät efter
    Hetzner-cutover — nya servern är snabbare, så baselining efter
    cutover. Troliga flaskhalsar: Leaflet-bundle, GA4+AdSense
    blockerar, kartbilder.
13. **Evergreen-innehåll.** `/statistik` (todo #6), guider
    ("Vad är skillnaden mellan misshandel och grov misshandel?"),
    ord i `Dictionary` → egna landningssidor med intern länkning.
14. **`@hasSection/@endif`-konsekvens-städning.** Kosmetiskt.

---

## 3-stegsplan

### Fas 1 — Quick wins (1 dag)

- [ ] Installera `spatie/laravel-sitemap` → scheduled generator
      nattligen. Inkludera `/`, `/handelser`, `/lan/*`, `/plats/*`,
      `/typ/*`, blog-poster, `/vma`. För events: senaste 90 dagar.
- [ ] Lägg till `Sitemap: https://brottsplatskartan.se/sitemap.xml` i
      `public/robots.txt`.
- [ ] Fallback-canonical + fallback-description i `layouts/web.blade.php`.
- [ ] Lägg till `BreadcrumbList` JSON-LD i `parts/breadcrumb.blade.php`.
- [ ] Lägg till global `WebSite` + `Organization` JSON-LD i
      `layouts/web.blade.php`.
- [ ] Slå ihop dubbel `<meta name="robots">` till en tagg.
- [ ] Audit alt-text: säkerställ att `crimeevent-small`,
      `crimeevent-previousPartners`, `crimeevent-helicopter`,
      `page.blade.php` har meningsfull alt.

### Fas 2 — Medel (2–3 dagar)

- [ ] Beslut + implementation: ta bort eller `noindex`+canonical för
      `/plats/*/handelser/{date}` och `/lan/*/handelser/{date}`.
      Samordna med todo #1.
- [ ] `Place` / `City` JSON-LD på `single-plats`, `single-lan`,
      `city.blade.php` (inkl. `geo`, `containedInPlace`).
- [ ] Noindex-strategi för gamla/thin events (scope via
      `$robotsNoindex` + Artisan-kommando `crimeevents:mark-thin`).
- [ ] Audit alla H1 med script — säkerställ exakt en per sida.
- [ ] Core Web Vitals-mätning post-cutover → rapportera topp-3
      flaskhalsar.

### Fas 3 — Stor omstuvning (veckor)

- [ ] Todo #10 (AI-omskrivning av vaga titlar) i produktion.
- [ ] Todo #6 (/statistik-sida) som evergreen-content.
- [ ] Dictionary-ord → egna landningssidor med intern länkning.
- [ ] ItemList JSON-LD på overview-sidor.
- [ ] LCP/INP-optimering baserat på Fas 2-mätningar.
- [ ] Search Console-genomgång: indexeringsfel, täckning, queries
      där vi visas men inte klickar.

---

## Schema.org-skiss för NewsArticle (nuläge + förslag)

Nuvarande `getLdJson()` i `app/CrimeEvent.php:1175` ser bra ut men kan
förstärkas:

```json
{
    "@context": "https://schema.org",
    "@type": "NewsArticle",
    "mainEntityOfPage": { "@type": "WebPage", "@id": "<permalink>" },
    "headline": "<titel max 110 chars>",
    "image": ["<1x1 640x640>", "<4x3 800x600>", "<16x9 1200x675>"],
    "datePublished": "<ISO8601>",
    "dateModified": "<ISO8601 senaste uppdatering>",
    "author": {
        "@type": "Organization",
        "name": "Brottsplatskartan",
        "url": "https://brottsplatskartan.se/"
    },
    "publisher": {
        "@type": "Organization",
        "name": "Brottsplatskartan",
        "url": "https://brottsplatskartan.se/",
        "logo": {
            "@type": "ImageObject",
            "url": "https://brottsplatskartan.se/img/brottsplatskartan-logotyp.png",
            "width": 600,
            "height": 60
        }
    },
    "description": "<160 chars plaintext>",
    "articleSection": "<brottstyp>",
    "keywords": ["<typ>", "<plats>", "<län>"],
    "isAccessibleForFree": true,
    "inLanguage": "sv-SE",
    "contentLocation": {
        "@type": "Place",
        "name": "<ort>, <län>",
        "address": {
            "@type": "PostalAddress",
            "addressLocality": "<ort>",
            "addressRegion": "<län>",
            "addressCountry": "SE"
        },
        "geo": {
            "@type": "GeoCoordinates",
            "latitude": "<lat>",
            "longitude": "<lng>"
        }
    },
    "about": {
        "@type": "Thing",
        "name": "<brottstyp>",
        "url": "https://brottsplatskartan.se/typ/<slug>"
    }
}
```

**Skillnader mot nuvarande:**

- `https://schema.org` istället för `http://` (mindre issue men
  modernare).
- Flera bildformat — Google vill ha 1x1, 4x3 _och_ 16x9.
- `articleSection`, `inLanguage`, `isAccessibleForFree`, `keywords`.
- `contentLocation` som `Place` med `PostalAddress` (bättre än
  nuvarande `GeoCircle` för SEO — `GeoCircle` används främst för
  _service areas_).
- `about` pekar på brottstyps-sidan → intern länkning semantiskt.
- `dateModified` från faktisk updated_at, inte = published.

---

## Risker

- **Over-optimization.** Nyckelord-stuffade titlar/descriptions
  triggar Google-straff. Håll titlar naturliga och under 60 chars;
  descriptions 120–160 chars, läsbara meningar.
- **Schema-spam.** Överdriven eller falsk JSON-LD (t.ex. `Review`-
  markup på icke-review) → manuell action. Håll oss till `NewsArticle`,
  `Place`, `BreadcrumbList`, `Organization`, `WebSite`.
- **Indexeringsjusteringar (noindex) kan tappa trafik temporärt.**
  Thin-events kan ändå rankera på long-tail. Mät GA4/Search Console 30
  dagar innan breddad utrullning. Rulla ut gradvis (äldsta först).
- **Sitemap med 300k URL:er.** Google rekommenderar max 50k per fil.
  Använd sitemap-index + dela per typ (events, platser, län).
- **Cache-coldfronten.** När canonical/noindex ändras globalt måste
  response cache rensas (`php artisan responsecache:clear`).
- **Canonical-bugg.** Fel canonical (t.ex. pekar från unik sida till
  startsida) kan avindexera allt. Kör spot-test med "view-source" på
  10 vyer efter deploy.

## Fördelar

- Större andel händelser indexerade → mer long-tail-trafik.
- Rich results (BreadcrumbList, NewsArticle) → bättre CTR i SERP.
- Renare domänauktoritet när thin/duplicate content avindexeras.
- Sitelinks searchbox (WebSite-schema) → branding i SERP.
- Snabbare omindexering av uppdaterade events via sitemap `lastmod`.
- Underlag för AdSense — bättre CPM på sidor med bättre engagement.

## Öppna frågor

- Ska single-events äldre än X dagar `noindex`? Tröskel: 180 eller 365
  dagar? Storlek av effekten okänd — behöver Search Console-data.
- `/plats/*/handelser/{date}` + `/lan/*/handelser/{date}`: bort helt
  eller behåll med canonical till `/handelser/{date}`? Beror på
  faktisk trafik (GA4 MCP behövs).
- Duplicat-län (21 + 2): vilken är kanonisk version? Behöver beslut
  (se todo #1).
- Ska sitemap innehålla alla ~297k events eller bara senaste 90 dagar?
  Rekommendation: rullande 90 dagar + separat archive-sitemap om vi
  verkligen vill att äldre indexeras.
- Har vi 404-trafik från indexerade gamla URL:er? Search Console-
  coverage-rapport behövs.
- Ska `og:image` för events använda statisk karta eller en ny
  auto-genererad sharing-bild med typ + plats-text overlay?

## Status / nästa steg

- [x] Inventera befintligt SEO-stöd (layout, controllers, JSON-LD)
- [x] Gap-analys prioriterad
- [x] 3-stegsplan
- [x] NewsArticle-skiss
- [ ] **Beslut:** gå igång med Fas 1 quick wins (rekommenderas — kan
      göras på en dag utan beroenden)
- [ ] Fas 2 kräver beslut om URL-strategi för datum-routes (synkas
      med todo #1)
- [ ] Core Web Vitals-mätning **efter** Hetzner-cutover
- [ ] Google Search Console-genomgång (separat session)
- [ ] GA4 MCP (#8) för att prioritera P1-insatser datadrivet

## Relaterade todos

- #1 — Cache-entries / URL-minskning (tight koppling P1 punkt 6)
- #2 — SEO-review (duplikat, rekommenderas mergeas hit)
- #6 — /statistik-sida (evergreen-content, Fas 3)
- #8 — GA4 MCP (datadriven prio)
- #10 — AI-titelomskrivning (Fas 3)
