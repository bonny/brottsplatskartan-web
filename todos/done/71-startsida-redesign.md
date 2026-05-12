**Status:** klar 2026-05-12 — Fas 1+2+3 implementerade, Fas 4 ej motiverad (mätning), tre efter-fixar för mobil-UX
**Senast uppdaterad:** 2026-05-12

## Efter-fixar 2026-05-12 (efter UX-feedback från användaren)

Tre uppföljnings-commits baserat på visuell granskning av deployad prod:

- **`c9bf8da`** — UX-fix mobil hero + tap-targets + visuell H1 + hyphens:
    - EventHero stacka (flex-direction: column) på <480 px så thumb tar
      full bredd och texten får hela viewporten (tidigare horisontell
      tvingade fram "indu-striområde"-radbrytningar)
    - sitebar\_\_EventsItemLink padding-y 8→12 + min-height 44 (iOS HIG)
    - sr-only H1 → visuell .StartPage\_\_title (18/22 px)
    - .EventHero\_\_title: overflow-wrap: anywhere + hyphens: auto
- **`4c20209`** — Alltid synliga län/städer (inga expand-toggles):
    - Sidopanel: mc-lan-list + mc-cities-list borttagna
    - Footer: mc-footer-lan + mc-footer-stader borttagna
    - Motivering: SEO-mässig intern länkning ska inte gömmas
- **`be5d7a2`** — Huvudnav i footer också alltid synlig:
    - mc-footer-bpk borttagen ("Visa alla länkar")
    - Resultat: alla footer-kolumner expanderade på alla viewports

# Todo #71 — Startsida-redesign: kompaktare layout + SEO-städ

## Fas 4 klar 2026-05-12 — commit `0c7ae4a`. Ingen åtgärd behövs (mätning visar att lazy-load ej är motiverad)

Mätskript: [`tmp-startsida-analys-2026-05-12/audit-network.mjs`](../tmp-startsida-analys-2026-05-12/audit-network.mjs).
Kört mot lokal dev (http://brottsplatskartan.test:8350/).

Resultat per viewport (efter Fas 1–3):

| Mått                   | Desktop 1440 | Tablet 820 | Mobile 390 |
| ---------------------- | ------------ | ---------- | ---------- |
| Total bytes            | 1368 kB      | 1368 kB    | 1368 kB    |
| Total requests         | 53           | 53         | 53         |
| Kart-tiles bytes       | 69 kB        | 69 kB      | 69 kB      |
| Kart-tiles requests    | 17           | 17         | 17         |
| Kart-tiles % av bytes  | 5.1 %        | 5.1 %      | 5.1 %      |
| Leaflet-assets (cache) | 223 kB / 9   | 223 / 9    | 223 / 9    |
| Lazy-able totalt       | 292 kB / 26  | 292 / 26   | 292 / 26   |

Tile-data laddas från egen tileserver (`kartbilder.brottsplatskartan.se`)
i WebP — varje tile är 5–8 kB. Totalt ~69 kB över 17 requests, vilket
är försumbart mot resten av sidans 1.3 MB.

Beslutsregel (definierad i todo-prompt):

- Kart-tiles ≥ 30 % av mobile bytes → **5.1 % — ej trigger**
- Kart-tiles ≥ 20 requests → **17 — ej trigger**
- Mobile > 2 MB OCH kart-tiles > 500 kB → **1.37 MB / 69 kB — ej trigger**

Lazy-load skulle besparat ~292 kB / 26 req (Leaflet + tiles tillsammans),
men eftersom kart-tiles ensamt är så få och så små finns ingen
bandwidth-motivering. Leaflet-assets cachas dessutom hårt mellan
sidvisningar.

UX-värde av kartan på startsidan bedöms högre än 21 % bytes-besparing
på första laddningen. **Ingen lazy-load implementeras.**

Om mätning i framtiden visar att tile-trafik växer (t.ex. om vi byter
till retina-tiles eller högre default-zoom), kan Fas 4 återöppnas.

## Fas 3 klar 2026-05-12 — commit `ab33758`. Mätning: mobile docH 7 429 → 4 929 px (−33.7 %), desktop oförändrad (3 779 → 3 747 ≈ mätbrus).

Mobile-paginering via CSS-only `<input type=checkbox>` + `<label>`-toggles
per sektion. Desktop och tablet behåller full listning tack vare
`@media (min-width: 769px)`-regel som tvingar `.MobileCollapse__content`
till `display: block`. Länkar ligger alltid kvar i DOM:en → crawl:bart
för Googlebot (verifierat manuellt med playwright + skiftning av
viewport).

Anmärkning: tidigt försök använde `<details>`/`<summary>` (samma
pattern som #64 per-plats-nyheter). Det visade sig att webbläsarens
interna `<details>`-rendering inte är CSS-överskridbar — även med
`display: block !important` på `.MobileCollapse__content` förblev
innehållet dolt på desktop när elementet saknade `[open]`. Byttes
därför till checkbox-pattern som är fullt CSS-styrt.

Audit-mätning före → efter (390×844 mobile):

| Mått                      | Före (Fas 2) | Efter (Fas 3) | Δ       |
| ------------------------- | ------------ | ------------- | ------- |
| Mobile `docH`             | 7 429 px     | 4 929 px      | −33.7 % |
| Tablet `docH` (820×1180)  | 5 010 px     | 4 978 px      | −0.6 %  |
| Desktop `docH` (1440×900) | 3 779 px     | 3 747 px      | −0.8 %  |
| `foldCards` mobile        | 8            | 8             | =       |
| `foldCards` desktop       | 8            | 8             | =       |

Toggle:ade sektioner (alla mobile-only, desktop ser hela listan):

- `parts/events-heroes.blade.php` — 6 små heroes + 8 normala
  ListEvent-kort i "Mest läst" viks ihop bakom toggle. Första 3
  hero-korten alltid synliga (det är huvudvärdet).
- `parts/lan-and-cities.blade.php` — sidebar widgets "Senaste
  händelserna & brotten i ditt län" (21 län-länkar) + "Senast hänt
  i din stad" (10 stad-länkar).
- `parts/widget-blog-entries.blade.php` — visar 1 senaste bloggpost,
  resten viks ihop.
- `parts/widget-vma-messages.blade.php` — arkiverade VMA viks ihop;
  aktuella VMA alltid synliga.
- `components/text-tv-box.blade.php` — visar 3 första nyheter per
  lista ("Senaste nytt" + "Mest läst"), resten viks ihop.
- `parts/sitefooter.blade.php` — alla tre footer-kolumner
  (huvudnav, län-lista, städer-lista) viks ihop på mobil. Affecterar
  hela sajten — bedöms OK eftersom desktop oförändrat och länkarna
  finns kvar i DOM.

Sektioner som **inte** toggle:ades (medvetet):

- "Senaste händelserna" + "Mest lästa händelserna" Timeline-listor —
  core content per brief, lämnade orörda.
- "Mest läst"-hero (första 3 stora kort) — primärt klick-mål.
- Brottsstatistik-widgeten — paragrafen är så kort att toggle-labelen
  äter upp vinsten (testat: gav +2 px på mobil).
- Karta + AdSense — utanför scope.

CSS-block tillagt i `public/css/styles.css` (efter EventHero-blocket):
`.MobileCollapse`, `.MobileCollapse__toggle` (visuellt dolt sr-pattern),
`.MobileCollapse__summary` (klickbar label med `+/−` prefix), och
desktop-media-query som tvingar innehåll till `display: block`.

Verifierat manuellt:

- Mobil 390px: alla 6 toggle-summaries synliga, content gömt vid
  load. Klick på label expanderar (docH 5004 → 8346 px efter klick
  på alla 9 toggle:s).
- Desktop 1440px: ingen summary synlig, allt innehåll alltid expanderat.
- Tablet 820px: identiskt med desktop (≥769px regel träffar).
- PHPStan level 5: 0 errors.

## Fas 2 klar 2026-05-12

Variant A (alla tre hero-kort kompakta horisontella rader, ingen
"super-hero" på topp). Bilden bedöms inte vara central för CTR — det
är listan över mest klickade händelser, inte ett redaktionellt urval.
Konsekvent kompakt layout gör listan mer scannbar.

Audit-mätning före → efter (lokalt mot brottsplatskartan.test:8350):

| Mått                 | Före (Fas 1) | Efter (Fas 2) |
| -------------------- | ------------ | ------------- |
| `foldCards` desktop  | 4            | 8             |
| `foldCards` mobile   | 3            | 8             |
| `foldCards` tablet   | 4            | 10            |
| Stora hero-kort höjd | 620 px       | 160 px        |
| Små hero-kort höjd   | ~280 px      | ~103 px       |
| Mobile `docH`        | 7 630 px     | 7 429 px      |
| `biggestMap.top`     | 228 px       | 228 px        |

Ändringar:

- `resources/views/components/crimeevent/hero.blade.php` — skriven om
  till horisontell flex-layout. Thumbnail 240×160 (large) / 160×110
  (small) till vänster, text till höger. Använder nya BEM-klasser
  `.EventHero` / `.EventHero--compact` / `.EventHero--small`. LCP-stöd
  (eager + fetchpriority) flyttat till img-elementet.
- `public/css/styles.css` — nytt block med `.EventHero*`-regler efter
  Event\_\_-blocket (rad ~742). Inkluderar mobile breakpoint vid 480 px
  som krymper thumbnail ytterligare.

Karta-containern (`biggestMap.top=228`) ligger redan inom målet
≤350 — ingen åtgärd där.

Verifierat: PHPStan level 5 OK, visuell granskning i desktop/tablet/
mobile via audit-screenshots OK (texten läsbar, bilder inte fult
klippta).

## Fas 1 klar 2026-05-12

SEO-städet är livt lokalt. Audit-data efter (jämfört med före, samma
viewport):

| Mått                       | Före                   | Efter (lokalt)                                                           |
| -------------------------- | ---------------------- | ------------------------------------------------------------------------ |
| `firstH1`                  | null                   | "Brottsplatskartan: Polisens händelser i hela Sverige"                   |
| H2 "Senaste händelserna"   | 2 förekomster          | 1 förekomst                                                              |
| Event-titlar               | `<h2>`                 | `<h3>`                                                                   |
| `schemas`                  | Organization + WebSite | + ItemList (10 items, abs URLs)                                          |
| `imgStats.withAlt / total` | 37 / 49 (76 %)         | 20 / 20, 24 / 24, 20 / 20 (100 %)                                        |
| `descLen`                  | 99                     | 144                                                                      |
| `foldCards` (desktop)      | 3                      | 4 (utan layout-ändring — endast H-hierarki gör att fold börjar tidigare) |

Lokal audit-result.json finns i
[`tmp-startsida-analys-2026-05-12/`](../tmp-startsida-analys-2026-05-12/).
Mätningen mot prod sker när commit:en deployats.

Ändrade filer:

- `app/Http/Controllers/StartController.php` — meta description 99 → 144
- `resources/views/start.blade.php` — H1 (sr-only) + ItemList-schema
- `resources/views/components/crimeevent/hero.blade.php` — large H2 → H3
- `resources/views/parts/bar-events.blade.php` — sr-only H2 → div + aria-label
- `public/js/events-map.js` — `alt=""` på expand/collapse-ikoner + tileLayer
- `tmp-startsida-analys-2026-05-12/audit.mjs` — `withAlt` räknar
  `hasAttribute('alt')` (alt="" är semantiskt korrekt för dekorativa
  bilder); dumpar även `imgsWithoutAlt` för åtgärds-hjälp

Verifierat: PHPStan level 5 utan nya fel, ItemList parsar som giltig
JSON-LD med 10 listitem.

Att verifiera manuellt efter deploy:

- Rich Results Test mot prod-URL — ItemList ska detekteras
- Stickprov GSC efter 2026-06-12: CTR + impressions på `/`

## Sammanfattning

Användar-observation 2026-05-12: "För stora kartbilder/händelser så det
får inte plats så mycket." Playwright-audit mot prod bekräftar — på
desktop 1440×900 syns bara **3 event-kort i fold**, på mobil 390×844
bara **2 kort**. Sidan är dessutom **30 viewports lång på mobil**
(docH=12 210 px). Auditen avtäckte också flera SEO-städ-jobb (H-hierarki,
schema-vakuum, saknade alt-texter).

Redesignen ska göra det möjligt att se mer per skroll, sänka cognitive
load, och samtidigt putsa SEO-grunderna.

## Audit-data (2026-05-12)

Underlag: [`tmp-startsida-analys-2026-05-12/`](../tmp-startsida-analys-2026-05-12/)
(audit-result.json + screenshots desktop/tablet/mobile).

### UX-fynd

| Mått                      | Desktop 1440 | Tablet 820 | Mobile 390 |
| ------------------------- | ------------ | ---------- | ---------- |
| Event-kort i fold         | 3            | 4          | 2          |
| Sidhöjd (docH)            | 7 737 px     | 8 334 px   | 12 210 px  |
| Antal kort totalt         | 38           | 45         | 48         |
| "Mest läst" hero-korthöjd | 591 px       | -          | -          |
| Huvudkartans top          | 518 px       | 518 px     | 628 px     |
| Bilder i fold (LCP-risk)  | 11           | 8          | 6          |

- **Hero-korten ("Mest läst") är 850×591 px på desktop** — tre stycken
  staplade tar 1 773 px, alltså mer än två fullskärmar.
- **Mobile-sidan är ~30 viewports lång** — orimligt scroll-djup, ingen
  paginering eller "visa fler"-toggle.
- **Huvudkartan startar på y=518 (desktop) / 628 (mobil)** — header +
  AdSense äter halva fold:en innan kartan börjar.

### SEO-fynd

| Aspekt                 | Status                                                                    |
| ---------------------- | ------------------------------------------------------------------------- |
| `<title>`              | OK — 55 tecken                                                            |
| `<meta description>`   | OK — 99 tecken (kunde vara 130–150 för fyllnad)                           |
| Canonical              | OK — `https://brottsplatskartan.se/`                                      |
| Robots                 | `max-image-preview:large` — OK                                            |
| Lang                   | `sv` — OK                                                                 |
| OG + Twitter           | OK, statisk `start-share-image.png` (kunde vara dynamisk men acceptabelt) |
| **H1**                 | **Saknas helt** — startsidan har 0 H1                                     |
| **H-hierarki**         | Event-titlar är `<h2>` (för tunga), `<h3>` blandas in inkonsekvent        |
| **Duplicerad H2**      | "Senaste händelserna" förekommer **två gånger** (top=0 + top=5102)        |
| **Schema.org**         | Bara `Organization` + `WebSite` — saknar `ItemList` för senaste händelser |
| **Saknade alt-texter** | 12 av 49 bilder saknar alt                                                |
| Interna länkar         | 171 — bra för länk-equity                                                 |
| HTML-vikt              | 297 kB desktop, 339 kB mobil — något tungt                                |
| Externa scripts        | 14 — kan konsolideras                                                     |
| Stylesheets            | 12 — kan konsolideras                                                     |
| Network requests       | 107 (39 img, 18 script, 14 css, 18 xhr, 7 font)                           |

## Förslag — uppdelat per fas

### Fas 1: SEO-städ + alt-texter (snabb-vinster, ≤1 dag)

Ingen design-diskussion krävs, ren kod:

1. **Lägg till H1** på startsidan — t.ex. "Brottsplatskartan: Polisens
   händelser i hela Sverige" eller liknande. Idag finns ingen H1 alls.
2. **Sänk event-titlar till H3** — H2 reserveras för sektions-rubriker
   ("Senaste händelserna", "Mest läst", "Brottsstatistik").
3. **Ta bort duplicerade H2** ("Senaste händelserna" syns två gånger).
4. **Fyll i 12 saknade alt-texter** — kör grep i blade-templates,
   identifiera vilka `<img>` det är.
5. **Lägg till `ItemList`-schema** för senaste händelser-listan (≤10
   events, samma data som redan renderas).
6. **Förläng meta description** till 130–150 tecken (idag 99) —
   utrymme att lägga in nyckelord som "blåljus", "trafikolyckor",
   "kommun" för CTR-lyft.

### Fas 2: Kompaktare event-kort (UX, 1–2 dagar)

Beslut krävs (design), men scope är tydligt:

1. **Krympa "Mest läst"-hero-korten** — 591 px höjd är för mycket. Mål:
   ≤300 px höjd, så tre stycken får plats i en fold-höjd på desktop.
   Alternativ:
    - Mindre bilder (eller text + thumbnail istället för stor bild)
    - En "hero" + två kompakta lista-rader istället för tre lika stora
2. **Krympa de mindre event-korten** (400×356) → ~150–200 px höjd med
   horisontell layout (thumbnail + text bredvid).
3. **Behåll kartan i nuvarande storlek** — den är inte problemet (882×360
   är rimligt), men flytta upp den så den startar tidigare i fold.

### Fas 3: Kortare mobile-sida (UX, 1 dag)

1. **Mobile docH 12 210 px = 30 viewports** är orimligt. Lägg till
   "visa fler"-toggles (`<details>`) per sektion — samma mönster som
   #64 redan använder för per-plats-nyheter.
2. **Eller paginera "Senaste händelserna"** — visa 5 på startsidan,
   "se fler" → /handelser (eller liknande).

### Fas 4: Karta-bantning vid behov (mätning först)

Auditen visar **10 kart-element** på startsidan (huvudkarta + Leaflet-
tiles). Det är inte uppenbart hur mycket detta drar mobile-data. Mät
först:

- Network total = 107 requests, 39 bilder — hur många är kart-tiles?
- Om kart-tiles dominerar mobile-bandwidth → lazy-loada kartan
  (klick-för-att-aktivera) på mobil

## Mätning

- **Före:** Audit-snapshot (denna) sparad i `tmp-startsida-analys-2026-05-12/`
- **Efter fas 1:** kör om `tmp-startsida-analys-2026-05-12/audit.mjs`
  → verifiera H1 finns, alt-täckning 100 %, ItemList syns i schemas
- **Efter fas 2+3:** mät **foldCards** (mål: desktop ≥6, mobil ≥3) och
  **docH** (mål: mobile ≤7 000 px, halvering)
- **GSC/GA4:** 30d-jämförelse av CTR + bounce rate på `/`. Stickprov
  2026-06-12. Risk: stora ändringar kan tappa direkt-trafik kortsiktigt.

## Risker

- **Förlorad annons-inventory** om vi krymper "Mest läst"-hero-korten —
  kolla om AdSense-units sitter där.
- **Förlorad CTR till event-sidor** om korten blir för kompakta — kan
  signalera "tråkigare" och sänka klick. Mät vs baseline.
- **Mobile-paginering** kan minska crawl-djup om allt göms bakom
  `<details>` — men `<details>` är crawlbart av Googlebot enligt
  current best practice.
- **Schema-fel** vid ItemList — testa i [Rich Results Test](https://search.google.com/test/rich-results)
  efter deploy.

## Beroenden

- Ingen blockerare. Fas 1 är ren kod, ingen beroende-fas.
- **Synergi med #46** (meny-konsolidering) — om meny ändras kan det
  påverka var H1 sitter / hur sektionerna ramas in.
- **Synergi med #59** ("Vad händer nu"-ruta) — om #59 byggs, blir
  startsidan ännu längre om vi inte samtidigt komprimerar; detta
  är därför en naturlig **före-fas till #59**.
- **Synergi med #64** — `<details>`-mönstret är redan etablerat per
  plats, återanvänd för mobile-paginering här.

## Confidence

- **Hög** för fas 1 (SEO-städ) — uppenbara fynd, ingen risk för regression
- **Medel** för fas 2 (kort-storlek) — designberoende, kräver A/B-känsla
  eller iteration mot bounce rate
- **Medel** för fas 3 (mobile-paginering) — `<details>` är säkert, men
  scroll-djup-minskning kan påverka annons-impressions

## Nästa steg

1. **Användar-beslut:** ska vi köra fas 1 nu (snabb-vinster utan
   design-diskussion) och separat planera fas 2–3?
2. Om ja → grep blade-templates för:
    - Frånvaron av H1 (`resources/views/start*.blade.php` eller liknande)
    - 12 saknade `alt=""` (kör en helper)
    - "Senaste händelserna" som förekommer två gånger
3. Lägg in ItemList-schema i samma struktur som #32 (schema-sweep) använde

## Inte i scope

- **Helt ny startsida** från grunden — vi itererar på befintlig.
- **Borttagning av AdSense** — separat beslut.
- **Helt ny navigations-struktur** — det ligger i #46.
- **Karta-byte** (Leaflet → annan biblioteks-stack) — ingen anledning,
  fungerar.
