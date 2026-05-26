**Status:** aktiv — bucket aktiverad 2026-05-26 (CrimeEvent::isVagueTitle); scheduler plockar nya events automatiskt. Backfill skippad (YAGNI). #52 åtgärd C (typ-listans title/meta) kvar.
**Senast uppdaterad:** 2026-05-26
**Källa:** Inbox Brottsplatskartan (2026-04-30) + #52 GSC-baseline 2026-04-30

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

## #52 åtgärd C — `/typ/trafikkontroll` listans title/meta (~1 470 klick/90d)

GSC-baseline (#52, 2026-04-30) visar att `/typ/trafikkontroll` tar 89 %
av impressionerna för queryn "trafikkontroll" (9 630/10 816) men har
**CTR 0.05 %** (5 klick på 9 630 imp). Symptom: thin/dålig title/meta
på själva typ-listan.

Det här är **utöver** AI-rewrite-jobbet nedan — den fixar enskilda
event-rubriker; listsidans title/meta är en separat fix:

- Hugg explicit `<title>` i `single-typ.blade.php` för
  `parsed_title === 'trafikkontroll'`-fallet (eller alla typer):
  "Trafikkontroll i Sverige — senaste polisinsatserna | Brottsplatskartan".
- Lägg till `<meta name="description">` med plats-/tids-vinkel.
- Mätning: GSC CTR på "trafikkontroll"-queryn 30/60/90d post-deploy.

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

## Utfall 2026-05-26

**Bucket aktiverad** i `CrimeEvent::isVagueTitle()` med
`preg_match('/^trafikkontroll(er)?$/iu', $t) === 1 => 'trafikkontroll'`.
PHPStan grön (`composer analyse`).

**Testfall (4 körda — den 5:e blockerades korrekt av `MIN_BODY_FOR_AI_REWRITE`):**

| #   | Event   | Län               | Body | AI-rubrik                                                           |
| --- | ------- | ----------------- | ---- | ------------------------------------------------------------------- |
| 1   | #502926 | Västerbottens län | 388  | "Trafikkontroller i Västerbotten: överlast och otillåten körning"   |
| 2   | #502929 | Jämtlands län     | 1218 | "Trafikkontroller i Jämtland – flera böter för hastighet och bälte" |
| 3   | #331923 | Stockholms län    | 334  | "Trafikkontroller på Värmdö – bil med körförbud stoppad"            |
| 4   | #502544 | Västerbottens län | 139  | "Trafikkontroll på Riksvägen/Tegsvägen – fem förare bötfälls"       |

Samtliga 40–60 tecken, platsbunden, neutral ton — gott resultat. Befintlig
prompt i `resources/views/ai/prompts/title-rewrite.blade.php` behövde inte
ändras.

**Volym i lokal DB (snapshot):**

- 13,438 trafikkontroll-events totalt med `body >= 100` (alla år)
- 1,196 senaste 365d
- 93 senaste 30d

**Backfill skippad (YAGNI):** Schedulern (`crimeevents:create-summaries
--vague-only --limit=100` var 15:e min) plockar nu automatiskt upp _nya_
trafikkontroll-events. Backfill av historiska events ändrar inte URL-slug
(bara visningstitel) → liten SEO-marginal mot ~$9 (365d) eller ~$100
(all-time) AI-kostnad. Om GSC-mätning visar att fresh-events redan klättrar
i pos behöver vi inte historik-backfill.

**Slug-migration ej aktuell:** URL-sluggar (`trafikkontroll-<lan>-<id>`)
byggs från `parsed_title` + plats vid event-skapande, inte från `title_alt_1`.
Bytet skulle kräva 301-mapping och kan göras separat om SEO-vinst kräver det.

## Mätning

GSC `compare_search_periods` 30/60/90d efter 2026-05-26 på URLs
`/<lan>/trafikkontroll-<lan>-*`. Förväntad effekt: CTR-lyft på "trafikkontroll
<ort>"-queries via specifika rubriker i SERP. Mätperioden överlappar med #36
(samma signal-typ).

**Notera:** #52 åtgärd C (typ-listans title/meta för `/typ/trafikkontroll`,
~1 470 klick/90d via "trafikkontroll"-query, CTR 0.05 %) är en separat fix
och kvarstår. Listsidans title/meta påverkas inte av per-event AI-rewrite.
