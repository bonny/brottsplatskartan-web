**Status:** aktiv (research-fas saknas)
**Senast uppdaterad:** 2026-04-27
**Relaterad till:** #37 (SCB-befolkning), #27 (rikare innehåll Lager 2)

# Todo #38 — Integrera BRÅ-data för riktig brottsstatistik

## Sammanfattning

Polisens händelseflöde (vår nuvarande datakälla) är **inte** heltäckande
brottsstatistik. Det är ett urval av händelser Polisens lokala redaktioner
väljer att publicera — täckningsgraden är låg (kanske <5 % av faktiskt
anmälda brott) och varierar kraftigt mellan regioner. Att räkna
`events / befolkning * 1000` på vår data ger missvisande siffror.

För **riktig brottsstatistik** krävs Brottsförebyggande rådets (BRÅ) data.
BRÅ har officiella anmälningssiffror per kommun + brottstyp som öppen data.

## Bakgrund

Upptäcktes 2026-04-27 i samband med #37-implementationen. Användaren
flaggade att Polisens RSS bara redovisar en bråkdel av faktiska brott
— att då räkna "brott per 1000 inv." på den datan vore desinformation.

`crimesPerThousand()`-helpern togs bort i samma stund som upptäckten
gjordes. SCB-befolkningsdatan (#37) är fortfarande korrekt och användbar
för befolkningsfakta — bara INTE för brott/1000-metriken.

## Mål

Integrera BRÅ:s öppna data så att Brottsplatskartan kan visa **ärlig**
brottsstatistik per kommun:

- **Brott per 1000 inv. per kommun** (CrimeGrade-modellen, validerad i #27)
- **Trend över år** (ökar/minskar)
- **Brottstyp-fördelning** (våld / stöld / inbrott / etc.)
- **Jämförelse mot rikssnitt + grannkommuner**

## Research-fas (steg 1, ~halvdag)

Inget av detta är verifierat ännu. Verifiera innan implementation:

### Kandidatkällor

1. **BRÅ statistikdatabas** — `https://statistik.bra.se/solwebb/action/start`
   - PX-Web-baserad? Har JSON-API?
   - Officiell anmäld brottsstatistik per kommun + år + brottstyp
2. **BRÅ öppna data** — `https://www.bra.se/statistik/oppna-data.html`
   - CSV-/Excel-export?
   - Licens — sannolikt CC0 men kolla
3. **SCB:s "Anmälda brott"** — möjligt att SCB också publicerar BRÅ-data
   genom sin egen kanal (`api.scb.se/.../OE/`?). Då samma stack som #37.

### Frågor att besvara

- **Granularitet:** finns kommun-nivå eller bara län? (Kommun krävs för
  ortssidor.)
- **Brottstyp-taxonomi:** matchar BRÅ:s kategorier vår nuvarande
  Polisen-baserade taxonomi (våld, stöld, inbrott osv.)? Antagligen ja
  — vår taxonomi kommer från Polisen som rapporterar till BRÅ.
- **Uppdateringsfrekvens:** BRÅ släpper preliminär statistik kvartalsvis,
  slutgiltig årligen. Dröjsmål: ~3-12 mån. Vad innebär det för UX
  ("statistik t.o.m. Q3 2025")?
- **Format:** API (idealiskt) vs CSV-dump (acceptabelt) vs Excel (skitigt).
- **Mappning:** kan vi återanvända kommunkoderna från `scb_kommuner`
  (#37)? Ja om BRÅ också använder SCB:s 4-siffriga kommunkoder. Bör
  verifieras.

## Förslag (preliminärt — beror på research)

### Schema (om data har kommun + år + brottstyp + antal)

```sql
CREATE TABLE bra_brott (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  kommun_kod VARCHAR(4) NOT NULL,
  brottstyp VARCHAR(64) NOT NULL,        -- e.g. 'våld', 'stöld', 'inbrott'
  ar SMALLINT NOT NULL,
  antal INT UNSIGNED NOT NULL,
  per_1000_inv DECIMAL(8,2) NULL,        -- pre-computed för snabb sortering
  KEY idx_kommun_ar (kommun_kod, ar),
  KEY idx_brottstyp_ar (brottstyp, ar),
  UNIQUE KEY idx_unique (kommun_kod, brottstyp, ar)
);
```

### Helper-API:er

```php
\App\BraStatistik::forKommun('0380')          // alla brottstyper, senaste året
\App\BraStatistik::forKommun('0380', 'inbrott', [2020, 2024])  // trend
\App\BraStatistik::topByCrime('inbrott', 10)  // topp-10 kommuner per brottstyp
\App\BraStatistik::neighbors('0380', 5)        // jämförelse mot 5 grannkommuner
```

Cachas i Redis 7 dagar (BRÅ-data ändras sällan).

### Import-kommando

```bash
docker compose exec app php artisan bra:import-statistik [--year=2024]
```

Idempotent. Körs manuellt vid varje BRÅ-release (1-2 ggr/år).
Schemalägg eventuellt att köra årligen i februari (efter slutgiltig
årsstatistik).

## Implementationsordning

1. **Research** (halvdag) — verifiera källa, format, granularitet, taxonomi
2. **Schema-design** — anpassa mot faktisk BRÅ-data
3. **Migration + import-kommando**
4. **Manuell verifiering** — jämför mot BRÅ:s egen webb för 5 kommuner
5. **Helper-API** + Redis-cache
6. **UI i #27 Lager 2** — använd BraStatistik istället för PlacePopulation::crimesPerThousand

## Risker

- **Granularitet kan vara län, inte kommun.** Då är CrimeGrade-modellen
  (per ort) inte möjlig — vi kan bara visa länsnivå. Stort skall
  påverka #27 Lager 2-design.
- **Brottstyp-taxonomi kan skilja.** Polisens RSS-kategorier är
  sannolikt en superset eller delmängd av BRÅ:s. Mappning kan bli
  ofullständig.
- **Officiell data har dröjsmål.** Visa alltid "data t.o.m. YYYY-MM"
  i UI för att inte vilseleda.
- **Datavolym.** 290 kommuner × ~30 brottstyper × ~10 år = 87 000 rader.
  Trivialt för MariaDB.

## Confidence

**Medel.** Datakällan finns men formatet är inte verifierat. Schemat
ovan är förslag som beror på research-utfallet. Hela todo:n kan behöva
omstruktureras efter steg 1 (research) — det är OK, det är poängen
med research-fasen.

## Beroenden mot andra todos

- **#37 (SCB-befolkning)** — krävs för "per 1000 inv."-beräkning. Klar.
- **#27 Lager 2** — väntar på #38 för riktig brottsstatistik.

## Inte i scope

- **Real-time-statistik.** BRÅ-data är retrospektiv (per år/kvartal),
  inte realtime. Det är ok — vår sajt visar "händelser just nu" med
  Polisen-data och "statistik historiskt" med BRÅ-data, två separata
  värden.
- **Egen brottsklassificering.** Använd BRÅ:s officiella taxonomi rakt
  av, inte vår egen tolkning.
- **Prediktiv analys.** Spår framtida brott från historisk statistik.
  Komplext, low value, etisk gråzon. Avvisa om diskuteras.
