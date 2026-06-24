**Status:** aktiv monitor — C/D/E/F/G klara (#54/#72/#75/#77 + LanController-titlar), kvar: A (vänta #76 Fas B), B (utöka #76). 2026-06-24-mätning: åtgärd E (LanController-titlar) gav **inget lyft** på "senaste blåljusen [LÄN]" (pos platt-till-sämre); "senaste blåljusen stockholm" **ej återhämtad** efter 30d (pos 6,7, CTR 25→5 %, strukturell extern omrankning 05-22) → SERP-inspektion gjord, riktad åtgärd: /stockholm-titel breddad (blåljus+senaste), **deployad 2026-06-24**. Mät 30d (2026-07-24). Nästa rapport 2026-07-30.
**Senast uppdaterad:** 2026-06-24
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
| C   | Lyfta `/typ/trafikkontroll` content + meta                                                      | ~1 470 clicks  | ✓ klar 2026-05-26 via #54 — mät 2026-06-25   |
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

## Uppdatering 2026-05-25 — E klar

**Rotorsak:** GSC drill-down (90d, 2026-02-24 → 2026-05-24) visade att
asymmetrin inte var "Stockholm-modell saknas för övriga län" utan
**title/meta-asymmetri** mellan Tier 1-städer och LanController:

- `/stockholm` (Tier 1) titel: `Polisen händelser Stockholm idag – brott, olyckor och larm` → pos 3.0, **CTR 21.47 %**, 4 780 clicks på "senaste blåljusen stockholm".
- `/lan/skane-lan` titel: `Brott och händelser från Polisen i Skåne län` → pos 7.3, **CTR 2.03 %**, 120 clicks på "senaste blåljusen skåne".

Tier 1-meta är säljande och kompakt; län-meta var klumpig
("närheten av Skåne län") + autogenererad lista med upprepade kategorier.

**Fix:** Bytt `LanController.php`:217 + :277-282 till exakt samma
mönster som `config/tier1-cities.php`:

```php
$pageTitle = "Polisen händelser {$lan} idag – brott, olyckor och larm";
$metaDescription = "Alla polisens händelser i {$lan} idag på karta – brott, trafikolyckor, bränder och larm. Aggregerat live från Polismyndigheten med 10 års arkiv.";
```

Verifierat lokalt mot `/lan/skane-lan` + `/lan/kronobergs-lan`.
PHPStan grön. Tog även bort död `$mostCommonCrimeTypesMetaDescString`-build.

**Mät 30d post-deploy:** "senaste blåljusen [LÄN]"-queries —
mål: pos 7–8 → 4–5, CTR ~2 % → ~5 %, ~1 000 extra clicks/90d.
Påverkar alla 21 län. Uppföljning: 2026-06-25 (30d).

**Risk:** kan paradoxalt boosta "polisen händelser [LÄN]"-queries på
bekostnad av "senaste blåljusen [LÄN]" — båda är värdefulla. Mätperioden
visar netto-effekt.

## Uppdatering 2026-06-24 — åtgärd E 30d-mätning + Stockholm-bevakning

Full rapport: `tmp-uppfoljning-2026-06-24/REPORT.md`. Genomgående confounder:
bred säsongsnedgång i impressions tvärs alla queries → CTR/position är renare
signaler än absoluta klick.

**Åtgärd E (LanController-titlar, deploy 2026-05-25) — lyft uteblivet.**
≈30d före (04-25→05-24) vs efter (05-25→06-20):

| Län-query | Pos (före→efter) | CTR (före→efter) | Impr        |
| --------- | ---------------- | ---------------- | ----------- |
| skåne     | 7,5 → 9,2        | 1,74 → 2,17 %    | 2 186→1 197 |
| jämtland  | 8,0 → 9,4        | 1,67 → 0 %       | 659→164     |
| blekinge  | 6,7 → 9,2        | 3,97 → 0 %       | 126→148     |

Mål (pos 7-8→4-5, CTR ~2→~5 %) **ej nått** — position platt-till-sämre.
Kontrast: "senaste blåljusen malmö" (Tier 1, **ej** åtgärd E) förbättrades
pos 5,1→3,9, CTR 8,2→14,7 % → Tier 1-mallen funkar, men LanControllers kopia
lyfte inte län-queries. Ingen demonstrerad vinst; fold in i kvartalsrapport.

**"senaste blåljusen stockholm" (Stockholm-bevakning, drop 2026-05-22) — ej
återhämtad.** ≈30d före drop (04-22→05-21) vs efter (05-22→06-20):

| Mått  | Före   | Efter |
| ----- | ------ | ----- |
| Pos   | 2,3    | 6,7   |
| CTR   | 25,4 % | 5,1 % |
| Klick | 1 867  | 354   |

Impressions stabila (7 346→6 887) → Google visar oss fortf., men position
fastnat ~6,7 och CTR kollapsad. Per uppföljningens villkor ("kvar ~pos 6 efter
30d → överväg riktad åtgärd") → **riktad åtgärd aktualiserad** (SERP-inspektion
/ "senaste"-intent-refresh på /stockholm). **Användarbeslut.**

### SERP-inspektion 2026-06-24 (riktad åtgärd vald + deployad)

Konkurrentanalys: `tmp-uppfoljning-2026-06-24/konkurrenter-blaljus-stockholm.md`.

- **Daglig tidslinje:** knivskarp cliff 2026-05-22 (pos 2,x→7,0 över en natt),
  sedan **låst pos 6,0–7,4 varje dag i 30 dagar** — strukturell omrankning, ej
  brus, noll passiv återhämtning. Impressions _steg_ efter dropen → Google
  visar oss mer men demoterade oss ur topp-klustret.
- **Teknik utesluten:** `/stockholm` crawlad 2026-06-24, "Submitted and indexed",
  fetch OK, Rich Results PASS. **Cannibalisering utesluten:** /stockholm är klart
  sidan Google serverar (323 klick); `/` snor bara 25.
- **SERP-fält:** topp-aktörer (Poliskoll #1 "Blåljus Stockholm – Senaste
  polishändelser i realtid", Polisinfo "…Senaste blåljus och brott idag") har
  **"blåljus" + "senaste" rakt i `<title>`**. Vår titel hade varken. Dropen är
  extern, men term-matchning är spaken vi själva styr.

**Åtgärd (deployad 2026-06-24):** `config/tier1-cities.php` →
`stockholm.pageTitle` ändrad till _"Polisen & blåljus i Stockholm idag –
senaste händelser, brott och larm"_ (+ description "Senaste blåljusen i
Stockholm idag …"). Behåller "polisen händelser"-orden (skydda topp-3-vinsten
på den frasen), lägger till **blåljus + senaste**. Endast Stockholm (per-stad-
fält). PHPStan grön. Deployets cache-warmup byggde om config-cachen.

**Mät 30d (2026-07-24):** "senaste blåljusen stockholm" (mål: upp från pos 6,9)

- bevaka att "polisen händelser stockholm idag" håller topp-3 (tradeoff-kollen).
