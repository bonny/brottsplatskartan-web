**Status:** aktiv (plan klar, redo för pilot)
**Senast uppdaterad:** 2026-04-21

# Todo #10 — AI-omskrivning av vaga event-titlar

## Sammanfattning

Polisens RSS-flöden producerar ibland generiska/tomma rubriker som
"Sammanfattning natt, region Nord" eller "Information om polisens
pressnummer" — texter med noll SEO-värde och dålig klickbenägenhet.
`parsed_title` härleds direkt ur RSS-titeln (mellan komma 1 och sista
kommat) och blir därför lika tom. Förslaget: kör en AI-rewrite
(Claude Haiku) på body-texten (`parsed_content`) och spara resultatet
i en **ny** kolumn `parsed_title_ai` — **utan** att förstöra nuvarande
`parsed_title` eller existerande permalinks. Slug-generering används
bara för nya rewrites eller bakom en feature-flag med 301-redirect
från gamla slugs.

Rekommenderad scope i fas 1: **bara nya händelser + senaste 30
dagarna** som pilot. Bakåtkatalog (200k+ events) i fas 2 efter
utvärdering.

## Nulägesanalys — hur parsas titel idag

### RSS till DB

- `app/Console/Commands/FetchEvents.php` (`crimeevents:fetch`, körs
  var 12:e minut) → `FeedController::updateFeedsFromPolisen()` →
  `FeedParserController::parseTitle($title)`.
- `FeedParserController.php:67-136`: RSS-titeln splittas på `,`.
    - Första delen = datum (eller "Uppdaterad …")
    - Sista delen = `parsed_title_location`
    - Resten (mitten) = `parsed_title`
- Resultatet skrivs till kolumnerna `parsed_title`, `parsed_title_location`,
  `parsed_date`, `parsed_updated_date` direkt i `crime_events`.

### Användning av `parsed_title`

- **Slug/permalink** (`CrimeEvent::getPermalink()` rad 419-470) — ingår i URL.
- **`<title>`/`<h1>`** på event-sidan (rad 953, 984).
- **Sök** (rad 613-615, LIKE på `parsed_title`).
- **Tweet-generering** (`app/Console/Commands/TweetCrimes.php`).
- **Feeds/RSS-output** (`FeedController`).
- **API-svar** (`ApiController`, `ApiEventsMapController`).
- **Kategoriseringshjälpare** (rad 1252, 1274 — matchar "inbrott",
  "brand" osv. via `Str::contains`).

### ContentFilter — relaterad men inte samma sak

`app/Services/ContentFilterService.php` markerar presstalesperson- och
pressnummer-rena händelser som `is_public = false` (dvs. helt
dolda). Presstalesperson-filtret är f.n. **avaktiverat** (rad 23-29)
eftersom det råkade blockera `sammanfattning natt`-poster som faktiskt
innehåller riktig händelsedata. Det är precis de här som
AI-omskrivning ska rädda SEO-mässigt — de ska **visas**, men med
bättre titel.

## Mönster — vaga titlar att fånga

SQL-utkast (kör som EXPLAIN/dry-run först):

```sql
-- Totalt antal "vaga" events (grov uppskattning)
SELECT
  CASE
    WHEN parsed_title REGEXP '(?i)sammanfattning ?(natt|dygn|morgon|kväll|dag)' THEN 'sammanfattning'
    WHEN parsed_title REGEXP '(?i)information om polisens pressnummer' THEN 'pressnummer'
    WHEN parsed_title REGEXP '(?i)presstalesperson' THEN 'presstalesperson'
    WHEN parsed_title REGEXP '(?i)dagens presstalesperson' THEN 'dagens-presstalesperson'
    WHEN parsed_title REGEXP '(?i)^(övrigt|annat|händelse)$' THEN 'generisk'
    WHEN CHAR_LENGTH(parsed_title) < 6 THEN 'för-kort'
    ELSE NULL
  END AS bucket,
  COUNT(*) c
FROM crime_events
WHERE is_public = 1
GROUP BY bucket
ORDER BY c DESC;
```

Konkreta mönster (observerade i slug-exemplet från uppgiften):

| Mönster                  | Regex                                 | Exempel                               |
| ------------------------ | ------------------------------------- | ------------------------------------- | ----------- | -------- | ---------------------------------- | ---------- | ---------------------------------------- | --- |
| Sammanfattning natt/dygn | `^(?i)sammanfattning[ -]?(natt        | dygn                                  | morgon      | kväll)`  | "Sammanfattning natt, region Nord" |
| Pressnummer-info         | `information om polisens pressnummer` | "Information om polisens pressnummer" |
| Presstalesperson         | `(dagens )?presstalesperson`          | "Dagens presstalesperson är på plats" |
| Generiskt "Övrigt"       | `^(övrigt                             | annat                                 | händelse)$` | "Övrigt" |
| Trunkerad/tom            | `CHAR_LENGTH < 6` eller NULL          | "Brand", "Stöld" utan kontext         |
| Regionstämpel utan plats | `region (nord                         | syd                                   | väst        | öst      | mitt                               | bergslagen | stockholm)` som suffix utan specifik ort |     |

Rekommenderat: börja med `sammanfattning` — det är det största SEO-
bortfallet (långa body-texter, generisk titel, återkommande flera
gånger om dagen, indexerade i Google).

## Teknisk plan

### 1. Migration

```php
Schema::table('crime_events', function (Blueprint $table) {
    $table->string('parsed_title_ai', 255)->nullable()->after('parsed_title');
    $table->timestamp('parsed_title_ai_at')->nullable();
    $table->string('parsed_title_ai_model', 64)->nullable();
    // För SEO och QA: spara originalet så vi kan göra diff/rollback
    // även om någon råkar skriva över parsed_title senare.
    $table->index('parsed_title_ai_at'); // för backfill-progress
});
```

Ingen ändring av existerande `parsed_title` — den är läskälla för
alla gamla permalinks och fallback.

### 2. Accessor i `CrimeEvent`

```php
public function getDisplayTitleAttribute(): string
{
    return $this->parsed_title_ai ?: $this->parsed_title ?: 'Polishändelse';
}
```

Byt ut `$event->parsed_title` → `$event->display_title` i:

- `resources/views/parts/crimeevent*.blade.php`
- `resources/views/single-*.blade.php`
- Meta-title/OG-title i layouts
- `getLdJson()` (Schema.org headline)
- Tweet-texten (`TweetCrimes.php`)

**Behåll `parsed_title` i URL-slug** (`getPermalink()`) för att undvika
att befintliga länkar i Google och externa sajter bryts. Se punkt 5.

### 3. Service + Kommando

`app/Services/TitleRewriteService.php`:

- Metod `rewriteTitle(CrimeEvent $event): ?string`
- Claude Haiku (billigt, snabbt).
- Prompt se nedan.
- Validering: 5-80 tecken, ingen trailing punkt, ingen ", region X"-
  suffix, inga citationstecken runt, inga emojis, startar med
  versal. Hallu-skydd: kräv att minst ett ortsnamn eller
  brottskategori från `parsed_content` finns i resultatet.
- Fallback: returnera `null` → behåll `parsed_title`.

`app/Console/Commands/RewriteTitles.php` (`crimeevents:rewrite-titles`):

```
--since=<days>          default 1
--pattern=<bucket>      sammanfattning|pressnummer|all
--limit=<n>             default 500
--dry-run
--force                 (kör även om parsed_title_ai redan finns)
```

### 4. Integration i fetch-pipeline

I `FetchEvents::handle()`, **efter** content-filtret, kör
`TitleRewriteService` på nya events som matchar ett vagt mönster.
Wrap i try/catch + timeout — får aldrig blockera en fetch-cykel.
Redis-låst per event-ID för idempotens.

Scheduler-entry i `app/Console/Kernel.php`:

```php
$schedule->command('crimeevents:rewrite-titles --since=1')
    ->hourly()
    ->withoutOverlapping();
```

### 5. Slug & 301-redirect

**Default: rör inte sluggen.** `parsed_title_ai` påverkar bara
rendering. Zero-risk för gamla länkar.

**Om** slug-byte ändå önskas (bättre SEO på nya events):

- Endast för events som ännu inte indexerats (< 24h gamla) — i
  `getPermalink()` välj `parsed_title_ai` om satt **och**
  `created_at > NOW() - 24h`.
- För äldre: lägg ny kolumn `slug_history` (JSON) och ny route som
  matchar gamla slugs → 301 till nuvarande. Alternativt generisk
  catch-all: matcha på trailing `-{id}` (det numeriska ID:et är sista
  delen av sluggen redan, rad 455) och redirecta till kanonisk URL om
  sluggen skiljer sig.
- Canonical-taggen i `<head>` måste peka på nya URL:en direkt.

### 6. Sitemap

`sitemap.xml` saknas idag (se `02-seo-review.md`). När den byggs:
använd `display_title` i `<image:title>`/`<news:title>` — AI-titlarna
får då effekt i Google News/Discover.

## Prompt-design

```
Du är en svensk nyhetsredaktör på Brottsplatskartan.se. Skriv en kort,
saklig och SEO-vänlig rubrik för följande polishändelse.

KRAV:
- Svenska.
- 40-70 tecken.
- Börja med brottstyp/händelsetyp (t.ex. "Misshandel", "Trafikolycka",
  "Brand").
- Inkludera ort om den nämns i texten.
- Ingen sensationalism, inga utropstecken, inga emojis, inga citattecken.
- Hitta INTE på detaljer som inte står i texten. Om texten är för vag,
  svara exakt: RUBRIK_OMÖJLIG
- Returnera ENBART rubriken, ingen förklaring, ingen punkt i slutet.

PLATS (från RSS): {parsed_title_location}
LÄN: {administrative_area_level_1}
ORIGINALRUBRIK: {parsed_title}

HÄNDELSETEXT:
{parsed_content}
```

Model: `claude-haiku-4-5` (eller motsvarande senaste Haiku).
`max_tokens: 60`, `temperature: 0.2`.

Post-check: om svaret === `RUBRIK_OMÖJLIG` → skriv `null`, behåll
original.

## Kostnadsuppskattning (Claude Haiku)

Antaganden per event:

- Input: prompt ~150 tokens + `parsed_content` ~400 tokens = **~550 tokens**
- Output: **~25 tokens**
- Haiku 4.5-prissättning (kolla aktuella siffror på anthropic.com):
  ~$1/M input, ~$5/M output.
- Cost/event ≈ (550 × $1 + 25 × $5) / 1M ≈ **$0.00068** ≈ 0.007 SEK.

Scenarier:
| Volym | Kostnad USD | Kostnad SEK (10 kr/USD) |
|---|---|---|
| Nya events (~500/dag, 20% vaga = 100/dag) | $0.07/dag ≈ $25/år | ~250 kr/år |
| Senaste 30 dagar backfill (~3 000 vaga) | $2 | ~20 kr |
| Full bakåtkatalog 200k events, 20% vaga = 40k | $27 | ~270 kr |
| Full bakåtkatalog ALLA 200k (om man vill testa) | $136 | ~1 400 kr |

Slutsats: **kostnaden är försumbar även för full backfill**. Flaskhalsen
är API rate-limits, inte pengar. Kör med prompt caching på
system-prompten för att halvera input-kostnaden ytterligare.

## Risker

1. **AI-hallucination** — modellen hittar på platser/detaljer som inte
   finns. Mitigering: validera att >=1 ord i rubriken finns i
   `parsed_content`/`parsed_title_location`. `RUBRIK_OMÖJLIG`-utgång för
   för vaga texter. Behåll alltid original för fallback.
2. **Slug-drift & 404** — om sluggen byts utan 301 tappas alla
   existerande Google-rankings. Mitigering: default är att inte
   ändra slug.
3. **Duplicerat innehåll** — om AI genererar väldigt lika rubriker för
   liknande händelser kan canonicalization bli problematisk. Mindre
   problem eftersom body-texten fortfarande varierar.
4. **Kostnadsrusning vid bugg** — loop som retrier kan spränga budget.
   Mitigering: `->withoutOverlapping()`, hard cap per körning,
   rate-limit middleware runt service.
5. **Polisen-text copyright** — vi skickar deras text till Anthropic.
   Texten är redan offentlig och vi replikerar inte i prompten mer än
   vi redan gör internt, men nämn i villkorstexten om nödvändigt.
6. **Tonalitets-drift** — AI kan bli mer "klickbete"-aktig än Polisens
   nyktra stil. Prompten tryckter på "saklig, ingen sensationalism".
   Stickprov + dashboard för manuell granskning rekommenderas i pilot.
7. **Kategoriserings-heuristik bryter** — `Str::contains($parsed_title,
'inbrott')` på rad 1252/1274 används för att välja ikon/kategori.
   Om vi ersätter `parsed_title` i UI men inte i heuristiken kan
   kategoriseringen bli inkonsekvent. Lösning: kör heuristik mot
   `parsed_title` (rå källa), visa `display_title`.

## Fördelar

- **SEO**: klickbara titlar i SERP, högre CTR, bättre ranking på
  "trafikolycka göteborg" etc.
- **Sociala medier**: Twitter/OG-kort blir läsbara istället för
  "Sammanfattning natt".
- **Tillgänglighet**: skärmläsare får meningsfulla länktexter.
- **Intern sökning**: bättre LIKE-träffar på `parsed_title_ai`.
- **Backward-compatible**: ingen migrering av historiska URL:er krävs
  om vi låter slug vara ifred.

## Bakåtkatalog (200k+ events)

Strategi:

1. **Fas 1** — bara nya events + sammanfattning-events från senaste 30
   dagarna. ~3 000 events, ~20 kr, 1 körning.
2. **Fas 2** (efter manuell stickprovsgranskning av fas 1): utvidga
   till alla vaga titlar senaste 12 månaderna. ~20k events, ~130 kr.
3. **Fas 3** (optionell): full bakåtkatalog — bara om SEO-vinsten
   bevisas. Kör i chunks om 1000 i nattsatser, `--sleep=1` mellan
   batch för att inte överbelasta API.

Progress-tracking: `parsed_title_ai_at IS NULL` + matchar vagt
mönster = "behöver bearbetas". Queryn är indexerad via
`parsed_title_ai_at`.

## Öppna frågor

1. Ska `display_title` också användas i slug för **helt nya** events
   (där ingen extern länk existerar ännu)? Risk/vinst?
2. Ska vi mellanlagra hela AI-svaret (JSON med confidence, alternativ
   etc.) eller bara strängen? Bara strängen räcker initialt.
3. Vill vi ha manuell override-kolumn (`parsed_title_manual`) för
   redaktörsingrepp på enskilda events? Låg prio.
4. Ska AI-titeln synas i RSS-feeden också, eller bara på sajten?
   Rekommendation: bara sajten initialt (RSS-konsumenter kan vara
   känsliga för plötsliga titelbyten).
5. Claude Haiku vs GPT-4.1-mini — lika billiga, lika kapabla. Vi har
   redan Claude-integration (`AISummaryService`, `ClaudePhp`), så
   Claude är default.
6. Hur upptäcker vi när Polisen börjar leverera **bra** titlar igen?
   Regelbundet re-sampling + dashboard med "% events med AI-titel
   senaste 7 dagarna".

## Status / nästa steg

**Status:** ej påbörjat — plan/RFC.

**Nästa konkreta steg:**

1. Kör SQL-analysen ovan mot produktion för att verifiera volymer per
   bucket. Uppdatera kostnadsberäkningen med faktiska siffror.
2. Beslut: bara `parsed_title_ai` (lågrisk) eller även slug-byte
   (högrisk, kräver redirect-infra)?
3. Skapa migration + `TitleRewriteService` + kommando med
   `--dry-run`, kör på 20 handplockade events, manuell granskning.
4. Pilot på senaste 30 dagars "sammanfattning"-events.
5. Utvärdera i Search Console efter 2-4 veckor.
6. Bestäm fas 2/3 baserat på data.

**Relaterade todos:** #2 (SEO-review, sitemap saknas — behöver
`display_title`), eventuellt #11 (större SEO-audit).
