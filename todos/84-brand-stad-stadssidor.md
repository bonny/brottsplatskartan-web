**Status:** avvaktar QW-mätning 2026-06-22 — stadssidor-beslut skjuts tills /brand-base visar positiv trend
**Senast uppdaterad:** 2026-05-26

# Todo #84 — /brand/{stad} stadssidor för top-15 städer

## Sammanfattning

`/brand` (1 568 c/mån, pos 6.9) rankar starkt på stads-queries utan att ha dedikerade stadssidor: `brand vallentuna` 89c, `brand mora` 63c, `brand telefonplan` 51c, `brand stora mellösa` 44c, `brand vretstorp` 42c, `brand österfärnebo` 41c, `brand bromölla` 33c. Långa svansen är **kommun-skopad**, inte län-skopad.

Skapa `/brand/{stad}` enligt /{lan}-mönstret för top-15 städer. Förväntad lyft: 750–2 000 clicks/mån.

## Bakgrund

- Kommer ur SEO-analysen 2026-05-23 ([[83-tema-sidor-polisinsats-skottlossning]] efterspel).
- Mönster: `/{stad}/handelser/{year}/{month}` (Tier 1) eller `/plats/{stad}` (övriga). Kombineras med `/brand/{stad}`.
- Query-villkor: samma som /brand (`parsed_title LIKE '%brand%|%mordbrand%|%brinner%|%brinna%|%rökutveckling%|%röklukt%'`) + ortsfilter på `administrative_area_level_2` eller `parsed_title_location`.
- Sitemap: lägg in alla 15 i `GenerateSitemap.php`.

## Förslag

### Fas 1 — Top-5 städer

Vallentuna, Mora, Telefonplan, Vretstorp, Österfärnebo (de med högst GSC-svans idag). Kopiera `/brand`-mönstret + stadsfilter.

### Fas 2 — Top-15

Lägg till Bromölla, Hallsberg, Karlstad, Sundbyberg, Stockholm/Blackeberg, samt stora orter (Göteborg, Malmö, Uppsala).

## Risker

- Tom-vid-låg-aktivitet: vissa småorter (Vretstorp, Österfärnebo) har 0 events de flesta dagar. Behöver `last 60 days`-fönster + soft-404-fallback (jfr [[79-soft-404-idag-fallback]]).
- Cannibalisering mot `/plats/{stad}`-sidor: små orter kanske redan rankar via /plats. Mät innan deploy.

## Confidence

**Hög för Fas 1** — direkt GSC-bevis. Förväntad lyft 400–800 c/mån.
**Medel för Fas 2** — beroende på event-frekvens per stad.

## Beroenden

- Standalone, men kan dra nytta av [[79-soft-404-idag-fallback]]-utfall innan deploy.
