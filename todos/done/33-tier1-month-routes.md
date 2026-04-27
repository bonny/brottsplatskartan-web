**Status:** klar 2026-04-27
**Senast uppdaterad:** 2026-04-27
**Härledd från:** SEO-review av #25 — Tier 1-URL-namespace-split

# Todo #33 — Tier 1-städernas månadsvyer på `/uppsala/handelser/{year}/{month}`

## Problem

Idag har Tier 1-städer (uppsala/stockholm/malmo/goteborg/helsingborg)
en split URL-namespace:

- **Stadssida:** `/uppsala` (CityController, todo #24)
- **Månadsvy:** `/plats/uppsala/handelser/2026/04` (PlatsController, todo #25)

`/plats/uppsala` 301:as till `/uppsala`, men månadsvyer fick ett
undantag i `CityRedirectMiddleware` så de fungerar via `/plats/`-
prefixet. Resultat: månadsvyn ligger inte i samma URL-namespace som
stadens primära sida — Google kan inte lika tydligt knyta ihop
"uppsala-domain authority" med månadsvyerna.

## Lösning

Bygg parallella routes på `CityController::month()`:

```
/uppsala/handelser/{year}/{month}
/stockholm/handelser/{year}/{month}
/malmo/handelser/{year}/{month}
/goteborg/handelser/{year}/{month}
/helsingborg/handelser/{year}/{month}
```

`/plats/uppsala/handelser/{year}/{month}` 301:as till sina
`/uppsala/handelser/{year}/{month}`-motsvarigheter. Allt annat
(icke-Tier 1-platser, län) fortsätter använda
`/plats/{plats}/handelser/{year}/{month}`.

## Konsekvenser

- **SEO:** Tier 1-månadsvyer rankar nu under stadens domain-authority.
  AI Overviews + Google entity-graph kan länka direkt.
- **Sitemap:** uppdatera Tier 1-månadsvyer-URL:erna i `GenerateSitemap`
  till `/{city}/handelser/{year}/{month}`-format.
- **Månads-arkiv:** sidopanelen på Tier 1-stadssidor länkar nu till
  `/uppsala/handelser/2026/04` istället för `/plats/uppsala/...`.
- **Pilot 301:** dagsvy `/plats/uppsala/handelser/15-april-2026`
  301:as till `/uppsala/handelser/2026/04#2026-04-15` (ett hopp, inte
  en kedja).

## Implementation

1. `CityController::month($city, $year, $month)` — delegerar till
   `PlatsController::month()`-logiken med plats=`$city`. Kan
   återanvända controller-metoden direkt eller extrahera till en
   delad service.
2. Route: `/{city}/handelser/{year}/{month}` med where-constraint
   year=`\d{4}`, month=`\d{2}` + `whereIn('city', tier1Cities)`.
3. `CityRedirectMiddleware` — uppdatera handelser-undantaget så
   Tier 1-månadsvyer 301:as från `/plats/{tier1}/handelser/...` till
   `/{tier1}/handelser/...`.
4. `GenerateSitemap` — Tier 1-månadsvyer skrivs på nya formatet.
5. `parts.month-archive` — Tier 1 → city-route, övriga → plats-route.
6. `Helper::isInMonthlyViewsPilot` — pilot-flaggan stödjer båda
   URL-namespace för Tier 1.

## Risker

- **Dubblettindexering** under övergångsfasen — Google ser både
  `/plats/uppsala/handelser/2026/04` och `/uppsala/handelser/2026/04`
  tills 301-redirecten är crawlad. Mitigeras av canonical-tagg som
  pekar på city-versionen.
- **Pilot-flaggan** måste fungera oavsett URL-namespace — testa båda
  paths i pilot.

## Status

- [x] Skapa todo #33
- [x] Implementera CityController::month()
- [x] Route + middleware-uppdatering
- [x] Sitemap + arkiv-länkar uppdaterade
- [x] Smoke-test lokalt + live
- [x] Push + verifiera prod

## Confidence

**Hög.** Schema är likadant, controller-logic delas, ändring är
additiv (gamla URL:er 301:as men finns kvar för en period).
