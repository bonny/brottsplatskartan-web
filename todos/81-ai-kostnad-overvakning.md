**Status:** aktiv — fas 1 implementerad + verifierad lokalt 2026-05-15, väntar push/deploy + prod-data-soak
**Senast uppdaterad:** 2026-05-15 (fas 1 implementerad: migration + config/ai-pricing.php + LogAiUsage-listener + ai:usage-command + prune-job. PHPStan grön. Lokal smoke-test loggade NewsClassifier-anrop korrekt: 2941 input + 141 output tokens = $0.003646)

# Todo #81 — Håll koll på hur mycket AI-anropen kostar

## Sammanfattning

Vi har 5 AI-agenter (3 Sonnet, 2 Haiku) som anropar Anthropic via `laravel/ai`. Faktura säger ~$10/dygn = ~$150–300/månad och stigande.

**Reviewer-revision 2026-05-15:** `laravel/ai` dispatchar `AgentPrompted`-event efter varje generering (`vendor/laravel/ai/src/Providers/Concerns/GeneratesText.php:82-84`) med både `prompt->agent`, `prompt->model` och `response->usage`. En enda event-listener fångar därför allt — inga call-site-ändringar i de 5 agenterna behövs.

**Prod-data revision 2026-05-15:** Tre tidigare hypoteser visade sig fel mot verkliga prod-siffror:

1. ~~Stockholm-pollen är dominatorn~~ → **Fel.** Stockholm-jobbet gör bara ~5 AI-anrop/dygn (`title_alt_1 IS NULL`-filtret dedupar). Total Sonnet ~27 anrop/dygn — försumbart.
2. ~~Sonnet är drivaren~~ → **Fel.** Det är **NewsClassifier-Haiku** med **~1000 anrop/dygn** (vs scheduler-baserad gissning på ~300).
3. ~~Volym är huvudproblemet~~ → **Fel.** Problemet är att system-prompten på **149 rader** (`resources/views/ai/prompts/news-classify.blade.php`) skickas **okachad** på varje anrop.

Grov kostnadsberäkning som matchar fakturan:

| Källa                            | Anrop/dygn | Snitt input tokens                                 | Snitt kostnad/dygn |
| -------------------------------- | ---------- | -------------------------------------------------- | ------------------ |
| NewsClassifier (Haiku)           | ~1000      | ~5000 (149-rad prompt + artikel)                   | **~$5.00**         |
| EventNewsMatcher (Haiku)         | ~300       | ~7000 (151-rad prompt + event + kandidat-artiklar) | **~$2.50**         |
| Titel-rewrites (Sonnet)          | ~22        | ~1500                                              | ~$0.10             |
| Daily/Monthly summaries (Sonnet) | ~5         | ~10000 (alla dagens events)                        | ~$0.20             |
| **Sannolik total**               |            |                                                    | **~$7.80/dygn**    |

Stämmer rimligt med fakturans $10/dygn (vi kan missa något, t.ex. failover-retries).

**Stora spakar (ordnade efter förväntad effekt):**

1. **Prompt-caching av system-prompt** → 90 % besparing på Haiku-input. Cache-read är $0.10/MTok vs input $1/MTok. Men: `laravel/ai` Anthropic-gateway **saknar write-stöd för `cache_control`** — vi kan bara läsa cache-tokens från response, inte sätta dem på request. Kräver PR mot `laravel/ai` eller bypass.
2. **Krympa system-prompterna** → om 149-rads prompt går att korta till ~30 rader: 80 % besparing. Behöver inget kachande, behöver bara prompt-engineering.
3. **Pre-filtrera bättre** → om regex/keyword-filter kan kasta uppenbart irrelevanta artiklar innan AI: minskar volymen direkt.
4. **Loggern (fas 1)** är fortfarande nödvändig för att bekräfta vad som faktiskt händer + fånga framtida ändringar.

## Bakgrund

### Faktura-läge

Anthropic-fakturan är enda källan idag — laggar och saknar uppdelning per pipeline. Exempel: `Invoice-288726C5-0024.pdf` (2026-05-14) = $10.01 auto-recharge credits. En sådan kommer **var eller varannan dag** → ~$150–300/månad, sannolikt på uppgång efter att fler pipelines deployats:

- #10 hela-Sverige rendering (2026-04-27)
- #64 fas-2 per-plats nyhetsaggregering (2026-05-02)
- #63 fas-1 event↔artikel-matchning (2026-05-12)

### AI-agenter i drift

| Agent                 | Modell            | Hårdkodad i `#[Model(...)]`-attribut      |
| --------------------- | ----------------- | ----------------------------------------- |
| `EventTitleRewriter`  | claude-sonnet-4-6 | `app/Ai/Agents/EventTitleRewriter.php:17` |
| `DailySummaryAgent`   | claude-sonnet-4-6 | `app/Ai/Agents/DailySummaryAgent.php`     |
| `MonthlySummaryAgent` | claude-sonnet-4-6 | `app/Ai/Agents/MonthlySummaryAgent.php`   |
| `NewsClassifier`      | claude-haiku-4-5  | `app/Ai/Agents/NewsClassifier.php:24`     |
| `EventNewsMatcher`    | claude-haiku-4-5  | `app/Ai/Agents/EventNewsMatcher.php:23`   |

API-nyckel: `ANTHROPIC_API_KEY` (config/ai.php:58), legacy fallback `CLAUDE_API_KEY`. Paket: `laravel/ai ^0.6.3`.

### Schemaläggning — kostnadsdrivaren

Från `app/Console/Kernel.php`:

| Kommando                                    | Modell | Frekvens              | Volym/dygn                        |
| ------------------------------------------- | ------ | --------------------- | --------------------------------- |
| `crimeevents:create-summaries` Stockholm    | Sonnet | **5 min**             | 288 körningar                     |
| `crimeevents:create-summaries` vague-only   | Sonnet | **15 min**            | 96 körningar                      |
| `summary:generate --all-tier1 --yesterday`  | Sonnet | dagligen 06:00        | 5 anrop                           |
| `summary:generate --all-tier1` (today)      | Sonnet | 30 min                | ~48 körningar (skip-if-unchanged) |
| `summary:generate-monthly --current`        | Sonnet | 6:e timme             | 4 körningar                       |
| `summary:generate-monthly` föregående månad | Sonnet | månads-1:a 02:00      | 5 anrop/månad                     |
| `app:news:ai-classify`                      | Haiku  | 15 + 45 varje timme   | 48 körningar, ≤50 art/körning     |
| `app:event-news:match`                      | Haiku  | var 4:e timme (xx:25) | 6 körningar, ≤50 events           |

**Sannolik dominator: Sonnet-titel-omskrivningen Stockholm-pollen (5 min × ~10–20 anrop/körning).**

### Loggning idag

- `agent_conversation_messages.usage` (text-kolumn, `database/migrations/2026_01_11_000001_create_agent_conversations_table.php:33`) finns men skrivs **bara om agenten använder conversation-flöde** via `vendor/laravel/ai/src/Storage/DatabaseConversationStore.php`. One-shot-anrop (`Agent::ask()` utan persisterad konversation) loggas inte alls.
- `news_articles.ai_classified_at` + `ai_reason` samt `crime_event_news.ai_model` finns som audit-trail för **vilken** modell, inte hur mycket.
- Ingen `CostTracker`/`UsageService` eller `ai_usage_*`-tabell.
- ~~`laravel/ai` dispatchar inga `AgentCalled`-events~~ **(fel — korrigerat 2026-05-15):** `AgentPrompted` dispatchas vid varje text-generering i `vendor/laravel/ai/src/Providers/Concerns/GeneratesText.php:82-84` med `prompt->agent` (klassinstans), `prompt->model` och `response->usage`. `AgentStreamed` ärver från samma event. Det ger central hook gratis.

### Vad laravel/ai exposerar

`vendor/laravel/ai/src/Responses/Data/Usage.php` har redan:

- `promptTokens` (input)
- `completionTokens` (output)
- `cacheWriteInputTokens`
- `cacheReadInputTokens`
- `reasoningTokens`

Vi kan plocka `$response->usage()` efter varje agent-anrop utan att rota i SDK:n.

## Förslag

### ~~Steg 0~~ ARKIVERAT — Stockholm-pollen är inte dominatorn

~~Tidigare plan: sänk Stockholm-frekvens + lägg på `--vague-only`.~~ Prod-data 2026-05-15 (7d):

| Dag        | Nya Stockholm-events | AI-anrop totalt (Sonnet) | Stockholm | Övriga |
| ---------- | -------------------- | ------------------------ | --------- | ------ |
| 2026-05-08 | 12                   | 31                       | 12        | 19     |
| 2026-05-09 | 8                    | 30                       | 8         | 22     |
| 2026-05-10 | 5                    | 18                       | 5         | 13     |
| 2026-05-11 | 2                    | 25                       | 2         | 23     |
| 2026-05-12 | 3                    | 19                       | 3         | 16     |
| 2026-05-13 | 4                    | 21                       | 4         | 17     |
| 2026-05-14 | 7                    | 24                       | 7         | 17     |

Stockholm-jobbet är skyddat av `WHERE title_alt_1 IS NULL` — varje event AI-anropas exakt en gång oavsett om jobbet körs var 5:e min eller var timme. Vi kan fortfarande **sänka frekvensen för CPU-hygienens skull** (288 → 96 körningar/dygn), men det är inte en kostnadsfråga.

### Nya steg 0 — adressera den faktiska dominatorn

**0a. Krympa system-prompterna** (~1 timme, ingen vendor-PR krävs)

- Mät baseline: kör NewsClassifier 1 gång och räkna prompt-tokens (lokalt + dump-prompt).
- Klipp `news-classify.blade.php` och `event-news-match.blade.php` till absolut minimum — Haiku 4.5 klarar mycket kortare instruktioner än `claude-2`-eran. Mål: 30–50 rader istället för 149/151.
- Verifiera precision på samma stickprov som vi har för #64/63 (50 artiklar, mål >85 %).
- **Förväntad besparing:** 60–80 % av Haiku-input-kostnad ≈ **$4–6/dygn**.

**0b. Pre-filter med regex/keyword** (~2 timmar)

- För `app:news:ai-classify`: lägg på en regex-stage som **direkt avfärdar** artiklar utan brott-relaterade nyckelord (rån, mord, stöld, polis, inbrott, narkotika, trafikolycka, …) innan AI-anrop.
- Hypotes: 30–50 % av nyhetsfeeden är politik/sport/kultur som AI:n ändå avfärdar.
- **Förväntad besparing:** ytterligare 30–50 % av kvarvarande NewsClassifier-volym ≈ **$1–2/dygn**.

**0c. Aktivera prompt-caching** (kräver vendor-PR, ~halv dag)

- `vendor/laravel/ai/src/Gateway/Anthropic/Concerns/ParsesTextResponses.php:331` läser `cache_creation_input_tokens` från response, men ingen kod i `vendor/laravel/ai/src/Gateway/Anthropic/` **sätter** `cache_control: {type: "ephemeral"}` på request-meddelanden. Verifierat 2026-05-15.
- Två vägar:
    - **PR mot `laravel/ai`** för att lägga till `->withCache()` på prompts → påverkar uppströms, ren lösning men beroende av merge.
    - **Bypass `laravel/ai` för Haiku-anropen** — använd Anthropic-SDK direkt med cache_control på system-prompt. Mer kod, men oberoende av uppströms.
- **Förväntad besparing:** 90 % av kvarvarande Haiku-input-kostnad efter 0a+0b ≈ **$1–2/dygn**.

### Fas 1 — implementerad lokalt 2026-05-15 ✓

**Filer:**

- `database/migrations/2026_05_15_120000_create_ai_usage_logs_table.php` — ny tabell.
- `config/ai-pricing.php` — prismatris Sonnet 4.6 + Haiku 4.5 + legacy Sonnet 4.5.
- `app/Listeners/LogAiUsage.php` — fångar `AgentPrompted`, räknar cost_usd_micros, insert till `ai_usage_logs`. Context plockas från `$_SERVER['argv']` (console) eller route-namn (http).
- `app/Console/Commands/AiUsageReport.php` — `ai:usage [--days=N] [--by=day|agent|model]`.
- `app/Providers/EventServiceProvider.php` — listener registrerad i `$listen`-array.
- `app/Console/Kernel.php` — prune-job 04:00 dagligen (90d retention).

**Smoke-test lokalt:** NewsClassifier-anrop loggade korrekt:

| Field           | Värde                                         |
| --------------- | --------------------------------------------- |
| agent           | `App\Ai\Agents\NewsClassifier`                |
| model           | `claude-haiku-4-5`                            |
| input_tokens    | 2941                                          |
| output_tokens   | 141                                           |
| cost_usd_micros | 3646 (=$0.003646)                             |
| context_json    | `{"source":"console","command":"tinker",...}` |

**Verifierat:** 2941 input tokens × $1/MTok + 141 × $5/MTok = $0.003646 ✓ matchar pris-formeln.

**PHPStan:** 0 errors.

**Återstår innan prod-deploy:**

1. Användarens godkännande att pusha (push triggar auto-deploy).
2. Backup prod-DB innan migration (memory: prod_db_backup).
3. Soak 24–48h i prod, kör `ai:usage --days=2 --by=agent` för att verifiera real-volym vs vår uppskattning.

### Fas 1 — original spec (för historik)

1. **Migration:** ny tabell `ai_usage_logs` (id, created_at, agent, model, invocation_id, input_tokens, output_tokens, cache_read_tokens, cache_write_tokens, reasoning_tokens, cost_usd_micros, context_json). Bounded size — `prune-stale`-jobb i scheduler tar bort >90 dygn gamla rader. `invocation_id` gör att vi kan korrelera mot ev. fail/retry-spår senare.
2. **Listener:** `App\Listeners\LogAiUsage` registreras mot `Laravel\Ai\Events\AgentPrompted`. Plockar:
    - `$event->prompt->agent::class` (eller `getAgentName()`)
    - `$event->prompt->model`
    - `$event->response->usage` (alla 5 token-fält)
    - räknar pris mot `config/ai-pricing.php` och insert:ar.
    - Vid okänd modell: logga rad med `cost_usd_micros = NULL` + `Log::warning('Unknown model for pricing', ...)`. `ai:usage`-kommandot lyfter detta.
    - **Context-data** plockas via `$_SERVER['argv']` om körning sker via artisan, eller `Request::route()->getName()` om HTTP. Lagras i `context_json`.
3. **Inga call-site-ändringar** i de 5 agenterna — det är hela poängen med listener-vägen.
4. **Artisan:** `ai:usage [--days=7] [--by=agent|model|day]` som dumpar tabell med dygnsvis kostnad per agent + totalt. Inkluderar varning om okänd modell loggats.

### Fas 2 — bara om fas 1 visar problem som steg 0 inte redan löst

- Dygnsrapport via mail till `par.thernstrom@gmail.com` (Laravel notification, scheduler 07:00).
- Threshold-alert (`Log::warning` om dygnskostnad > X USD).
- Prompt-caching av system-prompt (verifiera först att `laravel/ai` exposerar `cache_control` på write-vägen — `cacheRead`/`cacheWrite` parsas i SDK:n, men det är oklart om vi kan sätta det när vi skickar prompt).
- Dedupe-cache för titel-omskrivningar (samma titel ska inte rewrites två gånger).
- Skippa AI helt på events utan `parsed_content > N` tecken (skydd mot tomma Polisen-teasers).

### Prismatris (jan 2026, USD per MTok)

| Modell            | Input | Output | Cache write | Cache read | Reasoning |
| ----------------- | ----- | ------ | ----------- | ---------- | --------- |
| claude-sonnet-4-6 | $3.00 | $15.00 | $3.75       | $0.30      | $15.00    |
| claude-haiku-4-5  | $1.00 | $5.00  | $1.25       | $0.10      | $5.00     |

Reasoning-tokens betalas till output-pris (extended thinking-mode). Vi använder inte thinking idag men lägg fältet i schema + matris så vi inte behöver migrera senare om någon agent slår på det.

Lägg i `config/ai-pricing.php`, env-overridable om vi vill testa.

## Risker

- **Failover-anrop logas inte fullständigt.** `AgentPrompted` dispatchas med slutgiltig respons — om en provider failover:ar (`AgentFailedOver`-event finns) räknas misslyckade attempt-tokens INTE. Värst-fall: vi underrapporterar 5–10 %. Acceptabelt i fas 1.
- **`AgentStreamed` ärver från `AgentPrompted`** — listener triggas dubbelt om en agent kör stream:ad. Vi använder inte streaming idag, men listener bör explicit ignorera `AgentStreamed`-instanser (eller bara lyssna på basevent och filtrera) för säkerhets skull.
- **Prismatris hårdkodas** → måste underhållas vid modellbyte (sonnet-4-6 → 4-7 etc). `ai:usage` ska varna om okänd modell loggats utan pris.
- **Tabellstorlek:** ~400 anrop/dygn × 90 dagar = 36k rader. Försumbart, men prune-job skadar inte.
- **Context-data via `$_SERVER['argv']`** är ful men funktionell. Acceptabelt så länge vi inte behöver bryta ner på något finkornigare än kommando-namn.
- **Steg 0-risk:** om Stockholm-jobbet sänks till 15 min kan en specifik UX-feature på Stockholm-sidor (typ-A-titlar i nära realtid) försämras. Verifiera vad som faktiskt konsumerar Stockholm-titlar i realtid — sannolikt inget.

## Confidence

**Hög** (uppåt från medel efter prod-data 2026-05-15). Vi har nu:

- Faktiska volym-siffror per agent från prod-DB (7 dagar).
- Identifierad dominator (Haiku-Newsclassifier × stora system-prompts).
- Verifierad blockerare (laravel/ai saknar cache_control write-stöd).
- Tre konkreta åtgärder (0a/0b/0c) med uppskattad besparingseffekt.
- Listener-baserad logger-arkitektur som inte rör call-sites.

Kvarvarande osäkerhet är försumbar:

- Exakt prompt-token-storlek per agent (kräver lokal mätning, halvtimme).
- Hur stor andel av news-feed:n som regex kan kasta utan AI (kräver prod-stickprov).
- Huruvida `laravel/ai` accepterar en cache_control-PR uppströms vs om bypass är snabbare.
