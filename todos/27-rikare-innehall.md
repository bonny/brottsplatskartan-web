**Status:** aktiv (designfas — kompletteras av research-agent)
**Senast uppdaterad:** 2026-04-26
**Relaterad till:** #24 (Tier 1-städer), #25 (månadsvyer)

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

## Vinnar-mönster från liknande sajter

- **Unik egen data > kopierad extern data.** Wikipedia är low-value
  (alla har det). Spotcrime och Crimegrade vinner på unika
  visualiseringar och jämförelser.
- **Datavisualiseringar → dwell-time → CPM.** Heatmaps, trend-charts,
  donuts engagerar.
- **Jämförelser triggar engagement.** "Stockholm vs Uppsala-säkrast"-
  queries är vanliga.
- **Recency-signaler boostar.** Dynamiskt uppdaterade siffror tolkas
  som "färsk" av Google.

## Lager 1 — Kärninnehåll (på alla `/<ort>` + månadssidor)

| Tillägg                                      | Effort | SEO-värde | Notering                             |
| -------------------------------------------- | ------ | --------- | ------------------------------------ |
| Översiktskarta                               | —      | —         | Finns redan på Tier 1-städer         |
| Senaste 5 events                             | —      | —         | Finns redan                          |
| **NY: Trend-graf** (events/dag senaste 90d)  | Liten  | Hög       | Recency + visuellt → dwell-time      |
| **NY: Brottsfördelning** (donut per typ)     | Liten  | Medel     | Engagement, mindre sökord-rikt       |
| **NY: Mest lästa events i området** (7d/30d) | Liten  | Medel     | Datan finns i `crime_views`-tabellen |

## Lager 2 — Kontextualisering (kräver mer jobb, stort värde)

| Tillägg                                             | Effort | SEO-värde      | Beroende                         |
| --------------------------------------------------- | ------ | -------------- | -------------------------------- |
| **NY: Wikipedia-snippet** (befolkning, kommun, län) | Liten  | Låg            | Wikipedia REST API, cache 30d    |
| **NY: SCB-demografi** (befolkning per ort)          | Medel  | Medel          | SCB Open Data API                |
| **NY: "Brott per 1000 invånare"**                   | Medel  | **Mycket hög** | Kräver SCB-befolkning            |
| **NY: Jämförelsetabell** mot 3–5 grannstäder        | Medel  | **Mycket hög** | Triggar "X vs Y-säkrast"-queries |
| **NY: Heatmap** över händelse-koordinater           | Medel  | Hög            | Leaflet.heat eller liknande      |

## Lager 3 — AI-genererat innehåll (dyrast, rikast)

| Tillägg                                                              | Effort | SEO-värde | Beroende                                  |
| -------------------------------------------------------------------- | ------ | --------- | ----------------------------------------- |
| **NY: AI-månadssammanfattning** per ort                              | Medel  | Hög       | Claude API (har redan), DailySummary-jobb |
| **NY: AI-genererad "säkerhetstips"-sektion**                         | Medel  | Medel     | Datadriven men generativ                  |
| **NY: AI-trend-analys** ("ökat/minskat X jämfört med förra månaden") | Medel  | Hög       | Faktabaserat, undvik hallucinationer      |

## Inte i scope (fundera senare eller skippa)

- **Användarkommentarer** — modereringsbörda, AdSense-risk
- **Q&A/FAQPage schema** — fungerar bara om Q&A är äkta, inte spam-
  genererat. Risk för Google-straff.
- **Lokala nyhets-RSS** (Aftonbladet/Expressen) — komplexitet, upphovs-
  rättsfrågor
- **Trafikverket** — marginellt värde för en brotts-sajt

## Prioriteringsordning (förslag, kommer justeras efter research)

1. **Trend-graf** (Lager 1) — liten effort, hög UX/SEO-vinst,
   fungerar oberoende av #25
2. **SCB-befolkning + "brott per 1000 invånare"** (Lager 2) —
   sökord-rikt, unikt innehåll
3. **Jämförelse mot grannstäder** (Lager 2) — triggar X vs Y-queries
4. **AI-månadssammanfattning** för Tier 1-städer (Lager 3) —
   utöka befintlig DailySummary-infrastruktur
5. **Brottsfördelning + Mest lästa** (Lager 1) — kompletterande
6. **Heatmap** (Lager 2) — visuellt vinnande men lägre prioritet
7. **Wikipedia-snippet** (Lager 2) — sist, lägst marginalvärde

## Risker

- **Performance.** Heatmap + trend-graf på varje sida kan sänka LCP.
  Mät innan implementation, lazy-load bilden av kartan/grafen.
- **AI-hallucination.** Claude kan hitta på siffror om prompten inte
  är striktig. Allt AI-genererat innehåll måste fakta-checkas mot
  vår egen data via prompt-restriktioner.
- **Externa API-beroende.** Wikipedia/SCB kan vara nere → cache
  aggressivt + grace fallbacks.
- **Internationalization-fällor** för SCB — orter måste mappas mot
  SCB:s tätortskod. Vissa platser saknas i SCB:s register.
- **AdSense viewability.** Trend-graf ovanför fold kan trycka ned
  ad-units. Mät impact innan rollout.

## Beroenden mot andra todos

- **#25 (månadsvyer)** — månadssidan är en av de viktigaste platserna
  för detta innehåll. Synka implementeringsordning så vi inte bygger
  in i två versioner av sidan.
- **#11 (SEO-audit Fas 3)** — denna todo är delmängd av "Fas 3 evergreen-
  content"-spåret som #11 redan nämner. När #27 levereras, kvitta
  motsvarande punkter i #11.
- **#10 (AI-titlar)** — annan AI-användning. Börja med titlar (mindre
  risk) innan #27:s AI-månadssammanfattning skalas brett.

## Öppna frågor som behöver research

- Vilka liknande svenska sajter (hitta.se, eniro, lokaltidningar,
  spotcrime-motsvarigheter) gör vad — och har det fungerat?
- SCB Open Data: vilken endpoint för "befolkning per tätort"?
  Hur ofta uppdateras data?
- Wikipedia REST API: räcker `summary`-endpointen eller behöver vi
  parsa infobox?
- Hur väl funkar Leaflet.heat på mobil (80% av trafiken)?
- Vilka faktiska CTR-lyft har "X vs Y"-jämförelsesidor visat på
  liknande sajter?

## Status

Designfas. Research-agent ska komplettera med:

- Verifierade case-studies från liknande svenska sajter
- API-detaljer för SCB + Wikipedia
- Konkreta exempel på hur jämförelse-tabeller har strukturerats
  hos konkurrenter
- CWV-impact av tunga visualiseringar (vad kostar det i LCP/INP?)

Implementation startar tidigast efter att #25 (månadsvyer) har
designbeslut + URL-format-beslut, så vi inte bygger in samma sak
två gånger.
