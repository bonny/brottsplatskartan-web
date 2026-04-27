**Status:** aktiv (skissad — research-fas saknas)
**Senast uppdaterad:** 2026-04-27
**Relaterad till:** #38 (BRÅ-mönster), #27 (rikare innehåll)

# Todo #39 — MSB brand- och räddningsstatistik per kommun

## Sammanfattning

Brottsplatskartan visar redan "brand"-events från Polisens RSS-flöde,
men det är ofullständigt — Polisen publicerar bara ett urval. MSB
(Myndigheten för samhällsskydd och beredskap) har **officiell** statistik
över alla räddningstjänstens insatser per kommun, inklusive bränder,
trafikolyckor (räddningstjänst-perspektiv), drunkningstillbud m.m.

Direkt parallell till #38 (BRÅ-mönstret) — komplettera vår ofullständiga
händelsedata med officiell statistik.

## Bakgrund

Polisens RSS för "brand" innehåller t.ex.:
- Större bränder med blåljus-aktivitet
- Bränder med polisutredning (anlagd brand, brott)
- Inte: vanliga lägenhetsbränder, småbränder, automatlarm

MSB:s IDA-statistik (Insatsdataarkivet) täcker **alla** räddningstjänst-
insatser. Skulle ge ärligare bild för månadsvyer + ortssidor.

## Datakälla (att verifiera)

- **Översikt:** https://www.msb.se/sv/statistik/
- **IDA-statistik:** https://ida.msb.se/
- **Format:** sannolikt CSV/Excel-export från IDA-portalen, möjligen API
- **Granularitet:** kommun-nivå förväntas finnas (samma som BRÅ)
- **Licens:** att verifiera

Research-fas (~halvdag) krävs innan implementation.

## Förslag (preliminärt)

### Schema

```sql
CREATE TABLE msb_raddningsinsatser (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  kommun_kod VARCHAR(4) NOT NULL,
  ar SMALLINT NOT NULL,
  insats_typ VARCHAR(64) NOT NULL,        -- 'brand i byggnad', 'trafikolycka', 'drunkning', m.fl.
  antal INT UNSIGNED NOT NULL,
  source_url VARCHAR(500) NULL,
  imported_at TIMESTAMP NULL,
  UNIQUE KEY idx_unique (kommun_kod, ar, insats_typ),
  KEY idx_typ_ar (insats_typ, ar)
);
```

### Helper

```php
\App\MsbStatistik::forKommun('0380', 'brand i byggnad')
\App\MsbStatistik::topKommuner('drunkning', 10, $year)
```

Cachas i Redis 7d (uppdateras 1×/år).

### Import

```bash
docker compose exec app php artisan msb:import-raddningsinsatser --year=2024
```

Joinas mot `scb_kommuner` på namn (samma mönster som #38).

## Risker

- **Format okänt.** IDA-portalen kan kräva manuell export, scraping eller
  finns API. Verifiera i research-fasen.
- **Insats-taxonomi.** MSB:s indelning kan skilja sig från Polisens
  ("brand", "trafikolycka"). Mappnings-jobb kan behövas.
- **Begreppskrock.** "Räddningsinsats" är inte samma som "brott". Tydlig
  separation i UI: "Officiell statistik från MSB" inte blandat med
  "händelser från Polisen".

## Confidence

**Låg-medel.** Datan finns säkert (MSB är öppen myndighet) men formatet
är inte verifierat. Research-fas avgör om implementation tar 3h eller 3d.

## Beroenden mot andra todos

- **#37 (SCB-kommuner)** — krävs för kommunkod-mappning. Klar.
- **#38 (BRÅ)** — samma mönster, kan återanvända arkitekturmönster.
- **#27 Lager 2** — använd MSB-data för "officiell brand-/olycksstatistik"-
  visualisering parallellt med BRÅ-data.

## Inte i scope

- **Real-time räddningsinsatser** — MSB-data är retrospektiv (årsstatistik).
  Vår "händelser just nu" fortsätter med Polisen-data.
- **Olycksdetaljer per insats.** Bara aggregerad statistik per kommun.
