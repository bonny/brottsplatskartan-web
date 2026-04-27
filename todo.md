# Claude TODO – Brottsplatskartan

Index över förbättringsarbete. Varje todo har en egen fil under
[`todos/`](todos/) med fullständig analys. Konvention och
mappstruktur: [`todos/README.md`](todos/README.md).

Senast uppdaterad: 2026-04-27.



## Aktiva

| #   | Titel                                           | Status                                                                                              | Fil                                                                        |
| --- | ----------------------------------------------- | --------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------- |
| 10  | AI-omskriva vaga titlar                         | Plan klar — kostnad ~$27 för full backfill                                                          | [todos/10-ai-omskriva-titlar.md](todos/10-ai-omskriva-titlar.md)           |
| 16  | Rensa / avveckla gamla DO-servern (Dokku)       | Appar stoppade, väntar på soak innan radering                                                       | [todos/16-rensa-do-server.md](todos/16-rensa-do-server.md)                 |
| 25  | Månadsvyer istället för dagsvyer (datum-routes) | Pilot live 2026-04-27 (uppsala + västerås + uppsala-lan). 30d-mätning till 2026-05-27               | [todos/25-manadsvyer-datum-routes.md](todos/25-manadsvyer-datum-routes.md) |
| 27  | Berika ort- och månadssidor med rikare innehåll | Designfas — Lager 1–3 (egen data, externt + AI), research klar                                      | [todos/27-rikare-innehall.md](todos/27-rikare-innehall.md)                 |
| 29  | Audit + reducera indexerade pages               | Datum-routes + thin singles deployat, ~22k pages noindex:as. Mätperiod 30–90d i GSC                  | [todos/29-audit-indexerade-pages.md](todos/29-audit-indexerade-pages.md)   |
| 32  | Schema.org-sweep (NewsArticle/Dataset/FAQPage)  | Designfas — härledd från SEO 2026-review. Hög ROI, kan köras parallellt med #25                      | [todos/32-schema-sweep.md](todos/32-schema-sweep.md)                       |
| 34  | Långa event-slugs (URL:er med hela brödtexten)  | Designfas — events efter 2022-02-11 har 100+ teckens slugs pga description bakas in                  | [todos/34-langa-event-slugs.md](todos/34-langa-event-slugs.md)             |

### Beroenden

- **#28 → #10:** AI-titlar bygger på `laravel/ai`. Migrera först. *(#28 klar 2026-04-26 — beroendet löst, listas tills #10 startat.)*
- **#28 → #27 Lager 3:** AI-månadssammanfattningar bygger på `laravel/ai`. *(#28 klar 2026-04-26 — beroendet löst, listas tills #27 startat.)*

### Föreslagen ordning

1. **#25 Månadsvyer** — Uppsala-pilot pågår
2. **#32 Schema-sweep** — kör parallellt, hög ROI för AI Overviews
3. **#10 AI-titlar** — bygg på `laravel/ai` (#28 klar)
4. **#27** — innehållsberikning efter #25-piloten
5. **#16** (DO-avveckling) — efter ~2026-05-15 när soak på statiska sajterna är klar
6. **#29** — passiv mätperiod, åtgärder efter data

## Klara

Sorterade nyast först.

| #   | Titel                                                  | Klar       | Fil                                                                                    |
| --- | ------------------------------------------------------ | ---------- | -------------------------------------------------------------------------------------- |
| 33  | Tier 1-städer på `/{city}/handelser/{year}/{month}`    | 2026-04-27 | [todos/done/33-tier1-month-routes.md](todos/done/33-tier1-month-routes.md)             |
| 30  | CWV-optimering Fas 1 (LCP -84 %, perf 51→80)           | 2026-04-26 | [todos/done/30-cwv-optimering.md](todos/done/30-cwv-optimering.md)                     |
| 28  | Migrera AI-stack till `laravel/ai` (Sonnet 4.6 + v2)   | 2026-04-26 | [todos/done/28-migrera-laravel-ai.md](todos/done/28-migrera-laravel-ai.md)             |
| 21  | Migrera antonblomqvist.se + simple-fields.com (DNS ok) | 2026-04-26 | [todos/done/21-migrera-statiska-sajter.md](todos/done/21-migrera-statiska-sajter.md)   |
| 20  | Kartbilder med cirklar (default circle, soak ok)       | 2026-04-26 | [todos/done/20-kartbilder-med-cirklar.md](todos/done/20-kartbilder-med-cirklar.md)     |
| 11  | SEO-audit 2026 (Fas 1+2; CWV→#30, OG-image avfärdat)   | 2026-04-26 | [todos/done/11-seo-audit-2026.md](todos/done/11-seo-audit-2026.md)                     |
| 31  | TTFB-anomali på /lan/{lan} (löst av cache-warmup)      | 2026-04-26 | [todos/done/31-ttfb-anomali.md](todos/done/31-ttfb-anomali.md)                         |
| 26  | Search Console MCP (mcp-gsc) + sitemap submission      | 2026-04-26 | [todos/done/26-gsc-mcp.md](todos/done/26-gsc-mcp.md)                                   |
| 24  | Tier 1-städer (malmo/goteborg/helsingborg/uppsala)     | 2026-04-26 | [todos/done/24-stadcontroller-tier1.md](todos/done/24-stadcontroller-tier1.md)         |
| 23  | Case-redirect på /plats/{plats} + footer-städning      | 2026-04-26 | [todos/done/23-platssidor-case-duplikat.md](todos/done/23-platssidor-case-duplikat.md) |
| 22  | Fixa intern länk till /plats/stockholm                 | 2026-04-26 | [todos/done/22-stockholm-intern-lank.md](todos/done/22-stockholm-intern-lank.md)       |
| 1   | Cache-exkludering datum-routes (hybrid 30d)            | 2026-04-26 | [todos/done/01-minska-cache-urls.md](todos/done/01-minska-cache-urls.md)               |
| 8   | GA4 MCP (analytics-mcp + docs/analytics.md)            | 2026-04-26 | [todos/done/08-ga-mcp.md](todos/done/08-ga-mcp.md)                                     |
| 14  | Backup av övriga sajter på gamla DO-servern            | 2026-04-25 | [todos/done/14-backup-do-server.md](todos/done/14-backup-do-server.md)                 |
| 13  | Kommunicera "Hosted in EU" (footer + /sida/om)         | 2026-04-24 | [todos/done/13-hosted-in-eu.md](todos/done/13-hosted-in-eu.md)                         |
| 19  | /mest-last: filtrera bort gamla events (3-dagars)      | 2026-04-24 | [todos/done/19-mest-last-bara-nyligen.md](todos/done/19-mest-last-bara-nyligen.md)     |
| 17  | Ta bort `hetzner.*`-testdomänerna                      | 2026-04-24 | [todos/done/17-ta-bort-hetzner-domaner.md](todos/done/17-ta-bort-hetzner-domaner.md)   |
| 4   | Uppdatera mbtiles från 2017 (Planetiler z0-15, 2.4 GB) | 2026-04-23 | [todos/done/04-mbtiles-uppdatera.md](todos/done/04-mbtiles-uppdatera.md)               |
| 15  | Server-side cache för kartbilder (nginx-sidecar)       | 2026-04-23 | [todos/done/15-tiles-cache-caddy.md](todos/done/15-tiles-cache-caddy.md)               |
| 12  | LLM/AI-optimering (llms.txt, markdown per event)       | 2026-04-22 | [todos/done/12-llm-optimering.md](todos/done/12-llm-optimering.md)                     |
| 7   | PHPStan triage (alla 77 errors fixade, 0 på level 5)   | 2026-04-22 | [todos/done/07-phpstan-ci.md](todos/done/07-phpstan-ci.md)                             |
| 6   | Flytta Brottsstatistik → `/statistik`                  | 2026-04-21 | [todos/done/06-statistik-sida.md](todos/done/06-statistik-sida.md)                     |
| 5   | Laravel 12 → 13 + Spatie Response Cache 7 → 8 (SWR)    | 2026-04-21 | [todos/done/05-laravel-13-uppgradering.md](todos/done/05-laravel-13-uppgradering.md)   |
| 3   | Konsolidera blade-templates (event-kort)               | 2026-04-21 | [todos/done/03-blade-konsolidering.md](todos/done/03-blade-konsolidering.md)           |

## Avfärdade / sammanslagna

| #   | Titel                                      | Beslut                                         | Fil                                                                                                |
| --- | ------------------------------------------ | ---------------------------------------------- | -------------------------------------------------------------------------------------------------- |
| 18  | Attribution vid statiska kartbilder (ODbL) | Avfärdad 2026-04-24 — gråzon, om-sidan räcker  | [todos/rejected/18-attribution-vid-kartbilder.md](todos/rejected/18-attribution-vid-kartbilder.md) |
| 9   | Extern DB-backup                           | Avfärdad 2026-04-21 — Hetzner-snapshots räcker | [todos/rejected/09-extern-db-backup.md](todos/rejected/09-extern-db-backup.md)                     |
| 2   | SEO-review (legacy)                        | Sammanslagen med #11 (2026-04-21)              | [todos/rejected/02-seo-review.md](todos/rejected/02-seo-review.md)                                 |
