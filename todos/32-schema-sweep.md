**Status:** aktiv (designfas — kan köras parallellt med #25)
**Senast uppdaterad:** 2026-04-26
**Härledd från:** SEO 2026-review av #25

# Todo #32 — Schema.org-sweep: NewsArticle + Dataset + Place + FAQPage

## TL;DR

Lägg till strukturerad data sajt-brett för att vinna AI Overview-
citationer och Google Discover-placering 2026. Tre kategorier:

1. **`NewsArticle` per enskild event-sida** — saknas idag, högst ROI.
2. **`Dataset` på aggregeringssidor** (plats, län, typ-startsidor) — kvantifierbar
   datamängd som ingen konkurrent strukturerar.
3. **`Place` med Wikidata-`sameAs` på platssidor** — entity-graph-koppling.
4. **`FAQPage` med svar-format på topp av aggregeringssidor** — AI Overview-
   citation.

## Bakgrund

SEO-review av #25 (månadsvyer) avslöjade att hela sajtens
strukturerade data är 2018-nivå (`WebPage` + `BreadcrumbList`).
2026 har Google AI Overviews och Dataset Search blivit viktiga
trafikkällor och **bygger på strukturerad data**. Citerade sidor i
AI Overviews är de som har rätt schema-typ för rätt content-typ.

Utdrag från review:
> "Schema markup är kraftigt uppviktad 2026 just därför att
> AI-features parsear den. NewsArticle/Dataset-schema är det
> enskilt största lyftet."

`CrimeEvent::getLdJson()` returnerar idag schema, men granskningen
behövs för att verifiera att rätt typer används överallt.

## Scope

### Per-content-typ-strategi

| Sida-typ                   | Primär schema-typ         | Sekundär             | Status idag       |
| -------------------------- | ------------------------- | -------------------- | ----------------- |
| Enskild event              | `NewsArticle`             | `Place` + `Event`    | Verifiera         |
| Plats-startsida            | `Place` + `CollectionPage` | `BreadcrumbList`     | Saknas (Place)    |
| Län-startsida              | `Place` + `CollectionPage` | `BreadcrumbList`     | Saknas (Place)    |
| Typ-startsida (`/typ/...`) | `CollectionPage`          | `BreadcrumbList`     | Verifiera         |
| Dagsvy `/handelser/{datum}` | `Dataset`                | `BreadcrumbList`    | Verifiera         |
| Månadsvy (#25)             | `Dataset` + `FAQPage`     | `Place` + `BreadcrumbList` | Bygger med #25 |
| Startsidan                 | `WebSite` + `Organization` | `SearchAction`       | Verifiera         |

### NewsArticle på enskild event

Idag lyfter `getLdJson()` schema men det är inte verifierat att
`NewsArticle`-typen används korrekt med Google's krav:

- `headline` (≤110 tecken)
- `datePublished` + `dateModified`
- `author` (Polismyndigheten som `Organization`)
- `publisher` (Brottsplatskartan som `Organization` med `logo`)
- `image` (1200×675 + 800×600 + 640×640)
- `articleBody` eller `description`
- `articleSection` (brottstyp)
- `inLanguage` "sv-SE"

**Specifika krav 2026:**
- Datum-fält måste vara ISO-8601 med tidszon
- `author.@type` måste vara `Organization` eller `Person` (inte string)
- `image` måste vara absolut URL (inte relativ)
- För NewsArticle som "kvalificerar för Top Stories": `dateModified` ska
  uppdateras vid varje content-ändring (när AI rewriter (#10) körs t.ex.)

### Dataset på aggregeringssidor

Brottsplatskartan har faktiskt en kvantifierbar datamängd
(events per plats × period). `Dataset`-schema är gratisarbete med
direkt Google Dataset Search-impact.

Kärnfält:
```json
{
  "@type": "Dataset",
  "name": "Polishändelser i Uppsala",
  "description": "...",
  "spatialCoverage": { "@type": "Place", "name": "Uppsala", "geo": {...}, "sameAs": "wikidata-Q-id" },
  "temporalCoverage": "2026-04-01/2026-04-30",
  "variableMeasured": [...],
  "creator": { "@type": "Organization", "name": "Polismyndigheten" },
  "license": "CC0"
}
```

### Place med Wikidata-sameAs

För varje plats: hitta motsvarande Wikidata-Q-id via Wikidata API
+ cacha mappingen i DB (kolumn på `Locations`-tabellen). Ingen
löpande overhead efter initial backfill.

Wikidata API-mönster:
```
https://www.wikidata.org/w/api.php?action=wbsearchentities&search=Uppsala&language=sv&type=item&format=json
```

Manuell sanering för platser med flera matches (Stockholm = både
stad och län-Q-id, t.ex.).

### FAQPage med svar-format

Google AI Overviews extraherar text ordagrant ur synliga sektioner.
För ranking-vinst behöver vi:
- Synlig sektion på sidan (krav från Google för FAQPage-schema)
- Korta svar (40–80 ord max per fråga)
- Numeriska fakta i fetstil
- 3–5 frågor per sida, inte fler

Mönster per sidtyp:
- Plats-startsida: "Hur många brott i {plats} senaste 30 dagarna?", "Vanligast?", "Trend?"
- Månadsvy: "Hur många brott i {plats} {månad} {år}?", "Vanligast?", "Trend mot föregående månad?"
- Typ-sida: "Hur många {brottstyp} senaste 30 dagarna?", "Var sker de mest?", "Trend?"

## Risker

| Risk                                       | Mitigering                                                |
| ------------------------------------------ | --------------------------------------------------------- |
| Schema-spam-policy från Google             | Bara FAQ-svar som matchar synlig text på sidan            |
| Wikidata-Q-id-felmatchning                 | Manuell sanering av tier-1-städer + spot-check tier-2     |
| Dataset-schema som visar fel datapunkter   | Validera i Google Dataset Search-test innan rollout       |
| Performance-kostnad av schema-rendering    | Cache `getLdJson()`-output i Redis, invalidera vid update |
| AI Overview-citation kan ge färre clicks   | Acceptabel — vi är på radarn även utan klick (varumärke)  |

## Implementation-ordning

1. **Audit nuvarande schema** — vad lyfter `getLdJson()`, vad saknas? Validera
   varje sida-typ i Google Rich Results-test.
2. **Backfill Wikidata-Q-id** för tier-1-platser (top 50 platser efter trafik).
   Manuellt + Wikidata API.
3. **NewsArticle-fix på event-pages** — komplettera saknade fält. Validera
   mot Google's krav-checklist.
4. **Place-schema på plats- och län-startsidor** — koppla in Q-id, geo.
5. **Dataset-schema på aggregeringssidor** — börja med plats-startsida, sen
   typ-startsida.
6. **FAQPage på top-3 sida-typer** — plats-startsida, dagsvy, typ-sida.
   Synliga "Snabba fakta"-block + JSON-LD.
7. **Backfilla via batch-script + verifiera i GSC efter 2 veckor.**

## KPI:er

- Andel sidor med "Article", "Dataset" eller "FAQ"-rich-result i GSC
  Search Console (Enhancements-rapporten).
- Antal AI Overview-citationer (mät via GSC search-queries — sidor
  som rankar för "frågande"-queries).
- Impressions på rich-result-eligible-pages.

## Beroenden

- Inga blockers. Kan starta direkt.
- **Synergi med #25:** Dataset/FAQPage-schemat på månadsvyer byggs där.
  #32 hanterar resten av sajten.
- **Synergi med #10:** AI-omskrivna titlar förbättrar `headline` på
  `NewsArticle`. Kör #32 efter #10 om möjligt — eller acceptera att
  schema-värdet lyfter när #10 deployas.

## Confidence-nivå

**Hög.** Inga arkitektur-risker. Schema är additivt — tar inte bort
existerande funktion. Värde verifierbart via GSC inom 2–4 veckor
post-deploy.
