# Indexeringströskel för platssidor

Design, 2026-08-02. Följer av SEO-granskningen samma datum (fynd 2).

## Problemet

`/plats/{slug}` svarar 200 så snart det någonsin funnits en händelse på
platsnamnet. Kontrollen som avgör detta frågar efter händelser _någonsin_,
inte i den period sidan visar:

```php
$events = $this->getEventsInPlats($plats, $date, 14, $isToday);  // 14 dagar

if (!$events->count()) {
    $eventsExists = CrimeEvent::where(/* parsed_title_location / adm2 / locations.name */)
        ->exists();                                              // ingen tidsgräns
    if (!$eventsExists) {
        abort(404);
    }
}
// annars: rendera → HTTP 200
```

En gata med ett inbrott 2017 får därmed en permanent indexerbar sida som
aldrig visar något. Noindex-regeln finns men är bortkommenterad
(`PlatsController.php:226`).

### Uppmätt omfattning (prod, 2026-08-01)

| Mått                                             |               Antal |
| ------------------------------------------------ | ------------------: |
| Distinkta platsnamn (routens tre-vägs-matchning) |              32 275 |
| Med minst en händelse senaste 14 dagarna         |                 777 |
| **Renderar tom sida men svarar 200**             | **31 477** (97,6 %) |

Sidorna är mallidentiska med 17 ord i `<main>`, medan `<title>` och
meta-description lovar brottsdata:

> Portgatan: brott & händelser
> Inrapporterade händelser från Polisen.
> Inga händelser har rapporterats från Polisen denna dag.

Det är soft-404 i stor skala: Google måste hämta varje URL för att upptäcka
att den är tom, och eftersom 14-dagarsfönstret rullar finns ingen anledning
för den att sluta återbesöka.

De berörda namnen är i praktiken gatunamn — `mårtsbovägen`, `portgatan`,
`stämjärnsvägen`, `regissörgatan`. Precis det fall den bortkommenterade
koden oroade sig för. Av de 11 278 platser som har exakt en händelse
inträffade 5 924 (53 %) under 2016–2019.

## Beslut

**Tröskel:** en platssida är indexerbar från och med 5 händelser (all-time).

| Tröskel | Blir noindex        | Kvar indexerbara |
| ------- | ------------------- | ---------------- |
| < 3     | 16 118 (49,9 %)     | 16 157           |
| **< 5** | **20 796 (64,4 %)** | **11 479**       |
| < 10    | 25 281 (78,3 %)     | 6 994            |

Siffrorna är räknade på de 32 275 namnen ovan. `locations` innehåller
dessutom 445 203 rader med tomt namn (88 % av tabellen); de bildar en enda
bucket som mappar till `/plats/`-översikten, inte till en platssida, och är
exkluderade här.

**Fallback:** när 14-dagarsfönstret är tomt visas de senaste händelserna
oavsett ålder. Motivet är mätt: av de 11 479 sidor som klarar tröskeln har
bara 898 (7,8 %) en händelse inom 14 dagar, och 4 989 (43 %) har ingen på
över ett år. En tidsbegränsad fallback hade lämnat 43 % kvar som tomma.
Befintlig meta-description lovar redan "Senaste händelserna som skett i och
omkring X" — obegränsad fallback matchar det löftet.

**Omfattning:** regeln gäller båda grenarna i `day()` (med och utan län),
annars blir `/plats/nacka` och `/plats/nacka-stockholms-lan` inkonsekventa.
Månadsvyn `month()` rörs inte — den har redan sin egen grind
(`$robotsNoindex = $totalEvents < 3`, rad 500).

## Angreppssätt

Vi behöver inte räkna. Hämta de senaste N händelserna för platsen oavsett
datum och läs av antalet rader — det ger tröskeln, fallback-innehållet och
404-kontrollen från en enda cachad query.

Övervägda alternativ:

- **Separat `COUNT` + separat fallback-query.** Rakt fram men hämtar samma
  data i två omgångar, med två cachenycklar att hålla i synk.
- **Materialiserad `place_stats`-tabell.** Snabbast i runtime och skulle
  göra en framtida sitemap-utökning nästan gratis, men innebär migration,
  backfill och ett schemalagt jobb mot en query som redan är billig. YAGNI —
  designen stänger inte dörren för den.

## Lösning

### Nya metoder

En per gren, speglande befintliga `getEventsInPlats` /
`getEventsInPlatsWithLan`:

```php
getSenasteEventsInPlats(string $plats, int $limit): Collection
getSenasteEventsInPlatsWithLan(string $platsUtanLan, string $lan, int $limit): Collection
```

Kontrakt: _de N senaste händelserna för platsen, oavsett datum, nyast först._

Villkoren måste vara **identiska** med dagens `exists()`-kontroller —
`parsed_title_location` ELLER `administrative_area_level_2` ELLER
`locations.name`, plus `administrative_area_level_1` i län-grenen. Avviker de
ändras 404-beteendet, vilket är den enda delen av ändringen som kan göra
befintliga sidor otillgängliga.

Cachas via `Cache::remember` med 24 h TTL — en plats all-time-lista ändras
sällan. Queryn går mot det täckande indexet
`locations_name_crime_event_id_index (name, crime_event_id)`.

### Konstanter och vakter

```php
private const INDEXERAS_FRAN_ANTAL_HANDELSER = 5;
private const SENASTE_LIMIT = 10;

// Hämta alltid minst tröskeln — annars går antalet inte att avgöra.
$limit = max(self::SENASTE_LIMIT, self::INDEXERAS_FRAN_ANTAL_HANDELSER);

// locations har 445 203 rader med tomt namn. Tom sträng får aldrig nå
// queryn — den skulle matcha 88 % av tabellen. Gäller båda grenarna:
// $plats i den vanliga, $platsWithoutLan i län-grenen.
if ($plats === '') {
    abort(404);
}
```

Båda gör felläget strukturellt omöjligt i stället för upptäckt i efterhand.
Exponeringen för tomt namn finns i dagens `exists()` också.

### Kontrollflöde i `day()`

Dagens `exists()`-block i båda grenarna ersätts:

```php
$senaste = $this->getSenasteEventsInPlats($plats, $limit);

if ($senaste->isEmpty()) {
    abort(404);                       // ersätter exists()-kontrollen
}

$platsArIndexerbar = $senaste->count() >= self::INDEXERAS_FRAN_ANTAL_HANDELSER;

// Fallback: bara på den datumlösa URL:en, aldrig på explicita datum-routes
if ($events->isEmpty() && $dateOriginalFromArg === null) {
    $events = $senaste;
}
```

och i `$data`:

```php
'robotsNoindex' => !$platsArIndexerbar
    || \App\Helper::shouldNoindexForDateRoute($dateOriginalFromArg, $date['date']),
```

`robotsNoindex` sätts redan av `shouldNoindexForDateRoute()` för gamla
datum-URL:er. Den nya regeln OR:as ihop med den, den ersätter den inte.

Fallbacken gäller bara `/plats/{slug}`. På
`/plats/{slug}/handelser/2019-03-04` vore det vilseledande att visa 2024 års
händelser under 2019 års rubrik; den sidan fortsätter visa "inga händelser"
och är redan noindex via `shouldNoindexForDateRoute`. (Datum-routes 301:ar
numera oftast till månadsvyn eftersom `MONTHLY_VIEWS_PILOT` körs med `all`,
så grenen nås sällan — vakten behövs ändå för korrekthet.)

Tröskeln gäller alltid, oavsett datum, eftersom den handlar om platsen och
inte om dagen.

`$eventsByDay` byggs redan från `$events` och grupperar per dag, så
fallback-händelser från spridda datum renderas korrekt utan vy-ändring.
`metaDescription` är statisk text och påverkas inte.

## Gränsfall

| Fall                            | Beteende                                  |
| ------------------------------- | ----------------------------------------- |
| 0 händelser någonsin            | `abort(404)` — oförändrat                 |
| 1–4 händelser                   | 200, innehåll via fallback, **noindex**   |
| 5+ men inget senaste 14 dagarna | 200, innehåll via fallback, indexerbar    |
| 5+ med färska händelser         | 200, fönstret används, indexerbar         |
| Explicit gammalt datum          | 200, tom lista, noindex (befintlig regel) |

Cachen gör att en plats som går från 4 till 5 händelser tar upp till 24 h på
sig att bli indexerbar. Acceptabelt — Google återbesöker inte oftare.

## Verifiering

Inga automatiska tester (beslut 2026-08-02). Verifieras på renderad sida.

Mätpunkter: `<meta name="robots">` och antal ord i `<main>`. Utgångsvärden
tagna från prod 2026-08-01 innan ändringen:

| Fall                       | URL                   | Nu                 | Förväntat efter            |
| -------------------------- | --------------------- | ------------------ | -------------------------- |
| 1 händelse                 | `/plats/7 eleven`     | 200, index, 18 ord | 200, **noindex**, innehåll |
| 4 händelser                | `/plats/abborrvägen`  | 200, index, 17 ord | 200, **noindex**, innehåll |
| 5 händelser, inget på 4 år | `/plats/åbjörnsgatan` | 200, index, 17 ord | 200, index, **fallback**   |
| 58 händelser, färsk        | `/plats/kviberg`      | 200, index, 29 ord | oförändrad                 |
| Finns inte                 | `/plats/en-plats-xyz` | 404                | oförändrad                 |

Utöver stickproven: svep över ~200 slumpade platssidor efter deploy och
kontrollera att fördelningen noindex/index landar nära förväntade
64 % / 36 %. Det fångar systematiska fel som fem enskilda URL:er missar.

**Regressionsrisk att bevaka:** att `abort(404)` fortfarande träffar exakt
samma platser som idag. Svepet fångar det genom att räkna 404:or.

**Drift:** responscachen håller sidor i 30 min. `responsecache:clear` krävs
vid deploy för att noindex ska slå igenom direkt.

## Utanför omfattningen

- Att lägga in de kvarvarande ~11 479 platssidorna i `sitemap-main.xml`.
  Motiverat och naturligt nästa steg, men separat beslut.
- Kalibrering av tröskeln mot GSC-klickdata, på samma sätt som
  `isThinForSeo()` kalibrerades. Kräver att `mcp-gsc` är uppe igen; värdet 5
  är valt på fördelningen, inte på uppmätt trafik.
- Innehållsdjupet på de sidor som behålls. `/plats/kviberg` har 58 händelser
  och en färsk igår, men renderar ändå bara 29 ord i `<main>`.

## Försök 1 (2026-08-02) — deployad och återställd samma dag

Deployad som `e6821ab`, återställd som `7060830` efter ~1 timme.
Funktionellt korrekt, prestandamässigt oacceptabel.

**Vad som fungerade:** alla fem verifieringsfallen på prod, svep över 200
sidor gav 63 % noindex (mål ~64 %), 404-beteendet oförändrat.

**Varför den rullades tillbaka:** `getSenasteEventsInPlats()` mättes till
6–15 s på prod. Kalla platssidor gick från 3,5–4,8 s till 12,8–16,9 s och
load average steg 8,7 → 10,5 på fyra kärnor.

**Rotorsak, från EXPLAIN på prod:**

```
tabell=crime_events  typ=index  nyckel=crime_events_created_at_index
rader=10  extra=Using where
```

MySQL valde att vandra `created_at`-indexet baklänges och filtrera rad för
rad tills `LIMIT 10` var fylld. För ett ovanligt platsnamn innebär det
nästan hela tabellen på 507 000 rader. Därför var _ovanligare_ platser
långsammare: `abborrvagen` (4 händelser) 14,7 s mot `kviberg` (10
händelser) 6,4 s. Gamla `exists()` slapp detta eftersom den kunde stanna
vid första träffen.

Bidragande: **det finns inget index på `parsed_title_location`**, så den
OR-grenen är ett filter och inte en uppslagning.

**Vad nästa försök behöver göra annorlunda:** lös upp kandidaterna via de
indexerade vägarna först och sortera sedan en liten mängd, i stället för
att låta optimeraren para ihop `ORDER BY created_at` med ett OR-villkor:

```php
$ids = collect()
    ->merge(DB::table('locations')->where('name', $plats)
        ->orderByDesc('crime_event_id')->limit($limit)->pluck('crime_event_id'))
    ->merge(CrimeEvent::where('administrative_area_level_2', $plats)
        ->orderByDesc('created_at')->limit($limit)->pluck('id'))
    ->unique();

CrimeEvent::whereIn('id', $ids)->orderByDesc('created_at')->limit($limit)->get();
```

`locations(name, crime_event_id)` är täckande och `crime_event_id` är
stigande med tiden, så DESC på den är en bra proxy för nyast först. Ska
`parsed_title_location` ingå effektivt krävs ett index på den kolumnen.

**Lärdom om verifieringen:** lokal miljö dolde problemet helt — samma
datamängd men utan samtidig last, och `verifiera.sh` mäter bara HTTP-kod,
robots-tagg och ordantal. Nästa försök måste mäta **svarstid på kall
sida** före deploy, inte bara korrekthet. Lägg till `%{time_total}` i
verifieringsskriptet och sätt en gräns.

## Försök 2 (2026-08-02) — deployad

Migration `ee7b29a`, kod `782ddd6`. Två ändringar mot försök 1.

**1. Prefixindex på `crime_events.parsed_title_location`.** Kolumnen är
TEXT (max 20 tecken, snitt 11,2) och saknade index helt. Prefix 50 ger
fyra gångers marginal. Deployades ensamt före koden så effekten kunde
mätas isolerat.

**2. Kandidat-ID:n i stället för ett stort OR.**
`hamtaSenasteViaKandidatIds()` löser upp varje matchningsväg mot sitt
eget index, slår ihop ID:na och sorterar den lilla mängden:

| Steg                                  | Tid på prod |
| ------------------------------------- | ----------- |
| `locations` (täckande index)          | 3–31 ms     |
| `administrative_area_level_2`         | 2–22 ms     |
| `parsed_title_location` (efter index) | 1,5–45,9 ms |
| Slutlig `whereIn` på primärnyckel     | 4–21 ms     |

`locations`-steget filtrerar `is_public` explicit i joinen; de två
CrimeEvent-stegen får det globala scopet gratis. Utan det hade en plats
vars senaste händelser är dolda kunnat 404:a trots att äldre synliga
finns.

**Uppmätt hela queryn på prod före deploy** (grinden som saknades förra
gången):

| Plats         | Försök 1  | Försök 2    |
| ------------- | --------- | ----------- |
| `kviberg`     | 6 442 ms  | 72,8 ms     |
| `abborrvagen` | 14 715 ms | 11,2 ms     |
| `portgatan`   | 13 261 ms | 7,7 ms      |
| Län-varianten | —         | 5,1–16,7 ms |

**Utfall på prod efter deploy:** alla fem verifieringsfallen korrekta,
kalla sidladdningar 191–309 ms. Jämförelse: 12 800–16 900 ms med försök
1, och **3 500–4 800 ms med den ursprungliga koden** — indexet gör alltså
platssidorna snabbare än de var innan hela det här arbetet började,
eftersom även den gamla `exists()`-vägen låg på samma oindexerade kolumn.
Load average föll från 10,5 till 2,66.

**Svep över 200 slumpade platssidor på prod:**

| Mätning       | Försök 1 | Försök 2 | Mål      |
| ------------- | -------- | -------- | -------- |
| noindex       | 109      | 125      | —        |
| index         | 64       | 75       | —        |
| Andel noindex | 63 %     | **62 %** | ~64 %    |
| 404           | 1        | **0**    | 0 / lågt |
| Timeout       | **26**   | **0**    | 0        |

De 26 timeouterna i försök 1 var symptomet vi missade före deploy. Att de
är borta, och att 404-räknaren står på noll, bekräftar både prestandan
och att queryn träffar samma platser som den gamla `exists()`-kontrollen.

**Fälla vid mätning av svarstid:** `?nocache=` bryter **inte** responscachen
— parametern står i `ignored_query_parameters` i `config/responsecache.php`
(tillsammans med `t`, `_`, `timestamp`, `utm_*`, `gclid`, `fbclid`).
Cache-nyckeln blir densamma och ett cachat svar returneras, så mätningen
visar cache-träffar och inte rendering. Använd en parameter utanför den
listan, t.ex. `?kallmatning=$RANDOM`. Detta gav först felaktigt låga
"kalla" tider i verifieringen av försök 2; ommätt med rätt parameter
landar sidorna på 0,15–0,28 s, så slutsatsen stod sig — men mätningen
gjorde det inte.

**Indexerbarhet kontrollerad på live efter deploy** (att inget annat än de
tunna platssidorna blev noindex): `/`, `/stockholm`, `/goteborg`,
`/malmo`, `/uppsala`, `/lan/skane-lan`, `/lan`, `/statistik`, `/brand`,
`/inbrott`, `/plats/`, `/nara`, `/vma`, `/helikopter` samt färska
eventsidor — samtliga indexerbara. Gamla tunna eventsidor är fortsatt
noindex via `isThinForSeo()`, vilket är avsett (#29).

**Sidofynd, ej relaterat till den här ändringen:** `/om` ligger i
`sitemap-main.xml` (`GenerateSitemap.php:53`) men svarar **404**. Vi
skickar alltså en död URL till Google.

## Kalibrering mot GSC (2026-08-02)

Tröskelvärdet 5 valdes ursprungligen på fördelningen, inte på uppmätt
trafik, eftersom `mcp-gsc` låg nere. Servern blev åtkomlig samma kväll
(krävde `mcp[cli]<2`), så kalibreringen kunde göras i efterhand.

Underlag: 90 dagar, 2026-05-04 – 2026-08-01 — alltså **före** deployen,
vilket gör den till en ren baslinje. 9 527 platssidor med klick matchades
mot händelseantal; 31 klick (0,1 %) gick inte att matcha och är bortsedda
från. Metoden speglar routens matchning, inklusive kapning av
läns-suffix i `/plats/{plats}-{län}`.

| Tröskel        | Sidor m. klick som tappas | Klick/90d | % av platsklick | % av sajtens klick | Klick/mån |
| -------------- | ------------------------: | --------: | --------------: | -----------------: | --------: |
| < 2            |                       612 |       564 |           1,9 % |             0,43 % |       188 |
| < 3            |                     1 060 |     1 093 |           3,6 % |             0,82 % |       364 |
| **< 5 (live)** |                 **1 709** | **1 667** |       **5,5 %** |         **1,26 %** |   **556** |
| < 10           |                     2 972 |     3 347 |          11,0 % |             2,52 % |     1 116 |

Referensvärden för perioden: sajten 132 692 klick totalt, platssidorna
30 332 (22,9 % av sajten). Klick per händelseantal faller brant men har
lång svans: 1 händelse 564 klick, 2 händelser 529, 3 händelser 329.

**Bedömning.** Kostnaden ligger i övre kanten av precedensen —
`isThinForSeo()` kalibrerades mot ~1 % klickförlust, här är det 1,26 % av
sajtens totala klick.

Men siffrorna är ett **tak, inte en prognos**: de mäter vad sidorna drog
när de var indexerade, inte vad som faktiskt går förlorat. En del av
intentionen bör fångas av stads- och länssidorna som är kvar i indexet.

**Viktig observation:** soft-404-problemet löses av **fallbacken**, inte
av tröskeln. Varje platssida med minst en händelse visar nu riktigt
innehåll oavsett tröskel. Tröskelns kvarvarande motivering är enbart
kvalitetssignalen från tunt innehåll. Delarna är alltså separerbara — går
det att sänka tröskeln senare kommer soft-404 inte tillbaka.

**Beslut 2026-08-02:** behåll 5 och mät. Att sänka nu vore att agera på
ett tak. Grinden 2026-09-01 avgör.

## Kvarstår att mäta

**2026-09-01, 30 d efter deploy.** Jämför mot baslinjen ovan:

1. Drar platssidorna fortfarande ~30 332 klick/90d, eller har de tappat
   nära de 1 667 som de understa 1 709 sidorna stod för? Tappet ska vara
   **mindre** än 1 667 om stads- och länssidorna fångar upp intentionen.
2. Har GSC:s "Exkluderad av noindex-tagg" stigit med ~20 800?
3. Har soft-404-rapporten sjunkit?

Om tappet ligger nära hela 1 667 fångades ingenting upp — överväg då att
sänka tröskeln till 3, vilket enligt tabellen återför ~192 klick/mån och
ändå håller 16 118 sidor utanför indexet.
