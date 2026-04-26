**Status:** aktiv (designfas)
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

Laravel 13 har `laravel/ai` som officiell, mogen plattform. Utan
migration betalar vi dubbelt för varje ny AI-feature: först bygga
mot pre-1.0 SDK, sedan refactor senare.

## Vad `laravel/ai` ger oss

| Feature                                 | Direkt nytta                                                        |
| --------------------------------------- | ------------------------------------------------------------------- |
| **Structured output** via JSON schema   | Garanterat formaterad titel-output för #10                          |
| **Agents** (instructions + tools)       | Naturlig form för #27 Lager 3 (månadssammanfattningar)              |
| **Provider-failover**                   | Auto-fallback om Anthropic är nere                                  |
| **`#[UseCheapestModel]` / `Smartest`**  | Per use case — billig för titel-omskrivning, dyr för sammanfattning |
| **Embeddings + SimilaritySearch**       | Framtidsfeature: "Liknande events"-funktion via pgvector            |
| **Streaming + queueing**                | Bakgrundsjobb för bulk-omskrivning av 53k titlar                    |
| **`make:agent` / `make:tool`-kommando** | Skaffold-stöd                                                       |
| **Inbyggd test-stöd**                   | Faking + assertions                                                 |

## Befintlig AI-användning som ska migreras

Files som har Claude-anrop idag:

- `app/Services/AISummaryService.php` — kärnservice
- `app/Console/Commands/CreateAISummary.php` — manuell trigger
- `app/Console/Commands/GenerateDailySummary.php` — schedulerat jobb
- `app/Models/DailySummary.php` — datamodell (oförändrad)
- `app/CrimeEvent.php:487` — `getAITextAlternative1()` (om den ringer LLM)

## Implementation

### 1. Setup

```bash
composer require laravel/ai
composer remove claude-php/claude-php-sdk
php artisan vendor:publish --provider="Laravel\Ai\AiServiceProvider"
php artisan migrate
```

`.env` har redan `CLAUDE_API_KEY` — mappa till `ANTHROPIC_API_KEY` som
SDK:n förväntar sig (eller behåll `CLAUDE_API_KEY` och konfigurera
custom).

### 2. Skapa `DailySummaryAgent`

```php
namespace App\Ai\Agents;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;
use Laravel\Ai\Attributes\{Provider, Model, MaxTokens, Temperature};
use Laravel\Ai\Enums\Lab;

#[Provider(Lab::Anthropic)]
#[Model('claude-haiku-4-5-20251001')]   // billig för dagliga sammanfattningar
#[MaxTokens(2000)]
#[Temperature(0.7)]
class DailySummaryAgent implements Agent
{
    use Promptable;

    public function instructions(): string
    {
        return 'Du sammanfattar polishändelser från Brottsplatskartan…';
    }
}
```

### 3. Refaktorera `AISummaryService`

Tunnt wrappar agenten — bevarar API:et utåt mot
`GenerateDailySummary`-kommandot så schedulet inte bryts.

### 4. Skriv `EventTitleRewriterAgent` (förbereder #10)

Med structured output:

```php
class EventTitleRewriterAgent implements Agent, HasStructuredOutput
{
    public function schema(JsonSchema $schema): array {
        return [
            'title' => $schema->string()->maxLength(60)->required(),
            'reasoning' => $schema->string()->required(),  // för logging
        ];
    }
}
```

Den används inte än — bara förberedd så #10 kan ringa direkt.

### 5. Tester

`Laravel\Ai\Testing\Fake` + assertions. Skriv tester för
`DailySummaryAgent` och `EventTitleRewriterAgent`.

### 6. Verifiera schedulet

`php artisan schedule:list` ska visa `crimeevents:generate-daily-summary`
som vanligt. Kör manuellt för Stockholm: `php artisan
crimeevents:generate-daily-summary stockholm` och bekräfta att
`DailySummary`-tabellen får rad.

## Konfigurera failover (icke-kritiskt men bra)

```php
// config/ai.php
'failover' => [
    'enabled' => true,
    'providers' => ['anthropic', 'openai'],   // Anthropic primary
],
```

Kräver `OPENAI_API_KEY` i `.env` om vi vill aktivera. Kan skippas i
första iterationen — bara Anthropic är OK för nu.

## Risker

- **Schemafel i provider-konfiguration.** `php artisan migrate` skapar
  troligen tabeller för conversations + embeddings. Kolla att inga
  konflikter med befintliga tabellnamn.
- **API-version-skillnad.** `claude-php-sdk` använde `2023-06-01`.
  `laravel/ai` använder senaste — ändrade response-format kan kräva
  prompt-justering för `DailySummaryAgent`.
- **Cost-spike.** `#[UseSmartestModel]` använder dyraste modellen.
  Sätt explicit `#[Model(...)]` på alla agenter för förutsägbar kostnad.
- **`claude-php-sdk` removal kräver att INGEN kod refererar till det.**
  Verifiera med `git grep -n 'ClaudePhp\\|claude-php'` efter migration.

## Tester före deploy

- `composer analyse` (PHPStan level 5) ska vara grön
- `php artisan crimeevents:generate-daily-summary stockholm` ska
  generera ny `DailySummary`-rad
- Inspektera output kvalitativt — innehållet ska vara likvärdigt eller
  bättre än innan

## Tid

1-2 dagar. Mest tid går åt prompt-justeringar för att matcha tidigare
output-stil.

## Status

Designfas. Kör direkt — inga beroenden, ingen risk för befintlig
funktion (vi behåller samma command-API).

## Referenser

- <https://laravel.com/docs/13.x/ai-sdk>
- Laravel AI SDK på Packagist: `laravel/ai`
