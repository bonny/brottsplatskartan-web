**Status:** aktiv (idé — research saknas)
**Senast uppdaterad:** 2026-04-30
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
- Synergi med #59 (live-rutan kan visa "Media bevakar:" badge).

## Nästa steg

1. **Pilot:** välj 3 feeds (Expressen, SVT inrikes, SVT lokalt
   stockholm). Skriv RSS-importer + tabell. Ingen AI-matchning ännu —
   bara ackumulera data 7 dagar för att se volym.
2. **Matchnings-prototyp:** kör på 10 stora events manuellt valda
   (gripande, dödsfall) — utvärdera Claude-promptens precision.
3. **Beslut:** fortsätt eller skippa. Om fortsätt: scale upp till
   alla feeds + alla events, mät kostnad + match-rate 30d.
4. **UI:** designa "Mediabevakning"-sektion på event-sida.

## Inte i scope

- **Skrapa nyhetsartiklarnas full-body** — bara titel/summary från RSS.
  Body-skrapning bryter ofta paywall-villkor.
- **Pushnotiser** vid nya media-länkar — separat feature om någonsin.
