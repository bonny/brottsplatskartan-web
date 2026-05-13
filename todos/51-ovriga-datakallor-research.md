**Status:** aktiv (research-katalog, live-testad 2026-05-13)
**Senast uppdaterad:** 2026-05-13
**Relaterad till:** #50 (Trafikverket live), #69 (SMHI utbruten), #38/#39/#40 (statistik per kommun)

# Todo #51 — Övriga datakällor: research-skiss

## Sammanfattning

Samlingstodo för datakällor som potentiellt kan visualiseras på
Brottsplatskartan, **utöver** Trafikverket Trafikinformation (#50) som
behandlas separat. Var och en har egen confidence + best-fit. Bryts ut
till egna todos när någon prioriteras för implementation.

Research-fas klar 2026-04-29. Live-tester av alla endpoints körda
2026-05-13 — se sektionen **Live-tester** nedan för verifierade resultat.

## Live-tester 2026-05-13

Alla endpoints och RSS-feeds curl-testade lokalt. Sammanfattning:

| ID  | Källa                          | HTTP         | Verifierat resultat                                                       | Bedömning                         |
| --- | ------------------------------ | ------------ | ------------------------------------------------------------------------- | --------------------------------- |
| A   | SMHI IBW                       | 200          | 6 aktiva varningar i `warning.json` (sv+en+polygoner+severity)            | ✅ Bekräftad. Utbruten till #69   |
| B   | RTÖG RSS (`larmetgar.rtog.se`) | 200          | 7 dagsfärska larm 2026-05-12 (titel + plats + pubDate)                    | ✅ Pilot-klar idag                |
| B   | RSGBG Storgöteborg             | 200          | HTML med `class="alarmlist-item"` (scrapable), inget JSON-API             | 🟡 Scrape-arbete krävs            |
| B   | 12 andra RT-feeds              | mest 404/000 | Inga öppna feeds på de URL:er som tidigare research nämnde                | ❌ Täckning ~5-10 % av landet     |
| C   | Krisinformation **v3**         | 200          | `/v3/news?days=365` → 19 items totalt. **v2 är borta (404)**              | ❌ Glest + överlapp med VMAAlert  |
| E   | SSM gammastationer             | 200          | Pure SPA, ingen publik API i sidkod. Ingen ArcGIS-endpoint                | ❌ Kräver outreach till SSM       |
| F   | NV Naturvårdsregistret         | 200          | OpenAPI = skyddsområden (naturreservat) — **fel API för "utsläpp"**       | ❌ Inte vad vi vill               |
| F   | NV Utsläpp i siffror           | 200          | 1336 anläggningar via `/api/combinedsearch/anlaggning?dataformat=compact` | 🟡 Funkar men kuriosa-värde       |
| G   | Folkhälsomyndigheten           | -            | Bara HTML-sidor, **inget rent REST-API** hittat i quick test              | ❌ Statistikdatabas-export krävs  |
| H   | Stockholm Stad OGC             | 401          | Kräver API-nyckel (`openstreetgs.stockholm.se`)                           | ❌ Extra friktion utan klar vinst |

### Krisinformation v3 — innehållsanalys (19 items / 365 dagar)

Alla items har `ContentTypeName=newspage` (typerna är ihopslagna, ingen separat "vma" eller "news"-kategori).

| Typ                          | Antal/år | Exempel                                             |
| ---------------------------- | -------- | --------------------------------------------------- |
| VMA (samma som vår VMAAlert) | ~8       | "VMA Malmö 2026-05-01", "VMA Hässleholm 2025-09-07" |
| UD reseavrådan               | ~5       | "UD avråder Kuba", "Svenskar i Iran lämna landet"   |
| Vädervarningar rikstäckande  | ~2       | "Röd varning snöfall och vind"                      |
| Säkerhetsläge / terror       | ~2       | "Sänkning av terrorhotnivån"                        |
| Militärövning                | 1        | "Aurora 26 påverkar trafiken"                       |
| GPS-störningar               | 1        | "Varning för GPS-störningar Östersjön"              |

**Geografi:** `Area`-fältet är **tomt eller "Sverige"** i majoriteten — ingen polygon eller kommun-precision. Måste parse:as ur titeln.

**Slutsats C:** ~50 % VMA (vi har via VMAAlert), ~30 % utrikessäkerhet (fel scope för krimkarta), ~20 % övrigt (~4 items/år, glest). Geo-fältet otillförlitligt. SMHI (A) täcker väder bättre. **Inte värd egen integration.**

## Källor

### A) SMHI Konsekvensbaserade vädervarningar (IBW)

- **Endpoint:** `https://opendata-download-warnings.smhi.se/ibww/api/version/1/warning.json`
- **Vad:** Storm, snö, åska, gräsbrand, översvämning, halka, höga
  flöden — inkl. konsekvensbeskrivning
- **Geografi:** Polygoner i WGS84 (varningsområden, inte punkt)
- **Format:** REST/JSON + CAP XML
- **Licens:** Öppen, ingen API-nyckel
- **Confidence:** **Hög** — verifierad live 2026-05-13 (6 aktiva varningar)
- **Best fit:** **Polygon-overlay/varningsbanderoll på kartan.**
  Kompletterar VMA — SMHI är aktivt även när VMA är "tyst".
- **Implementation-skiss:** Egen Leaflet GeoJSON-layer, schemalagd
  fetch var 15:e min, cache 5 min. Color-coda på severity (gul/orange/röd).
- **Status:** Utbruten till **#69** (scope A — ren layer)

### B) Räddningstjänsters regionala RSS/HTML-larmlistor

- **Verifierade källor (2026-05-13):**
    - ✅ **RTÖG** — `https://larmetgar.rtog.se/rss/larmetgar.xml` (RSS, dagsfärsk; 7 items 2026-05-12)
    - 🟡 **RSGBG** — `https://rsgbg.se/Larm/larmlista` (HTML med `class="alarmlist-item"`, scrapable men inget JSON-API)
- **Avfärdade (404/000 i live-test):**
    - rtsod.se, brandkaren-attunda.se, helsingborg.se RSS, rsnv.se, raddsamf.se,
      raddningstjanstvast.se, brandskydd.se, sodertorn.brand.se, sodertornbrandforsvar.se,
      nerikesbrandkar.se, gastrike-raddningstjanst.se, rtmd.se, raddningstjansten.com,
      brandforsvar.uppsala.se, varberg.se, dalamitt.se, kkrt.se, medelpadbf.se
- **Vad:** Brand i byggnad, automatlarm, drunkningstillbud, sjukvårdslarm,
  utsläpp farligt ämne — sånt Polisen aldrig rapporterar
- **Geografi:** Kommun + plats-text (sällan färdig-koordinater) → kräver
  geokodning
- **Format:** RSS (RTÖG), HTML-scraping (RSGBG). Inget enhetligt API
- **Licens:** Öppen för publicerade larm
- **Confidence:** **Hög för RTÖG**, **låg på täckning** — endast 2 av 14
  testade räddningstjänster har öppet feed. Sveriges ~150 räddningstjänster
  täcks alltså <2 %.
- **Best fit:** **Mest direktlikt det Brottsplatskartan redan gör.**
  Per-källa-adapter. Börja smalt med RTÖG som pilot.
- **Implementation-skiss:** Adapter-pattern à la `crimeevents:fetch` —
  en parser per räddningstjänst. Geokoda via Nominatim/Polisens-mönstret.
  Egen kategori "Räddningstjänst-larm" i UI.
- **Risk:** Underhållsbörda per källa. Räcker tre källor för meningsfull
  täckning? Sannolikt nej — Polisens nationella feed slår alltid en
  hand-curated lista på täckning.

### C) Krisinformation.se API v3 — **avfärdad efter live-test 2026-05-13**

- **Endpoint:** `https://api.krisinformation.se/v3/news?days=N` (v2 är borta — 404)
- **Vad:** ContentType "newspage" (alla typer ihopslagna). 19 items totalt
  senaste 365 dagarna — ~50 % VMA (vi har redan via VMAAlert),
  ~30 % UD reseavrådan (fel scope), ~20 % övrigt (väder/militär/GPS, ~4/år).
- **Geografi:** `Area`-fältet tomt eller "Sverige" i majoriteten →
  ingen kommun/polygon-precision.
- **Format:** REST/JSON
- **Licens:** Öppen, ingen nyckel
- **Confidence:** API hög, **innehållsvärde lågt**
- **Avfärdad:** Glest flöde (~19/år), ~hälften är VMA (vi har det redan),
  geografi otillförlitlig. SMHI (A) täcker väderdelen bättre. Ingen
  meningsfull tilläggsdata över VMAAlert + SMHI.
- **Fallback-fall:** Om VMAAlert-leveransen någon gång slutar fungera
  kan v3/news fungera som passiv backup-källa för VMA-täckning.

### E) SSM Strålsäkerhetsmyndigheten — gammastationer

- **UI:** `https://karttjanst.ssm.se/gammastationer`
- **Vad:** Bakgrundsstrålning µSv/h från 28 fasta stationer
- **Geografi:** Punkt (28 stationer)
- **Format:** Pure SPA (verifierat 2026-05-13). Sidkod laddar
  `/bundle/main.js` utan synlig endpoint. `data.ssm.se` finns inte.
  Ingen ArcGIS REST på `karttjanst.ssm.se`.
- **Realtid:** Ja (var 60:e minut) — enligt UI
- **Confidence:** **Låg på API**, hög på data
- **Best fit:** Kuriosa-overlay ("Allt är normalt"). Stort värde _vid_
  allvarlig händelse (Tjernobyl-typ).
- **Nästa steg om prio:** Mejla SSM (`registrator@ssm.se`) om dokumenterad endpoint

### F) Naturvårdsverket — industriutsläpp & luftkvalitet

- **Korrekt endpoint (verifierat 2026-05-13):**
  `https://utslappisiffror.naturvardsverket.se/api/combinedsearch/anlaggning?dataformat=compact`
  → 1336 anläggningar (id + name). Per-anläggning detaljer + geo måste
  hämtas i separat anrop.
- **OBS:** `naturvardsregistret/rest/v3/` är **fel API** — det är
  skyddsområden (naturreservat), inte utsläpp. Filens tidigare länk var fel.
- **Vad:** Industriutsläpp per anläggning, luftkvalitetsmätningar
- **Geografi:** Punkt (anläggningar) + per kommun (statistik)
- **Realtid:** Mest årsstatistik. Luftkvalitet kan vara timvärden
- **Format:** REST/JSON
- **Licens:** Öppen (CC0/PSI)
- **Confidence:** Hög på data, **låg på Brottsplatskartan-relevans**
- **Best fit:** **BRÅ-mönstret** — "största utsläppare i din kommun".
  Inte live-events. Kuriosa-värde.

### G) Folkhälsomyndigheten — anmälningspliktiga sjukdomar

- **Källa:** SmiNet + Folkhälsodata
- **Vad:** Klamydia, salmonella, TBE m.fl. — fall per kommun/vecka
- **Geografi:** Kommun (vecka)
- **Format:** Statistikdatabas-export, **ej rent REST-API** (verifierat
  2026-05-13 — inga publika REST-endpoints hittade)
- **Licens:** Öppen
- **Confidence:** Medel (klumpig hämtning)
- **Best fit:** BRÅ-mönstret, kuriosa-värde

### H) Stockholms stad — OGC API Features

- **URL:** `openstreetgs.stockholm.se/geoservice/api/<key>/ogc/features/collections`
- **Vad:** Trafikincidenter (oklar realtidsstatus)
- **Format:** OGC API Features — **kräver API-nyckel** (verifierat
  2026-05-13: 401 utan nyckel: "You must provide a valid key").
  Anonym katalog finns inte.
- **Confidence:** Låg — kräver utforskning + nyckel-ansökan
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

## Förslag på prioritering (reviderad 2026-05-13 efter live-test)

1. **A) SMHI** — ✅ utbruten till **#69**, väntar på implementation
2. **B-RTÖG pilot** — ✅ verifierad RSS, en parser, smal start.
   Hoppa över ambitionen "alla räddningstjänster" — täckning ~2 % av landet.
3. **F) NV utsläpp** — funkar men kuriosa, inte live-events.
   Endast om "statistik per kommun"-mönstret ska byggas ut bredare.
4. **G) Folkhälsomyndigheten** — kuriosa, kräver Statistikdatabas-export
5. **E) SSM gammastationer** — kräver outreach till SSM
6. **H) Stockholms stad OGC API** — kräver API-nyckel
7. ~~C) Krisinformation~~ — **avfärdad** efter live-test (~50 % VMA-overlap,
   ~30 % UD reseavrådan, ~20 % övrigt glest, geo otillförlitlig)

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
