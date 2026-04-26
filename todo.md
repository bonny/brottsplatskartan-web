# Claude TODO – Brottsplatskartan

Index över pågående förbättringsarbete. Varje todo har en egen fil i
`todos/` med fullständig analys, risker, fördelar, öppna frågor
och föreslagen plan.

Senast uppdaterad: 2026-04-26.

Senaste klara: #26 (GSC-MCP + sitemap submission, 2026-04-26) + #22+#23+#24 (Tier 1-städer, 2026-04-26).

## Konvention

Varje todo-fil börjar med:

```
**Status:** aktiv | pausad | blockerad | klar YYYY-MM-DD | avfärdad YYYY-MM-DD
**Senast uppdaterad:** YYYY-MM-DD
**Blockerad av:** #N (om relevant)
```

Aktiva todos ligger direkt i `todos/`. Klara flyttas till `todos/done/`
och avfärdade/sammanslagna till `todos/rejected/`. Filer behålls för
historik — raderas aldrig.

---

## Aktiva

| #   | Titel                                           | Status                                                                                                                                      | Fil                                                                        |
| --- | ----------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------- |
| 11  | SEO-audit enligt best practice 2026             | **Aktiv 2026-04-24** — Fas 1 + mest av Fas 2 klar; återstår CWV post-cutover, noindex-strategi, OG-image, image sitemap, utökad NewsArticle | [todos/11-seo-audit-2026.md](todos/11-seo-audit-2026.md)                   |
| 10  | AI-omskriva vaga titlar                         | Plan klar, kostnad ~$27 för full backfill                                                                                                   | [todos/10-ai-omskriva-titlar.md](todos/10-ai-omskriva-titlar.md)           |
| 16  | Rensa / avveckla gamla DO-servern (Dokku)       | Aktiv 2026-04-25 — appar stoppade, statiska sajter migrerade, väntar på soak innan radering                                                 | [todos/16-rensa-do-server.md](todos/16-rensa-do-server.md)                 |
| 20  | Kartbilder: rita cirkel/område runt händelsen   | Aktiv 2026-04-24 — implementerad bakom feature-flag, redo för prod-rollout                                                                  | [todos/20-kartbilder-med-cirklar.md](todos/20-kartbilder-med-cirklar.md)   |
| 25  | Månadsvyer istället för dagsvyer (datum-routes) | Designfas 2026-04-26 — stort grepp, kräver URL-strategi-beslut + 301-migrering + sitemap-uppdatering                                        | [todos/25-manadsvyer-datum-routes.md](todos/25-manadsvyer-datum-routes.md) |

### Beroenden

- **#28 → #10:** AI-titlar bygger på `laravel/ai`. Migrera först.
- **#28 → #27 Lager 3:** AI-månadssammanfattningar bygger på `laravel/ai`.
- **#10 → #11:** bättre titlar förbättrar SEO. Kör #10 innan eller parallellt med #11.
- ~~**#14 → #16:** DO-servern får inte avvecklas innan backup är verifierad.~~ (#14 klar 2026-04-25)
- ~~**#21 → #16:** DO-servern bör inte raderas förrän statiska sajterna flyttats till BPK-Hetzner.~~ (#21 klar 2026-04-26)

### Föreslagen ordning

1. **#11 CWV-mätning** (2 timmar) — baseline innan stora ändringar
2. **#28 + #29 parallellt** — #28 låser upp #10/#27. #29 ren SEO-impact, ingen AI
3. **#10 AI-titlar** (efter #28) — bygg på `laravel/ai`
4. **#27 + #25** synkas — innehållsplan + URL-struktur designas tillsammans
5. **#21 → #16** (DNS-byte + DO-avveckling) — efter ~2026-05-15
6. **#20** (kartbilder cirklar) — flippa flagga i prod-env

---

## Klara

| #   | Titel                                                  | Klar       | Fil                                                                                    |
| --- | ------------------------------------------------------ | ---------- | -------------------------------------------------------------------------------------- |
| 3   | Konsolidera blade-templates (event-kort)               | 2026-04-21 | [todos/done/03-blade-konsolidering.md](todos/done/03-blade-konsolidering.md)           |
| 5   | Laravel 12 → 13 + Spatie Response Cache 7 → 8 (SWR)    | 2026-04-21 | [todos/done/05-laravel-13-uppgradering.md](todos/done/05-laravel-13-uppgradering.md)   |
| 6   | Flytta Brottsstatistik → `/statistik`                  | 2026-04-21 | [todos/done/06-statistik-sida.md](todos/done/06-statistik-sida.md)                     |
| 7   | PHPStan triage (alla 77 errors fixade, 0 på level 5)   | 2026-04-22 | [todos/done/07-phpstan-ci.md](todos/done/07-phpstan-ci.md)                             |
| 12  | LLM/AI-optimering (llms.txt, markdown per event)       | 2026-04-22 | [todos/done/12-llm-optimering.md](todos/done/12-llm-optimering.md)                     |
| 15  | Server-side cache för kartbilder (nginx-sidecar)       | 2026-04-23 | [todos/done/15-tiles-cache-caddy.md](todos/done/15-tiles-cache-caddy.md)               |
| 4   | Uppdatera mbtiles från 2017 (Planetiler z0-15, 2.4 GB) | 2026-04-23 | [todos/done/04-mbtiles-uppdatera.md](todos/done/04-mbtiles-uppdatera.md)               |
| 17  | Ta bort `hetzner.*`-testdomänerna                      | 2026-04-24 | [todos/done/17-ta-bort-hetzner-domaner.md](todos/done/17-ta-bort-hetzner-domaner.md)   |
| 19  | /mest-last: filtrera bort gamla events (3-dagars)      | 2026-04-24 | [todos/done/19-mest-last-bara-nyligen.md](todos/done/19-mest-last-bara-nyligen.md)     |
| 13  | Kommunicera "Hosted in EU" (footer + /sida/om)         | 2026-04-24 | [todos/done/13-hosted-in-eu.md](todos/done/13-hosted-in-eu.md)                         |
| 14  | Backup av övriga sajter på gamla DO-servern            | 2026-04-25 | [todos/done/14-backup-do-server.md](todos/done/14-backup-do-server.md)                 |
| 8   | GA4 MCP (analytics-mcp + docs/analytics.md)            | 2026-04-26 | [todos/done/08-ga-mcp.md](todos/done/08-ga-mcp.md)                                     |
| 1   | Cache-exkludering datum-routes (hybrid 30d)            | 2026-04-26 | [todos/done/01-minska-cache-urls.md](todos/done/01-minska-cache-urls.md)               |
| 22  | Fixa intern länk till /plats/stockholm                 | 2026-04-26 | [todos/done/22-stockholm-intern-lank.md](todos/done/22-stockholm-intern-lank.md)       |
| 23  | Case-redirect på /plats/{plats} + footer-städning      | 2026-04-26 | [todos/done/23-platssidor-case-duplikat.md](todos/done/23-platssidor-case-duplikat.md) |
| 24  | Tier 1-städer (malmo/goteborg/helsingborg/uppsala)     | 2026-04-26 | [todos/done/24-stadcontroller-tier1.md](todos/done/24-stadcontroller-tier1.md)         |
| 26  | Search Console MCP (mcp-gsc) + sitemap submission      | 2026-04-26 | [todos/done/26-gsc-mcp.md](todos/done/26-gsc-mcp.md)                                   |
| 21  | Migrera antonblomqvist.se + simple-fields.com          | 2026-04-26 | [todos/done/21-migrera-statiska-sajter.md](todos/done/21-migrera-statiska-sajter.md)   |

## Avfärdade / sammanslagna

| #   | Titel                                      | Beslut                                         | Fil                                                                                                |
| --- | ------------------------------------------ | ---------------------------------------------- | -------------------------------------------------------------------------------------------------- |
| 2   | SEO-review (legacy)                        | Sammanslagen med #11 (2026-04-21)              | [todos/rejected/02-seo-review.md](todos/rejected/02-seo-review.md)                                 |
| 9   | Extern DB-backup                           | Avfärdad 2026-04-21 — Hetzner-snapshots räcker | [todos/rejected/09-extern-db-backup.md](todos/rejected/09-extern-db-backup.md)                     |
| 18  | Attribution vid statiska kartbilder (ODbL) | Avfärdad 2026-04-24 — gråzon, om-sidan räcker  | [todos/rejected/18-attribution-vid-kartbilder.md](todos/rejected/18-attribution-vid-kartbilder.md) |
