**Status:** aktiv (skissad — research-fas saknas)
**Senast uppdaterad:** 2026-04-27
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
