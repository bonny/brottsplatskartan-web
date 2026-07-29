# 98 – Filtrera bort mediecenter-/press-administrativa händelser

**Status:** aktiv
**Senast uppdaterad:** 2026-07-29

## Problem

Polisens API publicerar rena press-administrativa meddelanden som händelser:
mediecentrets öppettider, telefonnummer, mejladresser. Exempel — den händelse
som startade todon:

> `https://brottsplatskartan.se/varmlands-lan/information-varmlands-lan-506947`
> "Idag, onsdag den 29 juli, är RMC stängt. Det går alltid att mejla till
> media.bergslagen@polisen.se vars inflöde vi hanterar i mån av tid."

Ingen brottsplats, ingen händelse, inget värde för besökaren. Polisen postar
dessutom samma text per län, så varje meddelande blir tre separata sidor.

## Mätning (prod, 365 dagar, 2026-07-29)

|                                              |              |
| -------------------------------------------- | ------------ |
| Events som nämner RMC/mediecenter/media-mejl | 3 903        |
| …redan filtrerade av befintliga mönster      | 3 707 (95 %) |
| **…fortfarande publika**                     | **203**      |

De 203 ligger under `parsed_title` **Information** (86) och **Övrigt** (91).
`isPhoneNumberInfo()` täcker "pressnummer"/"presstalesperson" men inte
"regionalt mediecenter"/"RMC"/`media.x@polisen.se`.

## Varför frasmatchning ensam inte räcker

Samma fraser står som fotnot i riktiga händelser:

- mordet vid moskén i Örebro (`341738`, offset 834)
- de grova rånen i Huskvarna (`477790`, offset 783)
- brandanhållandet på Junegatan (`504460`, offset 1131)
- misshandel och våldsamt upplopp (`499648`, offset 483)

En bred `/RMC/`-match hade dolt samtliga. Det är samma fälla som kommentarerna
i `ContentFilterService` redan varnar för (`undvik breda mönster som
/ordinarie pressnummer/i`).

## Lösning

`ContentFilterService::isMediaCenterInfo()` kräver **alla tre**:

1. Pressfras matchar: `regionalt/regionala media(c|ec)ent`, `\bRMC\b`,
   `media.x@polisen.se`, `mediafrågor`, `medieförfrågningar`
2. Första träffen inom **300 tecken** från textens början
3. Brödtexten (efter `strip_tags` + kollapsad whitespace) **< 600 tecken**

Utfall: **182 av 203** filtreras, **noll** falska positiva mot 17 manuellt
verifierade riktiga händelser. Trösklarna ligger i en glugg — allt uppmätt
skräp har offset ≤ 269, närmaste riktiga händelse 483; 300/350/400 gav
identiskt utfall.

De 21 som släpps igenom är riktiga händelser plus två nattsammanfattningar
där press-texten står först men riktiga händelser följer efter.

## Fallgrop: byte- kontra teckenoffset

Första mätningen använde `PREG_OFFSET_CAPTURE` (byte-offset) och konverterade
med `mb_substr()`, som räknar tecken. Med åäö blir offseten övervärderad, och
två gränsfall hamnade på fel sida. Rätt konvertering är
`mb_strlen(substr($text, 0, $byteOffset))`. Implementationen gör så, och
mätskriptet speglar kodvägen exakt.

## Kvar att göra

- [x] `isMediaCenterInfo()` + test med verkliga prod-texter
- [x] `getFilterReason()` publik, `CheckEventPublicity` slutar duplicera kedjan
- [ ] Backfill på prod: backup → `--since=400` dry-run → `--apply`
- [ ] `responsecache:clear` så cachade 200-svar inte fortsätter serveras
