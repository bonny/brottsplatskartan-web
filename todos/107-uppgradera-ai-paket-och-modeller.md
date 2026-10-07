**Status:** aktiv
**Senast uppdaterad:** 2026-10-07
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

## Confidence

medel — paketuppgraderingen är rutin men 1.0 är en major; modellbytet
kräver en manuell kvalitetsjämförelse.
