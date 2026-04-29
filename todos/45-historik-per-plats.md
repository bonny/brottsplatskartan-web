**Status:** aktiv (skissad — research-fas pågår)
**Senast uppdaterad:** 2026-04-29
**Relaterad till:** #38 (BRÅ), #39 (MCF), #27 (rikare innehåll), #25 (månadsvyer)

# Todo #45 — Historik per plats: år-vy + trend

## Sammanfattning

Idag visar `/uppsala` bara senaste årets siffror för BRÅ + MCF. Ingen
navigation till äldre år finns. Vi har dock historisk data 1998–2025 i
MCF-tabellen och 2021–2025 i BRÅ — den döljs.

Bygg ut sajten med år-historik per plats för att synliggöra trender och
ge SEO-yta för långsvansfrågor som "anmälda brott uppsala 2020" eller
"trafikolyckor stockholm 2018".

## Bakgrund — vilken data finns per år?

Inventering av tillgängliga datakällor med år-granularitet:

| Källa              | Granularitet                        | Period      | Status          |
| ------------------ | ----------------------------------- | ----------- | --------------- |
| Polisens händelser | dag, koordinat, brottstyp           | 2014→nu     | egen DB         |
| BRÅ (#38)          | år, kommun, totalt + per_100k       | 2021, 2023+ | aggregerat      |
| MCF (#39)          | år, månad, kommun, 14 händelsetyper | 1998→2025   | aggregerat      |
| SCB-kommuner (#37) | år, kommun, befolkning              | nuvarande   | bara senaste år |
| MonthlySummary     | månad, plats, AI-text               | Tier 1, ~6m | egen tabell     |
| VMAAlert           | tid, region                         | egen DB     | aggregeras lätt |
| CrimeView          | event, dag, antal visningar         | egen DB     | "mest läst"     |

### Härledbart per år (utan ny datakälla)

- **Antal Polishändelser** per år/plats — `whereBetween(created_at, …)` + `getEventsNearby`
- **Topp brottstyper** per år/plats — befintlig query med år-filter
- **Veckodag-/timme-mönster** — när på dagen/veckan inträffar mest brott
- **Mest lästa händelser** det året — `CrimeView` joinas mot år
- **Trend mot föregående år** — diff %, högre/lägre än snitt
- **Per-månad-fördelning** inom året (säsongsvariationer)

### Saknas och bör övervägas

- **SCB historisk befolkning** — för att normalisera "brott/1000 inv. 2018".
  Finns i SCB:s PxWeb (samma teknik som MCF #39). Implementation ~2h.
  Lägg till `ar`-kolumn i `scb_kommuner` som är unik per (kommun_kod, ar).
- **AI-årssammanfattning** — som `MonthlySummary` fast per år. Ny pipeline,
  men trivial om månadssamfattningarna finns (concatenate + summarize).
  ~3h.
- **BRÅ historik före 2021** — bra.se har 2015–2020 i interaktiv databas
  men inte CSV. Manuell scraping eller skip.

## Förslag (preliminärt)

### Route + vy

`/{plats}/statistik` (senaste året, default-redirect)
`/{plats}/statistik/{ar}` (specifikt år, t.ex. `/uppsala/statistik/2024`)

Visar år-överblick:

1. **Hero-siffror** — totalt antal händelser, vanligaste brottstyp, +/-
   mot föregående år
2. **BRÅ + MCF-block** för året (samma partials som idag, men för valt år)
3. **Topp brottstyper** — bar chart, top 10
4. **Per-månad-fördelning** — sparkline eller bar chart över årets 12 månader
5. **Veckodag-fördelning** — när inträffar mest
6. **Mest lästa händelser det året** — från CrimeView
7. **AI-årssammanfattning** (om tillgänglig)
8. **År-switcher** — `‹ 2023 · 2024 · 2025 ›`

### Mini-trend i befintliga `/uppsala`-block (snabbvinst)

Innan full sida: lägg sparkline + 5-års-tabell i nuvarande BRÅ/MCF-partials.
Använder `BraStatistik::trendForKommun()` (finns redan, oanvänd) +
ny `MCFStatistik::trendForKommun()`. Länk till `/uppsala/statistik/{år}`
för fördjupning.

### Helpers att lägga till

```php
\App\MCFStatistik::trendForKommun($kommunKod, $fromAr, $toAr): Collection
\App\Helper::getYearlyStatsForPlats($plats, $ar): array
\App\Helper::getMostReadEventsNearbyYear(...)
```

### SEO

- En sida per (plats × år) — Tier 1-städer × ~10 år = ~50 nya pages
- Långsvansfrågor: "brott uppsala 2020", "trafikolyckor stockholm 2019"
- Schema.org `Dataset` eller `Report` för att hjälpa Google förstå att
  detta är aggregerad statistik
- noindex för år-vyer utan tillräcklig data (innan 2017 har vi inga events)

## Risker

- **Sidor utan data.** /lan-sidor och okända platser har ingen kommun_kod
  → ingen BRÅ/MCF. Måste tydligt visa "ingen statistik tillgänglig" eller 404.
- **Cache-explosion.** Plats × år × topp-N + sparklines kan generera
  hundratusentals cache-keys. Använd response-cache 30d (datan är
  retrospektiv) och var restriktiv med plats-uppsättningen som får
  statistik-sidor.
- **AI-årssammanfattning är inte gratis.** Om vi auto-genererar för alla
  Tier 1 × alla år bakåt blir det ~50 Claude-anrop. ~$5. OK men bör
  väntra till resten är klart.
- **Innan 2014 har vi ingen Polishändelse-data** — bara MCF (1998+) och
  BRÅ (2021+). Skapar inkonsistent UX om man bläddrar bakåt: vissa block
  visar ingen data, andra gör. Lös genom tydlig tom-stat-design eller
  hård-kodad gräns "från 2014".

## Confidence

**Medel.** Datan finns redan i DB. Mest jobb i view-design + SEO-strategi.
Mini-trend i befintliga block är låg-risk snabbvinst (2h). Full
år-vy-sida är 1–2 dagar och bör föregås av att mini-trenden visat sig
användbar (GA4-signal).

## Föreslagen ordning

1. **Fas 1 (snabbvinst):** Mini-trend i nuvarande BRÅ + MCF-partials —
   sparkline + 5-års-tabell. ~2h. Mät i GA4 om folk scrollar och bläddrar.
2. **Fas 2 (om Fas 1 visar engagemang):** Full `/{plats}/statistik/{ar}`-vy
   för Tier 1-städer. ~1 dag.
3. **Fas 3 (om Fas 2 fungerar):** SCB historisk befolkning (PxWeb-import) +
   AI-årssammanfattning + utöka till plats-singles. ~1 dag.

## Beroenden mot andra todos

- **#38 (BRÅ)** — `trendForKommun` finns redan, oanvänd
- **#39 (MCF)** — `trendForKommun` saknas, behöver läggas till
- **#37 (SCB)** — historisk befolkning behövs för "per_100k 2018" — egen
  fas-3-import
- **#27 Lager 3** — AI-månadssummeringar finns; årssumeringar ny pipeline

## Inte i scope

- **Realtime-statistik** — år-vyn är retrospektiv per definition
- **Län-historik** — börja med kommun. Län kan komma senare via
  aggregering över kommuner i samma län
- **Brottstyp-uppdelning från BRÅ** — BRÅ exponerar bara totaler i CSV;
  per-brottstyp kräver SOL-scraping (avfärdat i #38)
