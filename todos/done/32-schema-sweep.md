**Status:** implementerat 2026-04-27 — väntar på 4v GSC-mätning
**Senast uppdaterad:** 2026-04-27 (steg 1–7 klara)
**Härledd från:** SEO 2026-review av #25

# Todo #32 — Schema.org-sweep: NewsArticle + Dataset + Place + FAQPage

## TL;DR

Lägg till och korrigera strukturerad data sajt-brett för att vinna AI Overview-
citationer och Google Discover-placering 2026. Fyra kategorier:

1. **Korrigera `NewsArticle` per enskild event-sida** — finns redan, men har
   3 verkliga buggar (saknad `sourceOrganization`, headline ej cap:ad till
   110 tecken, ingen `articleBody`).
2. **`Place` med Wikidata-`sameAs` på plats/län-startsidor** — entity-graph-koppling.
   Full backfill via batch + review-kö, inte bara top 50.
3. **`Dataset` på plats-startsidor + månadsvyer** — INTE på dagsvyer (schema-spam-risk).
4. **`FAQPage`** — schemat ger AI-Overview-extrahering, men inte SERP-rich-results
   längre (Google augusti 2023 för icke-auktoritativa sajter). Justera förväntningar.

## Audit (klar 2026-04-27)

| Sida-typ        | Vad finns idag                                                                          | Verkligt gap                                                       |
| --------------- | --------------------------------------------------------------------------------------- | ------------------------------------------------------------------ |
| Enskild event   | `NewsArticle` + `contentLocation` (Place) + `about` (Thing) + author/publisher/image[3] | `articleBody`, `sourceOrganization`, `wordCount`, headline-cap 110 |
| Plats-startsida | `place-jsonld.blade.php`-partial inkluderad                                             | Wikidata `sameAs`, ev. `containsPlace`, `CollectionPage`-wrapper   |
| Län-startsida   | Samma partial inkluderad                                                                | Wikidata `sameAs`, `Place.@type = AdministrativeArea`              |
| Typ-startsida   | Bara global `WebSite`/`Organization`                                                    | `CollectionPage` + `about: Thing`                                  |
| Dagsvy          | Inget specifikt                                                                         | `CollectionPage` + `BreadcrumbList` (INGEN Dataset)                |
| Månadsvy (#25)  | Full `Dataset` + `FAQPage` + `BreadcrumbList` ✓                                         | Klart                                                              |
| Startsida       | `WebSite` + `Organization` + `SearchAction` ✓                                           | `Organization.sameAs` (Wikidata + sociala)                         |
| `/statistik`    | `Dataset` ✓                                                                             | OK                                                                 |

Implementations-platser:

- `app/CrimeEvent.php::getLdJson()` (rad 1062) — NewsArticle-output
- `resources/views/parts/place-jsonld.blade.php` — Place-partial
- `resources/views/parts/itemlist-jsonld.blade.php` — ItemList-partial
- `resources/views/single-plats-month.blade.php` — Dataset/FAQPage-referens
- `resources/views/layouts/web.blade.php` (rad 74) — global WebSite/Organization

## Per-content-typ-strategi (reviderad)

| Sida-typ        | Primär schema-typ                  | Sekundär                              |
| --------------- | ---------------------------------- | ------------------------------------- |
| Enskild event   | `NewsArticle`                      | `Place` (via contentLocation)         |
| Plats-startsida | `Place` + `CollectionPage`         | `BreadcrumbList`                      |
| Län-startsida   | `Place` (AdministrativeArea) + `CollectionPage` | `BreadcrumbList`             |
| Typ-startsida   | `CollectionPage`                   | `BreadcrumbList` + `about: Thing`     |
| Dagsvy          | `CollectionPage`                   | `BreadcrumbList`                      |
| Månadsvy (#25)  | `Dataset` + `FAQPage`              | `Place` + `BreadcrumbList`            |
| Startsida       | `WebSite` + `Organization`         | `SearchAction`                        |

**Förändringar mot ursprunglig plan:**
- `Event`-typ TAS BORT som sekundär på event-sidor. Schema.org `Event`
  kräver `startDate` och är för schemalagda evenemang (konserter,
  möten). Rapporterade brott är inte `Event`. Risk för misleading
  markup-flaggning.
- `Dataset` TAS BORT från dagsvyer. Google Dataset Search post-2024
  flaggar query-resultat-slices som schema-spam. Behåll på plats-
  startsida + månadsvyer + `/statistik`.

## NewsArticle — buggar att fixa i `getLdJson()`

```php
// Lägg till — sourceOrganization för E-E-A-T-trust
"sourceOrganization" => [
    "@type" => "GovernmentOrganization",
    "name" => "Polismyndigheten",
    "url" => "https://polisen.se/",
],

// Cap:a — Google's NewsArticle-spec kräver ≤110 tecken
"headline" => mb_substr($title, 0, 110),

// Lägg till — articleBody (description ≠ articleBody för AI Overviews)
"articleBody" => $this->getDescriptionAsPlainText(),
"description" => mb_substr($this->getDescriptionAsPlainText(), 0, 200),
"wordCount" => str_word_count($this->getDescriptionAsPlainText()),

// Lägg till — speakable (Google Assistant + Bing Read Aloud, ~12-18% news-trafik 2026)
"speakable" => [
    "@type" => "SpeakableSpecification",
    "cssSelector" => ["h1", ".event-description"],
],

// Lägg till — isPartOf koppling till månadsvyn (entity-graph)
"isPartOf" => [
    "@type" => "CollectionPage",
    "@id" => route('platsMonth', [
        'plats' => $this->parsed_title_location_slug,
        'year' => $this->parsed_date->format('Y'),
        'month' => $this->parsed_date->format('m'),
    ]),
],
```

**`author` förblir Brottsplatskartan** (vi är publisher, inte Polismyndigheten).
`sourceOrganization` är rätt fält för aggregerat innehåll.

**Inte:** `Event`-typ. **Inte:** `dateModified`-bumpning för "freshness"
(Google deprecated 2023; bara bumpa vid faktisk content-ändring, t.ex.
när AI rewriter (#10) skriver om hela artikeltexten).

## Wikidata-strategi (utvidgad)

`php artisan wikidata:backfill`-kommando för **alla ~1000 platser**, inte bara top 50.

```php
// Auto-accept-regler
foreach ($locations as $location) {
    $results = wikidataApi('wbsearchentities', [
        'search' => $location->name,
        'language' => 'sv',
        'type' => 'item',
    ]);

    if (count($results) === 1) {
        $desc = $results[0]['description'] ?? '';
        $autoAccept = preg_match('/(kommun|stad|ort|län|tätort)/i', $desc);
        $location->wikidata_qid = $results[0]['id'];
        $location->wikidata_review_needed = !$autoAccept;
    } else {
        $location->wikidata_review_needed = true;
    }
}
```

**Fallback:** hämta sv-Wikipedia-URL → extrahera Q-id ur
`<link rel="alternate" type="application/wikibase-item">`.

**Schema-tillägg:**
- Ny kolumn `Locations.wikidata_qid` (string, nullable)
- Ny kolumn `Locations.wikidata_review_needed` (bool, default true)
- Ny kolumn `Locations.wikidata_verified_at` (datetime, nullable)

**Drift:** årlig cron (`@yearly`) för att fånga kommunsammanslagningar.

## Risker

| Risk                                                | Mitigering                                                               |
| --------------------------------------------------- | ------------------------------------------------------------------------ |
| Schema-spam-policy från Google                      | Bara FAQ-svar som matchar synlig text; ingen Dataset på dagsvyer         |
| `Event`-typ flaggas som misleading                  | Använd inte `Event` på event-sidor (#32 v2 — borttaget från strategin)   |
| Wikidata-Q-id-felmatchning                          | Auto-accept bara när 1 träff OCH desc innehåller kommun/stad/län         |
| Performance-kostnad av schema-rendering (~10ms/event) | Cache `ldjson:{event_id}:{updated_at}` i Redis 7d TTL                  |
| Microdata-konflikt om gammal inline-markup finns    | `grep -rn "itemprop\|itemscope" resources/views/` innan deploy           |
| FAQ-rich-result förväntas i SERP                    | Stryk från KPI; AI-Overview-extrahering är värdet                        |
| AI Overview-citation kan ge färre clicks            | Acceptabel — varumärkes-exponering kompenserar                           |

## Implementation-ordning (reviderad)

Prioriterad efter mätbart impact-per-tid:

| Steg | Insats                                                                              | Status                                                | Impact                          |
| ---: | ----------------------------------------------------------------------------------- | ----------------------------------------------------- | ------------------------------- |
|    1 | Fixa NewsArticle: sourceOrganization + headline-cap + articleBody + speakable + isPartOf | ✅ klart 2026-04-27 (`CrimeEvent::getLdJson`)    | Hög — ~200k sidor direkt        |
|    2 | Verifiera `place-jsonld` faktiskt inkluderas på single-plats + single-lan          | ✅ klart — fanns redan + CollectionPage tillagt        | Medel — ~1000 sidor             |
|    3 | Lägg `CollectionPage` + `about: Thing` på typ-startsidor                            | ✅ klart — `parts/collectionpage-jsonld` skapad        | Medel — ~50 sidor               |
|    4 | Wikidata-backfill: artisan-kommando + auto-accept + review-kö                       | ✅ klart — 291/328 auto-accept (88,7 %), 32 review     | Hög för long-tail AI Overviews  |
|    5 | Lägg `Place.sameAs` (Wikidata) på plats/lan-sidor när Q-id finns                    | ✅ klart — partial+plats+lan+city, 21 län hardcodade   | Hög entity-graph-vinst          |
|    6 | `Organization.sameAs` på startsidan + description + GitHub                          | ✅ klart — `layouts/web.blade.php`                     | Låg-medel disambiguation        |
|    7 | Schema-cache i Redis (`ldjson:{event_id}:{updated_at}`)                             | ✅ klart — 4,68 ms → 0,23 ms (~20x speedup)            | Performance, ej SEO             |
|    8 | GSC + Bing Webmaster-mätning efter 4v                                               | ⏳ pågår — mät 2026-05-25                              | Validering                      |

**FAQPage på fler sidor är medvetet INTE i listan** — månadsvyerna har redan
det, och bredare deploy ger ringa ROI när FAQ-rich-results inte längre
visas i SERP.

## KPI:er (reviderad)

- **Primär:** GSC Enhancements-rapporten — Article-rich-result-impressions ↑
  (mätbart 2-4v).
- **Sekundär:** GSC top-queries med "varför/hur/vad/hur många" — sidor som
  rankar för dessa = AI-Overview-kandidater.
- **Bing Webmaster Tools:** Copilot-citationer logas där; dubbel-mät vs
  Google.
- **Manuell SERP-spot-check:** 1g/v på topp-30-queries — manuell verifiering
  att vi citeras i AI Overviews.

**Stryk:** "FAQ-rich-result i GSC" som primär metric (visas inte längre).
**Stryk:** "Top Stories-eligibility" (kvalificerar inte utan Publisher
Center-status).

Tidshorisont: 4-8 veckor för Article/Dataset-rich-results; 8-12 veckor för
traffic-impact.

## Beroenden

- Inga blockers. Kan starta direkt.
- **Synergi med #25:** Dataset/FAQPage-schemat på månadsvyer byggs där.
  #32 hanterar resten av sajten.
- **Synergi med #10:** AI-omskrivna titlar förbättrar `headline` på
  `NewsArticle`. Kör #32 **före** #10 — schema-fixarna är låg-hängande
  frukt och #10 lyfter automatiskt när det körs.

## Implementation-detaljer (för 32 platser i review-kön)

Manuell sanering av `Place::where('wikidata_review_needed', true)
->whereNotNull('wikidata_qid')->get()` ska göras innan dessa 32
platser får sin Q-id i schemat. Idag ignoreras de aktivt
(view-villkoret `!$place->wikidata_review_needed`).

Lista dem med:
```bash
docker compose exec app php artisan tinker --execute='
App\Place::where("wikidata_review_needed", true)
  ->whereNotNull("wikidata_qid")->pluck("wikidata_qid", "name")
  ->each(fn($qid, $name) => print("$name => $qid\n"));
'
```

För varje rad: kolla `https://www.wikidata.org/wiki/{qid}`, jämför
mot platsen, sätt `wikidata_review_needed = false` om OK eller
uppdatera `wikidata_qid` om annan match är rätt.

5 platser fick ingen träff alls (`wikidata_qid IS NULL`) — kvalificerar
sannolikt inte för Wikidata-uppslag (smala bynamn). Lämnas som null.

## Confidence-nivå

**Hög.** Inga arkitektur-risker. Schema är additivt — tar inte bort
existerande funktion. Värde verifierbart via GSC inom 2-4 veckor
post-deploy. Steg 1-3 är låg-hängande frukt; Wikidata-steget (4-5) är
där den verkliga AI-Overview-vinsten ligger 2026 men kräver mest tid.
