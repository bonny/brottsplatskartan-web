**Status:** aktiv (designfas — plan klar 2026-04-26, nästa: kör Fas 0 baseline)
**Senast uppdaterad:** 2026-04-26
**Blocker för:** #10 (AI-titlar), #27 Lager 3 (AI-månadssammanfattningar)

# Todo #28 — Migrera AI-stack till `laravel/ai` (officiell SDK)

## Varför

Nuvarande AI-stack är pre-1.0 third-party-SDK utan moderna features:

```
"claude-php/claude-php-sdk": "^0.5.1"
DEFAULT_API_VERSION = '2023-06-01'   (gammal Anthropic API-version)
SDK_VERSION = '0.1.0'
```

Begränsningar:

- **Ingen structured output** — manuell prompt-engineering för att få
  formaterat svar tillbaka. Sköra mot LLM-uppdateringar.
- **Bara Anthropic** — om Claude API är nere har vi ingen fallback.
- **Ingen agents/tools** — varje use case kräver egen prompt-byggar-kod.
- **Pre-1.0** — semver-instabilt, riskabelt att skala på.

`laravel/ai` shippade stable 2026-03-17 tillsammans med Laravel 13.
Bygger på `prism-php/prism` under huven. Förstapartspaket → mer säker
satsning än egen tredjepartsintegration.

## Vad `laravel/ai` ger oss

| Feature                                 | Direkt nytta                                                        |
| --------------------------------------- | ------------------------------------------------------------------- |
| **Structured output** via JSON schema   | Garanterat formaterad titel-output för #10 (slipper "Rubrik: "/"Text: "-parsing) |
| **Agents** (instructions + tools)       | Naturlig form för #27 Lager 3 (månadssammanfattningar)              |
| **Provider-failover**                   | Auto-fallback om Anthropic är nere                                  |
| **`#[UseCheapestModel]` / `Smartest`**  | Per use case — billig för titel-omskrivning, dyr för sammanfattning |
| **Embeddings + SimilaritySearch**       | Framtidsfeature: "Liknande events"-funktion via pgvector            |
| **Streaming + queueing**                | Bakgrundsjobb för bulk-omskrivning av 53k titlar                    |
| **`make:agent` / `make:tool`-kommando** | Skaffold-stöd                                                       |
| **Inbyggd test-stöd**                   | Faking + assertions                                                 |

## Befintlig AI-användning som ska migreras

| Use case                       | Klass / Command                                       | Modell-anrop / Frekvens                          |
| ------------------------------ | ----------------------------------------------------- | ------------------------------------------------ |
| **Daglig sammanfattning**      | `App\Services\AISummaryService::generateDailySummary` | Claude direkt, var 30 min för Stockholm + 06:00 igår-batch |
| **Per-event titel-omskrivning** | `App\Console\Commands\CreateAISummary::generateSummary` | Claude direkt, var 5 min via `CreateAISummaries` (Stockholm-events utan `title_alt_1`) |
| **Visning av AI-text**         | `CrimeEvent::getParsedContentAlt1()` + `title_alt_1`-kolumn | Bara DB-läsning, ingen API-anrop                 |

Båda är `claude-sonnet-4-5-20250929` enligt `config/services.php`.

## Plan — verifiera samma resultat + utvärdera modell + prompt

Användarens krav (2026-04-26): vi ska kunna **verifiera att vi får
samma eller likvärdigt resultat** efter migration, **utvärdera om vi
ska byta AI-modell**, och **se över prompten**.

Tre faser för att svara på det utan att riva ut nuvarande kod i förtid:

### Fas 0 — Baseline (1 timme, ingen kodändring)

Snapshot av nuvarande output innan vi rör något. Lagras som
referens i `tmp-ai-baseline/` (gitignored).

1. **Plocka 10 referensdagar** med blandad volym för Stockholm-summering:
   - 3 lågvolym (1-3 events) — ex. nyår, midsommar, torsdag januari
   - 4 medel (5-15 events) — vanlig vardag
   - 3 högvolym (20+ events) — fredag/lördag, storhelg
   - SQL: `SELECT summary_date, events_count FROM daily_summaries
     WHERE area='stockholm' ORDER BY events_count DESC` → välj sample
2. **Plocka 20 referensevent** för titel-omskrivning:
   - 10 där vi redan har `title_alt_1` (= jämför mot existerande output)
   - 10 nya/kommande där `title_alt_1` är null
3. **Spara baseline** som JSON i `tmp-ai-baseline/`:
   ```json
   {
     "kind": "daily_summary",
     "input": { "area": "stockholm", "date": "2026-03-15", "events_data": [...] },
     "output": "Markdown-text från DB",
     "model": "claude-sonnet-4-5",
     "prompt_version": "current",
     "captured_at": "2026-04-26T16:00:00Z"
   }
   ```
4. **Mät kostnad** för 28 dagars körning från Anthropic-konsolen
   (input/output tokens + total $). Ger oss riktmärke.

**Output:** `tmp-ai-baseline/{daily,title}_*.json` + sammanfattnings-tabell
i `tmp-ai-baseline/README.md`.

### Fas 1 — Modell-utvärdering (4 timmar, ingen migration)

Innan vi byter SDK avgör vi om vi ska byta modell. Görs via direkt
API-anrop med samma prompt mot olika modeller. Inget produktions-
beroende ändras.

**Kandidater:**

| Modell                        | Input / Output ($/M tok) | Svenska kvalitet | Vår use case-passning |
| ----------------------------- | -----------------------: | ---------------: | --------------------- |
| Claude Sonnet 4.5 (idag)      |             $3 / $15     |        Excellent | Overkill för titel + sammanfattning |
| **Claude Haiku 4.5**          |             $1 / $5      |        Excellent | Sweet spot för båda use case |
| Claude Opus 4.7               |            $15 / $75     |        Excellent | Bara om kvalitet brister i Haiku |
| Gemini 2.5 Flash              |       $0.30 / $2.50      |             Good | Mycket billigt, ngn risk för Sv-kvalitet |
| GPT-4o-mini                   |            $0.15 / $0.60 |        Adequate  | Billigt men sämre Sv än Anthropic |

**Beslut-rationale:**

- Sonnet är overkill för en formaterings-/sammanfattningsuppgift med
  fast struktur. Haiku 4.5 är 3× billigare på input, 3× på output, med
  i princip lika bra svenska för standard-prosa.
- Anthropic-modeller har tonen vi vill ha (saklig, neutral, inte
  sensationalistisk). GPT-modeller är ofta för "amerikanska" i ton.
- För en svensk nyhetssajt vill vi inte byta till Gemini/GPT som
  default — risk för subtila tonskift som lockar ut hallucinationer
  eller pratigare output. Använd dem max som **failover**.

**Föreslaget beslut:** byt till **Haiku 4.5** som default för båda
use case. Sonnet kvar som opt-in via `CLAUDE_MODEL` om kvalitet brister.

**Kostnadsestimat per månad (vid Haiku):**

| Use case          | Calls/mån | Tokens in/ut | Kostnad Sonnet | Kostnad Haiku |
| ----------------- | --------: | -----------: | -------------: | ------------: |
| Daglig samman-Stockholm   |  ~1500   | ~5k / 0.4k   |  ~$30          |  ~$10         |
| Titel-omskrivning |   ~3000  |  ~0.4k / 0.2k |  ~$13          |   ~$4         |
| **Totalt**        |          |              |  **~$43/mån**  |  **~$14/mån** |

≈ $30/mån besparing. Skalar mer när #10 backfillar 50k titlar (#10 är
~$27 engångskostnad med Sonnet → $8 med Haiku).

**Verifierings-procedur:**

1. Skriv en throwaway-script `tmp-ai-baseline/compare_models.php`:
   ```php
   foreach ($models as $model) {
       foreach ($referenceCases as $case) {
           $output = callClaude($case->input, $model, $currentPrompt);
           file_put_contents("tmp-ai-baseline/{$model}/{$case->id}.txt", $output);
       }
   }
   ```
2. **Auto-mätning:** kör diff-script som beräknar:
   - Längd (ord, tecken)
   - Antal markdown-länkar `[text](url)`
   - Andel länkar med korrekt URL från event-listan
   - Tonalitet via enkel signaler-list ("hemskt", "fruktansvärt", "skandal" etc — sensationsord får inte öka)
3. **Manuell genomgång:** öppna båda outputs i Markdown-renderare,
   blind-betygsätt 5 par på 5-skala (Haiku vs Sonnet). Bara mig som
   bedömer — inget A/B-test mot användare.
4. **Acceptanskriterium:** Haiku ≥ 4/5 på blind-betygsättning + ingen
   regression i auto-mätning.

### Fas 2 — Prompt-genomgång (2 timmar)

Nuvarande prompts har tre konkreta problem:

#### Problem 1: ALL CAPS-instruktioner

`AISummaryService::buildPrompt()` har `VIKTIGT: ...` flera gånger.
Modern Claude svarar **bättre** på tydliga, neutrala instruktioner än
på shouting. ALL CAPS signalerar inte "viktigare" för modellen — bara
för människor.

**Fix:** ersätt med XML-taggar för struktur:

```xml
<rules>
  <rule>Inkludera ingen rubrik eller titel — skriv bara brödtexten direkt.</rule>
  <rule>Alla händelser som nämns måste få en klickbar länk.</rule>
  <rule>Använd markdown: [beskrivande text](url)</rule>
</rules>
```

#### Problem 2: System vs user prompt blandas

`AISummaryService` skickar allt som user-content. `CreateAISummary`
gör det rätt med `system: ...`-fält. Konsekvent system-prompt minskar
prompt-injection-risk och låter modellen fokusera bättre.

**Fix:** flytta "Du är en svensk nyhetsredaktör..." + alla `<rules>`
till system-prompten. User-prompten innehåller bara `<events>` + ev.
metadata.

#### Problem 3: "Rubrik: " / "Text: "-parsing är skör

`CreateAISummary` parsar output med `strpos($line, 'Rubrik: ')`.
Bryts om Claude skriver "**Rubrik:** " (markdown) eller "Rubriken: ".

**Fix:** structured output via `laravel/ai`:

```php
class EventTitleRewriter implements Agent {
    public function schema(JsonSchema $s): array {
        return [
            'title' => $s->string()->maxLength(60)->required(),
            'description' => $s->string()->required(),
            'reasoning' => $s->string()->required(),  // för logging/debug
        ];
    }
}
```

Inga regex-fel någonsin igen. Garanterat 60 chars för SEO-titel.

#### Bonus: max_tokens-avstämning

`AISummaryService` har `max_tokens: 8192` men output är ~300 tokens.
Förändras inget (det är ett tak, inte ett mål) men sätt till 1500 så
det matchar verkligheten — slipper se siffran och tro vi använder mer.

#### Few-shot examples (övervägs, inte beslutat)

Vi kan lägga till 1-2 exempel på "bra output" i system-prompten:

```xml
<example>
  <events>...</events>
  <output>Vid 14-tiden inträffade ett [rån mot en pizzeria](url)...</output>
</example>
```

**För:** mer konsistent ton, kortare instruktion (modellen härmar)
**Mot:** ökar input-token-kostnaden ~10 %, kan göra modellen för mallig

**Beslut:** vänta — kör utan first, lägg till om Haiku visar
ton-instabilitet.

### Fas 3 — Migration till `laravel/ai` (1 dag)

Efter Fas 0-2 är vi klara med data; nu byter vi SDK utan att ändra
beteendet.

```bash
docker compose exec -u root app composer require laravel/ai
docker compose exec app php artisan vendor:publish --provider="Laravel\Ai\AiServiceProvider"
docker compose exec app php artisan migrate
```

#### 3a. Skapa `App\Ai\Agents\DailySummaryAgent`

```php
namespace App\Ai\Agents;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;
use Laravel\Ai\Attributes\{Provider, Model, MaxTokens, Temperature};
use Laravel\Ai\Enums\Lab;

#[Provider(Lab::Anthropic)]
#[Model('claude-haiku-4-5-20251001')]   // Fas 1-beslut
#[MaxTokens(1500)]
#[Temperature(0.5)]                     // ner från 0.7 — saklig text behöver inte mycket variation
class DailySummaryAgent implements Agent
{
    use Promptable;

    public function instructions(): string
    {
        return view('ai.prompts.daily-summary')->render();
    }
}
```

Lägg system-prompten i en Blade-fil `resources/views/ai/prompts/daily-summary.blade.php`
så den är versionshanterad och lätt att diffa.

#### 3b. Skapa `App\Ai\Agents\EventTitleRewriter` med structured output

```php
#[Model('claude-haiku-4-5-20251001')]
#[MaxTokens(800)]
class EventTitleRewriter implements Agent, HasStructuredOutput {
    public function schema(JsonSchema $s): array {
        return [
            'title' => $s->string()->maxLength(60)->required(),
            'description' => $s->string()->required(),
        ];
    }
}
```

#### 3c. Refaktorera `AISummaryService` till tunn wrapper

Bevarar publik metod `generateDailySummary($area, $date)` så
schedulern inte bryts:

```php
public function generateDailySummary(string $area, Carbon $date): array
{
    $events = $this->getEventsForDate($area, $date);
    if ($events->isEmpty()) return ['summary' => null, 'ai_generated' => false];

    $existing = DailySummary::where(...)->first();
    if ($existing && $this->eventsUnchanged($events, $existing)) {
        return ['summary' => $existing, 'ai_generated' => false];
    }

    $summary = (new DailySummaryAgent)->prompt([
        'area' => $area,
        'date' => $date,
        'events' => $events,
    ])->text();

    // ... DailySummary::updateOrCreate(...) som idag
}
```

#### 3d. Refaktorera `CreateAISummary` till agent-anrop

```php
$result = (new EventTitleRewriter)->prompt([
    'type' => $crimeEvent->parsed_title,
    'headline' => $crimeEvent->parsed_teaser,
    'body' => strip_tags($crimeEvent->parsed_content),
])->structured();   // returnerar array med title + description

$crimeEvent->title_alt_1 = $result['title'];
$crimeEvent->description_alt_1 = $result['description'];
$crimeEvent->save();
```

#### 3e. Ta bort gamla SDK:n

```bash
docker compose exec -u root app composer remove claude-php/claude-php-sdk
```

Verifiera ingen kvarstående referens:
```bash
git grep -n 'ClaudePhp\|claude-php-sdk'
```

#### 3f. Konfigurera failover (skip i Fas 3 — överväg senare)

Standardiserat på Anthropic. Lägg till `OPENAI_API_KEY` om/när
Anthropic-incidenter blir vanliga.

### Fas 4 — Verifiera samma resultat (2 timmar)

Kör samma 10 dagars-input + 20 event-input genom NYA agenten. Spara i
`tmp-ai-baseline/post-migration/` med samma format som baseline.

**Auto-jämförelse:**
```bash
php artisan tinker
> // skript som diffar baseline vs post-migration på:
> //   - längd-deviation (acceptera ±20 %)
> //   - markdown-länkar (acceptera ±1)
> //   - sensationsord-räkning (får inte öka)
> //   - ord-överlapp på fakta (>70 % överlapp på platser/typer)
```

**Manuell genomgång:** 5 dags-summeringar + 5 titel-omskrivningar
side-by-side. Blind betyg gamla vs nya på:

- Faktakorrekthet (kritiskt — får inte regressera)
- Tonalitet (saklig, inte sensationalistisk)
- SEO-vänlig (specifika platser, brottstyper, ej för korta)
- Markdown-länkar funkar (URL-fältet matchar verkliga events)

**Go/no-go-kriterium:** Haiku-output ska bedömas ≥ baseline på
faktakorrekthet (alltid). Andra dimensioner får regressera ≤ 1 steg
av 5 i medeltal. Annars: tillbaka till Sonnet, behåll prompten.

### Fas 5 — Tester (1 timme)

`Laravel\Ai\Testing\Fake` ger oss enheter utan API-anrop:

```php
public function test_daily_summary_genererar_text() {
    Ai::fake(['Mock-output med [link](url)']);
    $service = new AISummaryService();
    $result = $service->generateDailySummary('stockholm', now());
    $this->assertNotNull($result['summary']);
    Ai::assertCalledTimes(1);
}
```

Cover:
- DailySummaryAgent: empty events, single event, multi-day events
- EventTitleRewriter: short content, long content, structured output validation
- AISummaryService: cached path (events_unchanged) skippar API-anrop

### Fas 6 — Deploy + observera (1 dag passiv)

1. Deploy via `git push main` → GitHub Actions
2. Soak 24 h, mät:
   - `docker compose logs scheduler | grep summary:generate` — felmeddelanden?
   - DB: `SELECT * FROM daily_summaries WHERE summary_date = today()` — ny rad genereras?
   - DB: `SELECT COUNT(*) FROM crime_events WHERE title_alt_1 IS NOT NULL AND created_at > now() - interval 1 day` — titlar omskrivs?
   - Anthropic-konsolen: kostnad/dag — bekräfta nedgång ~3×
3. Kvalitativ läsning: läs 3-5 nya AI-summeringar manuellt — låter de OK?

## Risker

- **Schemafel i provider-konfiguration.** `php artisan migrate` skapar
  troligen tabeller för conversations + embeddings. Kolla att inga
  konflikter med befintliga tabellnamn.
- **API-version-skillnad.** `claude-php-sdk` använde `2023-06-01`.
  `laravel/ai` använder senaste — ändrade response-format kan kräva
  prompt-justering för `DailySummaryAgent`.
- **Cost-spike om Smartest-modellen råkar laddas.** Sätt explicit
  `#[Model(...)]` på alla agenter.
- **Prompt-fel som ger garbage output.** Mitigera via Fas 4 (verifierar
  innan deploy).
- **Schedule-jobb hängs vid timeout.** `laravel/ai` har default-timeout
  (~120 s); var 30:e minut → 30 jobb/dag, om 5 % failar är det 1.5/dag.
  Lägg till `->onFailure(fn() => Log::error(...))` i scheduler.
- **Kvalitetsregression utan att vi märker det.** Mitigera via Fas 6
  manuell läsning, sätt rolling alert om `summary` < 100 chars.

## Tester före deploy

- `composer analyse` (PHPStan level 5) ska vara grön
- Fas 4 auto-jämförelse: alla acceptanskriterier uppfyllda
- Fas 5 unit tests: alla gröna
- Lokalt: `php artisan summary:generate stockholm --date=2026-04-25` — output ser likvärdigt ut

## Tid

| Fas        | Insats   |
| ---------- | -------- |
| Fas 0      | 1 h      |
| Fas 1      | 4 h      |
| Fas 2      | 2 h      |
| Fas 3      | 1 dag    |
| Fas 4      | 2 h      |
| Fas 5      | 1 h      |
| Fas 6      | passiv (observera 24 h) |
| **Total**  | **~2 dagar aktiv** |

## Beroenden

- Inga blockers — kan starta direkt.
- Blocker FÖR: #10 (AI-titlar) — bygger på `EventTitleRewriter`-agenten.
  Innan #28 är klar kan vi inte effektivt skala #10:s 50k-titel-backfill.
- Blocker FÖR: #27 Lager 3 (månadssammanfattningar) — bygger på agent-mönstret.

## Status

Plan klar 2026-04-26. Nästa: kör Fas 0 (baseline-snapshot, 1 h) när
implementationsfönster öppnas.

## Referenser

- <https://laravel.com/docs/13.x/ai-sdk>
- <https://github.com/laravel/ai>
- <https://prismphp.com/> (underliggande paket)
- Tidigare implementation: `app/Services/AISummaryService.php`
- Pricing: <https://www.anthropic.com/pricing> (Haiku 4.5)
