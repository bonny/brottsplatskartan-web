**Status:** klar 2026-04-26
**Senast uppdaterad:** 2026-04-26

# Todo #24 — Dedikerade stadssidor för Tier 1-städer

## Utfört

- `CityController::$cities` utökad från 1 (stockholm) till 5: + malmo,
  goteborg, helsingborg, uppsala. Alla med ASCII-slugs (malmö → malmo)
  för konsistens.
- `normalizeCitySlug()` använder nu `Helper::toAscii()` så att
  `/Malmö`, `/malmö` osv 301:as till `/malmo`.
- `StockholmRedirectMiddleware` → omdöpt till `CityRedirectMiddleware`
  med generaliserad `REDIRECTS`-konstant som täcker alla Tier 1-städer:
  `/plats/malmö`, `/plats/Malmö`, `/plats/malmo` etc → `/malmo`.
- `Kernel.php` uppdaterad till nya middleware-namnet.
- `city.blade.php` återanvänds som-är. AI-sammanfattningar är `null`
  för icke-Stockholm-städer eftersom `DailySummary`-jobbet bara
  genererar för Stockholm idag (separat utbyggnad senare).
- Footer + overview-helicopter pekar på de nya `/{city}`-URL:erna.

## Verifierat lokalt

- `/malmo`, `/goteborg`, `/uppsala`, `/helsingborg`, `/stockholm` →
  alla 200 OK
- `/plats/Malmö`, `/plats/malmö` → 301 → `/malmo`
- `/plats/Uppsala`, `/plats/uppsala` → 301 → `/uppsala`
- `/plats/Västerås` (icke-Tier 1) → 301 → `/plats/västerås`
- PHPStan level 5: 0 errors

## Tier 2 (uppföljning, inte i denna iteration)

Västerås, Linköping, Norrköping, Lund, Umeå, Örebro, Eskilstuna,
Gävle, Borås, Sundsvall — efter SEO-utvärdering på Tier 1 om ~30 dagar.

## Problem

Stockholm fick en dedikerad `/stockholm`-sida (CityController) som
drar **8 493 sessions/30d** från Google organisk. Övriga stora
städer ligger kvar på `/plats/{stad}`-formatet och rankar
position 7-10 trots hög impression-volym.

GA + GSC-data säger att vi tappar trafik på stora städer pga sämre
ranking-format:

| Stad        | Sessions /30d | "polisen händelser X"-position | Top-imp-query                     |
| ----------- | ------------: | -----------------------------: | --------------------------------- |
| Malmö       |           766 |      7.6 (1397 imps, CTR 2.2%) | "polisen händelser malmö"         |
| Göteborg    |           584 |       9.2 (955 imps, CTR 1.7%) | "polisen händelser göteborg idag" |
| Kiruna      |           429 |    3.4 (~290 imps, CTR strong) | "polis nyheter kiruna"            |
| Helsingborg |           380 |                 7.8 (624 imps) | "polisen händelser helsingborg"   |
| Uppsala     |        (~150) |                                | "händelser uppsala"               |

## Åtgärd — Tier 1 (denna iteration)

Lägg till **Malmö, Göteborg, Helsingborg, Uppsala** i CityController.
Replikera Stockholm-mönstret med stadsspecifik data:

- Lat/lng (centroid)
- Län-namn (för polisstation + lan-stats)
- Radius (km) för "events nearby"-query
- Page title, meta-description, H1

Plus: utöka StockholmRedirectMiddleware → CityRedirectMiddleware som
301:ar `/plats/{stad}` → `/{stad}` för Tier 1.

### AI-sammanfattningar

DailySummary-tabellen filtrerar på `area`-kolumnen. Idag finns bara
Stockholm-summaries. **Behåller `null` på Tier 1 för nu** — sidan
funkar utan, sammanfattning kan läggas till i uppföljande todo som
bygger ut `GenerateDailySummaries`-jobbet.

### Polisstation

`getPoliceStationsCached` filtrerar per län. Funkar för alla städer
direkt (kräver bara att vi sätter `lan`-fältet rätt).

## Tier 2 (nästa våg, separat todo)

Västerås, Linköping, Norrköping, Lund, Umeå, Örebro, Eskilstuna,
Gävle, Borås, Sundsvall — gör efter att Tier 1 visat mätbar effekt
(SEO-data efter ~30 dagar).

## Tier 3 — skippa

Förorter (Botkyrka, Haninge, Täby) ligger kvar på `/plats/`-format.
Queries där är mer geografiskt specifika.

## Risk

- **Indexering tar tid:** nya `/{stad}`-URL:er behöver crawlas av
  Google. 301 från `/plats/{stad}` säger till Google att flytta över.
  Förvänta 2-4 veckors ranking-rörelser.
- **Innehållsskillnad:** Stockholm-sidan har AI-sammanfattningar +
  visuella element. Tier 1-städer får först en "magrare" version
  utan sammanfattning. Acceptabelt — sidan är fortfarande rikare
  än `/plats/{stad}`.
- **Sitemap:** måste uppdateras så `/{stad}`-URL:er listas och
  `/plats/{stad}`-versionerna tas bort.

## Status

Implementeras nu (2026-04-26).
