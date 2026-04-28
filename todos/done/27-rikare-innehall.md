**Status:** klar 2026-04-28 — Lager 1 (trend-sparkline + brottstyp + mest-lästa) + Lager 2 (BRÅ-data + Wikidata-intro-paragraf) + Lager 3 (AI-månadssammanfattning + trendanalys) live på alla 5 Tier 1-städer + alla månadsvyer. Heatmap + AI-säkerhetstips avfärdade. Wikidata-utökning till plats-sidor utanför Tier 1 hänvisas till framtida todo.
**Senast uppdaterad:** 2026-04-28

> ## ✓ Update 2026-04-28 (kväll 4) — slutleverans
>
> Tre extra leveranser efter kväll 3:
>
> 1. **Wikidata utökad till proper intro-paragraf.** Tidigare bara "yta
>    187 km²" — användaren flaggade att det var snålt. Nu en sammanhållen
>    mening: *"Stockholm är Sveriges huvudstad. Kommunen har omkring
>    995 574 invånare. Stadens yta är 187 km² och det första kända
>    omnämnandet är från år 1187."* Två datakällor: Wikidata description
>    (sv) + grundat-år + yta, kombinerat med SCB-befolkning från
>    `scb_kommuner` via ny `BraStatistik::kommunInfo()`-helper. Skriver
>    "Kommunen har..." (inte "{namn} kommun") för att slippa genitiv-
>    fallgropen.
>
> 2. **Månadssumma flyttad från startsida till månadsvy.** Användaren
>    påpekade att `/uppsala` är en "live"-sida (dagsfärsk info), inte
>    rätt plats för förra månadens sammanfattning. Renderingen togs bort
>    från `city.blade.php` och visas nu bara på
>    `/<stad>/handelser/{år}/{månad}` där hela kontexten är en månad.
>
> 3. **Schemaläggning för pågående månad.** Pågående månad är "live" —
>    nya events tillkommer dagligen så summan måste uppdateras. Två
>    schedule-jobb i Kernel:
>    - Snapshot av föregående månad: `monthlyOn(1, '02:00')` (engångs)
>    - Innevarande månad: `cron 0 */6 * * *` (var 6:e timme)
>    Change-detection skip:ar AI-anrop när events oförändrade så cost
>    blir trivial när inget hänt.
>
> Status efter dagens session: alla 5 Tier 1-städer har april-2026-summor
> live på prod (Stockholm 61, Malmö 76, Göteborg 47, Helsingborg 24,
> Uppsala 32 events). Mars-summor genererade tidigare. Schedule kommer
> hålla dem aktuella.
>
> Filer: `app/Services/WikidataService.php` (utökat med description),
> `app/BraStatistik.php` (ny `kommunInfo()`-method),
> `app/Http/Controllers/CityController.php` (städat),
> `app/Console/Commands/GenerateMonthlySummary.php` (nya `--current`-flag),
> `app/Console/Kernel.php` (två schedule-jobb),
> `resources/views/parts/city-facts.blade.php` (ombyggd till paragraf),
> `resources/views/city.blade.php` (städat).
>
> ## ✓ Update 2026-04-28 (kväll 3) — Lager 3 AI-månadssamm. + Wikidata
>
> **AI-månadssammanfattning** (`MonthlySummaryAgent`, claude-sonnet-4-6,
> 2500 tokens) + AI-trendanalys inbäddad i prompten. Schedule:
> 1:a varje månad kl 02:00 UTC, alla 5 Tier 1-städer. Change-detection
> via event-ID-array gör omkörning gratis när events oförändrade.
>
> Visas på två platser:
> - `/<tier1-stad>` (startsida) — förra månadens sammanfattning som
>   "ingång till arkivet"
> - `/<tier1-stad>/handelser/{år}/{månad}` — sammanfattning för den
>   visade månaden
>
> Prompt-konstruktion strikt mot hallucinationer: ingen säkerhetsråd-
> sektion, ingen motiv-spekulation, säg "publicerade händelser" inte
> "anmälda brott". 300–450 ord, 4–8 markdown-länkar till individuella
> events. Verifierad live mot uppsala 2026-03 (38 events) — naturligt
> formulerad trendmening "ökade med femton procent jämfört med februari".
>
> **Wikidata-fakta** — `WikidataService::getCityFacts(qid)` hämtar
> grundat-år (P571) + yta (P2046, normaliserad till km²) från
> wbgetentities. Cache 30d. Visas som kompakt rad under h1 på Tier 1.
> Bug-fix: Helsingborgs Q-id var Q26793 = Bergen (Norge!) sen #32.
> Korrigerat till Q25411.
>
> **Bonus-bugfix:** pre-existing `/malmo/handelser/2026/03` visade bara
> 1 event (slug `malmo` matchade inte `Malmö` i DB). Ny
> `CityController::tier1DisplayName()` mappar slug→display-form med åäö.
> Malmö 1→71, Göteborg 16→36. Stockholm/Uppsala/Helsingborg oförändrade.
>
> **Återstår i scope:**
> - Wikidata-fakta för plats-sidor utanför Tier 1 (`/plats/{plats}`) —
>   kräver Q-id-mappning för fler platser (idag finns bara Tier 1 + 21 län)
>
> Filer: `app/Services/WikidataService.php`, `app/Ai/Agents/MonthlySummaryAgent.php`,
> `app/Models/MonthlySummary.php`, `app/Console/Commands/GenerateMonthlySummary.php`,
> `app/Services/AISummaryService.php` (utökat),
> `database/migrations/2026_04_28_110032_create_monthly_summaries_table.php`,
> `resources/views/ai/prompts/monthly-summary.blade.php`,
> `resources/views/components/monthly-summary.blade.php`,
> `resources/views/parts/city-facts.blade.php`,
> `app/Http/Controllers/{City,Plats}Controller.php`.
>
> ## ✓ Update 2026-04-28 — Lager 1 trend-sparkline + Lager 2 BRÅ live
>
> #38 + relaterade leveranser i prod:
>
> **Lager 2 BRÅ-sektion** — på 5 Tier 1-städer + alla plats-sidor som
> matchar en kommun via PlacePopulation + ny sektion på `/statistik`.
> Visar:
> - "Anmälda brott i {kommun} kommun {år}" — antal + per 100k
> - Procent-jämförelse mot befolkningsviktat rikssnitt
> - Län-grannar-tabell (på ortssidor) eller topp/botten 10 (statistik-sidan)
> - Källhänvisning Brå + mörkertal-disclaimer
>
> **Lager 1 trend-sparkline** — inline SVG bar-chart över events/dag
> senaste 90d på Tier 1-städer. 0 KB JS. Footer förtydligar att det är
> publicerade händelser, inte heltäckande statistik.
>
> **BRÅ-årgångar** — 2021, 2023, 2024, 2025 importerade. 2022 finns
> som 404 på bra.se, 2015–2020 finns aldrig som per-kommun-CSV. Det
> begränsar trend-grafen baserat på BRÅ-data tills vi eventuellt
> bygger SOL-scraping (egen todo om aktuellt).
>
> **Bug-fix #37:** auto-mappingen valde minsta tätorten vid namn-
> kollision (Lund→Gävle). Vänd ascending order så största vinner.
> Lund→Skåne, Sandviken→Sandviken, Kil→Kil korrekta nu.
>
> Filer: `app/BraStatistik.php`, `app/Helper.php` (getDailyEventCountsNearby),
> `app/Http/Controllers/{City,Plats,Statistics}Controller.php`,
> `resources/views/parts/bra-statistik.blade.php`,
> `resources/views/components/trend-sparkline.blade.php`,
> `resources/views/{city,single-plats,statistik}.blade.php`,
> `app/Console/Commands/{ImportBraAnmaldaBrott,AutoMapPlacePopulation}.php`.
>
> ## ✓ Update 2026-04-28 (kväll) — Lager 1 brottstyp + mest lästa live
>
> Lager 1 är nu komplett:
> - **Brottstyp-fördelning** (egen TypeBars-layout, 0 KB extra JS) —
>   topp 8 brottstyper senaste 30 dagarna
> - **Mest lästa events** (numrerad lista med permalinks + läsningar)
>   — joinar mot `crime_views`-tabellen
>
> Helpers: `Helper::getTopCrimeTypesNearby` + `getMostReadEventsNearby`.
> Cache 30min.
>
> ## ✓ Update 2026-04-28 (kväll 2) — designsystem konsoliderat
>
> Flera mobilrundor med designfeedback. Slutlig konsolidering:
>
> - **Widget-mönster** (`<section class="widget">` + `widget__title`)
>   ersatte Tailwind utility-klasser — alla nya sektioner använder
>   sajtens befintliga vit-bg + gul accent-border
> - **`.DataTable`** — generisk tabell-styling används av BRÅ-tabeller
>   (Tier 1, plats-sidor) och alla tabeller på `/statistik`
> - **`.RankedList`** — numrerad lista med blå cirkel-bullets, används
>   av mest-lästa-listan och rekord-dagar-listan på `/statistik`
> - **`.TypeBars`** — egen mobile-first bar-graf (etikett över stapel)
>   ersatte charts-css som överlappade etiketter på smal viewport.
>   Används både i city-context och `/statistik` topp-10
> - **`Helper::number($v, $decimals=0)`** — använder U+00A0 NBSP som
>   tusentalsavgränsare så "11 921 per 100 000" inte bryts mellan rader
> - **Cache-busting** på `styles.css` via `filemtime()` — uppdateringar
>   syns direkt utan hård-refresh
> - **`getHeadline()`** används på mest-lästa istället för `parsed_title`
>   så rubrikerna matchar event-listor och event-sidor
>
> **Återstår:**
>
> **Lager 2:**
> - ~~Heatmap~~ — avfärdad 2026-04-28. Bygger inte SEO-värde (canvas/SVG
>   indexeras inte), INP-risk på mobil, och event-volymen utanför Tier 1
>   är för gles för meningsfull densitet. Markörer + clustering räcker.
> - Trend-graf på BRÅ-data 2015–2025 (blockerad av att äldre årgångar
>   inte är publikt CSV-tillgängliga)
>
> **Lager 3 (AI-månadssammanfattning)** — opåverkad, oberoende, kan
> startas när som helst. Bygger på `DailySummary`-modellen som redan
> finns för dagsummeringar.

# Todo #27 — Berika ort- och månadssidor med rikare innehåll

## Varför

Pageviews/session är 1.3 över hela `/handelser`-prefixet. Sidor är
magra — bara en datumsorterad lista. Detta gör att:

- **AdSense-intäkt per session är låg** (få ad-impressions per visit)
- **SEO-signaler är svaga** (Google premierar dwell-time + topical depth)
- **Bouncing råder** — användaren hittar sin händelse, lämnar, kommer
  inte tillbaka

Rikare sidor med kontext, statistik, jämförelser och visualiseringar
löser samtliga tre problem. Kompletterar #25 (månadsvyer) — där är
infrastrukturen, här är innehållet.

## Vinnar-mönster (verifierat 2026-04-26 via research)

### CrimeGrade.org-modellen — den enda validerade

CrimeGrade visar `brott per 1000 invånare`, percentil-rankning mot
stat/nationellt snitt och tabell över 5 närliggande städer. Det är
vinnar-mönstret att efterlikna.

### Numbeo — för tunt

Förlitar sig på user-perception (index 0–100), inga grafer, bara 2 h2.
Inte rätt riktning för oss.

### SpotCrime — bara data, inget content

Minimalistisk: karta + lista, inga heatmaps eller jämförelser. Förlitar
sig på register-väggar för engagement. **Inte mönstret att kopiera.**

### SVT/lokaltidningar — tomrum att fylla

Ren nyhetslista, inga ortsfakta, inga jämförelser. **Brottsplatskartan
har inget reellt content-konkurrens på ortssideformatet** — vi kan ta
hela utrymmet.

### Programmatic SEO — Zapier vs hotels-fallet

- **Zapier:** 50 000 integrations-sidor → 5.8M organiska besök/månad
  (3-årig kvadrupling). Funkar för att varje sida har **unik data**.
- **Hotels-i-stad-experimentet:** 50 000 template-sidor → **98% avindexerade
  på 3 månader**. Tröskeln var <300 ord unikt per sida.
- **Brottsplatskartans position:** vi har _faktisk unik händelsedata_
  per ort + unik trend-graf + per-1000-rate → vi är i Zapier-kategorin,
  inte hotels-kategorin. Men varje ortssida måste ha ≥500 ord unikt
  content + 30-40% differentiering från andra orter.

### AdSense-impact av ATF-optimering

AccuWeather/Google-fallstudie: ATF-optimering gav **+34% viewability,
+42% CPM, +39% revenue**. Trend-grafen får INTE trycka ner ad-units
nedanför fold på mobil.

## Lager 1 — Kärninnehåll (på alla `/<ort>` + månadssidor)

| Tillägg                                      | Effort | SEO-värde | Tekniskt val                                                                            |
| -------------------------------------------- | ------ | --------- | --------------------------------------------------------------------------------------- |
| Översiktskarta                               | —      | —         | Finns redan på Tier 1-städer                                                            |
| Senaste 5 events                             | —      | —         | Finns redan                                                                             |
| **NY: Trend-graf** (events/dag senaste 90d)  | Liten  | Hög       | **Inline SVG** (0 KB JS) — inte Chart.js för en enkel linje. Lazy-loadad om below fold. |
| **NY: Brottsfördelning** (donut per typ)     | Liten  | Medel     | **Chart.js 4 tree-shaked** (~14 KB gzip). Inte ApexCharts (131 KB+).                    |
| **NY: Mest lästa events i området** (7d/30d) | Liten  | Medel     | Ren HTML, datan finns i `crime_views`-tabellen.                                         |

## Lager 2 — Kontextualisering (kräver mer jobb, stort värde)

| Tillägg                                           | Effort | SEO-värde      | Tekniskt val                                                                                             |
| ------------------------------------------------- | ------ | -------------- | -------------------------------------------------------------------------------------------------------- |
| **NY: Wikidata-fakta** (befolkning, kommun, area) | Medel  | Låg            | **Wikidata Q-id**, inte Wikipedia summary-prosa. Strukturerad data, ingen attribution-pålaga. Cache 30d. |
| **NY: SCB-tätortsbefolkning** (engångsimport)     | Medel  | Medel          | **Geopackage från SCB:s statistiska tätorter** (CC0, ingen attribution). Uppdateras vart 2–3 år.         |
| **NY: "Brott per 1000 invånare"**                 | Medel  | **Mycket hög** | CrimeGrade-modellen — den enda validerade vinnaren. Kräver tätortskod-mappning (förberedande todo).      |
| **NY: Jämförelsetabell** mot 5 grannstäder        | Medel  | **Mycket hög** | **Inbäddad i ortssidan**, inte separata `/jamfor/x-vs-y`-URL:er (thin-content-risk).                     |
| ~~Heatmap över händelse-koordinater~~             | —      | —              | **Avfärdad 2026-04-28** — canvas/SVG indexeras inte (inget SEO-värde), INP-risk på mobil, event-volym för gles utanför Tier 1. Markörer + clustering räcker. |

## Lager 3 — AI-genererat innehåll (dyrast, rikast)

| Tillägg                                                              | Effort | SEO-värde | Beroende                                  |
| -------------------------------------------------------------------- | ------ | --------- | ----------------------------------------- |
| **NY: AI-månadssammanfattning** per ort                              | Medel  | Hög       | Claude API (har redan), DailySummary-jobb |
| ~~AI-genererad "säkerhetstips"-sektion~~                             | —      | —         | **Avfärdad 2026-04-28** — E-E-A-T-risk (vi är inte säkerhetsexperter), hallucinations-risk på handlingsråd, helpful-content-straff för generiska tips. Polisen.se är auktoritativ avsändare. |
| **NY: AI-trend-analys** ("ökat/minskat X jämfört med förra månaden") | Medel  | Hög       | Faktabaserat, undvik hallucinationer      |

## Inte i scope (fundera senare eller skippa)

- **Wikipedia-summary-prosa** — strykt efter research. Wikipedia-trafik
  sjönk 8% mar–aug 2025 pga AI-svar. CC BY-SA-attribution skapar UX-friktion.
  Om vi vill ha befolkning från "Wikipedia"-source: använd Wikidata Q-id
  (strukturerad data utan attribution-pålaga).
- **ApexCharts** — strykt. 131 KB+ gzip vs Chart.js 14 KB tree-shaked
  för marginell feature-vinst.
- **Plotly.js** — strykt. >1 MB bundle, diskvalificerad för 80% mobil-
  trafik.
- **Separata `/jamfor/[a]-vs-[b]`-URL:er** — strykt. Programmatic SEO-
  straffrisk för thin content. Bygg jämförelsen IN i ortssidan istället.
- **Användarkommentarer** — modereringsbörda, AdSense-risk
- **Q&A/FAQPage schema** — fungerar bara om Q&A är äkta, inte spam-
  genererat. Risk för Google-straff.
- **Lokala nyhets-RSS** (Aftonbladet/Expressen) — komplexitet, upphovs-
  rättsfrågor
- **Trafikverket** — marginellt värde för en brotts-sajt

## Prioriteringsordning (justerad efter research)

1. **Trend-graf som inline SVG** (Lager 1) — 0 KB JS, lazy-loadad.
   Snabbaste vinsten utan CWV-risk.
2. **SCB-tätort + "brott per 1000 invånare"** (Lager 2) — den enda
   validerade vinnar-metriken (CrimeGrade-modellen). Engångs-import
   av Geopackage. Hög SEO + unikt.
3. **Jämförelsetabell mot 5 grannstäder, inbäddad i ortssidan**
   (Lager 2) — inte separata `/vs/`-URL:er.
4. **Brottsfördelning (donut, Chart.js tree-shaked) + Mest lästa**
   (Lager 1) — kompletterar Lager 1-bilden.
5. **AI-månadssammanfattning** för Tier 1-städer (Lager 3) —
   utöka befintlig DailySummary-infrastruktur. Striktig prompt mot
   egen data för att undvika hallucinationer.
6. ~~**Heatmap** (Lager 2)~~ — **avfärdad 2026-04-28**. Inget SEO-värde
   (canvas/SVG indexeras inte), INP-risk på mobil, event-volym för
   gles utanför Tier 1 för meningsfull densitet.
7. **Wikidata-fakta** (Lager 2) — sist eller stryk. Lägst marginal-
   värde efter att Wikipedia-trafik fallit.

## Risker

- **Performance.** Heatmap + trend-graf på varje sida kan sänka LCP.
  Mät innan implementation. **Lazy-load via IntersectionObserver för
  allt below fold** — fallstudie visade Lighthouse 30 → 90, LCP 6.5s →
  1.2s med bara den ändringen.
- **AdSense ATF-impact är kritiskt.** AccuWeather visade +42% CPM /
  +39% revenue från ATF-optimering — och baksidan: dålig placering
  kostar lika mycket. Trend-grafen får INTE trycka ner ad-units
  nedanför fold på mobil. Acceptanskriterium: RPM ska vara minst
  oförändrad efter rollout.
- **Programmatic SEO-straff** vid thin content. Tröskel: ≥500 ord
  unikt content per ortssida + 30-40% differentiering från andra
  orters sidor. Annars risk för 98%-deindex enligt hotels-fallet.
- **AI-hallucination.** Claude kan hitta på siffror om prompten inte
  är striktig. Allt AI-genererat innehåll måste fakta-checkas mot
  vår egen data via prompt-restriktioner.
- **Externa API-beroende.** SCB kan vara nere → cache aggressivt +
  grace fallbacks. Wikidata likadant.
- **Tätortskod-mappning är blocker.** Räkna med 1-2 dagars manuellt
  arbete för att mappa Brottsplatskartans plats-namn mot SCB:s
  tätortskoder + fallback till kommunnivå för platser som saknas
  i SCB:s register. Bör vara separat förberedande todo innan
  implementation startar.

## Beroenden mot andra todos

- **#25 (månadsvyer)** — månadssidan är en av de viktigaste platserna
  för detta innehåll. Synka implementeringsordning så vi inte bygger
  in i två versioner av sidan.
- **#11 (SEO-audit Fas 3)** — denna todo är delmängd av "Fas 3 evergreen-
  content"-spåret som #11 redan nämner. När #27 levereras, kvitta
  motsvarande punkter i #11.
- **#10 (AI-titlar)** — annan AI-användning. Börja med titlar (mindre
  risk) innan #27:s AI-månadssammanfattning skalas brett.

## Verifierade tekniska detaljer (research 2026-04-26)

### SCB Open Data

- **Befolkning per kommun**: `http://api.scb.se/OV0104/v1/doris/sv/ssd/BE/BE0101/BE0101A/BefolkningNy` (POST med JSON)
- **Statistiska tätorter** (mer relevant): SCB Geopackage,
  uppdateras vart 2–3 år. Definition: ≥200 invånare. <https://www.scb.se/vara-tjanster/oppna-data/oppna-geodata/statistiska-tatorter/>
- **Rate limit**: 150 000 celler/query, 30 calls/10s per IP. Ingen
  API-nyckel krävs.
- **Licens**: CC0 — ingen attribution.

### Wikidata (om vi vill ha "från Wikipedia"-källa)

- Hämta Q-id via `https://sv.wikipedia.org/api/rest_v1/page/summary/Uppsala` → `wikibase_item`
- Sen Wikidata API för strukturerad data (befolkning, area)
- Undviker prosa-parsning + CC BY-SA-attribution-pålaga

### Visualisering

- **Chart.js 4 tree-shakable till ~14 KB** för line-chart only
- **Inline SVG = 0 KB JS** för enkla linjer (rekommenderat för
  trend-grafen)
- **Leaflet.heat** <5 KB, klustrar punkter — designad för perf
- **Lazy-load via IntersectionObserver** för allt below fold

### Programmatic SEO

- Zapier-kategorin (unik data per sida) ✓
- ≥500 ord unikt + 30–40% differentiering = säkert
- <300 ord unikt = manuell straff-risk

## Status

Designfas. Research klar. Implementation startar tidigast efter:

1. **#25 har designbeslut** (URL-format för månadsvyn) — så vi inte
   bygger in samma sak två gånger
2. **Förberedande todo: tätortskod-mappning** — manuell mappning
   av plats-namn → SCB tätortskod + kommunfallback för platser
   som saknas. Räkna 1-2 dagars arbete.

När de två är klara: börja med Tier 1-städer + #25-piloten (Uppsala)
för att få enstaka komplett implementation att utvärdera mot KPI:er.
