**Status:** klar 2026-04-26 (löst av cache-warmup, ej persistent)
**Senast uppdaterad:** 2026-04-26
**Härledd från:** #11 SEO-audit (CWV-baseline 2026-04-26)

# Todo #31 — TTFB-anomali på `/lan/{lan}`

## Lösning (2026-04-26)

Re-mätning efter #30 Fas A+B+C + self-host: **TTFB Värmlands län gick
från 3 661 ms → 80 ms** utan att vi gjorde någon specifik fix för
TTFB. Baseline-mätningen 2026-04-26 träffade en cold cache och
representerade alltså ett worst-case-scenario, inte normal drift.

Svaret var inte en DB-query-flaskhals utan helt enkelt cache-miss.
Spatie Response Cache + Redis fungerar som det ska. Inget kod-arbete
krävdes.

## Varför (originalanalys)

CWV-baseline 2026-04-26 visade att `/lan/Värmlands län` har **3 562 ms
saving potential** på "Reduce initial server response time" — alltså
TTFB är abnormt högt. Andra sidor är runt 500-1500ms TTFB.

Värmlands län är en av våra topp-trafikerade landningssidor (1 202
sessions/30d från Google organisk). Att den är långsam drar ner
ranking på en värdefull sida.

## Antaganden / hypoteser

- Cache-miss på den specifika län-sidan
- Långsam DB-query för länsspecifika events
- Cold cache eftersom den faller utanför `RESPONSE_CACHE`-window
- N+1-problem för relaterad data (polisstationer, statistik)

## Plan

### Fas 1: Mätning + reproduktion (½ dag)

1. Kör `curl -w "@curl-format.txt" -o /dev/null
https://brottsplatskartan.se/lan/V%C3%A4rmlands%20l%C3%A4n` flera
   gånger för att se faktisk TTFB-varians
2. Verifiera om alla län-sidor har samma problem eller bara Värmland
3. Kör Laravel Telescope eller Debugbar lokalt på samma URL för att
   se query-räknare och request-timing

### Fas 2: Diagnos (½ dag)

Beroende på fynd:

- **Om alla län är långsamma:** problem ligger i `LanController` eller
  delade helpers. Audit `getSingleLanWithStats`, `getEventsForLan`,
  m.fl.
- **Om bara Värmland:** något plats-specifikt — kanske ovanligt många
  events där, eller en specifik query som inte funkar för det länet.
- **Om cache-miss:** kontrollera response-cache-konfig + scheduler för
  att förvärma.

### Fas 3: Fix (1-2 dagar)

Beroende på diagnos:

- Index-tillägg i DB för länsspecifika queries
- Eager loading för att slippa N+1
- Cache-pre-warm via scheduler för topp-län
- Query-rewrite till mer effektiv form

### Fas 4: Verifiera (½ dag)

Kör om Lighthouse mot Värmland + 2-3 andra län. Bekräfta TTFB < 800ms
på alla.

## Acceptanskriterium

- TTFB < 800ms på alla `/lan/*`-sidor (mätt 5 gånger, p95)
- Performance-poäng på `/lan/Värmlands län` ≥ 70 (var 55)

## Risker

- **Cache-pre-warm kan trigga API-rate-limits** mot polisens RSS om vi
  förvärmer för aggressivt
- **Index-tillägg på stor tabell** (`crime_events` har troligen
  miljontals rader) kan ta tid att skapa — kör med
  `ALGORITHM=INPLACE, LOCK=NONE` om InnoDB
- **Query-rewrite kan bryta delade helpers** som används av
  `PlatsController` också. Mät prestanda där också efter fix.

## Beroenden

- **#30 CWV-optimering** — kan göras parallellt eller efter. Inte
  blocker.

## Tid

2-3 dagar (mestadels mätning + diagnos, fix kan vara liten).

## Status

Designfas. Kan börja parallellt med #30 eftersom det är ett separat
spår (server-side performance vs client-side).
