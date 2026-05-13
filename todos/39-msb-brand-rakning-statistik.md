**Status:** implementerad 2026-04-29 — 14d-check 2026-05-13 visade ingen attribuerbar effekt, mätperiod förlängd till 60d (2026-06-29)
**Senast uppdaterad:** 2026-05-13 (14d GSC-check — ingen MCF-specifik signal, confounded med #24/#33/#10)
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

## Research (2026-04-29)

### TL;DR

Bästa möjliga utfall — **publik PxWeb v1 API**, exakt samma teknik som
SCB (#37) och utan inloggning. Kommunkoden är **SCB 4-siffrig** (`0180` =
Stockholm, `0380` = Uppsala) → trivial join mot `scb_kommuner`. Allt
sedan 1998 finns, månads- och timupplösning, 14 övergripande
händelsetyper. Implementation kan kopiera arkitekturen från #38 BRÅ rakt
av.

### Myndighetsbyte: MSB → MCF (2026)

Statistikfunktionen har flyttats från MSB till **Myndigheten för civilt
försvar (MCF)**. Gamla `https://ida.msb.se/` 301:ar till mcf.se.
Datasource ska refereras som "MCF (tidigare MSB)" i UI och commit-
meddelanden. Domännamn i koden bör vara `mcf.se` (inte `msb.se`) för att
inte rotna direkt.

### 1. Datakälla — bekräftad öppen PxWeb v1 API

- **Webgränssnitt:** https://statistik.mcf.se/ (PxWeb-portal, ingen
  inloggning krävs)
- **API-bas:** `https://statistik.mcf.se/PxWeb/api/v1/sv/PxData/`
  (klassisk PxWeb v1 — samma teknik som SCB)
- **Topp-noder:**
    - `A` — Omkomna i bränder
    - `B` — Räddningstjänstens insatser ← intressant
- **Format:** JSON in (POST), JSON ut. Också CSV/XLSX/PX via
  webgränssnittet.
- **Ingen autentisering. Ingen rate-limiting dokumenterad.**

### 2. Granularitet och insatstyper

Under `B/` finns sju kategorier (B1–B7), varje med ~2–7 detaljtabeller.
Den breda översiktstabellen är **B11** ("Antal inträffade och befarade
olyckor per övergripande händelsetyp, verksamhet/bostadstyp, plats,
månad, veckodag, timme och år"):

- **kommun (`kommun`):** 290 kommuner med SCB 4-siffrig kod (`0114`
  Upplands Väsby … `2584` Kiruna).
- **år (`ar`):** **1998 – 2025** (28 år).
- **månad / veckodag / timme:** finns separat — gör att vi kan bygga
  månadsstatistik direkt utan extra aggregat.
- **konvHandelsetypId (övergripande händelsetyp):** 14 typer
    - `1` Brand eller brandtillbud i byggnad
    - `3` Brand eller brandtillbud i annat än byggnad
    - `2` Trafikolycka
    - `4` Utsläpp av farligt ämne / fara för utsläpp
    - `5` Drunkning eller drunkningstillbud
    - `6` Nödställd person i andra fall (från 2005)
    - `7` Nödställt djur
    - `8` Stormskada
    - `9` Ras eller skred
    - `11` Översvämning av vattendrag (från 2005)
    - `12` Annan vattenskada
    - `13` Annan olycka eller tillbud
    - `14` Automatlarm utan brandtillbud
    - `15` Annan händelse utan risk för skada
- **verksBostadstypId:** 41 typer (Flerbostadshus, Villa, Sjukhus,
  Kriminalvård, Restaurang, Försvar, …) — antagligen too granular för
  vår användning, men finns om vi vill.

För djupare data (t.ex. _slutlig omfattning_ vid byggnadsbränder, eller
_skadegrad_ vid trafikolyckor) finns specifika tabeller B20–B72.

### 3. Licens och användarvillkor

Standard svensk myndighetsstatistik:

> "Du får gärna kopiera och sprida statistiktabeller, diagram och
> texter, men ange 'Källa: MSB'."

(MSB-formuleringen står kvar i många dokument fast organisationen heter
MCF nu — i UI använder vi "Källa: MCF (Myndigheten för civilt försvar)
— tidigare MSB" eller bara "Källa: MCF".)

PSI-direktivet → fri återanvändning. **Kostnadsfri.** Ingen avgift,
inget avtal.

### 4. Aktualitet

- Senast uppdaterad: **2026-03-10** för B-tabellerna.
- Data t.o.m. **2025** finns nu (mars 2026) → publiceringscykeln är
  **~Q1 året efter mätperioden** (samma rytm som BRÅ #38).
- Historik från 1998 (B10–B12) eller 2018/2022 (mer detaljerade
  tabeller).

### 5. Format-detaljer

POST mot tabell-URL med en JSON-query:

```json
{
    "query": [
        {
            "code": "kommun",
            "selection": { "filter": "item", "values": ["0380"] }
        },
        { "code": "ar", "selection": { "filter": "item", "values": ["2024"] } },
        {
            "code": "konvHandelsetypId",
            "selection": { "filter": "all", "values": ["*"] }
        }
    ],
    "response": { "format": "json" }
}
```

→ POST `https://statistik.mcf.se/PxWeb/api/v1/sv/PxData/B/B1/B11`

Andra dimensioner (verksamhet, månad, veckodag, timme, ContentsCode)
har `"elimination": true` — kan utelämnas i query och aggregeras
automatiskt.

### 6. Praktiskt verifierat — Uppsala (0380) 2024

```
Brand i byggnad                        171
Brand i annat än byggnad               176
Trafikolycka                           182
Utsläpp farligt ämne                    42
Drunkning                                5
Nödställd person                        31
Nödställt djur                           4
Stormskada                               8
Ras/skred                                0
Översvämning vattendrag                  0
Annan vattenskada                       15
Annan olycka/tillbud                    10
Automatlarm utan brandtillbud          465
Annan händelse utan risk               122
TOTAL                                 1231
```

Stockholm (0180) 2024 totalt: **4698** olyckor.

(Notera: ~38 % av alla "olyckor" i Uppsala 2024 var automatlarm utan
brandtillbud — viktigt att skilja ut i UI så man inte ger ett
missvisande intryck.)

### Konsekvenser för förslaget nedan

Schemat i förslaget är i grunden korrekt men kan justeras:

- **`kommun_kod VARCHAR(4)`** stämmer (matchar SCB).
- **`insats_typ VARCHAR(64)`** — bättre att lagra både `handelsetyp_id`
  (FK till statisk lookup) och MCF:s textnamn, så vi inte är beroende
  av en enda strängkonvention. Alternativt: enum-liknande mappning i
  PHP.
- Lägg till **`månad TINYINT`** så månadsvyer (#25) får faktiska siffror
  (t.ex. "183 trafikolyckor i Uppsala i augusti 2024"). PxWeb levererar
  månad i samma query.
- Importkommando: kör en query per kategori (B11 räcker för
  översiktsdata; B20/B30/B60/B70 om vi vill ha djupare data per
  insatstyp). Hämta hela Sverige × alla år × alla månader på en gång —
  det är ~290 × 28 × 12 × 14 ≈ 1.4M rader men många är 0; PxWeb svarar
  snabbt.
- Inkrementell uppdatering 1×/år (mars) räcker.

### Rekommendation

**Implementera.** Detta är best-case-scenariot:

1. Öppen API utan inloggning, identisk teknik som SCB (#37) som vi redan
   integrerat mot.
2. Kommunkoden är direkt joinbar mot `scb_kommuner` — ingen
   namnmappning behövs.
3. Månads- och timupplösning ger oss faktiska siffror till #25
   månadsvyer (t.ex. "officiell statistik: X bränder i Uppsala i augusti
   2024").
4. Komplementerar #38 BRÅ-data perfekt — BRÅ täcker brott, MCF täcker
   olyckor/räddning. Tillsammans en fyllig "officiell statistik"-sektion.
5. Avgör inte själv att automatlarm-andelen är för stor att visa — visa
   den separat med en kort förklaring; det _är_ värdefull
   transparens.

### Estimerad insats

**4–6 timmar** — under det preliminära "3h eller 3d"-spannet.

- 1h migration + schema (med månadskolumn)
- 2h artisan-kommando `mcf:import-raddningsinsatser` (POST mot B11 för
  alla år × kommuner; valfritt B20/B30/B60/B70 senare)
- 1h `MsbStatistik`-helper (rename gärna till `MCFStatistik` eller
  `RaddningsStatistik` — `MsbStatistik` rotnar pga myndighetsbytet)
- 1h kommun-helper-integration + cache (Redis 30d, uppdateras 1×/år)
- 0–1h initial UI-integration på en ortssida

### Källor

- Statistikportal: <https://statistik.mcf.se/>
- API-rot: <https://statistik.mcf.se/PxWeb/api/v1/sv/PxData/>
- B11-tabell:
  <https://statistik.mcf.se/PxWeb/api/v1/sv/PxData/B/B1/B11>
- MCF-info-sida om räddningsstatistik:
  <https://www.mcf.se/sv/amnesomraden/skydd-mot-olyckor-och-farliga-amnen/stod-till-kommunal-raddningstjanst/statistik-och-larande-fran-olyckor/statistik-raddningstjanstens-insatser/>
- Kvalitetsdeklaration 2024:
  <https://www.mcf.se/siteassets/dokument/amnesomraden/skydd-mot-olyckor-och-farliga-amnen/raddningstjanst/statistik-raddningstjanst/kvalitetsdeklaration-statistik-over-raddningstjanstens-insatser2024.pdf>
- Användarguide för verktyget:
  <https://www.mcf.se/siteassets/dokument/amnesomraden/skydd-mot-olyckor-och-farliga-amnen/raddningstjanst/statistik-raddningstjanst/sa-anvander-du-statistikverktyget.pdf>

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

## 14d-mätning (2026-05-13)

GSC-jämförelse pre-deploy (2026-04-15→04-28) vs post-deploy
(2026-04-29→05-12), per page + per query.

### Resultat — confounded med samtidiga deploys

Sidor där MCF visas (Tier 1 + `/plats/`) har stor click-uppgång, men
det är **URL-konsolidering**, inte MCF:

| Page                   | P1   | P2   | Δ       |
| ---------------------- | ---- | ---- | ------- |
| `/goteborg`            | 61   | 691  | +1033 % |
| `/malmo`               | 27   | 541  | +1904 % |
| `/uppsala`             | 0    | 236  | nytt    |
| `/helsingborg`         | 0    | 106  | nytt    |
| `/stockholm`           | 3130 | 3251 | +3.9 %  |
| `/plats/malm%C3%B6`    | 263  | 4    | −98.5 % |
| `/plats/g%C3%B6teborg` | 149  | 0    | −100 %  |

Spegelvändningen `/plats/...` → `/{city}` visar att uppgången är från
**#24/#35-cannibalization-fix**, inte MCF. Samtidigt:

- **#33** Tier 1 month routes deployades 2026-04-27 (−2d)
- **#10** AI-titles rollout 2026-04-27 (−2d)
- **#39** MCF 2026-04-29

Alla tre är confounded i samma 14d-fönster.

### MCF-specifika queries — frånvarande

Inga nya queries i top 50 som matchar MCF-content-tema:

- "räddningstjänsten statistik" — saknas helt
- "bränder Stockholm 2024" / "trafikolyckor Uppsala" — saknas
- "räddningstjänsten senaste larm stockholm" −46 % (gick ner)

Topplistan domineras av event-drivna ("mord ornö", "brand vallentuna")
och generic ("blåljus malmö") — ingen attribuerbar MCF-trafik ännu.

### Tolkning

14 dagar är **för kort** för innehållstillägg som MCF. SEO behöver
30–60d för:

- Google crawlar nya sektioner
- Kvalitetsbedömning väger in efter dwell-time-data
- Nya queries rankar för relaterade termer

GA4-token är utgången → kan inte mäta dwell time / bounce / interna klick.
Det är där MCF-värdet sannolikt syns först (rikare innehåll → längre
dwell → bättre Quality Score över tid).

### Action

- **Förläng mätperioden till 60d** (2026-06-29).
- Förnya GA4-token före nästa check så vi får dwell-time-data.
- Slå ihop med #25 månadsvyer-mätningen — samma fönster, samma sidor.
- Om 60d fortfarande inte visar effekt: avgör om feature ska behållas
  (lika gärna passiv content-quality som SEO-driver) eller fas-2 med
  prominentare placering/anchor.
