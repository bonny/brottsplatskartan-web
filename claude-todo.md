# Claude TODO – Brottsplatskartan

Index över pågående förbättringsarbete. Varje todo har en egen fil i
`claude-todos/` med fullständig analys, risker, fördelar, öppna frågor
och föreslagen plan.

Senast uppdaterad: 2026-04-21 (efter djupdykning per todo via sub-agenter).

---

## Todos

| #   | Titel                                               | Status                                           | Fil                                                                                      |
| --- | --------------------------------------------------- | ------------------------------------------------ | ---------------------------------------------------------------------------------------- |
| 1   | Minska cache-URL:er (`/plats/*/handelser/*` m.fl.)  | Avvaktar trafikdata (GA, se #8)                  | [claude-todos/01-minska-cache-urls.md](claude-todos/01-minska-cache-urls.md)             |
| 2   | ~~SEO-review (legacy)~~                             | Sammanslagen med #11 (2026-04-21)                | —                                                                                        |
| 3   | ~~Konsolidera blade-templates (event-kort)~~        | **Klar 2026-04-21** (9 → 3 Blade components, bugg-fix, partners-borttagning) | [claude-todos/03-blade-konsolidering.md](claude-todos/03-blade-konsolidering.md) |
| 4   | Uppdatera mbtiles från 2017                         | Planetiler-pipeline föreslagen                   | [claude-todos/04-mbtiles-uppdatera.md](claude-todos/04-mbtiles-uppdatera.md)             |
| 5   | ~~Laravel 12 → 13 + Spatie Response Cache 7 → 8 (SWR)~~ | **Klar 2026-04-21** (inkl. SWR + larastan-byte)  | [claude-todos/05-laravel-13-uppgradering.md](claude-todos/05-laravel-13-uppgradering.md) |
| 6   | ~~Flytta Brottsstatistik → `/statistik`~~           | **Klar 2026-04-21** (sidan + CTA på start/län/stad/handelser) | [claude-todos/06-statistik-sida.md](claude-todos/06-statistik-sida.md)    |
| 7   | ~~PHPStan triage + CI~~                             | **Klar 2026-04-22** (konfig + 30 bugfixar + baseline 47 + GitHub Actions CI) | [claude-todos/07-phpstan-ci.md](claude-todos/07-phpstan-ci.md) |
| 8   | Google Analytics MCP + ev. Search Console MCP       | Redo för setup                                   | [claude-todos/08-ga-mcp.md](claude-todos/08-ga-mcp.md)                                   |
| 9   | ~~Extern DB-backup~~                                | Avfärdad — Hetzner-snapshots räcker (2026-04-21) | —                                                                                        |
| 10  | AI-omskriva vaga titlar                             | Plan klar, kostnad ~$27 för full backfill        | [claude-todos/10-ai-omskriva-titlar.md](claude-todos/10-ai-omskriva-titlar.md)           |
| 11  | SEO-audit enligt best practice 2026                 | **Fas 1 + mest av Fas 2 klar 2026-04-21** (sitemap+image, JSON-LD NewsArticle/Place/ItemList/Dataset, H1-audit, preconnect, LCP) | [claude-todos/11-seo-audit-2026.md](claude-todos/11-seo-audit-2026.md) |
| 12  | ~~LLM/AI-optimering (llms.txt, markdown/URL, AI-botar)~~ | **Klar 2026-04-22** (robots.txt, llms.txt, markdown per event via .md-suffix, 99.7% payload-reduktion) | [claude-todos/12-llm-optimering.md](claude-todos/12-llm-optimering.md) |

---

## Beroenden och koppling

- **#1 ↔ #8:** beslutet om vilka routes som ska tas bort kräver trafikdata från GA4.
- **#10 → #11:** bättre titlar förbättrar SEO. Kör #10 innan eller parallellt med #11.
- **#11 → #6:** ny `/statistik`-sida bör designas med SEO-basics från dag ett.
- **#5 → (övrigt):** SWR i Response Cache 8 minskar behovet av `cache:warm`-scheduler och påverkar cache-strategin i #1.
- **#7 före större refaktor (#3, #5, #6):** baseline + CI ger trygghet vid större ändringar.

## Föreslagen ordning (efter Hetzner-cutover)

1. **#11 Fas 1** (sitemap, canonical-fallback, BreadcrumbList, robots.txt-fix) – 1 dag, hög SEO-vinst
2. **#8** (GA4 MCP) – ger data för #1 och vidare #11-prioritering
3. **#10** (AI-titlar) – pilot 30 dagar, ~20 kr, kvalitet innan bredd
4. **#1** (cache-exkludering för datum-routes) – snabb fix med `shouldCacheRequest`
5. **#7** (PHPStan fix-first → baseline → CI)
6. **#3** (blade-konsolidering), **#6** (/statistik), **#4** (mbtiles) – när tid finns
