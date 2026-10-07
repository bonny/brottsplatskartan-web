**Status:** aktiv — mätt 2026-10-07: nuvarande matchning fungerar bra, låg nytta att bygga om. Väntar på beslut
**Senast uppdaterad:** 2026-10-07
**Källa:** Pär, 2026-10-07 (uppföljning av #106)

# Todo #108 — Bättre gatunamnsdata och smartare platsmatchning

## Sammanfattning

Efter #106 (gatunamn från OSM) frågade Pär om det finns ett bättre, smartare
och mer korrekt sätt att få datan. Utredningen hittade bättre källor och ett
smartare matchningsgrepp, men **mätningen på prod visar att dagens matchning
redan fungerar bra** och att geokodningen kostar ~0 kr. Rekommendation:
gör bara småfixar, bygg inte om nu.

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

- 1 761 publika händelser, alla geokodade. 37 % fick minst en gatuträff.
- **Felplacering:** jämfört med medianen för händelser med samma ort i
  titeln hamnar 2,6 % mer än 30 km bort och 0,6 % mer än 60 km bort. Nästan
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
- **Google-geokodning:** ~1 800 händelser/månad × 1–2 anrop ≈ 2 000–4 000
  anrop, under Googles 10 000 gratis per månad för Geocoding (Essentials,
  sedan mars 2025; därefter $5/1 000). Kostnad i praktiken $0.

## Förslag

1. **Småfix nu:** lägg till `högsta` i `highways_ignored` (prod-DB, kräver
   OK). Eventuellt fler vanliga ord efter en titt på topp 200.
2. **Parkera ombyggnaden** (NVDB + kommunmatchning). Bygg först om
   felplaceringar blir ett märkbart problem eller Google-anropen passerar
   10 000/månad.
3. Om/när det blir aktuellt: NVDB från Lastkajen, matchning per kommun,
   mät med samma simulering som i #106 (`tmp-106/sim.py`).

## Risker

Ombyggnaden byter ut en matchning som fungerar; risk för nya fel (t.ex.
gator nära kommungräns, händelser där Polisen anger fel kommun).

## Confidence

hög för mätningen av felplacering (stort urval, tydlig signal); medel för
att den fångar fel inom samma ort (<15 km), som inte syns i den här metoden.
