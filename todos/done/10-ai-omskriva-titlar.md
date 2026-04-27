**Status:** klar 2026-04-27 — rendering aktiverad på 4596 prod-events (Schema headline + `<title>` + OG + meta), auto-trigger för hela Sverige via scheduler (var 15 min, --vague-only), transparens-rad på /sida/om. Mätning hänvisas till #36.
**Senast uppdaterad:** 2026-04-27

# Todo #10 — AI-omskrivning av vaga event-titlar

## Sammanfattning

Polisens RSS-flöden producerar ibland generiska rubriker som
"Sammanfattning natt, region Nord" eller "Information om polisens
pressnummer" — texter med noll SEO-värde och dålig CTR. `parsed_title`
härleds direkt ur RSS-titeln (mellan komma 1 och sista kommat) och blir
därför lika tom.

**Förslag:** kör en AI-rewrite (Claude Haiku via `laravel/ai`, klart
sedan #28) på body-texten (`parsed_content`) och spara resultatet i en
**ny** kolumn `parsed_title_ai` — utan att röra `parsed_title` eller
existerande permalinks. Slug-generering rör vi inte; detta är en
rendering-uppgradering, inte URL-byte.

## Scope

**Geografi:** Alla orter. Filter är på _titel-kvalitet_ (regex), inte
stad/län. Vaga titlar är lika SEO-skadliga i Sundsvall som i Uppsala.

**Tidsfönster:** Bara nya events från och med deploy. Bakåtkatalog
(~200k events) skippad i denna iteration — vi mäter värdet på nya
events först. Om GSC visar CTR-vinst efter 4-8 veckor återöppnas
backfill som separat todo.

**Modeller:** Bara `CrimeEvent`. `VMAAlert` har egen titelparsing och
rörs inte.

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

### Användning av `parsed_title` (alla ställen som måste hanteras)

- **Slug/permalink** (`CrimeEvent::getPermalink()`) — **rör inte**.
- **`<title>`/`<h1>`** på event-sidan — byt till `display_title`.
- **Schema.org `headline`** i `CrimeEvent::buildLdJson()` — byt till
  `display_title` (synergi med #32, se "Teknisk plan #7").
- **Sökning** (LIKE) — utöka till **båda** kolumnerna.
- **Tweet-generering** (`TweetCrimes.php`) — byt till `display_title`.
- **Feeds/RSS-output** (`FeedController`) — byt till `display_title`.
- **Markdown-output** (#12) — byt till `display_title`.
- **API-svar** (`ApiController`, `ApiEventsMapController`) — **behåll
  `parsed_title` bakåtkompat**, lägg till `display_title` som nytt fält.
- **Kategoriserings-heuristik** (rad 1252, 1274 — `Str::contains`) —
  **rör inte** (kör mot rå källa, se risk #7).

### ContentFilter — relaterad men inte samma sak

`app/Services/ContentFilterService.php` markerar presstalesperson- och
pressnummer-rena händelser som `is_public = false` (helt dolda).
Presstalesperson-filtret är f.n. avaktiverat (rad 23-29) eftersom det
råkade blockera `sammanfattning natt`-poster med riktig händelsedata.
Det är precis de här som AI-omskrivning ska rädda — de **visas**, men
med bättre titel.

## Mönster — vaga titlar att fånga

SQL för att kontrollera volym (kör som dry-run mot prod):

```sql
SELECT
  CASE
    WHEN parsed_title REGEXP '(?i)sammanfattning ?(natt|dygn|morgon|kväll|dag)' THEN 'sammanfattning'
    WHEN parsed_title REGEXP '(?i)information om polisens pressnummer' THEN 'pressnummer'
    WHEN parsed_title REGEXP '(?i)presstalesperson' THEN 'presstalesperson'
    WHEN parsed_title REGEXP '(?i)^(övrigt|annat|händelse)$' THEN 'generisk'
    WHEN CHAR_LENGTH(parsed_title) < 6 THEN 'för-kort'
    ELSE NULL
  END AS bucket,
  COUNT(*) c
FROM crime_events
WHERE is_public = 1 AND parsed_date > NOW() - INTERVAL 30 DAY
GROUP BY bucket
ORDER BY c DESC;
```

Mönster:

| Bucket           | Regex                                                         | Volym 30d (2026-04-27)         | Andel  |
| ---------------- | ------------------------------------------------------------- | ------------------------------ | ------ |
| sammanfattning   | `(?i)sammanfattning ?(natt\|dygn\|morgon\|kväll\|dag)`        | 358                            | 18.3 % |
| för-kort         | `CHAR_LENGTH < 6` (typ "Brand", "Stöld" — legit men SEO-svag) | 255                            | 13.1 % |
| generisk         | `(?i)^(övrigt\|annat\|händelse)$`                             | 113                            | 5.8 %  |
| pressnummer      | `(?i)information om polisens pressnummer`                     | 0 (filtreras av ContentFilter) | —      |
| presstalesperson | `(?i)(dagens )?presstalesperson`                              | 0 (filtreras av ContentFilter) | —      |

**Total vag = 37.2 % av events** (726 av 1954 senaste 30d). Pressnummer/presstalesperson markeras som
`is_public = 0` av `ContentFilterService` och dyker aldrig upp — kan tas bort som bucket men behålls
i regex för defensivt skydd.

**Stickprovs-findings (5-10 ex per bucket, 2026-04-27):**

- **`sammanfattning`** — alla är identiska ("Sammanfattning natt" / "Sammanfattning kväll och natt"),
  lokation = "X län", body 0-1861 tecken. Identiska titlar = max SEO-skada, max AI-vinst.
- **`för-kort`** — alla är legit korta brottstyper ("Brand", "Stöld") med rikt body-innehåll
  (88-558 tecken) och specifika orter ("Stockholm", "Uppsala", "Göteborg"). **Detta är guldgruvan** —
  det finns innehåll att skriva en bra rubrik från, och AI-rewrite ger dramatiskt bättre SERP.
- **`generisk`** — alla "Övrigt", body varierar (0-302 tecken). Vissa går att rewrite:a, andra inte.

## Teknisk plan

### 0. Pre-filter — billig regex-check innan AI-anrop

AI:n körs **bara** på events där `parsed_title` matchar ett vagt
mönster. Allt annat skippas helt — ingen API-kostnad, ingen latens.

Två steg: (1) klassificera titel, (2) verifiera att body är användbar.

```php
private const MIN_BODY_LENGTH = 100;

public static function isVagueTitle(?string $title): ?string
{
    if ($title === null) return 'tom';
    $t = trim($title);
    return match (true) {
        preg_match('/(?i)sammanfattning ?(natt|dygn|morgon|kväll|dag)/', $t) === 1 => 'sammanfattning',
        preg_match('/(?i)information om polisens pressnummer/', $t) === 1 => 'pressnummer',
        preg_match('/(?i)(dagens )?presstalesperson/', $t) === 1 => 'presstalesperson',
        preg_match('/(?i)^(övrigt|annat|händelse)$/', $t) === 1 => 'generisk',
        mb_strlen($t) < 6 => 'för-kort',
        default => null,
    };
}

public static function shouldRewrite(CrimeEvent $event): ?string
{
    $bucket = self::isVagueTitle($event->parsed_title);
    if ($bucket === null) return null;
    if (mb_strlen(trim($event->parsed_content ?? '')) < self::MIN_BODY_LENGTH) {
        // Ingen text att basera ny rubrik på — AI skulle bara svara
        // RUBRIK_OMÖJLIG. Spara anropet.
        return null;
    }
    return $bucket;
}
```

Anrop i fetch-pipelinen:

```php
$bucket = TitleRewriteService::shouldRewrite($event);
if ($bucket === null) {
    return; // titeln OK eller body för tunn — skippa AI helt
}
$ai = $rewriter->rewriteTitle($event, $bucket);
```

Effekt (verifierat mot prod 2026-04-27):

- ~63 % av nya events har redan OK titel — skippas direkt.
- ~37 % matchar vagt mönster, varav en del faller på body < 100 tecken
  (verifierat: vissa "Sammanfattning natt" och "Övrigt"-events har body=0).
- Faktisk AI-anropsvolym: ~20 events/dag. `bucket`-värdet skickas vidare
  till prompten så modellen vet _varför_ titeln är vag.

### 1. Migration

```php
Schema::table('crime_events', function (Blueprint $table) {
    $table->string('parsed_title_ai', 255)->nullable()->after('parsed_title');
    $table->timestamp('parsed_title_ai_at')->nullable();
    $table->string('parsed_title_ai_model', 64)->nullable();
    $table->index('parsed_title_ai_at');
});
```

Ingen ändring av `parsed_title` — den är läskälla för alla gamla
permalinks och fallback.

### 2. Accessor i `CrimeEvent`

```php
public function getDisplayTitleAttribute(): string
{
    return $this->parsed_title_ai ?: $this->parsed_title ?: 'Polishändelse';
}
```

Använd `$event->display_title` i alla ställen i listan ovan utom de
som explicit ska röra rå källan.

### 3. Service + Kommando

`app/Services/TitleRewriteService.php`:

- Använder `laravel/ai` (samma stack som migrerades i #28).
- Modell: `claude-haiku-4-5` (snabb, billig, klarar uppgiften).
- **Prompt caching aktiverad** på system-prompten — halverar
  input-kostnad. Kräver att system-prompt är samma sträng över alla
  anrop, så håll den statisk i en konstant.
- Validering (efter modellsvar):
    - 5–110 tecken (matchar Schema.org `headline`-cap från #32)
    - Inga newlines, inga citattecken, inga emojis
    - Börjar med versal
    - Ingen ", region X"-suffix
    - **Hallu-skydd:** rubriken måste innehålla minst ett ord ≥4
      tecken från ENDERA `parsed_content`, `parsed_title_location`
      eller `Dictionary`-tabellen (brottskategorier)
- Misslyckad validering eller `RUBRIK_OMÖJLIG` → returnera `null`
  → `display_title` faller transparent tillbaka till `parsed_title`.

`app/Console/Commands/RewriteTitles.php` (`crimeevents:rewrite-titles`):

```
--limit=<n>           default 100 (för manuell körning vid behov)
--pattern=<bucket>    sammanfattning|pressnummer|all (default: all)
--dry-run             skriv inte till DB, visa diff
--force               kör om även events där parsed_title_ai redan satt
```

**Ingen scheduler-entry för backfill** — bara on-demand vid behov.

### 4. Integration i fetch-pipeline

I `FetchEvents::handle()`, **efter** `ContentFilterService`, kör
`TitleRewriteService` på nya events vars `parsed_title` matchar ett
vagt mönster.

- Wrap i try/catch + 10s timeout — får aldrig blockera fetch-cykeln.
- Misslyckas: logga + behåll `parsed_title_ai = null`, fallback funkar
  transparent.
- Idempotens: skip om `parsed_title_ai` redan satt (om inte `--force`).
- Rate limit: Anthropic Tier 1 = 50 RPM Haiku. Med ~100 vaga events
  per dygn ligger vi tre storleksordningar under taket.

### 5. Slug & 301-redirect

**Default: rör inte sluggen.** `parsed_title_ai` påverkar bara
rendering. Zero-risk för gamla länkar.

Slug-byte för nya events är möjligt i en framtida fas men inte
inkluderat här — det kräver redirect-infrastruktur som inte ger
proportional vinst i fas 1.

### 6. ~~Sitemap~~

Sitemap finns sedan #11 / SEO-audit 2026. När `display_title` används
i Blade-vyerna kommer den automatiskt med — inget extra arbete.

### 7. Synergi med #32 (Schema.org NewsArticle)

`app/CrimeEvent.php::buildLdJson()` skriver idag `headline` från
`parsed_title`. **Måste byta till `display_title`** annars läcker halva
SEO-vinsten — Schema headline är en stark signal till Google.

```php
// Före:
"headline" => mb_substr($title, 0, 110),

// Efter:
"headline" => mb_substr($this->display_title, 0, 110),
```

`getLdJson()` cachas sedan #32 — cache-nyckeln inkluderar `updated_at`.
När `parsed_title_ai` sätts måste vi antingen touch-a `updated_at`
eller lägga `parsed_title_ai_at` i cache-nyckeln. **Rekommendation:**
lägg till `parsed_title_ai_at` i `getLdJson()`-cache-nyckeln så är det
deterministiskt.

### 8. Transparens på /sida/om

Lägg en kort mening:

> Titlar på vissa polishändelser har förbättrats med AI för läsbarhet.
> Originaltexten från Polisens RSS bevaras alltid och visas under
> varje händelse.

Inget per-event-attribution behövs — det är en system-egenskap, inte
redaktionellt innehåll.

## Prompt-design

```
Du är en svensk nyhetsredaktör på Brottsplatskartan.se. Skriv en kort,
saklig och SEO-vänlig rubrik för följande polishändelse.

KRAV:
- Svenska.
- 80-100 tecken (max 110, för Schema.org headline).
- Börja med brottstyp/händelsetyp (t.ex. "Misshandel", "Trafikolycka",
  "Brand").
- Inkludera ort om den nämns i texten.
- Inkludera ett konkret detaljelement om utrymmet räcker (plats,
  tidpunkt, omfattning) — utan att hitta på.
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

- Modell: `claude-haiku-4-5` via `laravel/ai`.
- `max_tokens: 60`, `temperature: 0.2`.
- Prompt caching aktiverad på system-instruktionerna.

## Kostnad

Per event ~$0.00068 (Haiku 4.5, ~550 input + 25 output tokens). Med
prompt caching halveras input-kostnaden.

Volymdata från prod 2026-04-27 (senaste 30d):

| Volym                                                                          | Kostnad   |
| ------------------------------------------------------------------------------ | --------- |
| Nya events ~65/dag, ~37 % vaga, ~80 % har body ≥ 100 tecken → ~20 AI-anrop/dag | ~60 kr/år |
| Med prompt caching aktiverad (~50 % rabatt på input)                           | ~30 kr/år |

Försumbar. Flaskhals är inte pengar utan API rate-limits — och med
~20 anrop per dygn ligger vi fyra storleksordningar under taket.

## Mätning (post-deploy)

Använd `mcp-gsc` (klar sedan #26) — `compare_search_periods`:

- 30d före deploy vs 30d efter
- Filter: URL-mönster som matchar event-sidor med vaga
  originaltitlar (sökbara via `parsed_title_ai IS NOT NULL`)
- Mätvärden: impressions, clicks, CTR, position
- Förväntad signal: ↑ CTR på events som fått AI-titel

Tröskel för att överväga backfill (fas 2): tydlig CTR-vinst (>20 %)
inom 8 veckor.

## Risker

1. **AI-hallucination** — modellen hittar på platser/detaljer.
   _Mitigering:_ hallu-skydd ovan + `RUBRIK_OMÖJLIG` + behåll original.
2. **Slug-drift & 404** — _N/A:_ sluggen rörs inte i denna fas.
3. **Duplicerat innehåll** — likadana AI-titlar för liknande händelser.
   _Mitigering:_ `temperature: 0.2` + body-texten varierar ändå +
   prompt instruerar att inkludera ett detaljelement.
4. **Kostnadsrusning vid bugg** — _Mitigering:_ `withoutOverlapping`,
   hard cap per körning, ingen backfill-loop.
5. **Polisen-text copyright** — texten är offentlig, vi replikerar
   ingenting mer än vad vi redan gör. Dokumentation på `/sida/om`.
6. **Tonalitets-drift** — AI mer "klickbete" än Polisens nyktra stil.
   _Mitigering:_ prompt trycker på "saklig, ingen sensationalism" +
   manuell stickprovsgranskning första veckan.
7. **Heuristik-konflikt** — `Str::contains($parsed_title, 'inbrott')`
   på rad 1252/1274 används för kategori-ikon. _Lösning:_ heuristiken
   körs mot **`parsed_title` (rå källa)**, rendering visar
   **`display_title`**. Får aldrig blandas.
8. **VMA-events** — `VMAAlert` har egen titelparsing. _Mitigering:_
   trigger körs **bara** i `CrimeEvent`-pipeline, `VMAAlert` rörs inte.
9. **`getLdJson()`-cache stale efter rewrite** — cache-nyckeln
   inkluderar `updated_at`. _Lösning:_ lägg till `parsed_title_ai_at`
   i cache-nyckeln (en-rads-ändring i `CrimeEvent::getLdJson()`).
10. **Sökning missar ena kolumnen** — om vi bara LIKE:ar mot
    `parsed_title` hittar användaren inte event via AI-titel.
    _Lösning:_ utöka sök-WHERE till båda kolumnerna.

## Fördelar

- **SEO:** klickbara titlar i SERP, högre CTR, bättre Schema.org
  `headline` (synergi med #32).
- **Sociala medier:** Twitter/OG-kort blir läsbara.
- **Tillgänglighet:** skärmläsare får meningsfulla länktexter.
- **Markdown/LLM-output (#12):** AI-titel automatiskt med.
- **Backward-compatible:** ingen URL-migrering, API behåller
  `parsed_title`.

## Öppna frågor

1. ~~Slug för nya events?~~ — Skippat i fas 1, evaluera fas 2.
2. ~~Spara hela AI-svaret eller bara strängen?~~ — Bara strängen.
3. Manuell override-kolumn (`parsed_title_manual`)? — Låg prio, bara
   om vi ser konkreta hallu-fall efter pilot.
4. RSS-feed: använda AI-titel eller behålla `parsed_title`?
   _Rekommendation:_ använd `display_title` också i RSS — RSS-
   konsumenter förväntar sig inte stabila titlar mellan polls.

## Status / nästa steg

**Status:** plan reviewad 2026-04-27, redo för implementation.

**Nästa konkreta steg:**

1. SQL-volymanalys mot prod (bekräfta vagheter per bucket).
2. Migration: `parsed_title_ai`, `parsed_title_ai_at`,
   `parsed_title_ai_model` + index.
3. `CrimeEvent::display_title`-accessor + uppdatera Blade/Schema/Tweet/
   Markdown/sökning. **Behåll `parsed_title` i slug + heuristik + API.**
4. `TitleRewriteService` (med `laravel/ai`, prompt caching, hallu-skydd)
    - `crimeevents:rewrite-titles`-kommando med `--dry-run`.
5. Manuell granskning på 20 handplockade events (dry-run).
6. Aktivera i `FetchEvents`-pipeline (efter ContentFilter).
7. Lägg transparens-rad på `/sida/om`.
8. Mät i GSC efter 4 veckor (`compare_search_periods` via mcp-gsc).

**Synergi med klara todos:**

- **#28** (`laravel/ai`) — service-stacken är redan migrerad.
- **#32** (Schema-sweep) — `headline` i NewsArticle byter till
  `display_title` (fångar halva SEO-vinsten).
- **#26** (mcp-gsc) — automatiserad CTR-mätning.
- **#12** (LLM-optimering) — Markdown-output följer med automatiskt.
