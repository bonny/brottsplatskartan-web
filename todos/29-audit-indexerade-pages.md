**Status:** aktiv (designfas)
**Senast uppdaterad:** 2026-04-26

# Todo #29 — Audit + reducera indexerade pages

## Varför

Vi har **~53 670 indexerade pages** enligt GSC (proxy: unika URL:er
med ≥1 impression senaste 30d). Många är "dead weight" — drar inte
trafik men finns indexerat och drar ner sajtens kvalitetssignal hos
Google.

Att skära URL-yta är ofta större SEO-vinst än att förbättra innehåll
på existerande pages, eftersom Googles "site quality signal" påverkas
av andelen low-value pages domänen presenterar.

## Bryt-ner av nuvarande indexerade pages (från GSC, 2026-04-26)

| Kategori                        | Andel av top 25k | Dead weight-risk       |
| ------------------------------- | ---------------- | ---------------------- |
| `/plats/*`                      | 7 445 (30%)      | Låg–medel              |
| Single events                   | 7 632 (30%)      | **Hög** (gamla, tunna) |
| `/lan/*` (med + utan datum)     | 2 814 (11%)      | Låg                    |
| `/handelser/{date}` (top-level) | 1 491 (6%)       | Låg                    |
| `/typ/*`                        | 59 (<1%)         | Hög för obskyra typer  |
| `/<stad>` (Tier 1)              | 2 (<1%)          | Ingen                  |
| Övrigt                          | 5 557 (22%)      | Variabel               |

## Kandidater för borttagning/noindex

### 1. Tunna events (största gruppen)

**Definition:** events äldre än X dagar **+** body-text < N ord
**+** views < M.

`crimeevents:mark-thin`-kommando är planerat sedan #11 men inte
implementerat. Detta är där vi börjar.

**Föreslagen tröskel (ska valideras):**

- Ålder > 365 dagar
- OCH body < 50 ord
- OCH views < 5

Action: `<meta robots="noindex,follow">` via `$robotsNoindex`-flaggan
som redan finns på `single-event.blade.php`.

**Estimat:** 10–30% av single events = 800–2 300 sidor
avindexeras. Drar troligen <1% av trafiken (enligt GSC: top events
drar ~9–12 clicks/månad, lågsvansen drar 0).

### 2. Plats-sidor med <5 events historiskt

**Definition:** orter med extremt låg event-frekvens — ofta små byar
som råkat få indexering pga RSS-träff någon gång.

GSC + DB-query: `SELECT plats, COUNT(*) FROM crime_events GROUP BY
plats HAVING COUNT(*) < 5` → kandidatlista.

Action: `<meta robots="noindex,follow">` på platsen, eller 301 till
länssidan om platsen ligger i ett tydligt län.

**Estimat:** 50–150 platser, marginell trafik.

### 3. Typ-sidor för obskyra brottstyper

`/typ/*` har bara 59 indexerade. Av dessa har troligen några extremt
låg trafik (sällsynta brottskategorier som dykt upp 1-2 gånger).

Action: `<meta robots="noindex,follow">` om <10 events totalt för
typen.

**Estimat:** 5–15 typer. Mycket marginell trafik.

### 4. Län × datum för avlägsna datum (inom #25-scope)

Täcks av #25 (månadsvyer) — inte i scope för #29.

### 5. Pages som returnerar 4xx men är indexerade

Gamla URL:er som flyttats utan 301. Hittas via:

```bash
mcp__mcp-gsc__check_indexing_issues
```

Action: 301 till nya placeringen, eller 410 om innehållet är borta.

**Estimat:** osäkert utan att köra rapporten.

### 6. Duplikat (samma incident, flera RSS-källor)

`ContentFilterService` finns redan men kanske inte täcker alla fall.
Audit: events skapade inom samma minut, samma plats, liknande
brottskategori.

Action: behåll en, 301 övriga.

**Estimat:** okänt — kräver analys.

## Implementation

### Fas 1: Mätning (1 dag)

1. Hämta full lista över low-impression URL:er via GSC
   (`get_advanced_search_analytics` med
   `filter_dimension=page sort_by=impressions ascending`)
2. Korsreferera mot vår DB för att identifiera vilken kategori varje
   URL tillhör
3. Producera rapport: hur många pages per kategori, total trafik per
   kategori

### Fas 2: Noindex-tröskel-implementation (1 dag)

1. Implementera `crimeevents:mark-thin` Artisan-kommando med
   `--apply`/`--dry-run`-flaggor (samma mönster som
   `crimeevents:check-publicity`)
2. Tröskel-logik konfigurerbar via flaggor
3. Sätter `is_thin = true`-flagga på events; blade-template läser
   den till `$robotsNoindex`
4. Liknande för platser och typer

### Fas 3: Pilot (1 dag)

1. Kör `crimeevents:mark-thin --since=730 --max-words=30 --dry-run`
   först — manuellt granska resultatet
2. Justera tröskel om för aggressiv eller för konservativ
3. Apply på en delmängd (t.ex. events från 2017–2018)
4. Mät i GSC efter 14 dagar: tappar vi trafik vi inte räknat med?

### Fas 4: Full rollout (1 dag)

Om pilot ser bra ut: applicera på hela katalogen. Mät 30 dagar.

### Fas 5: Sitemap-uppdatering

Sitemap måste exkludera `noindex`-pages. Redan på `GenerateSitemap`-
kommandot sannolikt — verifiera att thin events filtreras bort.

## KPI:er

| Metric                   | Before (baseline) | Target after 30d                                                               |
| ------------------------ | ----------------- | ------------------------------------------------------------------------------ |
| Indexed pages (GSC)      | ~53 670           | ↓ 5–15%                                                                        |
| Total clicks från Google | (mät dag 0)       | Oförändrad eller ↑ (paradoxalt — färre låg-värde URL:er kan boosta high-värde) |
| Total impressions        | (mät dag 0)       | ↓ proportionellt mot indexed pages                                             |
| CTR genomsnitt           | (mät dag 0)       | ↑                                                                              |
| Avg position             | (mät dag 0)       | ↑                                                                              |

Om total clicks sjunker > 5%: rulla tillbaka senaste batch.

## Risker

- **För aggressiv tröskel** = avindexerar pages som faktiskt drar
  long-tail-trafik. Mitigering: pilot på liten batch, mät 14 dagar
  innan skala.
- **Bot-trafik felmätt som "ingen trafik"** — vissa pages kan
  faktiskt få bot-trafik utan att registreras i GA4 (JS-blockerad).
  Mitigering: cross-reference mot nginx-loggar om möjligt.
- **Kaskadeffekt på interna länkar** — om vi noindex:ar sidor som
  länkas från andra sidor, kan internlänk-flödet skadas. Mitigering:
  använd `noindex,follow` (inte `noindex,nofollow`) så länkar
  fortfarande räknas.
- **GSC visar minst 53 670 — verkliga antalet är okänt.** API:n
  exponerar inte exakt total. Behöver complement med UI-rapport
  efter pilot för att verifiera.

## Beroenden mot andra todos

- **#11 SEO-audit** — har "Noindex-strategi för gamla/thin events"
  som öppen punkt. #29 levererar denna del. Kvitta i #11 efter rollout.
- **#25 Månadsvyer** — överlappande på datum-routerna men #29
  fokuserar på events, plats-sidor och typ-sidor.
- **Inget AI-beroende** — #29 är ren data + heuristik, ingen
  LLM-användning. Kan göras parallellt med #28.

## Tid

2-3 dagar (mätning + implementation + pilot + rollout).

## Status

Designfas. Kan starta direkt — inga blocker. Mest direkt SEO-impact
av alla öppna todos enligt analys.

## Referenser

- GSC `check_indexing_issues` för bulk-kontroll
- `crimeevents:check-publicity` som pattern för det nya
  `crimeevents:mark-thin`-kommandot
- Befintlig `$robotsNoindex`-flagga i `single-event.blade.php`
