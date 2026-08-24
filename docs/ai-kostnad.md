# AI-kostnad — vad som är gjort och vad som inte går

Kostnadsanteckningar för Brottsplatskartans fem AI-agenter i `app/Ai/Agents/`.
Löpande kostnadsspårning ligger i [todo #81](../todos/81-ai-kostnad-overvakning.md);
den här filen finns för att slippa göra om samma utredning två gånger.

Siffror mätta 2026-08-24 mot 30 dygn prod-data i `ai_usage_logs` och
Anthropics `/v1/messages/count_tokens`.

## Prompt-caching går inte — sluta utreda det

Anthropic mailar med jämna mellanrum att vår prompt-cache-träffgrad är låg
och att caching kan spara "upp till 43 %". **Det rådet går inte att följa
här**, och uppskattningen tar inte hänsyn till modellernas minsta cachebara
prefix.

Prefix under gränsen cachas **tyst** — inget felmeddelande, bara
`cache_creation_input_tokens: 0`. Gränsen är inte monoton mellan generationer:

| Modell                         | Minsta cachebara prefix |
| ------------------------------ | ----------------------: |
| Opus 5, Fable 5                |                     512 |
| Opus 4.8, Sonnet 5, Sonnet 4.6 |                   1 024 |
| Opus 4.7                       |                   2 048 |
| **Haiku 4.5**, Opus 4.6/4.5    |               **4 096** |

Våra systempromptar mot de gränserna:

| Agent               | Systemprompt | Modellens minimum | Cachebar? |
| ------------------- | -----------: | ----------------: | --------- |
| EventNewsMatcher    |    1 133 tok |     4 096 (Haiku) | Nej       |
| EventTitleRewriter  |      811 tok |    1 024 (Sonnet) | Nej       |
| NewsClassifier      |      993 tok |     4 096 (Haiku) | Nej       |
| DailySummaryAgent   |    1 502 tok |             1 024 | Ja        |
| MonthlySummaryAgent |    1 508 tok |             1 024 | Ja        |

De två cachebara står för 12 % av spenden och kör i skurar om 3–5 anrop inom
samma minut. Räknat på det faktiska burst-mönstret ger caching **~$0,83/mån**
— inte värt koden.

**Modellbyte hjälper inte.** Sonnet 5 (min 1 024) skulle cacha
EventNewsMatchers prompt, men landar på $38–49/mån mot Haikus $25,70 okachat.
Haiku utan cache slår varje Sonnet-med-cache-variant.

**`laravel/ai` stödjer det inte heller** (kontrollerat i v0.11.0):
`Gateway/Anthropic/Concerns/BuildsTextRequests.php` sätter `$body['system']`
som ren sträng och sätter aldrig `cache_control`. Det finns dock en escape
hatch — sista raden är `array_merge($body, $providerOptions)`, så en agent
som implementerar `HasProviderOptions` kan skriva över `system` med
cache_control-block. **Ingen vendor-PR behövs** om vi någon gång skulle vilja.
(Äldre anteckningar i #81 påstår motsatsen — de är inaktuella.)

## Det som faktiskt kostade: boilerplate per anrop

Före 2026-08-24 såg ett EventNewsMatcher-anrop ut så här:

| Del                        | Tokens | Andel |
| -------------------------- | -----: | ----: |
| Systemprompt               |  1 885 |  67 % |
| Schema-overhead            |    677 |  24 % |
| Faktisk event-/artikeldata |    246 |   9 % |

91 % boilerplate, skickat om 9 157 gånger i månaden. Två åtgärder, båda
genomförda:

1. **Nativa structured outputs** (uppgradering till `laravel/ai` 0.11.0).
   I 0.6.8 var `supportsNativeStructuredOutput()` falsk om providern saknade
   `anthropic_beta` i configen, så JSON-schemat skickades som ett syntetiskt
   verktyg. Så fort en request innehåller en `tools`-array injicerar Anthropic
   en protokollpreamble: **ett helt tomt verktyg kostar 523 tokens.** Vi
   använder inga verktyg — vi vill bara ha JSON tillbaka. Schema-overheaden
   gick 677 → 190 tok.
2. **Kortad systemprompt** för EventNewsMatcher: 1 885 → 1 133 tok.

Resultat: 2 562 → 1 323 tok per anrop. Total spend $51,59 → ~$38,7/mån.

## Batchning avfärdad — mätt, inte gissad

`MatchEventNews` gör ett anrop per (event, artikel)-par. Att batcha alla
kandidater för ett event i ett anrop skulle amortera systemprompten.

Mätt mot den faktiska kodvägen (`--dry-run` med schedulerns parametrar,
vilket respekterar den negativa cachen i `crime_event_news`):

| Kandidater/event |    Events |
| ---------------: | --------: |
|                1 | 33 (69 %) |
|                2 |        10 |
|                3 |         2 |
|                5 |         2 |
|                6 |         1 |

**75 par över 48 events → N = 1,56.** 69 % av eventen har exakt en kandidat,
där batchning sparar noll. Efter åtgärderna ovan är nettovinsten ~$3,5/mån —
mot att skriva om en kvalitetskänslig prompt för en svårare uppgift, ett
array-schema som måste mappas tillbaka till artikel-ID, och att ett fel nu
tappar N omdömen i stället för ett. Inte värt det.

Om det ändå ska göras: mät om N först. Ratiot beror på hur mycket
`place_news` innehåller och kan ändras.

## Att tänka på vid promptändringar

- **Eval mot prod-data, inte magkänsla.** Par som redan dömts ligger i
  `crime_event_news.is_match`. Exportera ett stratifierat urval, kör om genom
  nya prompten, jämför. Vid kortningen 2026-08-24 avslöjade en 80-parsseval
  att första utkastet återinförde en generisk-titel-FP (ett nyhetssvep
  accepterades med hög confidence) som #82 arbetat bort.
- **Rätt felriktning.** Prompten föreskriver `is_match=false` vid tvekan —
  bättre missa en match än visa en felaktig länk. En eval som byter en falsk
  positiv mot en falsk negativ är ett godkänt byte.
- **Scheduler-cachen.** Vid deploy av blade-promptar krävs
  `docker compose restart scheduler` på prod. `view:clear` räcker inte —
  scheduler-containern plockar inte upp ändrade kompilerade vyer automatiskt.
