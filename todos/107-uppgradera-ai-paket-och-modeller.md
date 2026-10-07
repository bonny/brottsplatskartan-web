**Status:** aktiv
**Senast uppdaterad:** 2026-10-07 (paket uppgraderat, modellbyte avvisat)
**Källa:** Pär, 2026-10-07

# Todo #107 — Uppgradera AI-paket och modeller

## Sammanfattning

AI-stacken har inte setts över sedan våren. `laravel/ai` ligger på en
0.x-version medan 1.x finns, och tre av fem agenter kör Sonnet 4.6, som
inte är senaste Sonnet. Se över båda, mät kvalitet och kostnad, och
uppgradera där det lönar sig.

## Nuläge (2026-10-07)

**Paket:** `laravel/ai` **v0.11.0** (2026-08-19). Senaste är **v1.1.0**
(2026-10-05); v1.0.0 kom 2026-09-23. Ett 0.x → 1.x-hopp kan ha brytande
ändringar — läs changelog/upgrade guide först.

**Agenter** (`app/Ai/Agents/`):

| Agent               | Modell              | Uppgift                         |
| ------------------- | ------------------- | ------------------------------- |
| DailySummaryAgent   | `claude-sonnet-4-6` | Dagssammanfattning              |
| MonthlySummaryAgent | `claude-sonnet-4-6` | Månadssammanfattning            |
| EventTitleRewriter  | `claude-sonnet-4-6` | Omskrivning av händelsetitlar   |
| EventNewsMatcher    | `claude-haiku-4-5`  | Klassifikation event × nyhet    |
| NewsClassifier      | `claude-haiku-4-5`  | Blåljus-klassning (pausad, #64) |

Nyare modeller finns: Sonnet 5.5, Opus 5.5 och Fable 5.1. Haiku 4.5 är
fortfarande senaste Haiku.

**Övrigt att städa:**

- `config/services.php` har `claude.model` = `claude-sonnet-4-5-20250929`
  (env `CLAUDE_MODEL`) från claude-php-sdk-tiden. Ingen kod läser den
  längre — ta bort.
- `config/ai-pricing.php` är en manuell prislista ("2026-01 snapshot").
  Nya modeller saknas; okänd modell loggas med `cost_usd_micros = NULL`,
  så **lägg till priset i samma commit som modellbytet**, annars blir
  `ai:usage` tyst fel.

## Förslag

1. **Paketet först, utan modellbyte.** Uppgradera `laravel/ai` till 1.x
   (`docker compose exec -u root app composer update laravel/ai`), läs
   upgrade guide, kör `composer analyse`, och kör varje agent en gång
   lokalt. Kolla särskilt structured output
   (`StructuredAgentResponse`, som `MatchEventNews` förlitar sig på) och
   usage-eventet som `App\Listeners\LogAiUsage` lyssnar på.
2. **Sonnet-agenterna:** jämför Sonnet 4.6 mot senaste Sonnet på ett
   tiotal riktiga indata per agent (titlar, dags-/månadssammanfattningar).
   Bedöm kvalitet och tokenförbrukning. Byt bara om det är bättre eller
   lika bra till samma eller lägre kostnad.
3. **Haiku-agenterna:** inget modellbyte tillgängligt — rör inte. Se
   `docs/ai-kostnad.md` innan något om caching/batchning utreds igen.
4. Uppdatera `config/ai-pricing.php` och `docs/ai-kostnad.md` med det
   som byts.

## Risker

- 0.x → 1.x kan ändra API:t för agenter/attribut och usage-events —
  kostnadsloggningen (#81) kan tyst sluta fungera.
- Scheduler-containern cachar kod: efter deploy, verifiera i
  `ai_usage_logs` att nya modellnamnet syns (se minnesnoten om
  scheduler-cache).
- Ny modell kan ge annan ton i titlar/sammanfattningar — SEO-mätningar
  i #54 och #60 bygger på nuvarande output.

## Genomfört 2026-10-07

Tre commits på worktree-grenen, inte deployade:

1. `chore(#107): uppgradera laravel/ai 0.11.0 → 1.1.0`
2. `chore(#107): ta bort död services.claude-config`
3. den här uppdateringen + `docs/ai-kostnad.md`

### Paketet: laravel/ai 0.11.0 → 1.1.0

`composer.json` fick `^1.1`. Låsfilen ändrade bara laravel/ai och dess
beroenden: laravel/prompts 0.3.25, laravel/serializable-closure 2.1.0,
symfony/console 7.4.20, symfony/string 8.1.7, service-/http-client-contracts
3.7.3, polyfills 1.43.0, ny symfony/yaml 8.1.8. **aws/aws-sdk-php,
aws-crt-php, mtdowling/jmespath.php och symfony/filesystem försvann** —
laravel/ai flyttade AWS-SDK:n till require-dev. Ingen av dem används av
appen (bara `bedrock`-providern i `config/ai.php`, som vi inte kör).

Brytande ändringar i 1.0 och hur de hanterades:

| Ändring                                                                                                                              | Påverkan                                                                                                                                                                                                                                                                                                          |
| ------------------------------------------------------------------------------------------------------------------------------------ | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `Usage` byggdes om: `promptTokens`/`completionTokens` → `inputTokens`/`outputTokens` i `TextUsage`; cache-/reasoning-fälten nullable | **Hade tyst stoppat kostnadsloggningen.** `LogAiUsage` läste de gamla fälten, insert hade kastat, fångats av try/catch och bara gett en warning. Fixat: `input_tokens` = `uncachedInputTokens()` (samma betydelse som förr: Anthropics okachade input), null → 0, reasoning räknas inte separat (ingår i output). |
| `inputTokens` inkluderar nu cache-tokens                                                                                             | Hanterat via `uncachedInputTokens()` ovan, så kostnaden inte dubbelräknar cache.                                                                                                                                                                                                                                  |
| Conversation-tabellerna → `steps`, `status`-kolumn                                                                                   | Ingen — vi kör bara `->prompt()` och tabellerna är borttagna (#102). Paketets migration publiceras bara, laddas inte automatiskt.                                                                                                                                                                                 |
| Middleware per steg, stream-protokoll som objekt, Gemini Interactions API                                                            | Ingen — används inte.                                                                                                                                                                                                                                                                                             |

Oförändrat och verifierat: attributen (`Provider`, `Model`, `MaxTokens`,
`Temperature`, `Timeout`), `HasStructuredOutput`, `StructuredAgentResponse`
(`toArray()` + array access), `decodeStructuredOutput()` returnerar fortfarande
`[]` vid oavkodbart svar, nativ structured output är default, och
`AgentPrompted` har samma fält. Nytt men oanvänt: `#[CacheInstructions]`.

`composer analyse`: inga fel.

**Smoke-test lokalt** (`tmp-107/smoke.php`, ett anrop per agent): alla fem
gav rätt svarstyp (`StructuredAgentResponse` för de tre schema-agenterna,
`AgentResponse` för sammanfattningarna) och var sin rad i `ai_usage_logs`
med kostnad — t.ex. EventNewsMatcher 1 498 in / 68 ut → 1 838 µUSD,
MonthlySummaryAgent 1 708 / 300 → 9 624 µUSD.

### Sonnet 4.6 mot Sonnet 5.5 — inget byte

Priser enligt platform.claude.com/docs/en/about-claude/pricing (2026-10-07):
Sonnet 4.6 $3/$15, Sonnet 5.5 $2/$10 per MTok (cache read $0,30 resp. $0,20).

Körning: `tmp-107/compare.php` med riktiga indata från lokala DB:n, byggda via
produktionskodens egna metoder (`getEventsForDate`, `getMonthlyEvents`,
`formatEventsForAI`). Variant A = agenten som den är (4.6, temperature 0,5).
B = 5.5 utan temperature (icke-default ger 400 på 5.5) och med
`thinking: between_tools` (ingen extended thinking, närmast 4.6).
C = 5.5 med default adaptive thinking, på 1–2 indata per agent.
Total kostnad för jämförelse + smoke ≈ $0,61.

| Agent               | Indata | Input-tok B/A | Output-tok B/A | Kostnad B/A | Latens A → B |
| ------------------- | -----: | ------------: | -------------: | ----------: | -----------: |
| EventTitleRewriter  |      5 |          1,26 |           1,29 |        0,85 |  4,5 → 3,8 s |
| DailySummaryAgent   |      5 |          1,22 |           1,44 |        0,88 |  9,2 → 4,7 s |
| MonthlySummaryAgent |      4 |          1,20 |           1,36 |        0,84 | 23,6 → 9,2 s |

Ny tokenizer äter upp det mesta av prisskillnaden: 12–16 % billigare per
anrop. Prod de senaste 30 dygnen (läst 2026-10-07): Sonnet-agenterna kostade
$20,84 (titlar $13,14, månad $4,46, dag $3,24), så ett byte sparar ~$2,7/mån.
Adaptive thinking (C) tänkte inte alls på titlar och dagar, men månaden fick
722 reasoning-tokens (+36 % output) och blev lika dyr som 4.6.

Kvalitet (läst för hand):

- **Titlar:** jämnt. 5.5 skriver mer ordagrant: behåller råa tidsstämplar
  ("11:27 Polispatrull på plats.") och kopierade "förhör mm." rakt av. 4.6
  ger något mer informativa rubriker ("Man gripen efter misshandel" mot 5.5:s
  "…efter bråk") men hade ett stavfel ("Hastighetskontollen").
- **Dag:** 5.5 skriver längre än prompten tillåter. Stockholm 6 händelser:
  4 stycken / ~260 ord mot regelns 2 stycken / ~120 ord (4.6: 2 stycken,
  ~190 ord). Samma mönster i Malmö och Uppsala.
- **Månad:** båda håller 260–280 ord (under regelns 300–450). 5.5 räknar
  trendprocenten exakt ("cirka tolv procent") men tar med 9–11 länkar mot
  regelns 4–8 och hade genusfel ("en misstänkt mordförsök",
  "en mordbrandsförsök"). 4.6 hade ett stavfel i ett gatunamn.

Slutsats: 5.5 är inte klart bättre, så kriteriet för byte uppfylls inte.
Modellerna står kvar på `claude-sonnet-4-6` och `config/ai-pricing.php` har
inte fått någon ny modell. Blir det aktuellt senare krävs promptjustering
(längd, antal länkar) och en ny mätning. Detaljer om parametrarna står i
[docs/ai-kostnad.md](../docs/ai-kostnad.md).

Rådata: `tmp-107/compare-{title,daily,monthly}.{json,md}` (gitignorerad).

### Städning

`services.claude` (`api_key` + `model`/`CLAUDE_MODEL`) borttaget — inget läste
det. `CLAUDE_API_KEY` behövs fortfarande: `config/ai.php` läser den direkt som
fallback för `ANTHROPIC_API_KEY`. `CLAUDE_MODEL` finns inte i någon env-mall i
repot; står den kvar i prods `.env` är den ofarlig.

### Efter deploy

1. `composer install` körs av deployen; kontrollera att den inte faller på
   låsfilen.
2. **Starta om schedulern** (`docker compose restart scheduler`). Den har den
   gamla koden laddad. Utan omstart kör den 0.11-vendor tills nästa recreate.
3. Verifiera kostnadsloggningen — det är den som kan gå sönder tyst:
    ```sql
    select agent, model, count(*), sum(cost_usd_micros is null)
    from ai_usage_logs where created_at > now() - interval 1 hour
    group by agent, model;
    ```
    Förvänta rader från EventNewsMatcher (var 15:e min) och EventTitleRewriter
    inom en timme, med `cost_usd_micros` ifyllt. Kolla också
    `storage/logs` efter `LogAiUsage misslyckades`.
4. Stickprov: en ny `title_alt_1` och en dagssammanfattning ser normala ut.

## Confidence

medel — paketuppgraderingen är rutin men 1.0 är en major; modellbytet
kräver en manuell kvalitetsjämförelse.
