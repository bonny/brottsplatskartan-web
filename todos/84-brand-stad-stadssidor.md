**Status:** QW 30d-mätning 2026-06-24 **negativ** — /brand/ pos 7,0→11,0, CTR 14,3→11,1 %, klick/dag ~halverat, "brandkarta" pos 2,7→6,7. Inte bara säsong (kvalitetssignaler ner). Lutar mot **avfärda stadssidor**; slutbeslut 60d-grind 2026-07-22 för att separera säsong.
**Senast uppdaterad:** 2026-06-24

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

## 2026-06-24 — QW 30d-mätning (negativ trend)

GSC, ≈30d före (2026-04-26→05-25) vs efter (2026-05-26→06-20). Korrekt URL
har trailing slash (`/brand/`):

| Mått                   | Före   | Efter    |
| ---------------------- | ------ | -------- |
| /brand/ position       | 7,0    | **11,0** |
| /brand/ CTR            | 14,3 % | 11,1 %   |
| /brand/ klick          | 1 623  | 650      |
| query "brandkarta" pos | 2,7    | 6,7      |

Klick/dag ~halverat (delvis säsong + 26d vs 30d-fönster), men **position OCH
CTR ner** = inte bara säsong, sidan har genuint försvagats. Premissen för #84
(stadssidor) var att /brand rankar starkt (pos 6,9) — den hypotesen håller inte
i mätningen. **Lutar mot avfärda.** Vänta på 60d-grind 2026-07-22 för att
separera säsongseffekt innan slutgiltigt go/no-go (tröskel: QW-lyft < 100 c/mån
→ avfärda).

## Beroenden

- Standalone, men kan dra nytta av [[79-soft-404-idag-fallback]]-utfall innan deploy.
