**Status:** avfärdad 2026-10-07 — Pär: ingen backfill. Gamla artiklar rensas efter 90 d, så de flesta gamla events går inte att matcha och träffarna skulle ändå försvinna. Idén att låta `app:news:prune` behålla matchade artiklar står kvar nedan om den blir aktuell
**Senast uppdaterad:** 2026-10-07
**Källa:** Inbox Brottsplatskartan (2026-10-04)

# Todo #105 — Backfilla nyhetsmatchning för gamla högtrafikerade händelser

## Sammanfattning

Varför matchas inte media/nyheter till denna? Det finns flera nyheter som handlar om denna ju.
https://brottsplatskartan.se/stockholm/explosion-huddinge-502381

**Status 2026-08-21: delvis.** Matchningen är omgjord (#82) och kör sedan 2026-08-21 var 15:e
minut för färska händelser. Men urvalet tittar bara på de senaste dygnens händelser — den här från
maj har fortfarande noll nyheter. **Öppen fråga: ska gamla högtrafikerade händelser backfillas?**

## Bakgrund

EventNewsMatcher (#82, kostnad i #102) körs bara på färska händelser.
Händelser från före omgjorda matchningen saknar nyheter trots att de har
trafik.

## Förslag

1. Ta fram de N mest besökta händelserna (`crime_views`) utan matchade
   nyheter.
2. Uppskatta kostnaden för en engångskörning med samma cap/dedup som
   produktionskoden (se docs/ai-kostnad.md).
3. Finns nyhetsartiklarna ens kvar i `news_articles` för så gamla händelser?

## Utredning 2026-10-07

Mätt mot prod-data (enbart SELECT via tinker). Kandidaturvalet speglades
lokalt i ett skript som följer `candidatesFor()` +
`copyVerdictsForJudgedStories()` i exakt kodordning: platsuppslag som
`resolvePlaceIds()`, `pn.pubdate` inom ±2 dygn, uteslut redan bedömda
artikel-id, `ORDER BY na.pubdate DESC LIMIT 100`, dedup på
`NewsArticle::storyKey`, **sedan** `take(20)`, sist bort med storys som
redan har ett beslut. Rådata och skript ligger i `tmp-105/` (gitignorerad).

### Finns artiklarna kvar för gamla händelser? Nej

`app:news:prune` raderar `news_articles` äldre än 90 dygn (`fetched_at`).
Äldsta artikel i dag: 2026-07-09. Både `place_news` och
`crime_event_news` har `cascadeOnDelete` på `news_article_id`, så när
artikeln rensas försvinner också **matchningen**. Äldsta raden i
`crime_event_news` är också 2026-07-09.

Det betyder att varje händelse tappar sin nyhetssektion ungefär 90 dygn
efter händelsen, även när matchningen gick bra. Det är det egentliga
problemet för gamla högtrafikerade händelser. Uteblivna matchningar är
en mindre del av det.

### Varför har 502381 inga nyheter?

- **Platsuppslaget fungerar.** `parsed_title_location = Huddinge` → place 83.
  (`administrative_area_level_2` är NULL, men den behövs inte här.)
- **Då:** händelsen skapades 2026-05-18. Matchningen återaktiverades
  2026-05-26 med `--days=7`, och cutoff blev 2026-05-19. Händelsen låg
  alltså ett dygn utanför fönstret och valdes aldrig.
- **Nu:** artiklarna från maj är rensade. Det finns inga kandidater kvar
  att matcha, och en eventuell matchning hade ändå kaskadraderats i
  augusti. Händelsen går inte att backfilla.

### Topp-N mest visade (händelser skapade senaste 6 mån)

Kostnad per anrop enligt `ai_usage_logs`, EventNewsMatcher, 30 dygn:
8 030 anrop, i snitt 1 613 input- och 71 output-tokens, $15,81 totalt,
alltså **$0,00197/anrop** (Haiku 4.5 $1/$5 per Mtok enligt
`config/ai-pricing.php`).

|     N | Skapade före artikelretentionen (omöjliga) | Saknar `crime_event_news` | …och har kandidater | Events med anrop | Haiku-anrop | Kostnad |
| ----: | -----------------------------------------: | ------------------------: | ------------------: | ---------------: | ----------: | ------: |
|   200 |                                         82 |                       152 |                  11 |               20 |         103 |   $0,20 |
|   500 |                                        249 |                       391 |                  49 |               73 |         472 |   $0,93 |
| 1 000 |                                        491 |                       795 |                  99 |              142 |         931 |   $1,83 |

Story-kopiering (#103) ersätter 9/18/32 anrop. 8–28 events saknar plats
(`parsed_title_location` matchar inget i `places`).

**De flesta kvarvarande kandidaterna har redan bedömts en gång.**
Avslag sparas först sedan 2026-08-21 (äldsta `is_match = 0`). Före det
körde matchningen 400–700 anrop/dygn och sparade bara träffar. Kandidater
för händelser i juli och början av augusti är därför till största delen
par som Haiku redan har avvisat. Siffrorna ovan överskattar alltså nyttan.

**Undantaget är ett glapp 2026-08-10–16.** Då finns inga
EventNewsMatcher-anrop alls i `ai_usage_logs`, så händelser från
2026-08-08–17 fick aldrig sina sena artiklar bedömda. Över **alla**
händelser (inte bara topp-N) ser det ut så här:

| Händelser skapade | Events med anrop | Haiku-anrop |   Kostnad |
| ----------------- | ---------------: | ----------: | --------: |
| 07-07 – 08-07     |              661 |       3 565 |     $7,02 |
| 08-08 – 08-16     |              230 |       1 364 |     $2,69 |
| efter 08-16       |               53 |         165 | (löpande) |

"Efter 08-16" är nästan bara 08-17 (morgonen innan matchningen var igång
igen) och 2026-10-05–07, som schemat tar själv.

Av topp-1000 ligger 42 events och 296 anrop ($0,58) i glappet. Där finns
till exempel 507842 (brand i Arboga, 19 kandidater) och flera
Stockholmshändelser med 20 kandidater och 0 rader.

### Kodstöd behövs inte

`eventsWithCandidates()` väljer redan alla events efter cutoff som har
obedömda kandidater, och ordningen spelar ingen roll när `--limit` täcker
allt. En `--top-viewed`-flagga skulle spara ~$2 jämfört med att köra
hela glappet, och det är inte värt koden.

## Rekommendation

1. **No-go på "backfilla gamla högtrafikerade" som det var formulerat.**
   Hälften av topp-1000 är äldre än artikelretentionen och kan inte
   matchas. För resten är den förväntade vinsten liten (redan avvisade
   par). Och det som matchas försvinner igen efter 90 dygn.
2. **Det som faktiskt ger effekt (beslut för Pär):** låt
   `app:news:prune` behålla artiklar som har en träff (`is_match = 1`) i
   `crime_event_news`, till exempel med `whereNotExists` i
   `PruneNewsArticles`. Det rör sig om ~1 900 träffrader på tre månader,
   så lagringen är försumbar. Utan den ändringen tappar varje händelse
   sin nyhetssektion efter ~90 dygn, också framtida stora händelser.
   Avslagsraderna kan fortfarande kaskadrensas.
3. **Om punkt 2 görs:** kör en engångsbackfill av glappet. Kör alltid
   dry-run först (gratis):

    ```bash
    docker compose exec app php artisan app:event-news:match --days=61 --limit=400 --dry-run | grep -c DRY-RUN
    docker compose exec app php artisan app:event-news:match --days=61 --limit=400
    ```

    `--days=61` gäller vid körning 2026-10-07 (cutoff ≈ 2026-08-07). Räkna
    om om det körs senare. Prognos: 332 events med obedömda artiklar, 287
    med anrop, **~1 560 anrop ≈ $3**. Glappets artiklar rensas annars
    ~2026-11-05–17, så utan punkt 2 är körningen bara värd ~5 veckors
    visning. Hoppa i så fall över den.

    Att ta med juli (`--days=93`, ~5 100 anrop ≈ $10) är inte värt det.
    Artiklarna rensas nu i dagarna och kandidaterna är till största delen
    redan avvisade.

## Risker

AI-kostnad; gamla artiklar kan vara rensade.

## Confidence

hög för siffrorna (prod-data, speglad kodväg). Prognosen bygger på
tie-ordning i `ORDER BY na.pubdate` och kan skilja ett fåtal anrop.
Verifiera med dry-run före riktig körning.
