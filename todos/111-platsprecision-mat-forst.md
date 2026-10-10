**Status:** aktiv — A1 deployad 2026-10-10 (`4c411897`), A2 `geocode:halsa` byggd, A3 mätt: LLM-extraktion avfärdad (läckage 0,2 %). Fas B klar 2026-10-10 (snapshot-test, #109.3–4). Nästa: fas C = #109.1, 2, 6
**Senast uppdaterad:** 2026-10-10
**Källa:** Brainstorm med Pär 2026-10-10 + kritisk granskning av den (subagent, samma dag)

# Todo #111 — Platsprecision: mät först, sen ärlig precision

## Sammanfattning

Efter #108 är grovfelen i stort sett lösta (0,6 % utanför länet), men 75 %
av punkterna är Google `APPROXIMATE`, dvs. ortsnivå. En brainstorm föreslog
LLM-extraktion av platsen (gata, korsning, POI, platstyp) och "andel på
gatunivå" som nordstjärna. Granskningen sköt ner båda som första steg:

- **"Gatunivå" är fel mått.** Taket sätts av texten (bara 21 % av träffarna
  ser ut som gator) och SEO och användare är organiserade per ort/län.
  Bättre mått: **ärlig precision** — visad precision ska motsvara datans
  precision. Följ falsk precision och fel ort/län. Målet är ändå alltid så
  exakt plats som möjligt (se Beslut).
- **LLM-extraktion är obevisad nytta** tills vi vet hur mycket dagens
  regex + Google faktiskt tappar.

Den här todon samlar mätningarna som avgör om något större är värt att
bygga, och idéerna som klarade granskningen. De billiga, verifierade
fixarna ligger i [#109](109-geokodning-billiga-fixar.md).

## Mät först

1. ~~**Läckaget**~~ — mätt 2026-10-10, se Resultat.
2. ~~**Taket**~~ — mätt 2026-10-10, se Resultat.
3. **Var trafiken finns:** sidvisningar per län/stad i GA4. Om det mesta är
   Stockholm/Göteborg/Malmö räcker handkurerade stadsdelscentroider där.

Data i gitignorerad `tmp-111/`. Prod bara läsande.

## Resultat A3 (2026-10-10, prod, 30 dagar, 1 752 publika händelser)

Skript och utdata: `tmp-111/export.php`, `tmp-111/analys.py`,
`tmp-111/analys.txt`.

- **Taket:** 23,6 % nämner en gata, 22,0 % ett vägnummer (E4, väg 40),
  1,5 % en korsning. **45,6 % nämner varken gata, väg eller POI** — där går
  det inte att bli mer exakt än orten.
- **Gatunamnslistan täcker nästan allt:** bara 15 händelser (0,9 %) nämner
  en gata som inte finns i listan, 23 unika namn, alla med en enda
  förekomst. Genitiv-s ("Storgatans") missas 0 gånger.
- **Läckaget:** 62 händelser (3,5 %) har en matchad gata men blir ändå
  `APPROXIMATE`. 59 av dem är trafikkontroller och sammanfattningar som
  räknar upp flera gator, där ortsnivå är rätt. **Kvar blir 3 (0,2 %).**
- **"75 % APPROXIMATE" överdriver hur grovt det är.** Av 878 händelser med
  en plats som är `APPROXIMATE` har 492 en finare ort eller stadsdel än
  titelns kommun bland sina träffar. Google returnerar då byns eller
  stadsdelens mittpunkt, vilket också heter APPROXIMATE. `google_types` (A1)
  skiljer dem åt framöver.
- **POI-ord är mest brus:** 136 av 197 träffar är "sjukhus", nästan alltid
  "fördes till sjukhus".
- **Den verkliga luckan är vägar:** 192 händelser (11 %) med vägnummer, ingen
  gata och ortsnivå. 135 av dem har en by som referenspunkt ("E45 i höjd
  med Kvarnberg"), så bara **57 (3,3 %)** saknar något finare än kommunen
  ("E4 strax norr om Hudiksvall").

**Slutsats:** LLM-extraktion har nästan inget att hämta. Det som återstår är
fel ort på grund av hur Google-frågan byggs (#109) och vägsträckor
(Trafikverket, se Parkerat).

## Beslut

- **2026-10-10 (Pär):** använd alltid så exakt plats som möjligt, även för
  sexualbrott och våld i nära relation. Polisens platser är redan väldigt
  oexakta, så vi avrundar inte ytterligare per brottstyp.

## Idéer som klarade granskningen

Ungefär i prioritetsordning:

1. ✅ **Spara Googles `types` och `partial_match`** per händelse (A1,
   deployad 2026-10-10). Ger en precisionsklass (route/intersection/locality/
   sublocality) gratis och `partial_match=true` är en stark signal att
   Google gissade. Grunden för måttet.
2. **Viewport-cirkel vid ortsnivå** i stället för exakt nål. Viewport och
   `location_geometry_type` sparas redan (`FeedController::geocodeItem`), så
   ingen LLM behövs.
3. ✅ **Regressionstest av Google-frågan** (fas B, 2026-10-10):
   `tests/Unit/GeocodeUrlSnapshotTest.php` med 54 prod-händelser i
   `tests/fixtures/geocode-fragor.json` (slump + länstitlar + många platser +
   kända fel som 510086 och 510987). Bygger frågan via
   `FeedController::geocodeUrlFor()` utan databas och utan Google-anrop.
   Uppdatera medvetet med `UPPDATERA_SNAPSHOT=1` och granska diffen.
4. ✅ **Hälsosiffra:** `php artisan geocode:halsa [--dagar=30 | --fran= --till=]`
   (A2) — länstest mot `polisen_location_name`, fördelning av
   `location_geometry_type` och precisionsklass från `google_types`. Körs vid
   behov, inte schemalagt: allt räknas i efterhand ur databasen.
5. **Strikt `components=locality:{titelort}|administrative_area:{län}`** och
   bara gata/stadsdel i `address`. Löser Trelleborg→Malmö strukturellt.
   Risk: fler `ZERO_RESULTS`, kräver reserv utan components. Mät mot facit.
6. ✅ **Spara API:ts `type`-fält** (A1, `polisen_type`). API-objektet
   (`polisen_raw`) slutade sparas samma dag efter code review: CrimeEvent
   cachas som hela modeller i Redis (`LanController`, `PlatsController`),
   rådatan låg där två gånger per händelse, och nästan allt i den finns redan
   i andra kolumner. Kolumnen står tom och tas bort nästa gång
   `crime_events` ändå behöver en ALTER (38 s skrivlås på prod).
7. **Litet facit:** ~100 händelser märkta med precisionsklass och rätt ort
   (inte meter — facitkoordinater saknas oftast).

## Parkerat (kanske senare)

- **Haiku som granskare, inte extraktor:** nattligt batchjobb som frågar om
  Googles adress stämmer med texten. Löpande mått utan att röra importen.
  Efter #110.
- **Trafikverket som precisionskälla** för trafikolyckor (har punkt,
  `start_time`, `road_number`; sparas 90 dagar). Största kvarvarande luckan
  enligt A3: 57 väghändelser/30 d (3,3 %) utan något finare än kommunen.
- **Handkurerade stadsdelscentroider** för de största städerna — beror på
  mätning 3.

## Avfärdat

- **"Andel på gatunivå" som nordstjärna** — se Sammanfattning.
- **Avrunda känsliga brottstyper till ortsnivå** — Pär 2026-10-10, se Beslut.
- **MCF räddningsinsatser som precisionskälla** — bara månadsaggregat per
  kommun, inga koordinater.
- **SCB-register före Google** — importen har varken punkter eller polygoner,
  saknar stadsdelar och småorter, och Google kostar $0.
- **Självlärande register från egen historik** — låser in och förstärker
  Googles egna fel.
- **Egen Nominatim/Pelias** — RAM saknas på CX33.
- **LLM-platsextraktion** — A3 visade läckage på 0,2 % för händelser med en
  plats; inget att hämta.
- **Logga saknade gatunamn** — A3 hittade 23 namn på 30 dagar, alla med en
  enda förekomst.
- **"Fel plats?"-knapp och dubblettdetektion** — YAGNI tills behovet syns.

## Risker

Viewport-cirkeln ändrar kartans utseende
för tre av fyra händelser — kolla att den inte blir brus i täta städer.

## Confidence

hög för A3-slutsatsen (mätt på 30 dagars prod-data); regex-mätningen
räknar fel på enskilda händelser men inte i en storleksordning som ändrar
slutsatsen.
