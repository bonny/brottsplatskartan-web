**Status:** aktiv (idé — research saknas)
**Senast uppdaterad:** 2026-04-30
**Källa:** Inbox Brottsplatskartan (2026-04-30)

# Todo #54 — Förbättra trafikkontroll-titlar (utöka AI-rewrite-coverage)

## Sammanfattning

> Titlar som dessa borde vi kunna hitta och förbättra
>
> - https://brottsplatskartan.se/vasterbottens-lan/trafikkontroll-vasterbottens-lan-501247
> - https://brottsplatskartan.se/jamtlands-lan/trafikkontroll-harjedalen-501244
> - https://brottsplatskartan.se/norrbottens-lan/trafikkontroll-norrbottens-lan-501241

Polisens RSS skickar regelmässigt rubriken "Trafikkontroll, &lt;län&gt;" /
"Trafikkontroll, &lt;kommun&gt;". Brödtexten är ofta detaljerad
(antal kontrollerade förare, fynd, plats), men rubriken — och därmed
URL-sluggen — blir generisk.

## Bakgrund

- `App\CrimeEvent::isVagueTitle()` (rad 71) klassar idag följande som
  vaga: `sammanfattning natt|dygn|...`, `presstalesperson`,
  `^(övrigt|annat|händelse)$`, samt titlar < 6 tecken. Träffas av AI-
  rewrite-pipelinen i #10.
- "Trafikkontroll, ..." matchar **inte** `isVagueTitle` — slipper igenom
  AI-rewrite trots att det är ett vagt mönster i praktiken.
- Stickprov 2026-04-30 på `vasterbottens-lan/trafikkontroll-...-501247`:
  brödtext nämner Skellefteå, Bolidenvägen, Åsele, antal förare med
  böter — gott om material för en bra rubrik.

## Förslag

1. **Utöka `isVagueTitle()`-mönstren:**

    ```php
    preg_match('/^trafikkontroll[, ]/iu', $t) === 1 => 'trafikkontroll',
    ```

    plus eventuellt liknande generika ("Sammanfattning trafik",
    "Polisens arbete i trafiken").

2. **Verifiera AI-rewrite-promptens rubrikkvalitet på trafikkontroll-
   exempel.** Promptens "kort, konkret, plats först"-instruktion bör
   funka, men kör 5 testfall och inspektera output innan backfill.
3. **Backfill:** kör befintligt `ai-rewrite-titles`-kommando (finns
   sedan #10) `--since=365` på trafikkontroll-bucketen.
4. **Mätning:** följ #36-mönstret — GSC-CTR på trafikkontroll-URL:er
   30/60/90d efter rewrite.

## Risker

- **URL-slug ändras inte automatiskt** för redan publicerade events
  (#10 ändrar bara visningstitel, inte slug). Gamla URL:er förblir
  generiska, nya får mer specifika slugs. Bedöm om migration av gamla
  slugs är värt risken (redirect-mängd, kanonisering).
- **Trafikkontroll utan brödtext** (rena formulärsvar) — `MIN_BODY_FOR_AI_REWRITE = 100`
  fångar dessa redan, men dubbelkolla.

## Confidence

**Hög** för titel-rewrite. **Medel** för slug-migration (öppen fråga
ovan).

## Beroenden

- Bygger på #10 (klar 2026-04-27).
- Mätning kan slå ihop med #36 (samma rapport-mall).

## Nästa steg

1. Skriv 5 testfall (3 trafikkontroll med rik body, 1 med tunn body, 1
   "Polisens arbete i trafiken"-variant). Kör AI-rewrite manuellt och
   bedöm output.
2. Om bra: utöka `isVagueTitle()` + backfill.
3. Beslut om slug-migration (separat).
