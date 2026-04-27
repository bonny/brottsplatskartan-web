**Status:** aktiv — mätperiod startad 2026-04-27, första check 2026-05-25
**Senast uppdaterad:** 2026-04-27
**Beroende av:** #10 (deployat 2026-04-27)

# Todo #36 — GSC-mätning av AI-titlars CTR-effekt

## Sammanfattning

Uppföljning till **#10**. Sedan 2026-04-27 visar prod 4596 events med
AI-omskrivna titlar i `<title>`, OG, meta-description och Schema headline.
Den här todon mäter SEO-effekten i Google Search Console och avgör om vi
ska gå vidare med fas 2 (auto-trigger i fetch-pipelinen + backfill av
historik).

## Bakgrund

#10-deploy:

- Aktiverade `display_title` / `display_description`-accessorer som läser
  `title_alt_1` / `description_alt_1` (genererade av `EventTitleRewriter`,
  Sonnet 4.6) med fallback till `parsed_title` / `parsed_content`.
- Synergi med #32: Schema `headline` får automatiskt AI-titeln.
- 4596 events i prod hade redan AI-data sedan tidigare manuella körningar
  av `crimeevents:create-summary`. Aktiveringen var en ren rendering-ändring,
  ingen DB-ändring.

Hypotes: AI-titlar är mer informativa + click-bara → högre CTR i Google
SERP. Schema headline är dessutom en stark ranking-signal, så ranking kan
också förbättras.

## Förslag — mätmetod

Använd `mcp-gsc` (klar sedan #26), `compare_search_periods`-verktyget:

| Parameter        | Värde                                         |
| ---------------- | --------------------------------------------- |
| Period 1 (före)  | 2026-03-28 → 2026-04-26 (30d före deploy)     |
| Period 2 (efter) | 2026-04-28 → 2026-05-27 (30d efter deploy)    |
| URL-filter       | `/.*-\d+$` (event-sidor — slug slutar med ID) |
| Mätvärden        | impressions, clicks, CTR, avg position        |

**Kompletterande analys:** för att isolera AI-effekten, dela datat:

- Events MED AI-titel (de 4596) — primär behandlingsgrupp
- Events UTAN AI-titel — kontrollgrupp (samma URL-mönster, olika tabell-fält)

Det kräver en separat join i analys-skriptet eftersom GSC inte vet om en
URL har AI-titel eller inte. Kan göras lokalt: pulla alla event-URLs från
GSC, joina mot `crime_events.title_alt_1 IS NOT NULL`-flagga.

## Trösklar för beslut

| CTR-förändring (AI-events vs kontrollgrupp) | Beslut                                                       |
| ------------------------------------------- | ------------------------------------------------------------ |
| > +20 % relativ                             | Tydlig vinst → kör fas 2 (auto-trigger + backfill ~$27)      |
| +5 % till +20 %                             | Marginell vinst → kör auto-trigger (gratis), skippa backfill |
| -5 % till +5 %                              | Ingen signifikant skillnad → vänta 4v till på mer data       |
| < -5 %                                      | Regression → utred (hallucinationer? tonalitet? rollback)    |

## Tidslinje

- **2026-04-27**: Deploy + start mätperiod.
- **2026-05-25** (~4v): Första check. Tidigt — ranking-effekter ofta lagged.
- **2026-06-22** (~8v): Andra check om fas 1 inte gav tydlig signal.
- **2026-07-27** (~12v): Sista check + slutgiltigt beslut om fas 2.

GSC har vanligtvis 1-3 dagars datafördröjning, så ge marginal innan check.

## Risker

1. **Liten signal**: 4596 events är ~1.4 % av totala 328 136 events. Resten
   visar oförändrad rendering, så CTR-skillnaden kan drunkna i statistiskt
   brus. _Mitigering_: filter på events MED `title_alt_1` (treatment-grupp).
2. **Confounding factors**: Andra ändringar (#32 schema-sweep, #33 Tier 1
   month-routes) kan också påverka CTR samtidigt. _Mitigering_: kontrollgrupp.
3. **Säsong**: april → maj kan ha säsongseffekter på blåljus-trafik
   (terminens slut, valborg). _Mitigering_: jämför med samma period
   föregående år om möjligt.

## Confidence

**Hög** på metoden — `compare_search_periods` är beprövat sedan #26.
**Oklar** på resultatet (det är ju det vi mäter). Förväntar +5 % till
+30 % CTR-vinst på treatment-gruppen baserat på allmänna
SEO-rapporter om SERP-titel-optimering.

## Status / nästa steg

**2026-04-27 — startat:** ren mätperiod, inga åtgärder.

**Plan:**

1. Vänta till 2026-05-25.
2. Kör `compare_search_periods` via mcp-gsc med URL-filter ovan.
3. Joina output mot `crime_events.title_alt_1`-flagga lokalt.
4. Skriv resultatrapport: CTR före/efter per grupp + beslut om fas 2.
5. Stäng denna todo + öppna fas 2-todo om motiverat.

## Relaterade todos

- **#10** (klar 2026-04-27) — implementation som mäts här.
- **#26** (klar 2026-04-26) — mcp-gsc-verktyget som används.
- **#32** (klar 2026-04-27) — Schema-sweep, sambandsfaktor i CTR-effekten.
