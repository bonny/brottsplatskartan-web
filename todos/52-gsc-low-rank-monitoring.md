**Status:** aktiv (research klar 2026-04-30 — väntar på beslut om vilka åtgärder som bryts ut)
**Senast uppdaterad:** 2026-04-30
**Källa:** Inbox Brottsplatskartan (2026-04-30)

# Todo #52 — Bevaka GSC-queries där vi rankar lågt på högvolym

## Sammanfattning

> seo:
> hålla koll på fraser som det söks mest på men som vi inte är på topp för.
> det är områden vi borde bli bättre på? använd Google Search Console MCP
> för att hitta vilka fraser många söker på.

Återkommande SEO-monitor: hitta queries med **hög impressioner men låg
position** (typiskt impressioner ≥ 1 000/90d, position ≥ 7). Det är fraser
Google redan associerar oss med men där vi rankar precis utanför topp-3
— låg-hängande frukt om vi förbättrar innehåll/internlänk/titel.

## Bakgrund

- mcp-gsc är installat sedan #26 (klar 2026-04-26).
- Befintlig analys-doc: [`docs/analytics.md`](../docs/analytics.md) har
  GA4-mönster men ingen återkommande GSC-low-rank-rapport.
- Beslagade andra mätperioder pågår (#25 månadsvyer 30d till 2026-05-27,
  #36 AI-titlar tills 2026-07-27, #29 indexerade pages).

## Baseline 2026-04-30 (90 d, 2026-01-30 → 2026-04-29)

Full rapport: `tmp-research/gsc-52/2026-04-30-low-rank-baseline.md`
(gitignorerad — pekas hit för referens vid nästa körning).

### Topp-10 vinst-potential

Vinst-uppskattning: `impressions × (0.15 − nuvarande_CTR)` där 0.15 är
antagen topp-3 CTR.

| Query                         | Imp 90d | Pos |    CTR | Potential clicks | Sannolik orsak                                     |
| ----------------------------- | ------: | --: | -----: | ---------------: | -------------------------------------------------- |
| polisen händelser             |  91 807 | 7.5 | 1.10 % |          ~12 800 | **Cannibalisation** — 15+ pages konkurrerar        |
| polisen                       |  23 671 | 6.9 | 0.12 % |           ~3 520 | Generisk fras, oklar landningssida                 |
| aktuella brott                |  14 898 | 4.1 | 0.66 % |           ~2 140 | Borderline rank, sannolikt CTR-fix                 |
| trafikkontroll                |  10 816 | 9.4 | 1.42 % |           ~1 470 | `/typ/trafikkontroll` har 0.05 % CTR (synergi #54) |
| polisen norrbotten            |  10 416 | 8.9 | 2.00 % |           ~1 350 | Lan-URL `/lan/Norrbottens län` legacy-format       |
| sos alarm händelser stockholm |   7 762 | 8.2 | 3.75 % |             ~870 | Ingen "SOS Alarm"-vinkel i title                   |
| polisinsats                   |   5 520 | 9.3 | 0.76 % |             ~790 | Saknar `/typ/polisinsats` (bekräfta)               |
| blåljus västerås idag         |   5 847 | 9.0 | 1.69 % |             ~780 | "[stad] idag"-mönster — inget matchande URL        |
| händelser polisen             |   4 318 | 8.7 | 1.51 % |             ~580 | Variant av "polisen händelser"                     |
| polisen händelser stockholm   |   4 283 | 9.4 | 2.94 % |             ~520 | Pekar på `/stockholm` men rankar bara pos 9        |

Total potential ~24 800 extra klick/90 d = ~8 270/mån vid topp-3-flytt.

### Kategorier i topp-50

1. **"polisen händelser [LÄN/STAD]"** — ~14 av topp-50 queries.
   Genomgående pos 7–10 trots dedikerade länsidor + ortsidor. Tyder på
   cannibalisation (Google roterar mellan flera nästan-likvärdiga sidor).
2. **"blåljus [STAD] idag"** — pos 8–10 på 8+ varianter (Västerås,
   Linköping, Eskilstuna, Solna, Huddinge, Dalarna, Eskilstuna,
   Jönköping, Göteborg). Vi har inte sidor som matchar "[stad] idag"-
   intentionen — endast generella ortsidor.
3. **"trafikkontroll"** — synergi med #54.
4. **"polisinsats"** — generisk fras (5 520 imp, pos 9.3) utan
   landningssida.
5. **"aktuella brott"** — pos 4.1 men CTR 0.66 % — borderline rank,
   sannolikt en title/snippet-fix räcker för topp-3.
6. **"senaste blåljusen [LÄN]"-asymmetri** — Stockholm rankar topp-3
   (3 970 klick, CTR 17.7 %) men Skåne/Kronoberg/Malmö/Värmland alla
   pos 7–10. Modellen funkar för Stockholm; varför inte övriga län?

### Drill-down: "polisen händelser" cannibalisation

15+ pages tar imp på samma query. Top-5:

- `/stockholm` — 39 176 imp, pos 7.6
- `/` — 29 721 imp, pos 7.6
- `/plats/göteborg` — 3 358 imp, pos 6.8
- `/lan/Skåne län` — 3 151 imp, pos 8.1 (legacy URL-format)
- `/lan/Västra Götalands län` — 1 499 imp, pos 7.6 (legacy URL-format)

Ingen sida tar topp-3. Värt egen audit i samband med #29.

### Drill-down: "trafikkontroll" thin-content

`/typ/trafikkontroll` (typ-listan) tar 89 % av impressionsen (9 630/10 816)
men har **CTR 0.05 %** (5 klick på 9 630 imp) — sannolikt thin/dålig
title/meta. Specifika trafikkontroll-events rankar topp-3 men har
försumbar imp-share. Synergi med #54.

## Förslag

### Monitor-loopen

1. **Definition "lågrankad högvolym":** imp ≥ 1 000/90d, position ≥ 7,
   CTR < 8 %, exkludera brand queries (`brottsplatskartan*`,
   `brottskartan*`, `brottsplats*`).
2. **Återkommande rapport** — kvartalsvis (90 d / 90 d compare) i
   `tmp-research/gsc-52/YYYY-MM-DD-rapport.md`. Mall = baseline-filen.
3. **Kategorisera** topp-30 enligt mönstren ovan; bryt ut nya todos när
   en kategori har flera queries med samma mönster.
4. **Verktyg:**
    - `mcp__mcp-gsc__get_advanced_search_analytics` — bredd
    - `mcp__mcp-gsc__compare_search_periods` — period-jämförelse
    - `mcp__mcp-gsc__get_search_by_page_query` — drill-down vid cannibalisation

### Konkreta åtgärder från baseline (potentiellt egna todos)

| Tag | Åtgärd                                                                                          | Vinstpotential | Fragment                                     |
| --- | ----------------------------------------------------------------------------------------------- | -------------- | -------------------------------------------- |
| A   | Cannibalisation-audit "polisen händelser" → välj canonical, fixa H1/title/meta                  | ~12 800 clicks | Egen todo om scope > 1 dag                   |
| B   | "[stad]/idag"-mönster för blåljus-queries                                                       | ~3 000 clicks  | Egen todo                                    |
| C   | Lyfta `/typ/trafikkontroll` content + meta                                                      | ~1 470 clicks  | Synergi #54 — del av den                     |
| D   | Egen `/typ/polisinsats` om den saknas                                                           | ~790 clicks    | Egen todo om bekräftat saknas                |
| E   | "senaste blåljusen [LÄN]"-asymmetri — varför Stockholm men inte övriga?                         | ~1 000 clicks  | Stickprov-fix, sannolikt 2h                  |
| F   | Lan-URL legacy-format (`/lan/Norrbottens län` etc.) — Stockholm-mönstret från #35 till alla län | ~1 350+ clicks | Egen todo, hög-prio (många queries påverkas) |
| G   | "aktuella brott" CTR-fix (title/meta tweaks)                                                    | ~2 140 clicks  | Mikrojobb — 30 min                           |

## Risker

- **GSC-data laggar 2–3 dagar** — kvartals-mätning ska köra > 5 dagar
  in i månaden så perioden är komplett.
- **Cannibalisering syns inte i query alone** — alltid följa upp med
  `query × page` för misstänkta fall (se baseline).
- **Topp-3-CTR-antagandet (15 %)** är en grov tumregel; varierar
  brutalt per query-typ. Vid prioritering: använd potentialen som
  rangordning, inte absolut prognos.
- **Mätperiodens längd** — 90d ger stabilitet men maskar säsongs-
  trender. Komplettera med 28d-blick vid plötsliga rörelser.

## Confidence

**Hög.** Mätverktygen finns och baseline visar att potentialen är
substantiell (~25k extra klick/90d top-10 om alla flyttas till topp-3).
Det viktiga är att inte glömma att köra rapporten kvartalsvis och att
bryta ut åtgärder A–G till egna todos när scope sätts.

## Beroenden

- Bygger på #26 (GSC MCP installerad).
- Synergi med #29 (indexerade pages) — F (lan-URL-format) hör hemma
  där eller som egen todo.
- Synergi med #54 (trafikkontroll) — C täcks delvis där.
- Synergi med #35 (Uppsala-redirect) — F skulle vara samma mönster
  fast bredare.

## Nästa steg

1. **Beslut:** vilka av A–G blir egna todos? (Användarbeslut.)
   Förslagen prioritetsordning: F > A > B > E > G > D > C(via #54).
2. När todos är skapade — markera #52 som "monitor only" och kör
   nästa rapport 2026-07-30 (~90 d efter baseline).
3. Sätt upp `/schedule`-routine om kvartals-rapporten ska automatiseras
   (annars manuell körning).
