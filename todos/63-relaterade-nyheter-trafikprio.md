**Status:** aktiv (RSS-grund deployad 2026-05-01 — fas-1-pilot pågår, blockerar #60/#64)
**Senast uppdaterad:** 2026-05-01

# Todo #63 — Automatisera/förenkla relaterade nyheter, prioritera high-traffic events

## Sammanfattning

Kan vi automatisera eller förenkla hämtning av relaterade nyheter till en
händelse? Och bör vi prioritera hämtning främst för de händelser som
faktiskt får mycket trafik — istället för att jaga matchningar för alla
~500 events/dygn?

**Beslut efter SEO-research (2026-05-01):** #63 är **fas 1 av en
flerstegs-plan** som leder till [#60](60-auto-lank-nyheter-ai-rss.md):s
breda ansats. SEO-datan visar att event-trafiken är flat-tail (top-50 =
bara 20 % av clicks → bredd vinner SEO-mässigt på sikt), men #63 är
rätt ställe att börja för att **billigt validera AI-precision, UI och
nofollow-policy** innan vi skalar upp.

## Bakgrund

- Brottsplatskartan har idag ingen integration med media-feeds.
- ~500 events/dygn — bara en bråkdel får signifikant trafik. Lång svans
  av events får < 10 visningar/dygn.
- Att köra Claude-matchning på alla events är trolig overkill om vi
  bara vill berika de mest besökta sidorna.
- GA4 + GSC vet vilka events som får trafik — vi kan ranka dagligen.

## Förslag

### Variant A: Trafik-triggad fetch (smal, billig)

1. **Schemalagd job dagligen** (eller var 4:e h):
    - Hämta top-N events från GA4 senaste 7d (säg N=50–200).
    - Eller: top-N events med flest sidvisningar/CTR i GSC.
2. **Bara för dessa N events:** hämta relaterade nyheter via:
    - **RSS-pre-filter** (#60-pipeline) → kandidat-artiklar.
    - **Claude Haiku-matchning** → bekräfta `ja/kanske/nej`.
3. **Cache:** `crime_event_news` (event_id, news_id, confidence,
   ai_reason). Skippa retry för 30d om inget träffades.
4. **Visning:** "Mediabevakning"-sektion på event-sidan, bara om
   matchning finns.

**Vinst:** ~50–200 events/dygn istället för 500 → 80 % billigare.
Träffar exakt de sidor besökare faktiskt läser.

### Variant B: Förenkla — strunta i RSS, använd bara Google News-sök

1. För varje event som passerar trafik-tröskeln, kör en
   **Google News query** (gratis via news.google.com/rss?q=...) med
   `parsed_title + parsed_title_location + datum`.
2. Dedupliera, ta top-3 träffar, visa direkt utan AI-matchning.
3. Risken: irrelevanta träffar. Men för stora händelser (skottlossning,
   gripande) brukar Google News redan vara välsorterad.

**Vinst:** ingen Claude-kostnad alls. Risk: precisions-fall.

### Variant C: Hybrid

- **Pre-filter via Google News-query** (gratis, smal).
- **Claude validerar bara matchningarna** (1–2 anrop per event istället
  för 10).
- **Trafik-tröskel** styr fetch-frekvens: top-50 events/dag varje 4:e h,
  resten en gång per vecka.

## Trafik-data idag

GA4 (`mcp__analytics-mcp__run_report`) ger pageViews per route. Behöver
en daglig query på `pagePath ~= /handelse/...` toppN. GSC ger position +
clicks per page.

## Risker

- **Trafik-bias:** stora storstadshändelser får ännu mer media-länkning,
  glesbygd får ingen → SEO-asymmetri. Acceptabelt för en första
  iteration men värt att flagga.
- **Dubbelarbete med #60:** om båda implementeras separat blir det
  förvirrande — välj en pipeline.
- **GA4-API-kostnad:** att fråga GA4 dagligen är gratis upp till
  kvotgränsen. Cache resultatet.

## Beslut fattade (2026-05-01)

1. **Slå inte ihop med #60** — #63 är fas 1, #60 är fas 3. Flerstegs-
   plan med data-grindar mellan stegen.
2. **Variant C (hybrid)** — Google News SE-search som gratis pre-filter
    - Claude Haiku-validering på kandidater.
3. **Trafik-tröskel:** top-50 events från GA4 senaste 7d för fas 1.
   Fas 2 expanderar till top-1000 (~70 % av event-clicks).

## Research — RSS/API + ToS för svenska nyhetssajter

**Detaljerad rapport:** [`tmp-news-research/news-rss-tos-2026-05-01.md`](../tmp-news-research/news-rss-tos-2026-05-01.md) (gitignored).

### Slutsats

18 av 22 utredda sajter har fungerande RSS. Inga storstadsmediers
publika ToS förbjuder titel-länkning eller RSS-fetch. Endast TT
Nyhetsbyrån (§5.1) förbjuder explicit "robotar, skrapor, spindlar" —
men deras innehåll syndikeras ändå via DN/SvD/Expressen så vi förlorar
ingenting.

### Rekommenderad fas-1 (5 källor)

| #   | Källa                  | RSS-URL                                                                         | Motivering                                                                            |
| --- | ---------------------- | ------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------- |
| 1   | **Google News SE**     | `news.google.com/rss?hl=sv&gl=SE&ceid=SE:sv`                                    | Sök per plats/datum, täcker alla medier i en query — bästa pre-filter för Variant B/C |
| 2   | **SVT Nyheter**        | `svt.se/rss.xml` + `/nyheter/inrikes/rss.xml` + `/nyheter/lokalt/{ort}/rss.xml` | Public service-uppdrag = juridiskt rentaste valet                                     |
| 3   | **Aftonbladet**        | `rss.aftonbladet.se/rss2/small/pages/sections/senastenytt`                      | Högsta volym på brottsbevakning                                                       |
| 4   | **Expressen-familjen** | `feeds.expressen.se/{nyheter,gt,kvallsposten}`                                  | Tre feeds = full lokal storstadstäckning, samma pipeline                              |
| 5   | **DN**                 | `dn.se/rss/`                                                                    | Kvalitetssignal; paywall driver klick mot källan                                      |

### Undvik

- ⚠ **TT Nyhetsbyrån** — ToS §5.1 förbjuder robotar/skrapor
- **Omni** — ingen publik feed (alla `/rss`-varianter ger 404)
- **DI** — för smal ekonomifokus relativt brottsdomän

### Juridisk grund (vi citerar inte body, bara titel + källa + länk)

- **Svensk URL § 22** (citaträtt) — täcker fall vi inte ens åberopar
- **Svensson C-466/12** (EU-dom) — länkning till lagligt publicerat
  material är inte ny tillgänglighet → inget upphovsrättsbrott
- **DSM-direktivet art. 15** (press-publishers' right) — påverkar bara
  utdrag längre än "very short extracts"; titel + länk är OK

## Uppdaterad rekommendation

**Variant C (hybrid) med Google News SE som pre-filter:**

1. Daglig GA4-query → top-50 mest besökta events senaste 7d
2. Per top-event: Google News SE-search (`?q={parsed_title}+{ort}`,
   datum-filter ±2 dagar) → 0–10 kandidatartiklar (gratis)
3. Claude Haiku validerar varje kandidat → spara bara `ja`-träffar i
   `crime_event_news`
4. Visning: "Mediabevakning"-sektion på event-sidan, bara om matchning
   finns. Format: `<sajt-favicon> Titel — källa — datum`. **Använd
   `rel="nofollow"`** på outbound-länkar (SEO-research-rek).

**Kostnadsestimat:** ~50 events × ~5 kandidater × Haiku ($0.001) =
**~$0.25/dygn**. Värt det även för låg träffrate.

## SEO-research (2026-05-01)

Detaljerad analys: [`tmp-news-research/seo-60-vs-63-2026-05-01.md`](../tmp-news-research/seo-60-vs-63-2026-05-01.md).

**Nyckelfynd:** event-trafiken är **flat-tail** — top-50 ger bara 20 %
av GSC event-clicks (90d). Det är kontraintuitivt: pareto-antagandet
("fokusera där trafiken redan är") håller inte. 80 % av sökeftefrågan
ligger i long-tail (15 755 unika sidor får clicks), och det är där
positions-lyften händer (rank 8–15 är rörlig; top-50 ligger redan på
rank 6–10 = mättat).

**Detta vänder rekommendationen jämfört med #63:s ursprungliga premiss.**
Smal ansats är inte SEO-optimal **på sikt** — men det är rätt sätt att
**börja** för att billigt validera AI-precision och UI innan vi
investerar i bred täckning.

## Fas-plan (uppdaterad — ersätter Variant A/B/C-valet)

**Fas 1 — #63 smal (4–6v, ~$0.25/dygn):**

- Top-50 events från GA4 senaste 7d
- Google News SE pre-filter + Haiku-validering
- Etablera UI, AI-precision-tröskel >80 %, `rel="nofollow"`-policy
- **Mätning:** CTR + dwell time på event-sidor med media-sektion vs
  utan. AI-precision via stickprov på 30 träffar.

**Fas 2 — mid-tier (om fas 1 passerar grindar):**

- Top-1000 events ≥10 clicks/30d (~10 events/dygn, ~$1/dygn)
- Täcker ~70 % av event-clicks
- **Mätning:** GSC-positions-lyft per query-typ, 30d-jämförelse mot
  baseline.

**Fas 3 — #60 full bredd (om mid-tier passerar):**

- Alla nya events (~150/dygn med media-länkar, ~$5/dygn)
- Långsiktig SEO-vinst på long-tail
- Förutsättning: GSC-positions-lyft >1.0 i mid-tier + AI-precision
  håller.

**Grindar mellan faserna:** AI-precision >80 %, CTR-effekt mätbar,
ingen indikation på "thin content"-flagga från GSC.

## Confidence

**Medel.** Tekniskt enkel — det smala scoped:t (top-N events) gör det
billigt och billigt att avfärda om utfallet är dåligt. Men beslutsfrågan
om hur det relaterar till #60 är öppen.

## Beroenden

- **Synergi med #60** — samma pipeline-stomme, bara olika scope. En
  bör ersätta eller subsumera den andra.
- Bygger på #8 (GA4 MCP) för trafik-data.
- Bygger på #28 (laravel/ai) för Claude-matchning (om Variant A/C).

## Nästa steg

1. Bestäm: slå ihop med #60 eller pilot:a som egen smalare variant?
2. Om egen pilot: kör Variant A med top-50 events från GA4 senaste 7d,
   manuell utvärdering på 10 events att precision/recall är OK.
3. Mätperiod 14d → beslut om scale upp till #60:s breda pipeline.

## Implementations-status (2026-05-01)

**Fas 0 klar:** RSS-fetcher live i prod 2026-05-01 (commits `d25e91a` +
`7589d57`).

- 29 RSS-feeds hämtas var 15:e min till `news_articles`-tabellen
- Källor: Google News SE, SVT (rss + inrikes + 20 lokala redaktioner),
  Aftonbladet, Expressen-familjen (3 feeds), DN, SvD
- Dedupe via `content_hash` (sha256 source|url) + unique-index
- Retention 90d via `app:news:prune` dagligen
- Bara titel + summary från feeden — ingen body-skrapning
- Polite UA, CURLOPT_CONNECTTIMEOUT 5s, set_timeout 8s, withoutOverlapping
- ~880 artiklar/körning, ~80k rader steady-state

**Filer:**

- `app/Console/Commands/FetchNewsRss.php`
- `app/Console/Commands/PruneNewsArticles.php`
- `app/Models/NewsArticle.php`
- `config/news-feeds.php`
- `database/migrations/2026_05_01_120000_create_news_articles_table.php`
- `database/migrations/2026_05_01_140000_news_articles_pubdate_to_datetime.php`

**Återstår för fas 1:**

1. GA4-query för top-50 events senaste 7d (var 4:e h cron)
2. Per top-event: Google News SE-search → kandidatartiklar
3. Claude Haiku-validering → spara `crime_event_news` (event_id, news_id, confidence, ai_reason)
4. Visning på event-sidan: "Mediabevakning"-sektion (`rel="nofollow"`)
5. Mätning: AI-precision (stickprov 30 träffar), CTR + dwell time
