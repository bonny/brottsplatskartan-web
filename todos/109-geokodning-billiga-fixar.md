**Status:** aktiv — punkt 1–4 och 6 klara 2026-10-10 (#111 fas B–C); kvar: 5 (valfri polygonkontroll). Ny 2026-10-07, från granskningen av #108; utökad 2026-10-10 med punkt 6 och 8 (brainstorm + granskning, se #111). Ingen kod skriven
**Senast uppdaterad:** 2026-10-10
**Källa:** Oberoende granskning av #108 (subagent, 2026-10-07)

# Todo #109 — Geokodning: billiga fixar för felplacering och omgeokodning

## Sammanfattning

Granskningen av #108 visade att platsmatchningen i stort sett fungerar och att
en NVDB-ombyggnad inte är värd det, men hittade några billiga fel i hur vi
bygger Google-frågan och hanterar uppdaterade händelser. Uppskattat ~0,5 dag.
Allt nedan är verifierat i koden 2026-10-07. Punkt 6 och 8 lades till
2026-10-10 efter en brainstorm om platsmatchning och en kritisk granskning
av den (subagent). Övriga idéer och mätningar därifrån ligger i
[#111](111-platsprecision-mat-forst.md). Med dem blir det ~0,75 dag.

## 1. Rensa Google-frågan från fel orter — ✅ klart 2026-10-10

`FeedController::getGeocodeURL()` slog ihop **alla** `locations` för
händelsen plus titelort och län. Nämnde texten en ort i ett annat län åkte den
med (510987 "Information, Gotland" → Stockholm).

**Gjort:** `FeedController::platserForGoogle()` tar bort länsnamn ("skåne",
"värmland" — Polisens län läggs till sist ändå) och kommuner i **andra län**
än händelsens (kommun → län ur `scb_kommuner`; "X kommun" normaliseras).
Gator, stadsdelar, byar och kommuner i samma län behålls.

**Eval mot Google innan deploy** (30 dagars publika prod-händelser, 1 749 st,
skript `tmp-111/eval-109-1.php`): två varianter jämfördes.

| Variant                                             | Frågor ändrade | Utanför länet | Enskilda händelser flyttade ≥ 2 km                            |
| --------------------------------------------------- | -------------: | ------------: | ------------------------------------------------------------- |
| A: ta även bort andra kommuner när titeln är kommun |            131 |         7 → 1 | 8: 2 bättre, 3 lika, **3 sämre** (Hultsfred, Häggvik, Bäckby) |
| **B: bara län + kommuner i andra län (vald)**       |            115 |         7 → 1 | 2: 1 oklar (Berg/Linköping), 1 sämre (Bäckby, Google-tur)     |

Variant A föll på att Polisens titelkommun inte alltid är där det hände:
511151 "Vimmerby" hände på stationen i Hultsfred, 510753 "Upplands Väsby" i
Häggvik. Variant B rättar sex av sju länsfel (Gotland, bedrägerivarningar,
en sammanfattning) och flyttar ingen punkt ut ur sitt län.

**510086 (Trelleborg → Malmö) löses inte av punkt 1**: Malmö ligger i samma
län. Men texten innehåller inte längre "malmö" eller "skåne" — Polisen har
uppdaterat händelsen och platserna tolkades aldrig om. Det är punkt 2.

## 2. Uppdaterade händelser tolkas om — ✅ klart 2026-10-10

`CheckForEventsUpdates` körde vid `CHANGED` bara `geocodeItem()` med de gamla
platserna: gator i Polisens uppdateringar plockades aldrig upp, gamla platser
låg kvar och varje omgeokodning var ett anrop med samma fråga.

**Gjort:** `FeedController::tolkaOmEfterAndring()` tömmer platserna, tolkar
texten igen och geokodar om **bara om Google-frågan ändrats**. Flyttade
punkter loggas som `Omgeokodad efter ändring hos Polisen` (fråga och punkt
före/efter) — sök på det för att se hur ofta första geokodningen var fel.

**Lärdom från första körningen på prod:** av 8 omgeokodade blev 3 bättre
(Borlänge, Kvälleberg, Svampen) men 3 sämre. När Polisen avslutar en händelse
ersätts texten ofta med "Försvunnen man anträffad", och gatan försvann.
Hittas inga platser i den nya texten behålls nu de gamla. De tre (511089,
511023, 510857) återställdes för hand.

## 3. Småbuggar i geokodningen — ✅ klart 2026-10-10

- `geocodeItemFallbackVersion()` jämförde `$result_results` (en array) med
  `"OK"`, så Googles felstatus passerade tyst. Kontrollerar nu
  `$result_status` och loggar `Reservgeokodning misslyckades` med status.
- Reservvägen använde `GOOGLE_API_KEY`, huvudvägen `GEOCODE_GOOGLE_APIKEY`.
  De är **olika nycklar** på prod. Reservvägen använder nu
  `GEOCODE_GOOGLE_APIKEY`, som bevisligen har Geocoding. `GOOGLE_API_KEY`
  används inte längre någonstans i koden; den står kvar i prod-env och
  `deploy/.env*.example` tills Pär bestämmer om den ska bort.
- Prio 3 i `findLocations()` var alltid tom, så varje händelse fick en tom
  location. Den togs bort, och `parseItemForLocations()` hoppar över tomma
  namn. Reservvägen hängde på just den tomma prio 3-raden (frågan blev
  "Umeå, " utan län); den bygger nu frågan av `parsed_title_location` +
  `polisen_location_name`. Snapshot-testet visade att Google-frågan inte
  ändras av att den tomma raden försvinner.

## 4. Kostnadsrisk — ✅ tak infört 2026-10-10

`FetchEvents` försökte geokoda om alla händelser med `geocoded = 0` från de
senaste 15 dagarna vid varje körning (var 12:e minut), alltså upp till ~1 800
anrop för en händelse som aldrig går att geokoda. Nu max 10 försök per
händelse (`FetchEvents::MAX_GEOKODFORSOK`, räknare i cachen under
`geokodforsok:{id}`), och en varning `Ger upp geokodning efter 10 försök`
loggas när taket nås.

## 5. Valfritt: polygonkontroll (~0,5 dag)

Om geokodad punkt hamnar utanför titelns län (polygoner finns:
`resources/geo/lansgranser.geojson`) — geokoda om med bara "ort, län".
Kommunpolygoner kan hämtas som länen (OSM admin_level=7, jfr
`deploy/lansgeometri.py`). Ger samtidigt ett löpande mått på felplacering
(idag 0,6 % utanför länet).

## 6. Ändrade API-fält läses in — ✅ klart 2026-10-10

`updateFeedsFromPolisen()` hoppade över befintliga `polisen_id`, så ändrad
`name`/`summary` lästes aldrig in. **Mätt på prod 2026-10-10:** 14 av 500
händelser i API:t skilde sig från det sparade (4 titlar, 10 sammanfattningar),
alla riktiga ändringar, inga falska av formatering. Exempel: 511009 "Knivlagen"
→ "Mord/dråp, försök"; 510995 "Linköping" → "Östergötlands län"; flera
"Försvunnen person" → "anträffad". Skript: `tmp-111/jamfor-api.php`.

**Gjort:** importen jämför titel och sammanfattning för publika befintliga
händelser. Vid skillnad: `FeedController::uppdateraFranApi()` sparar ny titel,
sammanfattning och `polisen_type`, tolkar om titel/datum, hämtar detaljsidan
igen och kör `tolkaOmEfterAndring()`. Den gamla versionen skrivs över — att spara
versionerna och visa "uppdaterad" för användaren är [#112](112-versionshistorik-for-handelser.md).

## 7. (Avfärdad) Avrunda känsliga brottstyper

Föreslogs 2026-10-10 och avfärdades av Pär samma dag: vi använder alltid så
exakt plats som möjligt. Polisens platser är redan väldigt oexakta, så vi
vill inte göra dem sämre.

## 8. Rätta docs/polisen-api.md

Doc:en säger att `location.gps` är "län- eller kommun-mittpunkt". Lokalt är
det exakt en punkt per län (`count(distinct gps)` = 1 per län). Rätta till
"länets mittpunkt".

## Risker

Fix 1 kan göra frågan för snål när titelns ort är ett län ("Skåne län") och
texten nämner rätt kommun — behåll då kommunen. Mät före/efter med län-testet
och stickprov (se #108).

## Confidence

hög för att felen finns (verifierade i koden); medel för storleken på vinsten —
enskilda felplaceringar, inte systematiska.
