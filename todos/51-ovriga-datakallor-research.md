**Status:** aktiv (research-skiss — väntar på prioritering efter #50)
**Senast uppdaterad:** 2026-05-06
**Relaterad till:** #50 (Trafikverket live), #38/#39/#40 (statistik per kommun)

# Todo #51 — Övriga datakällor: research-skiss

## Sammanfattning

Samlingstodo för datakällor som potentiellt kan visualiseras på
Brottsplatskartan, **utöver** Trafikverket Trafikinformation (#50) som
behandlas separat. Var och en har egen confidence + best-fit. Bryts ut
till egna todos när någon prioriteras för implementation.

Research-fas klar 2026-04-29. Implementation kräver per-källa skissfas.

## Källor

### A) SMHI Konsekvensbaserade vädervarningar (IBW)

- **Endpoint:** `https://opendata-download-warnings.smhi.se/ibww/api/version/1.json`
- **Vad:** Storm, snö, åska, gräsbrand, översvämning, halka, höga
  flöden — inkl. konsekvensbeskrivning
- **Geografi:** Polygoner i WGS84 (varningsområden, inte punkt)
- **Format:** REST/JSON + CAP XML
- **Licens:** Öppen, ingen API-nyckel
- **Confidence:** **Hög**
- **Best fit:** **Polygon-overlay/varningsbanderoll på kartan.**
  Kompletterar VMA — SMHI är aktivt även när VMA är "tyst".
- **Implementation-skiss:** Egen Leaflet GeoJSON-layer, schemalagd
  fetch var 15:e min, cache 5 min. Color-coda på severity (gul/orange/röd).

### B) Räddningstjänsters regionala RSS/HTML-larmlistor

- **Källor:**
    - Räddningstjänsten Östra Götaland: `http://rtog.se/rss/larmetgar.xml` (RSS)
    - Räddningstjänsten Storgöteborg: `rsgbg.se/Larm/larmlista` (HTML-scrape)
    - Räddsamf Småland-Blekinge, Bergslagen, RSNV, Södertörn — varierande
- **Vad:** Brand i byggnad, automatlarm, drunkningstillbud, sjukvårdslarm,
  utsläpp farligt ämne — sånt Polisen aldrig rapporterar
- **Geografi:** Kommun + plats-text (sällan färdig-koordinater) → kräver
  geokodning
- **Format:** RSS (vissa), HTML-scraping (de flesta). Inget enhetligt API
- **Licens:** Öppen för publicerade larm
- **Confidence:** Hög per källa, **medel** på täckning (ca 10–20 av
  Sveriges ~150 räddningstjänster har öppet feed)
- **Best fit:** **Mest direktlikt det Brottsplatskartan redan gör.**
  Stort kunskapsgap-fyllande. Kräver per-källa-adapter.
- **Implementation-skiss:** Adapter-pattern à la `crimeevents:fetch` —
  en parser per räddningstjänst. Geokoda via Nominatim/Polisens-mönstret.
  Egen kategori "Räddningstjänst-larm" i UI.
- **Risk:** Underhållsbörda — varje sida som ändrar HTML kräver fix

### C) Krisinformation.se API v2

- **Endpoint:** `https://api.krisinformation.se/v2/feed` + `/v2/news`
- **Vad:** VMA + redaktionella krisartiklar (skolhot, militärövningar,
  samhällskriser). Bredare än bara VMAAlert.
- **Geografi:** `Area`-objekt (Country/County/Municipality).
  `GeometryInformation` finns i schemat men är ofta `null` i praktiken
- **Format:** REST/JSON + Atom XML, CAP v1.2
- **Licens:** Öppen, ingen nyckel
- **Confidence:** Hög på API, **medel** på geografi-användbarhet
- **Best fit:** Kan komplettera VMAAlert-modellen. Visning på län-nivå
  (samma mönster som BRÅ).
- **Risk:** Polygoner saknas oftast → reduceras till "lista per län"

### E) SSM Strålsäkerhetsmyndigheten — gammastationer

- **UI:** `https://karttjanst.ssm.se/gammastationer`
- **Vad:** Bakgrundsstrålning µSv/h från 28 fasta stationer
- **Geografi:** Punkt (28 stationer)
- **Format:** Karttjänst — bakomliggande JSON/WMS måste
  reverse-engineeras eller mejla SSM
- **Realtid:** Ja (var 60:e minut)
- **Confidence:** **Låg på API**, hög på data
- **Best fit:** Kuriosa-overlay ("Allt är normalt"). Stort värde _vid_
  allvarlig händelse (Tjernobyl-typ).
- **Nästa steg om prio:** Mejla SSM om dokumenterad endpoint

### F) Naturvårdsverket — industriutsläpp & luftkvalitet

- **Endpoints:** `https://geodata.naturvardsverket.se/naturvardsregistret/rest/v3/` och `oppnadata.naturvardsverket.se`
- **Vad:** Industriutsläpp per anläggning, luftkvalitetsmätningar
- **Geografi:** Punkt (anläggningar) + per kommun (statistik)
- **Realtid:** Mest årsstatistik. Luftkvalitet kan vara timvärden
- **Format:** REST + WMS/WFS GeoJSON
- **Licens:** Öppen (CC0/PSI)
- **Confidence:** Hög
- **Best fit:** **BRÅ-mönstret** — "största utsläppare i din kommun".
  Inte live-events.

### G) Folkhälsomyndigheten — anmälningspliktiga sjukdomar

- **Källa:** SmiNet + Folkhälsodata
- **Vad:** Klamydia, salmonella, TBE m.fl. — fall per kommun/vecka
- **Geografi:** Kommun (vecka)
- **Format:** Statistikdatabas-export, **ej rent REST-API**
- **Licens:** Öppen
- **Confidence:** Medel (klumpig hämtning)
- **Best fit:** BRÅ-mönstret, kuriosa-värde

### H) Stockholms stad — OGC API Features

- **URL:** dataportalen.stockholm.se
- **Vad:** Trafikincidenter (oklar realtidsstatus)
- **Confidence:** Låg — kräver utforskning
- **Best fit:** Sekundärt — endast om Trafikverket inte täcker innerstad
  bra nog

## Skippade källor (inte värt research nu)

- **Sveriges Radio trafikmeddelanden-API** (`api.sr.se/api/v2/traffic/messages`)
  — för stor överlapp med Trafikverket (#50, live sedan 2026-05-03); SR:s
  API är dessutom officiellt unmaintained. Avfärdad 2026-05-06.
- **SOS Alarm händelseinformation** — kommersiellt avtal krävs
- **Sjöfartsverket RAIS** — avgiftsbelagt (PSI-undantag)
- **Statens haverikommission** — bara historiska utredningar, månader sent
- **Elsäkerhetsverket** — bara aggregerad årsstatistik, ingen kommun-grad
- **Arbetsmiljöverket** — ingen geografisk granularitet under län publikt
- **VISS (Länsstyrelsen)** — vattenstatus, inte incidents
- **SVA djursjukdomar** — inget öppet API publicerat

## Confidence

**Medel.** Källorna finns och är öppna, men implementation-effort varierar
från "1 kvällsprojekt" (SMHI, A) till "långsiktig adapter-portfölj"
(räddningstjänster, B). Prioritering ska göras per källa.

## Förslag på prioritering (från research)

1. **A) SMHI** — låg risk, hög synlighet, minimal kod (snabbaste vinsten)
2. **B) Räddningstjänster RSS** — högst kunskapsgap-fyllande men störst
   underhållsbörda. Börja med 1–2 RSS-källor (RTÖG först)
3. **F) Naturvårdsverket** — om vi vill bygga ut "statistik per kommun"-
   mönstret efter BRÅ/MSB/STRADA
4. **C) Krisinformation.se** — låg ansträngning, marginellt värde över
   befintlig VMAAlert
5. **G) Folkhälsomyndigheten** — kuriosa, låg prio
6. **E) SSM gammastationer** — coolhets-faktor, kräver outreach
7. **H) Stockholms stad OGC API** — sannolikt skippa, oklar realtidsstatus

## Beroenden mot andra todos

- **#50 Trafikverket** — separat todo, hög prio. När den är klar är
  Leaflet-layer-konceptet återanvändbart för A) och B)
- **#38 BRÅ klar** — etablerar "statistik per kommun"-mönstret för F/G

## Nästa steg

När någon källa prioriteras för implementation: bryt ut till egen todo
med detaljerad schema/import/UI-skiss (använd #50 som mall).

## Inte i scope

- Implementation av enskilda källor — den här todon är bara en
  research-katalog
- Sekretessbelagda källor (SOS Alarm, RAIS) — alltid avfärdas
- Källor utan geografi alls (rikstotaler, riksdagsstatistik m.m.)
