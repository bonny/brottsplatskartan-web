**Status:** aktiv (Lager 2 startad 2026-04-28 — BRÅ-sektion live på 5 Tier 1-städer. Lager 1 + Lager 3 + utökning till plats-sidor återstår.)
**Senast uppdaterad:** 2026-04-28 — Lager 2 BRÅ-sektion deployad i prod
**Relaterad till:** #24 (Tier 1-städer), #25 (månadsvyer), #37 (SCB-befolkning), #38 (BRÅ-data)

> ## ✓ Update 2026-04-28 — Lager 2 BRÅ-sektion live
>
> #38 levererat och i prod. BRÅ-sektion deployad på alla 5 Tier 1-
> stadssidor (Stockholm, Göteborg, Malmö, Helsingborg, Uppsala) med:
>
> - "Anmälda brott i {kommun} kommun {år}" — antal + per 100k
> - Procent-jämförelse mot befolkningsviktat rikssnitt
> - Län-grannar-tabell sorterad per_100k med aktiv kommun framhävd
> - Källhänvisning Brå + mörkertal-disclaimer
>
> Filer: `app/Http/Controllers/CityController.php`,
> `resources/views/parts/bra-statistik.blade.php`,
> `resources/views/city.blade.php`. Helper-API: `App\BraStatistik`.
>
> **Återstår i Lager 2:**
>
> - Utökning till plats-sidor (`/plats/{plats}`) som matchar en kommun
>   via PlacePopulation (t.ex. Lund, Norrköping, Linköping)
> - Trend-graf 2015–2025 när vi importerar fler årgångar (just nu
>   bara 2024+2025)
> - Heatmap över händelse-koordinater (kräver INP-mätning först)
>
> **#37 är klar och användbar** för befolkningsfakta separat från
> BRÅ-sektionen. **Lager 1 (egen data: trend, donut, mest lästa)** och
> **Lager 3 (AI-månadssammanfattning)** är fortfarande opåverkade —
> kan startas oberoende.

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
| **NY: Heatmap** över händelse-koordinater         | Medel  | Hög            | **Leaflet.heat** (<5 KB). Mät INP innan rollout — kan vara dyrt vid >5 000 punkter.                      |

## Lager 3 — AI-genererat innehåll (dyrast, rikast)

| Tillägg                                                              | Effort | SEO-värde | Beroende                                  |
| -------------------------------------------------------------------- | ------ | --------- | ----------------------------------------- |
| **NY: AI-månadssammanfattning** per ort                              | Medel  | Hög       | Claude API (har redan), DailySummary-jobb |
| **NY: AI-genererad "säkerhetstips"-sektion**                         | Medel  | Medel     | Datadriven men generativ                  |
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
6. **Heatmap** (Lager 2) — efter att man mätt INP på mobil med
   riktiga datavolymer.
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
