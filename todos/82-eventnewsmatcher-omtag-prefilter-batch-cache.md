**Status:** aktiv — Fas 1 soak passerad 2026-06-02 (täckning 10,5 %, $2,25/dygn < $3-gate → ingen Fas 2). Fas 1.5 brus-/kostnadsfixar gjorda 2026-06-02 (väntar deploy + ny soak)
**Senast uppdaterad:** 2026-06-02

# Todo #82 — EventNewsMatcher: mät rotorsak först, fixa minst möjliga

## Sammanfattning

EventNewsMatcher stängdes av i #81 (2026-05-17) pga 1,6 % event-täckning
($18/mån). Vi upptäckte 2026-05-18 att enskilda high-profile events
(`502381` Huddinge-explosion, `502408` Östersund) hade rika
kandidat-pooler (12 artiklar var, 10 träffar manuellt) — vilket motsäger
"AI hittar inget"-tolkningen.

**Första utkastet av denna todo** föreslog ett 4-fas-omtag (TF-IDF
prefilter → batch → caching → lyft urvalet). En kritisk review
2026-05-18 avfärdade det som premature optimization mot YAGNI: vi
optimerade $0.60/dygn innan vi visste om coverage ens går att lyfta,
och TF-IDF är fel verktyg på svensk blåljustext med synonympar
(smäll/explosion, krock/olycka).

**Omtag efter review:** börja med mätning, gör minsta möjliga fix,
verifiera att täckning lyfts, optimera kostnad bara om det blir nödvändigt.

## Bakgrund

### Pipelinen idag (4 steg)

1. **`app:news:fetch-rss`** — RSS-feeds i `config/news-feeds.php` →
   `news_articles`.
2. **`ClassifyNewsArticles`** — regex-prefilter (blåljus-termer) →
   `classified_at`.
3. **`AiClassifyNewsArticles`** (NewsClassifier-agent, Haiku 4.5) —
   extraherar `places_mentioned` → en rad per place i `place_news`.
4. **`MatchEventNews`** — top-20 events efter trafik → `candidatesFor()`
   joinar `place_news` på `place_id` + datum ±2d → en Haiku-call per
   (event × artikel).

### Fyra möjliga rotorsaker (rangordnade efter sannolikhet)

| #   | Rotorsak                                                     | Var i koden                                                                                                                    | Mätbar hur                                                       |
| --- | ------------------------------------------------------------ | ------------------------------------------------------------------------------------------------------------------------------ | ---------------------------------------------------------------- |
| A   | **`resolvePlaceIds()` returnerar tom array**                 | `MatchEventNews.php:226` — kräver exakt match mot `places.name` på `parsed_title_location` eller `administrative_area_level_2` | SQL: andel events 30d där ingen `place_id` löses                 |
| B   | **`places_mentioned`-extraktionen i NewsClassifier är tunn** | `AiClassifyNewsArticles.php` + Haiku-prompten                                                                                  | Andel artiklar med ≤1 `place_news`-rad; manuell stickprov-review |
| C   | **RSS-feeds täcker bara 4-5 lokaltidningar**                 | `config/news-feeds.php` (9 källor, mest rikstäckande)                                                                          | Listning av kommuner med ≥1 lokal feed                           |
| D   | **Top-20-trafikfilter missar nya high-news events**          | `MatchEventNews::topViewedEventIds` (rad 165)                                                                                  | Stickprov: hur många av senaste 30d high-news events kom med?    |

Review-poängen är att vi gissar mellan A-D istället för att mäta. **A
är gratis att mäta och kan ensam förklara hälften av problemet.**

## Förslag — fasad plan, mätstyrd

### Fas 0 — Mätning (1-2 h, ingen kod-deploy)

SQL-mätningar mot prod-DB (read-only, faller under standing approval):

```sql
-- A: hur många events 30d får tom place_ids?
SELECT
  COUNT(*) AS total,
  SUM(parsed_title_location IS NULL AND administrative_area_level_2 IS NULL) AS missing_both,
  SUM(parsed_title_location NOT IN (SELECT name FROM places)
      AND administrative_area_level_2 NOT IN (SELECT name FROM places)) AS no_place_match
FROM crime_events
WHERE created_at >= NOW() - INTERVAL 30 DAY;

-- B: distribution av place_news per artikel
SELECT places_per_article, COUNT(*) FROM (
  SELECT news_article_id, COUNT(*) AS places_per_article
  FROM place_news WHERE pubdate >= NOW() - INTERVAL 30 DAY
  GROUP BY news_article_id
) t GROUP BY places_per_article ORDER BY places_per_article;

-- C: feed-bredd per kommun (top 50 events-tunga kommuner)
-- (kombinera crime_events.administrative_area_level_2 med news_articles.source
--  efter feed-källor — mer komplext, görs i tinker)

-- D: skuggkörning — hur många av senaste 30d events skulle top-20-filtret missa?
-- (jämför topViewedEventIds(7, 20) × 4 (var 12 h × 2 dygn) över 30d
--  mot mängden events med ≥2 kandidater i place_news ±2d)
```

**Beslutspunkt:** efter Fas 0 vet vi vilken av A-D som dominerar och
kan rita resten av planen. Om A > 50 % → fixa place-resolution först.
Om C dominerar → expandera RSS-feeds (gratis, ingen AI). Om D dominerar
→ byt urval. Bara om B dominerar är AI-pipelinen själv flaskhalsen.

### Fas 1 — Minsta möjliga fix (uppskattning, justeras efter Fas 0)

Reviewens 20-raders förslag:

1. **Slopa top-20-filtret** — byt `topViewedEventIds()` mot "alla events
   senaste 7d med ≥2 kandidater i `place_news` ±2d" (Fas 4 i förra utkastet).
2. **Bättre `resolvePlaceIds()`** — om båda exakta lookups misslyckas,
   testa LIKE-match på stadsdel + kommun, eller fallback till
   `administrative_area_level_1` (län) med en strängare filter senare.
3. **Lägg på substring-koll i `candidatesFor()`** — om `event.parsed_title_location`
   finns och varken `article.title` eller `article.summary` nämner den
   eller `administrative_area_level_2`, drop. Adresserar Östersunds 8
   falska positiver direkt.

Behåll en-Haiku-per-par. Ingen TF-IDF, ingen batch, ingen caching.

### Fas 2 — Batch per event (endast om Fas 1 ger för hög kostnad)

Skicka 1 event + N kandidater i en call, JSON-array tillbaka. Tröskel
för att starta: om Fas 1 ger > $5/dygn eller > 500 calls/dygn. Annars
inte värt komplexiteten.

### Fas 3 — Match-vid-import (alternativ till cron-schemat)

Reviewens förslag: kör matchning vid event-skapande istället för i batch
2 ggr/dygn. En batch-call per nytt event mot artiklar inom ±2d. Fördelar:

- Inget urvalskriterium behövs — varje event matchas exakt en gång
- Lägre latens till publik visning (minuter istället för upp till 12 h)
- Naturlig backpressure (bara nya events, inte alla)

Risk: om en relevant artikel publiceras _efter_ eventet missas den. Kan
mildras med en sekundär backfill-körning (matchar events 1-3 dygn
gamla mot artiklar publicerade efter event:s `created_at`).

Övervägs om Fas 1+2 visar att schemabaserad matchning är klumpig.

### Fas 4 — Prompt caching (sista mile)

Bara om steady state efter Fas 1-3 är > $3/dygn. System-prompten är
~500 tokens; caching ger 30-50 % input-besparing på den. Inte värt
implementations-tiden om kostnaden redan är låg.

## Eval-set (krävs för Fas 2)

Tabellen `crime_event_news` har idag positiva matches (alla `is_match=true`
som Haiku har sparat). Negativ-set saknas — krävs för att mäta
precision/recall.

**Recept:**

1. Exportera alla `crime_event_news`-rader med `confidence='hög'` → 50-100
   positiva par (kvalitetskontrollera 10 stickprov manuellt först).
2. För samma events, hämta kandidaterna som Haiku sade `is_match=false`
   under DRY-RUN-loggar — det är inte loggat idag, kräver en
   engångskörning av `app:event-news:match` med ett nytt
   `--log-decisions`-flag som sparar alla beslut (även false) till disk
   eller separat tabell.
3. Manuellt review:a 50 false-par som confidence-stickprov.

**Estimat:** 4-8 h manuell tid för 100 par totalt. Krävs bara om
Fas 2 (batch) byggs.

## Förväntad kostnadsbild (omräknad efter review)

Reviewens påpekande: $0.30-0.50/dygn vid 10-15 % täckning är önsketänkande.
Linjär skalning från $0.59/dygn vid 171 calls (#81-data) ger ~$10/dygn
vid 3000 calls.

| Steg                             | Calls/dygn | $/dygn  | Täckning   | Kommentar                                 |
| -------------------------------- | ---------- | ------- | ---------- | ----------------------------------------- |
| Idag (avstängt)                  | 0          | $0      | 0 %        | —                                         |
| Legacy (top-20, en-och-en)       | ~170       | $0.59   | 1,6 %      | #81-mätning                               |
| + Fas 1 (lyft urval + substring) | ~1500      | $5-7    | mål 8-12 % | grov uppskattning, kalibreras efter Fas 0 |
| + Fas 2 (batch)                  | ~150       | $1-2    | 8-12 %     | batch sänker call-count ~10×              |
| + Fas 4 (caching)                | ~150       | $0.50-1 | 8-12 %     | sista 50 % på system-prompt               |

Slutmål: ~$15-30/månad för 5-8× täckning. Värt det jämfört med #81:s
$18/månad för 1,6 %.

## Soak-mätning 2026-06-02 (Fas 1, 8 dygn sedan deploy 2026-05-26)

Mätt mot prod (read-only SQL):

| Gate-kriterium                        | Mål                     | Utfall                                          | Verdikt        |
| ------------------------------------- | ----------------------- | ----------------------------------------------- | -------------- |
| Täckning (publika events m. ≥1 match) | >10 %/dygn              | **10,5 %** totalt (56/534), fulla dygn ~10–14 % | ✅             |
| Kostnad                               | <$3/dygn → annars Fas 2 | **$2,25/dygn** snitt (5 289 calls, $17,96/8d)   | ✅ ingen Fas 2 |

Lyft 1,6 % → 10,5 % = **~6,4×** (träffar slutmålets 5–8×). 405 match-rader
(375 hög / 30 medel) på 88 events. **Men $2,25/dygn ≈ $67/mån > slutmålet
$15–30** → kostnadsfråga som spåras i #81.

### Precision-stickprov (eyeball på alla 56 matchade events)

Headline-matchen rätt i ~54/56. Tre brus-mönster, samtliga billiga:

1. **Generiska digest-titlar** — svt-texttv "Inrikesnotiser", Mitt i
   "Morgonens nyheter i Stockholm" kopplas till specifika events.
2. **Dubblett-artiklar** — samma (källa, titel) återkommer som
   nästan-dubbletter (svt-texttv hämtas om vid varje sid-uppdatering;
   "Brand i industribyggnad i Älvsjö" ×15 mot ett event). Varje dubblett
   = en redundant Haiku-call. **Mätt: 17,5 % av kandidat-callsen** (2 281
   → 1 882 efter dedup på (källa, normaliserad titel)) ≈ ~$12/mån.
3. **Topic/temporal-drift** — enstaka AI-FP (Lessebo fick "skatt på
   drivmedel", Umeå fick åtal för gammal misshandel, 503425 Uppsala ärvde
   503332:s Björklinge-artiklar). Lägre volym, svårare/risk → ej åtgärdat.

### Fas 1.5 — brus-/kostnadsfixar (gjorda 2026-06-02, ej deployade)

- **Fix A (mönster 1):** `generic_title_prefixes` i
  `config/news-classification.php` + guard i `ClassifyNewsArticles` —
  digest-titlar klassas aldrig till place_news (blir aldrig kandidater).
- **Fix B (mönster 2):** dedup på (källa, `normalizeTitle`) i
  `MatchEventNews::candidatesFor` — en Haiku-call per distinkt story i
  stället för en per dubblett. `normalizeTitle` strippar texttv-sidnummer
  ("…191"). Verifierat med fokuserat skript (9/9 pass) + PHPStan grön.
- **Kvar (mönster 3):** topic/temporal-drift — substring-koll (Fas 1
  punkt 3, aldrig implementerad) övervägs vid behov efter nästa soak.

**Nästa steg:** deploya Fas 1.5 → ny ~7d-soak → mät om $/dygn faktiskt
sjönk ~17 % (mål ~$1,85/dygn ≈ ~$56/mån) + att täckning hålls och
generiska-titel-FP försvinner. Då beslut om #82 stängs eller om Fas 4
(caching) behövs för att nå slutmålet $15–30/mån.

## Risker

- **Fas 0 visar något oväntat** — t.ex. att rotorsaken är D (top-20
  filter ensamt) och Fas 1 är overkill. Planen tål det: minsta-möjliga-fix-
  principen gäller per steg.
- **Substring-kollen är för aggressiv** — artikel kan beskriva eventet
  utan att nämna platsen explicit ("man knivskuren på krogen igår"
  utan stadsdel). Mitigering: filtret är opt-in, kan stängas av med
  flagga.
- **Batch-prompten tappar precision** — eval-set kräver innan rollout.
- **Hela ansatsen är fel** — om B (NewsClassifier `places_mentioned`)
  är roten är all matchning-side-optimering meningslös. Fas 0 fångar det.

## Vad reviewen ändrade vs första utkastet

| Första utkastet                          | Efter review                                         |
| ---------------------------------------- | ---------------------------------------------------- |
| TF-IDF prefilter (Fas 1) som hörnsten    | **Skrotat** — fel verktyg på synonymrika texter      |
| 4 faser i fast ordning                   | **Mät-driven** — beslut efter Fas 0                  |
| Place-resolution som rotorsak nämns inte | **Lyfts som rotorsak A** — primär kandidat           |
| RSS-bredd som bisats                     | **Lyfts som rotorsak C** — gratis hävstång           |
| Match-vid-import saknas                  | **Tillagt** som Fas 3-alternativ                     |
| Eval-set: "destilleras ur"               | **Konkret 3-stegs-recept** med tidsestimat           |
| Kostnader $0.10-0.50/dygn                | **Realistiska $1-7/dygn** baserat på linjär skalning |

## Confidence

medel-hög — mätningarna i Fas 0 är billiga och deterministiska.
Resten av planen är _villkorlig_ på vad de visar, vilket är poängen.
Risken är att vi bygger Fas 1 utan att invänta Fas 0-svar, då återgår
vi till samma gissningsproblem som första utkastet hade.

## Beroende på

- **#81 soak** (2026-05-24) — vänta in det beslutet innan Fas 1 deployas
  så vi inte rullar tillbaka avstängningen för att direkt ändra design.
- **Fas 0 mätningar** — blockerar all kod-aktion. ~1-2 h SQL.
