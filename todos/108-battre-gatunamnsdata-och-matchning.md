**Status:** parkerad 2026-10-07 — NVDB-ombyggnaden avråds i nuvarande form (oberoende granskning samma dag); `högsta` tillagd i `highways_ignored`. Billiga fixar flyttade till #109
**Senast uppdaterad:** 2026-10-07
**Källa:** Pär, 2026-10-07 (uppföljning av #106)

# Todo #108 — Bättre gatunamnsdata och smartare platsmatchning

## Sammanfattning

Efter #106 (gatunamn från OSM) frågade Pär om det finns ett bättre, smartare
och mer korrekt sätt att få datan. Utredningen hittade bättre källor och ett
smartare matchningsgrepp, men **mätningen på prod visar att dagens matchning
redan fungerar bra** och att geokodningen kostar ~0 kr. Rekommendation:
bygg inte om. En oberoende granskning (subagent, 2026-10-07) bekräftade
slutsatsen men rättade underlaget — se "Granskning" nedan. De billiga
förbättringar den hittade ligger i [#109](109-geokodning-billiga-fixar.md).

## Alternativa källor

| Källa                                                  | Innehåll                                      | Licens                                        | Bedömning                                                |
| ------------------------------------------------------ | --------------------------------------------- | --------------------------------------------- | -------------------------------------------------------- |
| OSM (idag, se #106)                                    | Gatunamn + POI-brus (hållplatser, butiker)    | ODbL                                          | Fungerar; kräver filtrering                              |
| **Trafikverket NVDB** ([Lastkajen][lastkajen])         | Officiella vägdatabasen, gatunamn med kommun  | CC0, gratis konto                             | Bäst för gator — inget POI-brus, har kommun och geometri |
| Lantmäteriet belägenhetsadresser ([geodata.se][lmadr]) | Varje adress: gata, nummer, kommun, koordinat | Avgiftsfri sedan 2025-02, CC BY 4.0 + villkor | Mest exakt, mer krångel (Geotorget-konto, GDPR-villkor)  |
| Lantmäteriet ortnamn                                   | Officiella ort- och naturnamn                 | Öppen                                         | Bra för orter/stadsdelar                                 |

[lastkajen]: https://www.trafikverket.se/lastkajen
[lmadr]: https://www.geodata.se/geodataportalen/GetMetaDataById?id=5615eaa7-bdf6-4cd7-a795-0520c9ac60e5

**Smartare grepp:** matcha per kommun i stället för mot en platt
Sverige-lista ("finns gatan i just den här kommunen?"). Med NVDB:s
kommunkoppling försvinner falska träffar som "Norra"/"Okänd", stopplistan
(`highways_ignored`, 901 rader) behövs mindre, och gatans koordinat kan
ersätta Google-geokodningen. Valfritt: låt titelomskrivningen (Sonnet)
extrahera "gata + ort" i samma anrop och validera mot NVDB.

## Mätning 2026-10-07 (prod, 30 dagar, read-only)

Data i `tmp-108/` (gitignorerad).

- 1 761 publika händelser, alla geokodade. **72,6 %** fick minst en träff i
  listan (rättat — 37 % räknade bara prio 1-träffar). Bara 21 % av
  träffarna ser ut som gator; resten är orter och stadsdelar.
- **Felplacering (svag metod, se Granskning):** jämfört med medianen för
  händelser med samma ort i titeln hamnar 2,6 % mer än 30 km bort och 0,6 %
  mer än 60 km bort. Metoden utesluter de 28 % som bara har län i titeln och
  ser inte fel under 30 km. Nästan
  alla dessa är **rätt**: byar i stora landsbygdskommuner (Hemavan i
  Storuman, Nikkaluokta i Gällivare, Svappavaara i Kiruna). Tydligt fel bara
  i enstaka fall, t.ex. 510987 (Gotland → "stockholm" i texten) och
  bedrägerivarningar som räknar upp många orter.
- **Träffkvalitet:** 2 745 träffar på 1 559 unika namn. De vanligaste är
  riktiga orter och stadsdelar (umeå 40, skellefteå 27, södermalm 21 …).
  Enda tydliga skräpet bland topp 60: **"högsta" (21)**, från "högsta
  hastighet".
- **Polisens egen koordinat** (`polisen_gps_*`) duger inte som referens:
  `polisen_location_name` är alltid länet, så punkten är länsnivå.
- **Google-geokodning:** ~2 200 anrop/månad (ett per ny händelse inkl.
  icke-publika, plus omgeokodning när Polisen uppdaterar texten), under Googles 10 000 gratis per månad för Geocoding (Essentials,
  sedan mars 2025; därefter $5/1 000). Kostnad i praktiken $0.

## Granskning 2026-10-07 (oberoende subagent)

Bekräftar att ombyggnaden inte är värd det, men rättar underlaget:

- **Bättre mått — län-test:** geokodad punkt mot rätt läns polygon
  (`resources/geo/lansgranser.geojson`, #78). **11 av 1 761 (0,6 %)** hamnar
  utanför sitt län; 7 är bedrägerivarningar/sammanfattningar som räknar upp
  många orter, 2 troligen rätt nära länsgräns, 510987 (Gotland → Stockholm)
  äkta fel.
- **Stickprov 30 enskilda händelser:** 29 rätt, 1 fel — 510086 ("Försvunnen
  kvinna, Trelleborg") hamnade i Malmö (~27 km) eftersom "malmö" och "skåne"
  i texten skickades med till Google. Under 30 km, så medianmetoden missar
  det.
- 75 % av punkterna är `APPROXIMATE` (ortsnivå) — precisionen begränsas av
  geokodningen, inte av stopplistan.
- **NVDB innehåller bara vägar.** #106 mätte att en lista med bara vägar tappar
  72 % av träffarna (3 955 → 1 371), eftersom orter och stadsdelar försvinner.
  Att ersätta OSM-listan med NVDB vore alltså en försämring; kommunmatchning
  skulle dessutom kräva en ortnamnskälla med kommunkoppling.
- **Kommun per händelse:** `parsed_title_location` är kommun i 72 %, län i
  28 %. `administrative_area_level_2` är tom i 99 %, `polisen_location_name`
  alltid län. Kommunmatchning ger alltså nästan inget för var fjärde händelse.
- Uppskattad insats för ombyggnaden: 3–6 dagar plus en ny datapipeline.
  Besparing: 0 kr.

## Förslag

1. **Gjort 2026-10-07:** `högsta` tillagd i `highways_ignored` på prod (id
   1698, backup `backups/prod-2026-10-07-171625.sql.gz` före).
2. **Bygg inte om till NVDB** i nuvarande form.
3. **Billiga fixar som ger det mesta av vinsten:** se
   [#109](109-geokodning-billiga-fixar.md) (~0,5 dag).
4. Om kommunmatchning någon gång blir aktuellt: kräver ortnamn med
   kommunkoppling (Lantmäteriet ortnamn eller OSM place-noder +
   kommunpolygoner), inte bara NVDB. Mät med `tmp-106/sim.py` och län-testet.

## Risker

Ombyggnaden byter ut en matchning som fungerar: tappade ort-/stadsdelsträffar
(största risken), gator nära kommungräns, titelkommun som är fel eller för grov
(bedrägerivarningar, E-vägar "mellan X och Y"), och egen koordinat i stället
för Google tappar Googles förmåga att lösa korsningar och POI:er.

## Confidence

medel — län-testet och stickprovet är bra signaler men litet urval för fel
inom samma ort; första versionen av den här todon angav "hög", vilket var för
högt.
