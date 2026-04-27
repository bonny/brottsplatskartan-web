# Claude TODO – Brottsplatskartan

Index över förbättringsarbete. Varje todo har en egen fil under
[`todos/`](todos/) med fullständig analys. Konvention och
mappstruktur: [`todos/README.md`](todos/README.md).

Senast uppdaterad: 2026-04-27 (+#39 MSB + #40 Trafikverket STRADA — skissade idé-todos parallella till #38-mönstret).

## Aktiva

| #   | Titel                                           | Status                                                                                | Fil                                                                        |
| --- | ----------------------------------------------- | ------------------------------------------------------------------------------------- | -------------------------------------------------------------------------- |
| 16  | Rensa / avveckla gamla DO-servern (Dokku)       | Appar stoppade, väntar på soak innan radering                                         | [todos/16-rensa-do-server.md](todos/16-rensa-do-server.md)                 |
| 25  | Månadsvyer istället för dagsvyer (datum-routes) | Pilot live 2026-04-27 (uppsala + västerås + uppsala-lan). 30d-mätning till 2026-05-27 | [todos/25-manadsvyer-datum-routes.md](todos/25-manadsvyer-datum-routes.md) |
| 27  | Berika ort- och månadssidor med rikare innehåll | Designfas — Lager 1–3 (egen data, externt + AI), research klar                        | [todos/27-rikare-innehall.md](todos/27-rikare-innehall.md)                 |
| 29  | Audit + reducera indexerade pages               | Datum-routes + thin singles deployat, ~22k pages noindex:as. Mätperiod 30–90d i GSC   | [todos/29-audit-indexerade-pages.md](todos/29-audit-indexerade-pages.md)   |
| 36  | GSC-mätning av AI-titlars CTR-effekt            | Mätperiod startad 2026-04-27, första check 2026-05-25                                 | [todos/36-gsc-matning-ai-titlar.md](todos/36-gsc-matning-ai-titlar.md)     |
| 38  | Integrera BRÅ-data för riktig brottsstatistik   | Research klar 2026-04-27 — CSV-källa identifierad, redo för implementation (~3-4h)    | [todos/38-bra-data-integration.md](todos/38-bra-data-integration.md)       |
| 39  | MSB brand- och räddningsstatistik per kommun    | Skissad — research-fas saknas (parallell till #38)                                    | [todos/39-msb-brand-rakning-statistik.md](todos/39-msb-brand-rakning-statistik.md) |
| 40  | Trafikverket STRADA olycksstatistik per kommun  | Skissad — research-fas saknas (parallell till #38)                                    | [todos/40-trafikverket-strada-olyckor.md](todos/40-trafikverket-strada-olyckor.md) |

### Beroenden

- **#28 → #27 Lager 3:** AI-månadssammanfattningar bygger på `laravel/ai`. _(#28 klar 2026-04-26 — beroendet löst, listas tills #27 startat.)_
- **#10 → #36:** GSC-mätning bygger på #10:s rendering-deploy. _(#10 klar 2026-04-27 — #36 mätperiod startad samma dag.)_
- **#37 → #27:** SCB-befolkning för befolkningsfakta + storlekssortering. _(#37 klar 2026-04-27 — beroendet löst, listas tills #27 startat.)_
- **#38 → #27 Lager 2:** "Brott per 1000 inv." kräver riktig BRÅ-statistik (Polisens händelser är inte heltäckande). Aktiv blocker.

### Föreslagen ordning

1. **#25 Månadsvyer** — Uppsala-pilot pågår
2. **#38 BRÅ-data** — research-fas, blockerar #27 Lager 2:s "brott per 1000 inv."
3. **#27** — innehållsberikning efter #25-piloten + #38 (Lager 1 + Lager 3 är opåverkade och kan startas tidigare)
4. **#16** (DO-avveckling) — efter ~2026-05-15 när soak på statiska sajterna är klar
5. **#29** — passiv mätperiod, åtgärder efter data
6. **#36** — passiv GSC-mätning, första check 2026-05-25 (eventuell fas 3 backfill om vinst)

## Uppföljningar — datum att komma ihåg

Datum-bundna manuella åtgärder som inte går att autoschemalägga (kräver lokala
MCP:s som `mcp-gsc`, SSH-nycklar till prod, eller mänsklig bedömning).
Granska veckovis. När en åtgärd är gjord, flytta raden till "Avklarade" nedan
eller markera todon som klar.

| Datum      | Åtgärd                                                       | Todo                                       |
| ---------- | ------------------------------------------------------------ | ------------------------------------------ |
| 2026-05-15 | DO-server: radera efter soak (statiska sajter migrerade)     | [#16](todos/16-rensa-do-server.md)         |
| 2026-05-25 | GSC-mätning AI-titlar — första check (4v post-deploy)        | [#36](todos/36-gsc-matning-ai-titlar.md)   |
| 2026-05-27 | Månadsvyer-pilot — 30d-mätning Uppsala/Västerås              | [#25](todos/25-manadsvyer-datum-routes.md) |
| 2026-06-22 | GSC-mätning AI-titlar — andra check (8v)                     | [#36](todos/36-gsc-matning-ai-titlar.md)   |
| 2026-07-27 | GSC-mätning AI-titlar — tredje check (12v) + beslut om fas 3 | [#36](todos/36-gsc-matning-ai-titlar.md)   |
| 2026-07-27 | Indexerade pages — slutmätning (90d post-noindex)            | [#29](todos/29-audit-indexerade-pages.md)  |

### Avklarade uppföljningar

(flytta hit med faktiskt utfallsdatum när en check-in är gjord)

## Klara

Sorterade nyast först.

| #   | Titel                                                               | Klar       | Fil                                                                                    |
| --- | ------------------------------------------------------------------- | ---------- | -------------------------------------------------------------------------------------- |
| 37  | Tätortskod-mappning (SCB) för "brott/1000 inv."                     | 2026-04-27 | [todos/done/37-tatortskod-mappning-scb.md](todos/done/37-tatortskod-mappning-scb.md)   |
| 10  | AI-omskriva vaga titlar (rendering + auto-trigger för hela Sverige) | 2026-04-27 | [todos/done/10-ai-omskriva-titlar.md](todos/done/10-ai-omskriva-titlar.md)             |
| 35  | Redirect `/lan/Uppsala län` → `/uppsala` (Stockholm-mönstret)       | 2026-04-27 | [todos/done/35-lan-redirect-uppsala.md](todos/done/35-lan-redirect-uppsala.md)         |
| 32  | Schema.org-sweep (NewsArticle+Place+sameAs+CollectionPage+cache)    | 2026-04-27 | [todos/done/32-schema-sweep.md](todos/done/32-schema-sweep.md)                         |
| 34  | Långa event-slugs — kort URL för events från 2026-04-28             | 2026-04-27 | [todos/done/34-langa-event-slugs.md](todos/done/34-langa-event-slugs.md)               |
| 33  | Tier 1-städer på `/{city}/handelser/{year}/{month}`                 | 2026-04-27 | [todos/done/33-tier1-month-routes.md](todos/done/33-tier1-month-routes.md)             |
| 30  | CWV-optimering Fas 1 (LCP -84 %, perf 51→80)                        | 2026-04-26 | [todos/done/30-cwv-optimering.md](todos/done/30-cwv-optimering.md)                     |
| 28  | Migrera AI-stack till `laravel/ai` (Sonnet 4.6 + v2)                | 2026-04-26 | [todos/done/28-migrera-laravel-ai.md](todos/done/28-migrera-laravel-ai.md)             |
| 21  | Migrera antonblomqvist.se + simple-fields.com (DNS ok)              | 2026-04-26 | [todos/done/21-migrera-statiska-sajter.md](todos/done/21-migrera-statiska-sajter.md)   |
| 20  | Kartbilder med cirklar (default circle, soak ok)                    | 2026-04-26 | [todos/done/20-kartbilder-med-cirklar.md](todos/done/20-kartbilder-med-cirklar.md)     |
| 11  | SEO-audit 2026 (Fas 1+2; CWV→#30, OG-image avfärdat)                | 2026-04-26 | [todos/done/11-seo-audit-2026.md](todos/done/11-seo-audit-2026.md)                     |
| 31  | TTFB-anomali på /lan/{lan} (löst av cache-warmup)                   | 2026-04-26 | [todos/done/31-ttfb-anomali.md](todos/done/31-ttfb-anomali.md)                         |
| 26  | Search Console MCP (mcp-gsc) + sitemap submission                   | 2026-04-26 | [todos/done/26-gsc-mcp.md](todos/done/26-gsc-mcp.md)                                   |
| 24  | Tier 1-städer (malmo/goteborg/helsingborg/uppsala)                  | 2026-04-26 | [todos/done/24-stadcontroller-tier1.md](todos/done/24-stadcontroller-tier1.md)         |
| 23  | Case-redirect på /plats/{plats} + footer-städning                   | 2026-04-26 | [todos/done/23-platssidor-case-duplikat.md](todos/done/23-platssidor-case-duplikat.md) |
| 22  | Fixa intern länk till /plats/stockholm                              | 2026-04-26 | [todos/done/22-stockholm-intern-lank.md](todos/done/22-stockholm-intern-lank.md)       |
| 1   | Cache-exkludering datum-routes (hybrid 30d)                         | 2026-04-26 | [todos/done/01-minska-cache-urls.md](todos/done/01-minska-cache-urls.md)               |
| 8   | GA4 MCP (analytics-mcp + docs/analytics.md)                         | 2026-04-26 | [todos/done/08-ga-mcp.md](todos/done/08-ga-mcp.md)                                     |
| 14  | Backup av övriga sajter på gamla DO-servern                         | 2026-04-25 | [todos/done/14-backup-do-server.md](todos/done/14-backup-do-server.md)                 |
| 13  | Kommunicera "Hosted in EU" (footer + /sida/om)                      | 2026-04-24 | [todos/done/13-hosted-in-eu.md](todos/done/13-hosted-in-eu.md)                         |
| 19  | /mest-last: filtrera bort gamla events (3-dagars)                   | 2026-04-24 | [todos/done/19-mest-last-bara-nyligen.md](todos/done/19-mest-last-bara-nyligen.md)     |
| 17  | Ta bort `hetzner.*`-testdomänerna                                   | 2026-04-24 | [todos/done/17-ta-bort-hetzner-domaner.md](todos/done/17-ta-bort-hetzner-domaner.md)   |
| 4   | Uppdatera mbtiles från 2017 (Planetiler z0-15, 2.4 GB)              | 2026-04-23 | [todos/done/04-mbtiles-uppdatera.md](todos/done/04-mbtiles-uppdatera.md)               |
| 15  | Server-side cache för kartbilder (nginx-sidecar)                    | 2026-04-23 | [todos/done/15-tiles-cache-caddy.md](todos/done/15-tiles-cache-caddy.md)               |
| 12  | LLM/AI-optimering (llms.txt, markdown per event)                    | 2026-04-22 | [todos/done/12-llm-optimering.md](todos/done/12-llm-optimering.md)                     |
| 7   | PHPStan triage (alla 77 errors fixade, 0 på level 5)                | 2026-04-22 | [todos/done/07-phpstan-ci.md](todos/done/07-phpstan-ci.md)                             |
| 6   | Flytta Brottsstatistik → `/statistik`                               | 2026-04-21 | [todos/done/06-statistik-sida.md](todos/done/06-statistik-sida.md)                     |
| 5   | Laravel 12 → 13 + Spatie Response Cache 7 → 8 (SWR)                 | 2026-04-21 | [todos/done/05-laravel-13-uppgradering.md](todos/done/05-laravel-13-uppgradering.md)   |
| 3   | Konsolidera blade-templates (event-kort)                            | 2026-04-21 | [todos/done/03-blade-konsolidering.md](todos/done/03-blade-konsolidering.md)           |

## Avfärdade / sammanslagna

| #   | Titel                                      | Beslut                                         | Fil                                                                                                |
| --- | ------------------------------------------ | ---------------------------------------------- | -------------------------------------------------------------------------------------------------- |
| 18  | Attribution vid statiska kartbilder (ODbL) | Avfärdad 2026-04-24 — gråzon, om-sidan räcker  | [todos/rejected/18-attribution-vid-kartbilder.md](todos/rejected/18-attribution-vid-kartbilder.md) |
| 9   | Extern DB-backup                           | Avfärdad 2026-04-21 — Hetzner-snapshots räcker | [todos/rejected/09-extern-db-backup.md](todos/rejected/09-extern-db-backup.md)                     |
| 2   | SEO-review (legacy)                        | Sammanslagen med #11 (2026-04-21)              | [todos/rejected/02-seo-review.md](todos/rejected/02-seo-review.md)                                 |
