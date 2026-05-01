**Status:** aktiv (RSS-grund deployad 2026-05-01 av #63 — fas-3-scope, kör efter #63 fas 1+2 visar positivt utfall)
**Senast uppdaterad:** 2026-05-01
**Källa:** Inbox Brottsplatskartan (2026-04-30)

# Todo #60 — Auto-länka events till nyhetsartiklar via AI + RSS

## Sammanfattning

> Auto-länka nyheter via AI genom att använda sig av text + länkar från feeds
>
> - https://feeds.expressen.se/nyheter/
> - https://www.dn.se/rss/
> - https://news.google.com/rss?hl=sv&gl=SE&ceid=SE:sv
> - https://www.svt.se/rss.xml
> - https://www.svt.se/nyheter/inrikes/rss.xml
> - https://www.svt.se/nyheter/lokalt/stockholm/rss.xml
> - https://www.svt.se/nyheter/lokalt/orebro/rss.xml
> - https://www.svd.se/feed/articles.rss

Idé: pollar svenska nyhetshus-RSS, matchar mot polishändelser via Claude
("är denna artikel en utförligare beskrivning av denna händelse?"),
länkar in artikeln på event-sidan som "Mediabevakning".

## Bakgrund

- Polisens RSS är knapp/kort. Lokal media skriver ofta detaljerat om
  större händelser (gripande, dödsfall, större bränder).
- Brottsplatskartan har idag ingen integration med media-feeds.
- AI-stack finns sedan #28 (laravel/ai + Claude Sonnet 4.6).
- Befintlig `texttv:importera` är ett liknande mönster (extern feed →
  matchning → events).

## Datakällor (från inboxen)

| Källa          | URL                                          | Kommentar                           |
| -------------- | -------------------------------------------- | ----------------------------------- |
| Expressen      | `feeds.expressen.se/nyheter/`                | Full Sverige                        |
| DN             | `dn.se/rss/`                                 | Full Sverige                        |
| Google News SE | `news.google.com/rss?hl=sv&gl=SE&ceid=SE:sv` | Aggregerad — ger duplikater         |
| SVT            | `svt.se/rss.xml`                             | Full SVT                            |
| SVT inrikes    | `svt.se/nyheter/inrikes/rss.xml`             | Bättre relevans                     |
| SVT lokalt     | `svt.se/nyheter/lokalt/{ort}/rss.xml`        | Per region — Stockholm, Örebro etc. |
| SvD            | `svd.se/feed/articles.rss`                   | Full SvD                            |

## Förslag

### Pipeline

1. **Schemalagd fetch** (var 15:e min) av RSS-feeds → cache i ny tabell
   `news_articles` (id, source, url, title, summary, pubdate, content_hash).
2. **Matchnings-pass** för nya events (senaste 24h):
    - Hämta event + dess plats + brottstyp.
    - Filtrera `news_articles` på samma datum ± 2 dagar och länsstrings-
      träff (t.ex. "Stockholm" om event är i Stockholms län).
    - Skicka till Claude (Haiku 4.5 — billigast) med en kort prompt:
      "Är denna nyhetsartikel om denna polishändelse? Svara `ja`,
      `kanske`, eller `nej` plus 1 mening motivering."
    - `ja` → spara matchning i `crime_event_news` (event_id, news_id,
      confidence, ai_reason).
3. **Visning på event-sida:** sektion "Mediabevakning" med 1–3 länkar
   (taggade med källa och pubdate).
4. **Mätning:** match-rate per event-typ. För-stor matchnings-frekvens
   → tighta promptens "samma incident"-krav. För liten → utöka till
   `kanske` med tydlig "möjligen relaterad"-badge.

### Kostnad-estimat

- ~500 events/dygn × ~10 nyhetsartiklar att jämföra mot per event ×
  Haiku 4.5 (~$0.001 per anrop typiskt body): grovt **$5/dygn**.
- Optimera: RSS-fetch dedupar via `content_hash`. Pre-filter på
  län/datum reducerar Claude-anrop dramatiskt.

## Risker

- **Felmatchningar är synliga och pinsamma.** Om vi länkar en DN-artikel
  som inte är samma incident förlorar vi förtroende. **Höj tröskeln**
  — bara `ja`-matchningar på första iterationen, ingen `kanske`.
- **Upphovsrätt/citaträtt:** länka är OK, citera body är gränsfall.
  Visa bara titel + källa + länk (ingen utdragstext från artikeln).
- **AI-kostnad** vid större volym — mät innan rollout. Cache LLM-svar
  per `(event_id, news_id)`-par så vi inte betalar dubbelt vid retry.
- **Källkvalitet:** Google News-flödet är aggregerat → många dubbletter.
  Skippa eller dedupa hårt på `content_hash`.

## Confidence

**Medel.** Tekniskt rakt på sak — utmaningen är match-precision och
kostnadskontroll.

## Beroenden

- Bygger på #28 (laravel/ai installerat).
- **Bygger på [#63](63-relaterade-nyheter-trafikprio.md)** — #63 är fas
  1+2 av samma pipeline; #60 är fas 3 (full bredd) som kör om #63 visar
  positivt utfall.
- Synergi med #59 (live-rutan kan visa "Media bevakar:" badge).

## SEO-research (2026-05-01)

Detaljerad analys: [`tmp-news-research/seo-60-vs-63-2026-05-01.md`](../tmp-news-research/seo-60-vs-63-2026-05-01.md).

**Nyckelfynd:** event-trafiken på Brottsplatskartan är **flat-tail**, inte
Pareto. Top-50 ger bara **20 % av GSC event-clicks** (90d) — 80 % ligger
i long-tail (15 755 unika sidor får clicks). Det betyder att #60:s breda
ansats är SEO-mässigt starkare än det smala #63 — men först efter att
#63 har validerat AI-precision och UI.

**Bred ansats vinner på:**

- Long-tail = 80 % av sökklicken — där positions-lyften är möjliga
  (rank 8–15 är rörlig; top-50 ligger redan på rank 6–10)
- Crawl-budget: bred ansats reaktiverar tusentals sidor med låg
  crawl-rate efter #29:s noindex-purge
- Topical authority byggs i bredd för aggregator-siter

**Risker att hantera:**

- 150 outbound-länkar/dygn till samma 5 domäner kan misstolkas som
  link-farm utan disciplin → använd `rel="nofollow"` på media-länkar
- AI-precision <80 % → fel-länkar pinsamma → höj tröskeln, bara `ja`-svar

## RSS/ToS-research (2026-05-01)

Detaljerad rapport: [`tmp-news-research/news-rss-tos-2026-05-01.md`](../tmp-news-research/news-rss-tos-2026-05-01.md).

**Fas-1-källor (bekräftade RSS + ToS-rena):** Google News SE, SVT
Nyheter, Aftonbladet, Expressen-familjen (3 feeds), DN.
**Undvik:** TT (förbjuder robotar i §5.1), Omni (ingen feed), DI
(fel fokus). Juridiskt rent: URL § 22 + Svensson C-466/12 + DSM art. 15.

## Nästa steg (uppdaterad fas-plan)

**Fas 0 (klar):** RSS/ToS-research + SEO-analys 2026-05-01.

**Fas 1 — kör #63 först (4–6v):**

- Smal pilot (top-50 events från GA4) med Google News SE som pre-filter
    - Claude Haiku-validering. Etablera UI, AI-precision-tröskel, nofollow-
      policy. Mät CTR + dwell time på event-sidor med media-sektion vs utan.

**Fas 2 — mid-tier (om fas 1 visar precision >80 % och positivt CTR/dwell):**

- Top-1000 events ≥10 clicks/30d (~10 events/dygn, ~$1/dygn). Täcker
  ~70 % av event-clicks.

**Fas 3 — full #60 (bred):**

- Alla nya events. Förutsättning: mid-tier visar GSC-positions-lyft >1.0
  och AI-precision håller. Kostnad ~$5/dygn för Claude Haiku.

## Inte i scope

- **Skrapa nyhetsartiklarnas full-body** — bara titel/summary från RSS.
  Body-skrapning bryter ofta paywall-villkor.
- **Pushnotiser** vid nya media-länkar — separat feature om någonsin.
