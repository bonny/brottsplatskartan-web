**Status:** avfärdad 2026-04-29 — kommunnivå ej fritt tillgänglig (STRADA Uttagswebb kräver myndighetsavtal); öppen Trafa-statistik bara på län-nivå, vilket inte matchar ortssidornas kommun-modell
**Senast uppdaterad:** 2026-04-29
**Relaterad till:** #38 (BRÅ-mönster), #39 (MSB-mönster), #27 (rikare innehåll)

# Todo #40 — Trafikverket STRADA olycksstatistik per kommun

## Sammanfattning

Brottsplatskartan visar trafikolyckor från Polisens RSS — som med övriga
händelsetyper är detta ett urval, inte heltäckande statistik. Trafikverket/
Transportstyrelsen har **STRADA** (Swedish Traffic Accident Data Acquisition),
det officiella systemet för all rapporterad olycksdata på väg och järnväg.
STRADA samlar data från både polis och sjukvård.

Direkt parallell till #38 (BRÅ-mönstret) och #39 (MSB-mönstret).

## Bakgrund

Polisens RSS för "trafikolycka" innehåller bara:

- Olyckor med polis-/blåljusinsats
- Olyckor med dödlig utgång
- Större trafikstörningar

STRADA täcker:

- **Alla** polis-rapporterade olyckor (inkl. utan blåljus)
- **Alla** sjukvårds-rapporterade olyckor (akutmottagningar)
- Skadegrad, fordonstyp, väglag, plats, tid

Det är den officiella källan för svensk olycksstatistik.

## Datakälla (att verifiera)

- **Trafikverket öppna data:** https://www.trafikverket.se/tjanster/oppna-data/
- **STRADA-portalen:** https://www.transportstyrelsen.se/sv/vagtrafik/statistik/
- **API:** Trafikverket har dataportal med vissa REST-endpoints (vägstatistik,
  vägarbete, hastighet) — oklart om STRADA är öppet via API
- **Möjligt hinder:** STRADA-rådata kan kräva ansökan/avtal (innehåller
  personuppgifter på olycksrapport-nivå). **Aggregerad statistik per
  kommun** bör dock vara fritt tillgänglig.

Research-fas (~halvdag) krävs:

- Är aggregerad kommun-statistik fri åtkomst, eller bara genom Trafikverkets
  webbgränssnitt?
- Finns CSV/JSON-exporter, eller bara PDF-rapporter?
- Licens?

## Research (2026-04-29)

### Producent och datakällor

Officiell statistik över vägtrafikskador i Sverige produceras av
**Trafikanalys (Trafa)** — inte Trafikverket eller Transportstyrelsen,
även om STRADA-databasen tekniskt förvaltas av Transportstyrelsen.
Rådatan i STRADA matas in av polis och akutsjukvård.

Tre potentiella åtkomstvägar testade:

| Källa                                  | Granularitet             | Åtkomst           | Format         |
| -------------------------------------- | ------------------------ | ----------------- | -------------- |
| Trafa (officiell statistik)            | **Län** + storstad-varav | Fri               | xlsx, PDF      |
| Transportstyrelsen STRADA-uttagswebb   | Detaljerad (event-nivå)  | Avtal krävs       | Webgränssnitt  |
| Transportstyrelsen "Beställ statistik" | Ad-hoc kommun går trolig | Formulär/manuellt | Excel via mejl |
| SCB statistikdatabasen                 | (speglar Trafa)          | Fri               | PxWeb / CSV    |

### 1. Trafa — fri xlsx, men bara län-nivå

- **Sida:** https://www.trafa.se/vagtrafik/vagtrafikskador/
- **Senaste:** Vägtrafikskador 2025 (publicerad 2026, men 2024-rapporten räcker för MVP)
- **Direkta xlsx-URL:er** (verifierade hämtningsbara, 1.2 MB):
    - 2024: `https://www.trafa.se/globalassets/statistik/vagtrafik/vagtrafikskador/2024/vagtrafikskador-2024.xlsx`
    - 2025: `https://www.trafa.se/globalassets/statistik/vagtrafik/vagtrafikskador/2025/vagtrafikskador-2025.xlsx`
- **Innehåll:** ~28 tabellflikar (1.1–7.2). Granularitet i geografi:
    - **21 län** (Stockholms län, Uppsala län, …)
    - **3 storstadskommuner som "varav"-rader:** Stockholms kommun, Göteborgs kommun, Malmö kommun
    - **Inga andra kommuner** finns separat redovisade.
- **Skadegrad:** Dödade, svårt skadade, lindrigt skadade — separata kolumner/flikar.
- **Trafikantkategori:** finns (bilist, MC, cyklist, fotgängare etc.) — i tabell 3.x.
- **Tidsupplösning:** årsdata. Månad finns nationellt men inte regionalt.
- **Historikdjup:** vissa tabeller går till 1985 (t.ex. tabell 6.4: dödade per 100 000 inv. per län åren 1985–2024).
- **Licens:** Sveriges officiella statistik (SOS) — fri användning enligt SOS-förordningen, ange "Källa: Trafikanalys". Inte CC0 men praktiskt fri.
- **Kommunkoder:** **saknas i datan** (samma som BRÅ #38). Bara namn på län/storstadskommun. Joinas mot `scb_kommuner` på namn.

### 2. STRADA Uttagswebb — kräver avtal

- **Sida:** https://www.transportstyrelsen.se/sv/om-oss/statistik-och-analys/statistik-inom-vagtrafik/olycksstatistik/om-strada/anvandarstod1/strada-uttagswebb/
- **Åtkomst:** beviljas till polis, kommuner, regioner, Trafikverket, forskare
  med formellt uppdrag. Kontakt via regional Strada-koordinator eller
  `stradauttag@transportstyrelsen.se`.
- **För Brottsplatskartan (privat sajt, ingen myndighetsroll):** sannolikt
  ingen tillgång. Även om beviljat skulle data inte vara fritt redistribuerbar
  pga sekretessen för rådata med sjukvårdsuppgifter.
- **Slutsats:** ej användbar för automatiserad import.

### 3. "Beställ statistik" — manuell engångsförfrågan

- **Sida:** https://www.transportstyrelsen.se/sv/om-oss/statistik-och-analys/statistik-inom-vagtrafik/olycksstatistik/bestall-statistik/
- Beställning via formulär: tidsperiod, geografi, trafikantgrupp, skadegrad.
  Aggregerad statistik lämnas ut "i de flesta fall" eftersom
  identifikation av enskild ska undvikas.
- Inga avgifter eller leveranstider angivna på sidan. **Sannolikt fungerande
  för engångsuttag av kommun-tabell**, men:
    - Manuellt jobb per uppdatering (inte automatiserbart)
    - Inga garantier för formatstabilitet eller årlig återförsäljning
    - Oklar redistributionsrätt
- **Slutsats:** olämplig som datapipeline. Kan användas för en engångs-
  baseline om man vill, men då skulle datan bli statisk och föråldras.

### 4. SCB statistikdatabasen — speglar Trafa

- SCB:s sida om vägtrafikskador (https://www.scb.se/.../vagtrafikskador/)
  pekar på Trafa som producerande myndighet. PxWeb-tabeller under
  `START/TK/` är i praktiken samma data som Trafa-xlsx — alltså län-nivå.
- Ingen ny information.

### Sammanfattning av kritisk fråga

> _"Är aggregerad kommun-statistik fri eller bakom inloggning/ansökan?"_

**Svar:** För övriga 287 kommuner (alla utom Stockholm, Göteborg, Malmö)
finns ingen färdigaggregerad öppen kommunstatistik. Den måste antingen
beställas manuellt per gång eller plockas ur STRADA Uttagswebb (kräver
avtal). Nuvarande ambition i todon — automatiserad årlig import per
kommun, samma mönster som #38 — är **inte genomförbar med öppna kanaler**.

### Vad finns då fritt och automatiserbart?

- **21 län × årsdata × 3 skadegrader** + **Stockholm/Göteborg/Malmö** som varav.
- Mönstret #38 fungerar tekniskt — bara att schema:t blir `lan_kod` istället för
  `kommun_kod` (med specialfall för storstad).
- För ortssidor som ligger på kommun-nivå (t.ex. /uppsala) skulle vi kunna
  visa "Olyckor i Uppsala län" som approximation, men det är märkbart
  grövre än BRÅ-datan (#38) och kan kännas missvisande.

### Rekommendation

**Avfärda i nuvarande omfång** (kommun-nivå, automatiserad import).

**Skäl:**

- 287 av 290 kommuner kräver manuell beställning eller avtalsbaserad
  STRADA-åtkomst — bryter mot målbilden för automatiserad pipeline.
- Län-nivå finns fritt men matchar inte ortssidornas geografiska modell.
  Att visa "olyckor i Uppsala län" på `/uppsala` (som är en kommun-sida)
  blir svagare än brott från #38.
- Trafikolyckor täcks redan i UI via Polisens RSS som realtids-händelser.
  STRADA skulle bara komplettera med statistik — Lager 2-värde, inte
  Lager 1-värde — och tappar verkningsgrad utan kommun-nivå.

**Möjligt scope-skifte (separat övervägande):**

- Skapa `trafa_olyckor_lan` med Trafa:s xlsx som källa. ~3–4h jobb
  (ladda xlsx, plocka tabell 6.4 + 1.x, joina på `lan_kod`). Visar
  "officiell olycksstatistik per län" på länssidor (`/lan/{lan}`)
  istället för på ortssidor. Värde: ärligare bild på länsnivå än
  Polisen-RSS-urvalet, kompletterar #38. Bör i så fall vara en
  egen ny todo (#NN) med tydligt län-scope, inte en omtolkning av #40.

**Estimerad insats om #40 körs som-är (kommun-nivå):** Inte möjligt —
kräver myndighetsstatus eller manuellt arbete vi inte vill ha.

**Estimerad insats för läns-variant (om scope skiftas):** ~3–4h
(xlsx-parser, schema, helper, scheduler), samma mönster som #38.

## Förslag (preliminärt)

### Schema

```sql
CREATE TABLE strada_olyckor (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  kommun_kod VARCHAR(4) NOT NULL,
  ar SMALLINT NOT NULL,
  skadegrad ENUM('dödsolycka', 'svår', 'lindrig', 'egendom') NOT NULL,
  antal INT UNSIGNED NOT NULL,
  source_url VARCHAR(500) NULL,
  imported_at TIMESTAMP NULL,
  UNIQUE KEY idx_unique (kommun_kod, ar, skadegrad),
  KEY idx_skadegrad_ar (skadegrad, ar)
);
```

### Helper

```php
\App\StradaStatistik::forKommun('0380', 2024)         // alla skadegrader
\App\StradaStatistik::dodsolyckor($year)               // riksstatistik
\App\StradaStatistik::topKommuner('dödsolycka', 10)
```

### Import

```bash
docker compose exec app php artisan strada:import-olyckor --year=2024
```

Samma mönster som #38/#39 — joina på kommunnamn mot `scb_kommuner`.

## Risker

- **Åtkomstbegränsning.** STRADA-rådata har sekretess (sjukvårdsuppgifter).
  Aggregerad statistik bör vara fri men måste verifieras.
- **Format okänt.** Trafikverket har en bra dataportal men STRADA-aggregat
  kanske bara finns som PDF-rapporter — i så fall är CSV-extraction
  manuellt jobb varje år.
- **Begreppskrock med Polisen.** Polisens "trafikolycka" är inte samma
  som STRADA:s skadegrad-klassificering. Tydlig separation i UI.
- **Dubbelräkning.** Polisens RSS visar redan trafikolyckor live. STRADA-
  data ska komplettera, inte ersätta — visa som "Officiell olycksstatistik"-
  sektion separat.

## Confidence

**Låg-medel.** Trafikverket är generellt öppen med data, men STRADA är ett
specialfall pga sjukvårdskoppling. Research-fas avgör om implementation
tar 3h (om CSV finns) eller blir avfärdad (om bara PDF/portal).

## Beroenden mot andra todos

- **#37 (SCB-kommuner)** — krävs för kommunkod-mappning. Klar.
- **#38 (BRÅ)** + **#39 (MSB)** — samma arkitekturmönster.
- **#27 Lager 2** — visualisera "Officiell olycksstatistik från Trafikverket".

## Inte i scope

- **Real-time olyckor** — Trafikverket har "Trafikinformation"-API för det,
  men det är annan integration (kompletterar Polisens "trafikolycka"-events
  i händelseflödet, inte statistik). Eventuellt egen framtida todo.
- **Olycksplatser-detalj.** Bara aggregerad statistik per kommun.
- **Vägkvalitetsdata.** Inte i Brottsplatskartans scope.
