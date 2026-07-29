# 98 – Filtrera bort mediecenter-/press-administrativa händelser

**Status:** klar 2026-07-29
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

`ContentFilterService::isMediaCenterInfo()` kräver **alla fyra**:

1. Ikongruppen är `ovrigt` eller `sammanfattning` — aldrig en brottskategori
2. Pressfras matchar: `regionalt/regionala media(c|ec)ent`, `\bRMC\b`,
   `media.x@polisen.se`, `mediafrågor`, `medieförfrågningar`
3. Första träffen inom **300 tecken** från textens början
4. Brödtexten (efter `strip_tags` + kollapsad whitespace) **< 600 tecken**

Testet körs mot `parsed_content` och, om det inte träffar, mot `description`.

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

## Efter code-review (2026-07-29)

Granskningen hittade tre saker. Alla mätta mot hela lokala datasetet
(~500 k events, hela historiken) genom att anropa `isMediaCenterInfo()`
direkt i stället för att skriva om logiken i mätskriptet.

**1. `parsed_content` ensamt räckte inte.** Fältet skrapas från polisen.se och
blir NULL för alltid om skrapningen fallerar en gång: `parseItem()` ignorerar
`'ERROR'` från `parseItemContentAndUpdateIfChanges()`
(`FeedController.php:414`), `parseItemForLocations()` sätter
`scanned_for_locations = true` ändå (`:369`), och `FetchEvents.php:61`
plockar bara upp rader med `scanned_for_locations = 0`. `description` sätts
vid insert från JSON-API:t (`FeedController.php:514`) och finns alltid.
Fallback dit fångar **125** fler, varav 64 har tom `parsed_content`. Att också
skanna `title` gav noll extra träffar — utelämnat.

**2. Korta riktiga händelser med mediefotnot kunde döljas.** Ett verkligt fall
i historiken: event 152427 (`Brand`, Ullevigaraget, 348 tecken, frasen på
offset 240) föll innanför båda gränserna. Skyddet är brottskategorin — polisen
filar press-meddelanden som Information/Övrigt eller Sammanfattning, aldrig som
Brand. `getIconGroup()` måste nu vara `ovrigt` eller `sammanfattning`.

Granskaren föreslog i stället att stryka `mediafrågor`/`medieförfrågningar`
som "minst nytta, mest risk". Mätningen säger emot: de är ensam bärare för
**128** träffar (125 + 3) över hela historiken, inte 17. Kategorigrinden ger
samma skydd utan att tappa dem.

**3. Ogiltig UTF-8 gjorde kontrollen tyst verkningslös.** `preg_replace` och
`preg_match` med `/u` returnerar `null`/`false` på en enda latin-1-byte →
brödtexten blev `''` och metoden svarade false. Byten skrubbas nu med
`mb_convert_encoding()` före normaliseringen.

Utfall efter ändringarna, hela historiken: **4 240** fångade, fördelat på
`ovrigt` (4 238) och `sammanfattning` (2) — **noll** brottskategorier.

## Utfört

- [x] `isMediaCenterInfo()` + test med verkliga prod-texter (15 tester)
- [x] `getFilterReason()` publik, `CheckEventPublicity` slutar duplicera kedjan
- [x] Deployad `2c9b063`
- [x] Backfill på prod: backup `prod-2026-07-29-092228.sql.gz` → dry-run
      `--since=400` (191 träffar, samtliga "Mediecenter-information") → `--apply`
- [x] `responsecache:clear`
- [x] Code-review-uppföljning deployad `980540a`, andra backfillen
      (`prod-2026-07-29-104020.sql.gz`) tog **6** till — två press-meddelanden
      × tre län, varav ett med tom `parsed_content`

## Resultat på prod (2026-07-29)

**197 sidor** markerade `is_public = false` totalt: 191 i första passet
(107 `Övrigt`, 82 `Information`, 2 `Sammanfattning natt`) plus 6 i andra passet
efter code-review-fixarna (samtliga `Övrigt`). Ingen brottskategori i någon
av listorna.

Verifierat efter backfill:

- De tre startsidorna för RMC-meddelandet ger nu **404**
- Samtliga tio stickprovade riktiga händelser är kvar publika (mordet vid
  moskén, rånen i Huskvarna, Junegatan-branden, misshandeln på anstalten,
  Södermanland-stölderna, nattsammanfattningarna, trafikkontrollveckan)
- **22** publika träffar på pressfrasen återstår över 400 dagar — alla riktiga
  händelser med press-fotnot, plus två nattsammanfattningar där press-texten
  står först men riktiga händelser följer efter

Nya händelser filtreras automatiskt: `crimeevents:fetch` kör
`markEventsAsNonPublic(1)` efter varje hämtning.

## Uppföljning

Volymen är ~0,5 press-meddelanden per dygn (3 län × varannan dag). Om polisen
byter formulering slutar filtret träffa utan att något larmar. Kontrollera vid
nästa GSC-genomgång att `Information`/`Övrigt` inte kryper tillbaka i
indexerade sidor (hör ihop med #29).
