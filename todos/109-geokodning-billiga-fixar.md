**Status:** aktiv — punkt 3, 4 klara 2026-10-10 (#111 fas B); kvar: 1, 2, 6 (fas C) och 5. Ny 2026-10-07, från granskningen av #108; utökad 2026-10-10 med punkt 6 och 8 (brainstorm + granskning, se #111). Ingen kod skriven
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

## 1. Skicka bara titelns ort/län till Google (~1 h)

`FeedController::getGeocodeURL()` slår ihop **alla** `locations` för händelsen
plus `parsed_title_location` och `polisen_location_name` till en adress. Nämner
texten en annan ort eller ett annat län åker det med.

Exempel: 510086 "Försvunnen kvinna, Trelleborg" hamnade i Malmö centrum (~27
km fel) eftersom "malmö" och "skåne" stod i texten.

Förslag: ta bort orter/kommuner/län ur frågan som skiljer sig från titelns ort.
Behåll gator och stadsdelar.

## 2. Bugg: uppdaterade händelser söks aldrig igenom på nytt (~1–2 h)

`CheckForEventsUpdates` (rad ~74): vid `CHANGED` körs bara
`geocodeItem()`, inte `parseItemForLocations()`. Följder:

- Gator som tillkommer i Polisens uppdateringar plockas aldrig upp. Exempel:
  510391 "Rättelse: Brottsplats är Bondegatan".
- Varje omgeokodning skickar exakt samma fråga som förut — ett bortkastat
  Google-anrop (~300/månad).
- Gamla locations tas inte bort.

Förslag: vid `CHANGED`, töm locations, kör `parseItemForLocations()` och
sedan `geocodeItem()`.

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

## 6. Läs om API-fälten när Polisen ändrar en händelse (~1 h)

`FeedController::updateFeedsFromPolisen()` (rad ~497) hoppar över
`polisen_id` som redan finns. Ändrad `name` eller `summary` i API:t läses
alltså aldrig in igen; bara den skrapade detaljsidan jämförs
(`CheckForEventsUpdates`). Rättelser ("Brottsplats är …") kommer ofta just
där.

Förslag: jämför `name`/`summary`/`type` för befintliga id, uppdatera
`polisen_type` (sparas sedan 2026-10-10, annars fastnar den på första
versionen) och kör samma väg som punkt 2 (töm locations → `parseItemForLocations()` → `geocodeItem()`) vid
ändring. Logga gammal och ny punkt: det ger gratis en felsignal för hur ofta
första geokodningen var fel.

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
