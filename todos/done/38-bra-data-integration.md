**Status:** klar 2026-04-28 — datapipeline + helper-API levererat och importerat i prod (2024+2025, 290/290 kommuner). UI-integration kvar i #27 Lager 2.
**Senast uppdaterad:** 2026-04-28
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

## Research-utfall 2026-04-27

### Källa: BRÅ:s årliga kommun-CSV (inget API)

**Stabil URL-mönster:**
```
https://bra.se/download/<id>/<timestamp>/Anm%C3%A4lda%20brott%20kommunerna%20YYYY.csv
```

Verifierade URL:er:
- 2024: `https://bra.se/download/18.41109aad195b25241f818dbf/1742982579590/Anm%C3%A4lda%20brott%20kommunerna%202024.csv`
- 2025: `https://bra.se/download/18.3b6b697b19d24d83762a45f/1774625599791/Anm%C3%A4lda%20brott%20kommunerna%202025.csv` (publicerad 2026-03-27)

URL:erna är inte programmatiskt härledbara (download-id ändras varje
år). Lös via en konfig-konstant per år ELLER manuell URL-input via
artisan-flagga.

**Format:** UTF-8 BOM, semikolon-separerad, CRLF, 290 rader.
```
Kommun;Antal;Per 100 000 inv.
Ale;2537;7810
Alingsås;4293;10100
```

### Begränsning: bara totaler, ingen brottstyp

Den officiella öppna-data-CSV:n har **endast** total-antal anmälda brott
+ per 100 000 inv. **Ingen brottstyp-fördelning per kommun.**

Brottstyp-uppdelning kräver scraping av SOL-databasen
(`statistik.bra.se/solwebb/action/...`) — Java-app från ~2005 med
session-cookies + form-POST. Bräckligt och **ej** rekommenderat för MVP.

**Konsekvens för #27 Lager 2:** vi kan visa "Anmälda brott totalt
per 100 000 inv." som CrimeGrade-modellen-light, men inte donut-graf
av brottstyp baserat på BRÅ-data. Brottstyp-donuten i #27 Lager 1
fortsätter använda Polisens-data och märks som "publicerade händelser".

### Granularitet, frekvens, licens

- **Kommun-nivå JA** (alla 290 + stadsområden i Stockholm/Göteborg/Malmö från 2002)
- **Tidsupplösning:** årsdata. CSV ger inte månads-/kvartalsdata (det finns nationellt men inte per kommun)
- **Slutlig årsstatistik:** publiceras runt 31 mars året efter
- **Licens:** fri användning, ange "Källa: Brå" — INTE CC0 men praktiskt fri
- **Senaste tillgängliga:** 2025 (publicerad 2026-03-27)

### Kommunkoder saknas

CSV:n har **bara kommunnamn**, inga SCB-koder. Måste joinas på namn
mot `scb_kommuner`-tabellen (#37). 290 rader, namn är standardiserade
men vissa edge cases finns (åäö-encoding, "Falun" vs "Falu kommun"-typ).
Trim + UTF-8-normalisering bör räcka.

### Avfärdade alternativ

- **SCB:s api.scb.se** — speglar inte BRÅ:s anmälda brott. SCB hänvisar till BRÅ.
- **Officiellt API** — finns inte. Inga REST-endpoints, ingen PX-Web.
- **Brottstyp-uppdelning** — kräver SOL-scraping. Ej i MVP.

## Förslag (justerat efter research)

### Schema (totaler per kommun + år)

```sql
CREATE TABLE bra_anmalda_brott (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  kommun_kod VARCHAR(4) NOT NULL,
  ar SMALLINT NOT NULL,
  antal INT UNSIGNED NOT NULL,
  per_100k INT UNSIGNED NOT NULL,
  source_url VARCHAR(500) NULL,        -- för spårbarhet
  imported_at TIMESTAMP NULL,
  UNIQUE KEY idx_unique (kommun_kod, ar),
  KEY idx_ar (ar)
);
```

Inget brottstyp-fält i MVP — CSV:n har bara totaler. Lägg till om/när
SOL-scraping byggs (separat todo om det blir aktuellt).

### Helper-API

```php
\App\BraStatistik::forKommun('0380')              // senaste året
\App\BraStatistik::forKommun('0380', [2020, 2025]) // trend över år
\App\BraStatistik::topKommuner(10, $year)          // topp-10 per 100k
\App\BraStatistik::neighbors('0380', 5)            // jämför mot 5 grannar
\App\BraStatistik::rikssnitt($year)                // riksgenomsnitt för jämförelse
```

Cachas i Redis 7 dagar (datan ändras bara 1×/år).

### Import-kommando

```bash
# Auto-resolve URL för året (kräver underhåll av URL-tabell i kommandot)
docker compose exec app php artisan bra:import-anmalda-brott --year=2025

# Eller explicit URL för flexibilitet
docker compose exec app php artisan bra:import-anmalda-brott --year=2025 --url=https://bra.se/download/...
```

Steg internt:
1. Ladda ner CSV (UTF-8 BOM, semicolon, CRLF)
2. Parse med `League\Csv` eller `fgetcsv` med rätt delimiter
3. För varje rad: trim + UTF-8-normalisera kommunnamn → joina mot `scb_kommuner` på namn → få kommun_kod
4. Logga rader som inte matchar (sannolikt 0 om datan är ren)
5. Upsert i `bra_anmalda_brott`

Idempotent. Körs en gång per år efter BRÅ-release i mars/april.

### Schemaläggning

Lägg till i `app/Console/Kernel.php`:
```php
$schedule->command('bra:import-anmalda-brott --year=' . (date('Y') - 1))
    ->yearlyOn(4, 15, '03:00');  // 15 april varje år, 3:00 UTC
```

Failsafe: körs 2 veckor efter typisk publicerings-datum (31 mars).
URL-uppdatering kräver dock manuell pull i `bra:import-anmalda-brott`-
kommandot — alternativt kan en `BraReleaseUrlResolver`-service
crawla `https://www.bra.se/statistik/oppna-data.html` för att hitta
ny URL automatiskt.

## UI-mönster (för #27 Lager 2)

Förslag på text per ortssida:

> **Brottsstatistik 2025**
> 1 234 anmälda brott i Uppsala kommun (7 050 per 100 000 invånare).
> Rikssnittet är 12 800 per 100 000 invånare.
> _Källa: Brå (officiell anmäld brottsstatistik)._

Format-element:
- Antal + per 100k inv. (sortérbart i jämförelsetabell)
- Jämförelse mot rikssnitt (procentskillnad)
- 5-grannar-tabell sorterad på per_100k
- Linje-graf trend 2015-senaste år (ren SVG)
- Källhänvisning + datum i fotnot

## Implementationsordning

1. ~~**Research**~~ ✓ klar 2026-04-27 — CSV med totaler, ingen brottstyp, 290 rader/år
2. **Migration + import-kommando** — `bra_anmalda_brott`-tabell + `bra:import-anmalda-brott`
3. **Importera 2024 + 2025** — verifiera kommun-namn-join (förvänta 0 missar)
4. **Manuell verifiering** — jämför mot BRÅ:s egen webb för 5 kommuner
5. **Helper-API** (`BraStatistik`) + Redis-cache (7d TTL)
6. **Schemalägg årlig import** — Kernel.php cron 15 april
7. **UI i #27 Lager 2** — när #25 + #27 startar

## Risker

- ~~**Granularitet kan vara län, inte kommun.**~~ Verifierat: kommun-nivå finns.
- ~~**Brottstyp-taxonomi.**~~ Verifierat: CSV har bara totaler, ingen taxonomi-fråga i MVP.
- **URL-instabilitet.** BRÅ:s download-id ändras varje år (inte programmatiskt
  härledbar). Hantera via konfig per år ELLER auto-resolver mot listsidan.
- **Kommunnamn-mismatch.** 290 rader bör matcha rakt av men "Falun" vs
  "Falu kommun" eller liknande kan finnas. Logga icke-matchade rader vid import.
- **Officiell data har 3+ månaders dröjsmål.** Visa "Statistik {år}. Källa: Brå."
  så användare förstår tidsfördröjningen.
- **Source-attribution.** BRÅ:s licens är inte CC0. Kräver "Källa: Brå" i UI.
- **Datavolym.** 290 kommuner × ~10 år = 2 900 rader. Trivialt.

## Confidence

**Hög.** Research bekräftar att källan finns, är stabil (verifierad
nedladdning av 2024 + 2025), och har rätt granularitet. MVP är ~3-4 timmars
implementation: schema + import-cmd + helper. UI-integration i #27 är
separat arbete.

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
