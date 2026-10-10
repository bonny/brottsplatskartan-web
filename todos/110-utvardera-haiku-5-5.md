**Status:** aktiv
**Senast uppdaterad:** 2026-10-10 (code review: siffror, källor och plan rättade)

# Todo #110 — Utvärdera Haiku 5.5 för EventNewsMatcher och NewsClassifier

## Sammanfattning

Claude Haiku 5.5 (`claude-haiku-5-5`) släpptes 2026-10-07 och kostar ungefär en
tiondel av Haiku 4.5 per token. Efter att den nya tokenizern ger ~30 % fler
tokens landar vi grovt på ~13 % av dagens Haiku-kostnad: **~$16,40/mån →
~$2–4/mån** (osäkerheten är thinking-tokens). Haiku är **45 %** av AI-notan
(`ai_usage_logs` på prod, 30 dagar till 2026-10-10: Haiku 4.5 $16,44 på 8 372
anrop, Sonnet 4.6 $20,19, totalt $36,63). Besparingen blir ~$13/mån. Men tre
saker i koden måste hanteras och kvaliteten mätas innan bytet.

## Bakgrund

Priser per MTok (Anthropics docs, 2026-10-07):

|            |   Haiku 4.5 | Haiku 5.5 (prompt ≤ 100k tok) |
| ---------- | ----------: | ----------------------------: |
| Input      |       $1,00 |                         $0,10 |
| Output     |       $5,00 |                         $0,50 |
| Cache read |       $0,10 |                         $0,01 |
| Batch      | $0,50/$2,50 |                   $0,05/$0,25 |

Prompts över 100k tokens kostar 5× mer, men våra anrop ligger på ~1 300 tok.

Ingen brådska, men datumet behöver läsas rätt: Haiku 4.5 är **Active**, inte
deprecated. "Not sooner than October 15, 2026" på [model
deprecations](https://platform.claude.com/docs/en/about-claude/model-deprecations)
är en undre gräns, inget beslut. Anthropic varslar minst 60 dagar före
pension, så utan varsel är tidigaste möjliga pension ~60 dagar efter ett
framtida varsel (tidigast ~2026-12-09 räknat från 2026-10-10). Gränsen är
nästan passerad, så ett varsel kan komma när som helst — kolla sidan igen
när todon tas upp. Haiku 5.5 har "not sooner than October 7, 2027".

NewsClassifier är pausad sedan #64 (2026-06-01), så det är i praktiken
EventNewsMatcher som kostar. Siffrorna ovan kommer från
[docs/ai-kostnad.md](../docs/ai-kostnad.md).

Källor:

- https://platform.claude.com/docs/en/models/haiku-5-5/overview
- https://platform.claude.com/docs/en/models/haiku-5-5/migration-guide
- https://platform.claude.com/docs/en/about-claude/pricing

## Förslag

1. **Thinking + `MaxTokens`.** Haiku 5.5 kör adaptive thinking som default
   (effort `medium`), och thinking-tokens räknas mot `max_tokens`. Med dagens
   `#[MaxTokens(400)]` kan ett anrop stoppa på `max_tokens` innan JSON:en
   kommer.
    - **Prova först att stänga av thinking** med toppnivånyckeln
      `thinking: {type: "disabled"}` via `HasProviderOptions`. Den krockar inte
      med `output_config` (samma mönster som `between_tools` för Sonnet i
      [ai-kostnad.md](../docs/ai-kostnad.md)). Haiku 5.5 accepterar `disabled`
      vid effort `low`/`medium`/`high`; standard är `medium`, så det går utan
      att röra effort.
    - **Lägg inte `output_config.effort` i `HasProviderOptions`.**
      `array_merge($body, $providerOptions)` är grund: en nästlad
      `output_config` ersätter hela nyckeln, schemat försvinner, svaret blir
      prosa, `decodeStructuredOutput()` returnerar [] och varje par hoppas
      över som tomt svar och frågas om vid varje körning. Effort kräver en
      egen lösning (djup merge eller stöd i laravel/ai) och provas bara om
      `disabled` tappar kvalitet.
    - Höj `MaxTokens` om thinking lämnas på.
    - Uppdatera kommentaren i `NewsClassifier.php` ("Haiku tänker inte ändå
      utan flagga") — gäller inte längre.
2. **Refusals och avklippta svar — den verkliga risken är omfrågning.**
   Haiku 5.5 kör säkerhetsklassificerare som kan ge `stop_reason: "refusal"`,
   och har ingen server-side fallback. Vårt indata är brotts-/våldsnyheter.
   laravel/ai mappar `refusal` till `FinishReason::ContentFilter` och
   `max_tokens` till `FinishReason::Length`, nåbart via
   `$response->steps->last()->finishReason`.
   `MatchEventNews` (rad ~141–149) sparar redan inte tomma svar som
   `is_match=false` och kraschar inte — den hoppar över paret. Men raden i
   `crime_event_news` **är** den negativa cachen, så ett överhoppat par skickas
   igen vid varje schemaläggning i 15 dagar: kostnad varje gång och uppblåst
   `errors`. Hantera `ContentFilter` explicit: spara raden med en markör
   (t.ex. `ai_reason = 'refusal'`, `is_match = false`) så att paret inte
   frågas om, och logga andelen. `Length` ska fortsatt hoppas över (det är
   ett fel hos oss, inte ett svar).
3. **Eval** som vid förkortningen av prompten 2026-08-24 i
   [#102](102-ai-kostnad-boilerplate-per-anrop.md): 80 stratifierade
   prod-dömda par, 98,8 % samstämmighet (metod och siffra i
   [ai-kostnad.md](../docs/ai-kostnad.md), "Att tänka på vid
   promptändringar"; #107 utvärderade bara Sonnet). Samma 80 par ur
   `crime_event_news.is_match` genom `claude-haiku-5-5` med thinking `disabled`. Mät samstämmighet, faktiska tokens inkl.
   thinking, och **räkna `ContentFilter` och `Length` som fel i
   samstämmigheten** — annars faller de ur urvalet som "tomma" och siffran
   ser bra ut medan en del av paren i prod frågas om för evigt. Kör ev. ett
   stickprov av NewsClassifier-artiklar också, men den är pausad.
4. **Vid godkänt:** byt `#[Model]` i båda agenterna. (`ai_model` i
   `MatchEventNews` läses sedan 2026-10-10 ur `EventNewsMatcher`:s
   `#[Model]` — ingen tredje ändring.) Lägg till `claude-haiku-5-5` i
   `config/ai-pricing.php` (annars loggas `cost_usd_micros = NULL`).
   **Trappat pris:** prompts över 100k tokens kostar 5×; `config/ai-pricing.php`
   och `LogAiUsage` stöder bara ett pris per modell. Ofarligt vid ~1 300
   tokens per anrop, men skriv en kommentar vid raden så att ingen återanvänder
   modellen för långa prompts (batchning) utan att räkna rätt. Uppdatera
   kommentarsblocket i `EventNewsMatcher.php` ("Modell: Haiku 4.5", "kräver
   4 096 tokens … modellbyte hjälper inte heller"), cachetabellen och
   kostnadssiffrorna i ai-kostnad.md. Restart av scheduler på prod efter
   deploy.
5. **Kolla prompt-caching igen efter bytet.** Haiku 5.5:s minsta cachebara
   prefix är 512 tokens mot Haiku 4.5:s 4 096, så EventNewsMatchers ~1 133
   tokens instruktioner kan cachas. Vinsten är liten i dollar (cache read
   $0,01/MTok mot input $0,10) men gratis att mäta med
   `cache_read_input_tokens`. Påståendet i ai-kostnad.md att modellbyte inte
   hjälper gällde Sonnet/Haiku 4.5.

Inga ändringar behövs för temperature, prefill eller forced `tool_choice` —
vi använder inget av det i Haiku-agenterna.

## Risker

- Kvalitet: prompten är finjusterad för Haiku 4.5 (#82, #102). En generisk-
  titel-FP kan komma tillbaka — evalen ska fånga det.
- Thinking kan äta upp en del av besparingen om effort inte går att styra
  rent via laravel/ai.
- Falska refusals på våldsnyheter → tappade matchningar.

## Confidence

medel — prisvinsten är dokumenterad och stor, men thinking-styrningen via
laravel/ai och refusal-andelen på vårt innehåll är okända tills de mätts.
