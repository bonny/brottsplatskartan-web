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
