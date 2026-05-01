# Claude TODO – Brottsplatskartan

Index över förbättringsarbete. Varje todo har en egen fil under
[`todos/`](todos/) med fullständig analys. Konvention och
mappstruktur: [`todos/README.md`](todos/README.md).

Senast uppdaterad: 2026-05-01 (#53 — empirisk analys: 180 träffar, 2 FP, lösning = ta bort 1 regex).

## Aktiva

| #   | Titel                                                   | Status                                                                                              | Fil                                                                                                |
| --- | ------------------------------------------------------- | --------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------- |
| 25  | Månadsvyer istället för dagsvyer (datum-routes)         | Pilot live 2026-04-27 (uppsala + västerås + uppsala-lan). 30d-mätning till 2026-05-27               | [todos/25-manadsvyer-datum-routes.md](todos/25-manadsvyer-datum-routes.md)                         |
| 29  | Audit + reducera indexerade pages                       | Datum-routes + thin singles deployat, ~22k pages noindex:as. Mätperiod 30–90d i GSC                 | [todos/29-audit-indexerade-pages.md](todos/29-audit-indexerade-pages.md)                           |
| 36  | GSC-mätning av AI-titlars CTR-effekt                    | Mätperiod startad 2026-04-27, första check 2026-05-25                                               | [todos/36-gsc-matning-ai-titlar.md](todos/36-gsc-matning-ai-titlar.md)                             |
| 39  | MSB/MCF brand- och räddningsstatistik per kommun        | Implementerad 2026-04-29 — väntar på trafikmätning 2026-05-13                                       | [todos/39-msb-brand-rakning-statistik.md](todos/39-msb-brand-rakning-statistik.md)                 |
| 41  | Datumnavigering som årskalender                         | Idé — ny visuell månadsnav (12 rutor + heatmap-färg)                                                | [todos/41-arskalender-datumnavigering.md](todos/41-arskalender-datumnavigering.md)                 |
| 45  | Historik per plats: år-vy + trend                       | Skissad — fas 1 mini-trend (2h), fas 2 /{plats}/statistik/{år}-vy                                   | [todos/45-historik-per-plats.md](todos/45-historik-per-plats.md)                                   |
| 46  | Slå samman Händelser/Senaste/Mest lästa i menyn         | Importerad från GitHub #76 — kräver design + redirect-strategi                                      | [todos/46-meny-handelser-konsolidering.md](todos/46-meny-handelser-konsolidering.md)               |
| 47  | Slå ihop stad-URLs (plats vs plats+län vs län)          | Importerad från GitHub #68 — fortsättning på #23/#35-mönstret                                       | [todos/47-sla-ihop-stad-urls.md](todos/47-sla-ihop-stad-urls.md)                                   |
| 48  | Polisens JSON-API + bättre geocoding                    | Fas 1 + Fas 2 deployat 2026-04-29 — soak pågår, mätperiod på geo-träff                              | [todos/48-geocode-inkludera-lan.md](todos/48-geocode-inkludera-lan.md)                             |
| 50  | Trafikverket Trafikinformation: live på kartan          | Skissad + SEO/AdSense-review klar — väntar på beslut om indexerbarhet & editor-text                 | [todos/50-trafikverket-trafikinformation-live.md](todos/50-trafikverket-trafikinformation-live.md) |
| 51  | Övriga datakällor: research-skiss                       | Research-katalog (SMHI, räddningstjänst-RSS, Krisinfo, m.fl.) — bryts ut per källa                  | [todos/51-ovriga-datakallor-research.md](todos/51-ovriga-datakallor-research.md)                   |
| 52  | GSC-monitor: lågrankade högvolym-queries                | Baseline klar 2026-04-30 — 7 åtgärder identifierade (A–G), ~25k clicks/90d potential                | [todos/52-gsc-low-rank-monitoring.md](todos/52-gsc-low-rank-monitoring.md)                         |
| 53  | Återaktivera presstalesperson-filter                    | Klar för impl — empirisk analys 2026-05-01: ta bort `/presstalesperson.*tjänst/i`, 178 träffar 0 FP | [todos/53-aterativera-presstalesperson-filter.md](todos/53-aterativera-presstalesperson-filter.md) |
| 54  | Trafikkontroll-titlar: utöka AI-rewrite                 | Idé — utöka `isVagueTitle()` med trafikkontroll-mönster                                             | [todos/54-trafikkontroll-titlar.md](todos/54-trafikkontroll-titlar.md)                             |
| 55  | Kortare/snyggare URL:er för kartbilder                  | Skissad — proxy-route `/k/{id}-{w}x{h}`; blockerad av #61                                           | [todos/55-kortare-kartbild-urls.md](todos/55-kortare-kartbild-urls.md)                             |
| 57  | Aktivera Hetzners referral-program                      | Idé — länk på `/sida/om` med transparent disclosure                                                 | [todos/57-hetzner-referral.md](todos/57-hetzner-referral.md)                                       |
| 58  | spatie/laravel-markdown-response för AI-agenter         | Idé — utöka #12 till alla sidor via auto-detect-middleware                                          | [todos/58-laravel-markdown-response.md](todos/58-laravel-markdown-response.md)                     |
| 59  | "Vad händer nu"-ruta (Krimkartan-känsla)                | Idé — kompakt feed-komponent på startsidan                                                          | [todos/59-vad-hander-nu-ruta.md](todos/59-vad-hander-nu-ruta.md)                                   |
| 60  | Auto-länka events till nyheter via AI + RSS             | Fas 3 (bred) — kör efter #63 visat positivt utfall; SEO-research klar 2026-05-01                    | [todos/60-auto-lank-nyheter-ai-rss.md](todos/60-auto-lank-nyheter-ai-rss.md)                       |
| 61  | Caddy med cache-handler, ersätt nginx-tiles             | Skissad — egen Caddy-image med Souin/badger; konsoliderar tile-cache + frigör #55                   | [todos/61-caddy-cache-handler.md](todos/61-caddy-cache-handler.md)                                 |
| 63  | Relaterade nyheter — prio:a high-traffic events         | Fas 1 (smal pilot) blockerar #60; SEO-research klar 2026-05-01 — Variant C rek                      | [todos/63-relaterade-nyheter-trafikprio.md](todos/63-relaterade-nyheter-trafikprio.md)             |
| 64  | Per-plats nyhetsaggregering — "Senaste nyheter i {ort}" | Skissad — kompletterar #60/#63 med klassifikation per plats; större SEO-träffyta                    | [todos/64-per-plats-nyhetsaggregering.md](todos/64-per-plats-nyhetsaggregering.md)                 |

### Beroenden

- **#10 → #36:** GSC-mätning bygger på #10:s rendering-deploy. _(#10 klar 2026-04-27 — #36 mätperiod startad samma dag.)_
- **#61 → #55:** `/k/*`-routen i #55 vill ha cache-handler i Caddy igång före launch. _(#61 ska soaka 7d innan #55 startas.)_
- **#63 → #60:** #63 är fas-1-pilot (smal, top-50 events) som validerar AI-precision + UI; #60 är fas-3-rollout (alla events). SEO-research 2026-05-01 visar att event-trafik är flat-tail → bred ansats vinner på sikt, men smal pilot först.

### Föreslagen ordning

1. **#25 Månadsvyer** — Uppsala-pilot pågår, 30d-mätning till 2026-05-27
2. **#41** — datumnavigering som årskalender — bygger på #42-fundament
3. **#50** — Trafikverket live-feed (egen layer + API-nyckel) — hög confidence
4. **#29** — passiv GSC-mätperiod, åtgärder efter data
5. **#36** — passiv GSC-mätning, första check 2026-05-25
6. **#39** — passiv mätning, första check 2026-05-13
7. **#51** — bryt ut SMHI/räddningstjänst-källor till egna todos när prio sätts

## Uppföljningar — datum att komma ihåg

Datum-bundna manuella åtgärder som inte går att autoschemalägga (kräver lokala
MCP:s som `mcp-gsc`, SSH-nycklar till prod, eller mänsklig bedömning).
Granska veckovis. När en åtgärd är gjord, flytta raden till "Avklarade" nedan
eller markera todon som klar.

| Datum      | Åtgärd                                                       | Todo                                            |
| ---------- | ------------------------------------------------------------ | ----------------------------------------------- |
| 2026-05-13 | MCF räddningsstatistik — utvärdera trafikimpact i GA4 + GSC  | [#39](todos/39-msb-brand-rakning-statistik.md)  |
| 2026-05-25 | GSC-mätning AI-titlar — första check (4v post-deploy)        | [#36](todos/36-gsc-matning-ai-titlar.md)        |
| 2026-05-27 | Månadsvyer-pilot — 30d-mätning Uppsala/Västerås              | [#25](todos/25-manadsvyer-datum-routes.md)      |
| 2026-06-22 | GSC-mätning AI-titlar — andra check (8v)                     | [#36](todos/36-gsc-matning-ai-titlar.md)        |
| 2026-06-30 | GSC image-search — 60d-mätning av nya `getMapAltText()`      | [#62](todos/done/62-getmapalttext-image-seo.md) |
| 2026-07-27 | GSC-mätning AI-titlar — tredje check (12v) + beslut om fas 3 | [#36](todos/36-gsc-matning-ai-titlar.md)        |
| 2026-07-27 | Indexerade pages — slutmätning (90d post-noindex)            | [#29](todos/29-audit-indexerade-pages.md)       |
| 2026-07-30 | GSC-monitor: kvartalsrapport (90d compare mot baseline)      | [#52](todos/52-gsc-low-rank-monitoring.md)      |

### Avklarade uppföljningar

(flytta hit med faktiskt utfallsdatum när en check-in är gjord)

## Klara

Sorterade nyast först.

| #   | Titel                                                               | Klar       | Fil                                                                                          |
| --- | ------------------------------------------------------------------- | ---------- | -------------------------------------------------------------------------------------------- |
| 62  | `getMapAltText()` förbättrad för image-search-SEO                   | 2026-05-01 | [todos/done/62-getmapalttext-image-seo.md](todos/done/62-getmapalttext-image-seo.md)         |
| 56  | Kartbilder: skarpare på retina (DPR @2x srcset)                     | 2026-04-30 | [todos/done/56-kartbilder-format.md](todos/done/56-kartbilder-format.md)                     |
| 16  | Rensa / avveckla gamla DO-servern (Dokku)                           | 2026-04-29 | [todos/done/16-rensa-do-server.md](todos/done/16-rensa-do-server.md)                         |
| 44  | EventsMap-API filtreras per stad eller län                          | 2026-04-28 | [todos/done/44-eventsmap-filter-per-plats.md](todos/done/44-eventsmap-filter-per-plats.md)   |
| 42  | Designa om månadsnav i högerspalten                                 | 2026-04-28 | [todos/done/42-manadsnav-hogerspalt-design.md](todos/done/42-manadsnav-hogerspalt-design.md) |
| 43  | Designbuggar på Tier 1-månadsvy (grå ruta + brutna marker-ikoner)   | 2026-04-28 | [todos/done/43-manadsvy-design-bugs.md](todos/done/43-manadsvy-design-bugs.md)               |
| 27  | Berika ort- och månadssidor med rikare innehåll (Lager 1+2+3)       | 2026-04-28 | [todos/done/27-rikare-innehall.md](todos/done/27-rikare-innehall.md)                         |
| 38  | BRÅ anmälda brott per kommun (datapipeline + helper)                | 2026-04-28 | [todos/done/38-bra-data-integration.md](todos/done/38-bra-data-integration.md)               |
| 37  | Tätortskod-mappning (SCB) för "brott/1000 inv."                     | 2026-04-27 | [todos/done/37-tatortskod-mappning-scb.md](todos/done/37-tatortskod-mappning-scb.md)         |
| 10  | AI-omskriva vaga titlar (rendering + auto-trigger för hela Sverige) | 2026-04-27 | [todos/done/10-ai-omskriva-titlar.md](todos/done/10-ai-omskriva-titlar.md)                   |
| 35  | Redirect `/lan/Uppsala län` → `/uppsala` (Stockholm-mönstret)       | 2026-04-27 | [todos/done/35-lan-redirect-uppsala.md](todos/done/35-lan-redirect-uppsala.md)               |
| 32  | Schema.org-sweep (NewsArticle+Place+sameAs+CollectionPage+cache)    | 2026-04-27 | [todos/done/32-schema-sweep.md](todos/done/32-schema-sweep.md)                               |
| 34  | Långa event-slugs — kort URL för events från 2026-04-28             | 2026-04-27 | [todos/done/34-langa-event-slugs.md](todos/done/34-langa-event-slugs.md)                     |
| 33  | Tier 1-städer på `/{city}/handelser/{year}/{month}`                 | 2026-04-27 | [todos/done/33-tier1-month-routes.md](todos/done/33-tier1-month-routes.md)                   |
| 30  | CWV-optimering Fas 1 (LCP -84 %, perf 51→80)                        | 2026-04-26 | [todos/done/30-cwv-optimering.md](todos/done/30-cwv-optimering.md)                           |
| 28  | Migrera AI-stack till `laravel/ai` (Sonnet 4.6 + v2)                | 2026-04-26 | [todos/done/28-migrera-laravel-ai.md](todos/done/28-migrera-laravel-ai.md)                   |
| 21  | Migrera antonblomqvist.se + simple-fields.com (DNS ok)              | 2026-04-26 | [todos/done/21-migrera-statiska-sajter.md](todos/done/21-migrera-statiska-sajter.md)         |
| 20  | Kartbilder med cirklar (default circle, soak ok)                    | 2026-04-26 | [todos/done/20-kartbilder-med-cirklar.md](todos/done/20-kartbilder-med-cirklar.md)           |
| 11  | SEO-audit 2026 (Fas 1+2; CWV→#30, OG-image avfärdat)                | 2026-04-26 | [todos/done/11-seo-audit-2026.md](todos/done/11-seo-audit-2026.md)                           |
| 31  | TTFB-anomali på /lan/{lan} (löst av cache-warmup)                   | 2026-04-26 | [todos/done/31-ttfb-anomali.md](todos/done/31-ttfb-anomali.md)                               |
| 26  | Search Console MCP (mcp-gsc) + sitemap submission                   | 2026-04-26 | [todos/done/26-gsc-mcp.md](todos/done/26-gsc-mcp.md)                                         |
| 24  | Tier 1-städer (malmo/goteborg/helsingborg/uppsala)                  | 2026-04-26 | [todos/done/24-stadcontroller-tier1.md](todos/done/24-stadcontroller-tier1.md)               |
| 23  | Case-redirect på /plats/{plats} + footer-städning                   | 2026-04-26 | [todos/done/23-platssidor-case-duplikat.md](todos/done/23-platssidor-case-duplikat.md)       |
| 22  | Fixa intern länk till /plats/stockholm                              | 2026-04-26 | [todos/done/22-stockholm-intern-lank.md](todos/done/22-stockholm-intern-lank.md)             |
| 1   | Cache-exkludering datum-routes (hybrid 30d)                         | 2026-04-26 | [todos/done/01-minska-cache-urls.md](todos/done/01-minska-cache-urls.md)                     |
| 8   | GA4 MCP (analytics-mcp + docs/analytics.md)                         | 2026-04-26 | [todos/done/08-ga-mcp.md](todos/done/08-ga-mcp.md)                                           |
| 14  | Backup av övriga sajter på gamla DO-servern                         | 2026-04-25 | [todos/done/14-backup-do-server.md](todos/done/14-backup-do-server.md)                       |
| 13  | Kommunicera "Hosted in EU" (footer + /sida/om)                      | 2026-04-24 | [todos/done/13-hosted-in-eu.md](todos/done/13-hosted-in-eu.md)                               |
| 19  | /mest-last: filtrera bort gamla events (3-dagars)                   | 2026-04-24 | [todos/done/19-mest-last-bara-nyligen.md](todos/done/19-mest-last-bara-nyligen.md)           |
| 17  | Ta bort `hetzner.*`-testdomänerna                                   | 2026-04-24 | [todos/done/17-ta-bort-hetzner-domaner.md](todos/done/17-ta-bort-hetzner-domaner.md)         |
| 4   | Uppdatera mbtiles från 2017 (Planetiler z0-15, 2.4 GB)              | 2026-04-23 | [todos/done/04-mbtiles-uppdatera.md](todos/done/04-mbtiles-uppdatera.md)                     |
| 15  | Server-side cache för kartbilder (nginx-sidecar)                    | 2026-04-23 | [todos/done/15-tiles-cache-caddy.md](todos/done/15-tiles-cache-caddy.md)                     |
| 12  | LLM/AI-optimering (llms.txt, markdown per event)                    | 2026-04-22 | [todos/done/12-llm-optimering.md](todos/done/12-llm-optimering.md)                           |
| 7   | PHPStan triage (alla 77 errors fixade, 0 på level 5)                | 2026-04-22 | [todos/done/07-phpstan-ci.md](todos/done/07-phpstan-ci.md)                                   |
| 6   | Flytta Brottsstatistik → `/statistik`                               | 2026-04-21 | [todos/done/06-statistik-sida.md](todos/done/06-statistik-sida.md)                           |
| 5   | Laravel 12 → 13 + Spatie Response Cache 7 → 8 (SWR)                 | 2026-04-21 | [todos/done/05-laravel-13-uppgradering.md](todos/done/05-laravel-13-uppgradering.md)         |
| 3   | Konsolidera blade-templates (event-kort)                            | 2026-04-21 | [todos/done/03-blade-konsolidering.md](todos/done/03-blade-konsolidering.md)                 |

## Avfärdade / sammanslagna

| #   | Titel                                          | Beslut                                                                            | Fil                                                                                                  |
| --- | ---------------------------------------------- | --------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------- |
| 49  | Feeda in Krisinformation.se RSS                | Avfärdad 2026-04-29 — brand-/UX-/SEO-mismatch; VMA täcker redan det akuta         | [todos/rejected/49-feeda-in-krisinformation.md](todos/rejected/49-feeda-in-krisinformation.md)       |
| 40  | Trafikverket STRADA olycksstatistik per kommun | Avfärdad 2026-04-29 — kommunnivå kräver myndighetsavtal, öppen data bara län-nivå | [todos/rejected/40-trafikverket-strada-olyckor.md](todos/rejected/40-trafikverket-strada-olyckor.md) |
| 18  | Attribution vid statiska kartbilder (ODbL)     | Avfärdad 2026-04-24 — gråzon, om-sidan räcker                                     | [todos/rejected/18-attribution-vid-kartbilder.md](todos/rejected/18-attribution-vid-kartbilder.md)   |
| 9   | Extern DB-backup                               | Avfärdad 2026-04-21 — Hetzner-snapshots räcker                                    | [todos/rejected/09-extern-db-backup.md](todos/rejected/09-extern-db-backup.md)                       |
| 2   | SEO-review (legacy)                            | Sammanslagen med #11 (2026-04-21)                                                 | [todos/rejected/02-seo-review.md](todos/rejected/02-seo-review.md)                                   |
