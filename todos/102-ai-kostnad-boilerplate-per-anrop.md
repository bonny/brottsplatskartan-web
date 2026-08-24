**Status:** Fas 1, 2 och 4 klara och deployade 2026-08-24. Fas 3 (batch) avfärdad på mätning: N=1,56 kandidater/event, 69 % av eventen har exakt en kandidat → nettovinst ~$3,5/mån mot betydande kvalitetsrisk. Uppmätt utfall: 2 562 → 1 323 tok/anrop, spend $51,59 → ~$38,7/mån (−25 %).
**Senast uppdaterad:** 2026-08-24

# Todo #102 — AI-kostnad: skär boilerplate per anrop

## Sammanfattning

Anthropic mailade 2026-08-23 att vår prompt-cache-träffgrad är låg och att
caching kunde spara "upp till 43 %" av API-spenden. Utredningen visar att
**rådet inte går att följa** — men den blottade tre andra åtgärder värda
mer än caching någonsin kunde gett.

Kärnfyndet: av EventNewsMatchers **2 808 snitt-tokens per anrop** är bara
**246 (9 %)** faktisk event- och artikeldata. Resten är systemprompt
(1 885) och schema-overhead (677) som skickas om 9 157 gånger i månaden.

**Källa:** Inbox Brottsplatskartan (2026-08-24)

## Bakgrund

### Faktisk kostnadsbild (prod, `ai_usage_logs`, 30 dygn t.o.m. 2026-08-24)

| Agent               | Modell     |  Anrop | Systemprompt |    Kostnad | Andel |
| ------------------- | ---------- | -----: | -----------: | ---------: | ----: |
| EventNewsMatcher    | haiku-4-5  |  9 157 |    1 885 tok |     $31,02 |  60 % |
| EventTitleRewriter  | sonnet-4-6 |  1 044 |      811 tok |     $14,44 |  28 % |
| MonthlySummaryAgent | sonnet-4-6 |    124 |    1 508 tok |      $3,60 |   7 % |
| DailySummaryAgent   | sonnet-4-6 |    234 |    1 502 tok |      $2,53 |   5 % |
| **Totalt**          |            | 10 559 |              | **$51,59** |       |

`cache_read_tokens` och `cache_write_tokens` är `0` på samtliga rader.

### Varför prompt-caching inte går

Anthropics **minsta cachebara prefix är modellspecifikt**, och prefix under
gränsen cachas **tyst** — inget felmeddelande, bara `cache_creation_input_tokens: 0`.
Systempromptarna mättes exakt via `/v1/messages/count_tokens`:

| Agent               | Systemprompt |     Modellens minimum | Cachebar?                                |
| ------------------- | -----------: | --------------------: | ---------------------------------------- |
| EventNewsMatcher    |    1 885 tok | **4 096** (Haiku 4.5) | Nej — hela requesten toppar på 3 112 tok |
| EventTitleRewriter  |      811 tok |    1 024 (Sonnet 4.6) | Nej                                      |
| DailySummaryAgent   |    1 502 tok |                 1 024 | Ja                                       |
| MonthlySummaryAgent |    1 508 tok |                 1 024 | Ja                                       |

De två cachebara står för 12 % av spenden och kör i skurar om 3–5 anrop
inom samma minut (verifierat mot `ai_usage_logs`). Räknat på det faktiska
burst-mönstret ger caching **~$0,83/mån = 1,6 %** — inte 43 %.

Modellbyte hjälper inte heller: Sonnet 5 (min 1 024) skulle cacha
EventNewsMatchers prompt, men landar på $38–49/mån mot Haikus $25,70
okachat. **Haiku utan cache slår varje Sonnet-med-cache-variant.**

### Var pengarna faktiskt går — tool-preamblen

Vi kör `laravel/ai` 0.6.8. Där är `supportsNativeStructuredOutput()` falsk
om providern saknar `anthropic_beta` i configen — vilket vår gör. Alltså
skickas JSON-schemat som ett **syntetiskt verktyg** (`output_structured_data`)
istället för via `output_config.format`.

Så fort en request innehåller en `tools`-array injicerar Anthropic en
protokollpreamble som lär modellen verktygsanrop. Isolerad mätning
(haiku-4-5, `count_tokens`):

| Request                      |  Tokens |
| ---------------------------- | ------: |
| Tom request (bara `"x"`)     |       8 |
| \+ ett **helt tomt** verktyg | **531** |
| \+ vårt schema som verktyg   |     684 |
| \+ samma schema **nativt**   |     197 |

Ett verktyg utan namn, beskrivning eller fält kostar **523 tokens**. Vi
använder inga verktyg — vi vill bara ha JSON tillbaka. Vi betalar
preamblen på varje anrop för ett protokoll vi aldrig utnyttjar.

`laravel/ai` v0.9.0 gjorde nativa structured outputs till default. Uppmätt
skillnad på våra faktiska requestformer:

| Agent              | 0.6.8 (verktyg) | 0.11.0 (nativt) | Skillnad |
| ------------------ | --------------: | --------------: | -------: |
| EventNewsMatcher   |       2 562 tok |       2 075 tok | **−487** |
| EventTitleRewriter |       1 471 tok |         975 tok | **−496** |

## Förslag

### Fas 1 — Korta `event-news-match.blade.php` (~$10/mån, låg risk)

1 885 → ~800 tokens. 67 % av den dyraste agentens input är en systemprompt
som aldrig kan cachas, så varje sparad token slår igenom 9 157 gånger.
Ingen arkitekturändring. Kräver eval-stickprov mot matchningskvalitet.

`#81` rad 118 föreslår redan detta, men mot 149-radersversionen av
`news-classify` — `event-news-match` är fortfarande orörd.

### Fas 2 — Uppgradera `laravel/ai` 0.6.8 → 0.11.0 (~$6/mån + resiliens)

Tar bort tool-preamblen på tre agenter. Utöver kostnaden ger v0.11.0 tre
saker som spelar roll för en obevakad scheduler som kör var 15:e minut:

- failover när Anthropic avvisar pga usage limit (PR #864)
- failover på transienta gateway-/Cloudflare-fel (PR #884)
- mappning av `refusal` och `model_context_window_exceeded` (PR #881)

**Uppgraderingsrisk — låg för oss, men två saker att hantera:**

- **Framework-bump tvingas fram.** v0.11.0 kräver
  `illuminate/json-schema: ^13.15`; vi kör `laravel/framework` **13.9.0**.
- **Nativa structured outputs är en beteendeändring** för tre agenter →
  eval före rollout. Escape hatch: `'use_native_structured_output' => false`.
- Nativa schemat kräver `additionalProperties: false` explicit, annars 400.

De tre `Likelihood Of Impact: High`-punkterna i UPGRADE.md gäller
**Conversations** (polymorfa participants, `approval_state`) och
embeddings-/transcription-builders. Vi använder ingetdera: tabellerna
`agent_conversations` och `agent_conversation_messages` finns från den
publicerade migrationen men har **0 rader på prod** och ingen kod rör dem.
Rimligast är att droppa de oanvända tabellerna istället för att migrera dem.

### Fas 3 — Batcha kandidater per event ($8–13/mån, hög insats)

`MatchEventNews.php:106` är en nästlad loop med ett `->prompt()` per
(event, artikel)-par. Blir inre loopen ett anrop per event amorteras
1 885-tokensprompten över N kandidater. Besparing = $17,3 × (1 − 1/N),
alltså ~$8/mån vid N=2 och ~$13/mån vid N=4.

**MÄTT 2026-08-24 → AVFÄRDAD.** Kört `--dry-run` med schedulerns egna
parametrar (`--days=7 --limit=50`), vilket går exakt samma kodväg och
respekterar den negativa cachen i `crime_event_news`:

| Kandidater/event |    Events |
| ---------------: | --------: |
|                1 | 33 (69 %) |
|                2 |        10 |
|                3 |         2 |
|                5 |         2 |
|                6 |         1 |

**75 par över 48 events → N = 1,56**, inte de 2–4 som antogs ovan. 69 % av
eventen har exakt en kandidat, där batchning sparar noll anrop.

Efter Fas 1+2 är boilerplaten nere i 1 323 tok/anrop, så batchning skulle
spara 3 297 anrop × 1 323 tok ≈ $4,4/mån brutto. Den batchade prompten måste
dessutom vara längre (N-vägs bedömning, artikel-avgränsare, array-schema) på
*alla* anrop inklusive de 69 % som inget vinner → netto **~$3,5/mån**.

Mot det står: omskrivning av en kvalitetskänslig prompt för en svårare
uppgift, ett array-schema som måste mappas tillbaka till artikel-ID, och att
ett fel nu tappar N omdömen i stället för ett. `#82` flaggade redan
"batch-prompten tappar precision". Gaten i denna todo — "öppna igen bara om
Fas 1+2 inte räcker" — stängde alltså på mätning, precis som avsett.

### Fas 4 — Prompt-caching: gör inte

~$0,83/mån motiverar inte koden. Dokumentera **varför** så nästa mail
eller nästa reviewer inte gör om utredningen.

## Rättelser till befintliga todos

Två påståenden i arkivet är felaktiga och bör rättas när detta byggs:

1. **`#81` rad 32 och 130** säger att `laravel/ai` saknar write-stöd för
   `cache_control` och att caching därför kräver vendor-PR eller
   SDK-bypass. Det stämmer att gatewayen aldrig sätter `cache_control`
   (verifierat även i v0.11.0 — `BuildsTextRequests.php:34` sätter
   `$body['system']` som ren sträng). Men rad 71 avslutar med
   `array_merge($body, $providerOptions)`, så en agent som implementerar
   `HasProviderOptions` kan skriva över `system` med cache_control-block.
   **Ingen vendor-PR behövs.** Vi ska ändå inte använda det — men
   blockeraren är inte den som står där.

2. **`#82` rad 318** avfärdar caching med "~500-token systemprompt →
   ~$49→~$45". Systempromptem är **1 885 token**, 3,8× antagandet.
   Slutsatsen (skippa caching) var rätt, men av fel skäl — och samma
   felaktiga premiss underskattade batch-lyftet i Fas 2.

## Risker

- **Kortare systemprompt sänker matchningskvaliteten.** `#82` visade att
  generiska-titel-FP är den känsliga axeln. Eval-set före rollout, och
  bevaka täckningen (10,7 % vid senaste soak).
- **Nativa structured outputs ändrar svarsform.** Idag kommer svaret som
  `tool_use`-block; nativt som JSON. `MatchEventNews.php:130-132` har
  `??`-fallbackar som maskerar tysta fel — verifiera att de inte börjar
  slå in efter uppgraderingen.
- **Framework 13.9 → ≥13.15** drar in ändringar utanför AI-vägen.
- **Scheduler-cache:** vid deploy av blade-prompter krävs
  `docker compose restart scheduler` på prod — `view:clear` räcker inte.

## Confidence

**Hög** på diagnosen — varje siffra är uppmätt mot prod-data eller
Anthropics `count_tokens`-API, inte uppskattad från filstorlek.

**Medel** på besparingssiffrorna för Fas 1 och 3. Fas 2:s ~$6/mån är
uppmätt på faktiska requestformer och håller. Fas 1 antar att prompten
går att halvera utan kvalitetstapp — otestat. Fas 3:s spann beror på ett
omätt N.
