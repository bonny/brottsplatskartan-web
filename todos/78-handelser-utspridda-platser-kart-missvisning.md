**Status:** Fas 1 deployad 2026-10-07; områdescirkel (alt A) över länet tillagd samma dag — Fas 2 villkorlig på mätning
**Senast uppdaterad:** 2026-10-07

# Todo #78 — Hantera händelser som nämner många utspridda platser

## Sammanfattning

Vissa polishändelser (typiskt "Sammanfattning natt", "Sammanfattning dag",
"Sammanfattning kväll", regionövergripande lägesbilder) nämner flera
adresser/orter som geografiskt ligger långt ifrån varandra. Idag renderas
händelsen som **en enda cirkel** på kartan — vald utifrån primär lat/lng
från Polisens API eller vår geocoding. Resultatet kan vara grovt missvisande:
en sammanfattning av nattens händelser i hela Stockholms län hamnar som en
prick i centrala Stockholm, fast brotten skedde i Södertälje, Norrtälje
och Nynäshamn.

Behöver utreda: hur upptäcker vi multi-plats-händelser, och hur
representerar vi dem på kartan utan att lura läsaren?

## Bakgrund

- Polisens "Sammanfattning"-händelser är en känd typ — de plottas på
  en samordningspunkt (ofta polisstationens adress eller länets centrum).
- Vi har redan AI-flöden (Haiku 4.5) för titel-rewrite (#10) och
  event↔nyhet-matchning (#63) — samma stack kan extrahera platsnämn
  ur brödtexten.
- Geocoding finns redan (#48 — Polisens JSON-API + bättre geocoding).
- Kartrendering: Leaflet på frontend, statiska kartbilder via egen
  tileserver (kartbilder.brottsplatskartan.se).

### Exempel-events att utgå från vid analys

- https://brottsplatskartan.se/vastra-gotalands-lan/sammanfattning-natt-vastra-gotalands-lan-502091
- https://brottsplatskartan.se/gavleborgs-lan/trafikolycka-personskada-soderhamn-halsingland-flera-allvarliga-trafikolyckor-i-halsingland-pa-grund-av-extrem-halka-499567
- https://brottsplatskartan.se/varmlands-lan/sammanfattning-natt-varmlands-lan-502034
- https://brottsplatskartan.se/varmlands-lan/sammanfattning-natt-varmlands-lan-501958
- https://brottsplatskartan.se/varmlands-lan/sammanfattning-natt-varmlands-lan-501926
- https://brottsplatskartan.se/jamtlands-lan/sammanfattning-natt-jamtlands-lan-501956
- https://brottsplatskartan.se/vastra-gotalands-lan/sammanfattning-kvall-och-natt-vastra-gotalands-lan-501866
- https://brottsplatskartan.se/vastra-gotalands-lan/sammanfattning-kvall-och-natt-vastra-gotalands-lan-501791

Notera att även icke-"Sammanfattning"-händelser drabbas — t.ex.
Gävleborg-trafikolycks-eventet ovan beskriver "flera allvarliga
trafikolyckor i Hälsingland" → ren rubrik-regex räcker inte, brödtext-
parsing krävs för full täckning.

## Förslag (skiss — behöver utvärderas)

Detekterings-heuristik:

1. **Titel-mönster:** `^Sammanfattning (natt|dag|kväll|morgon|helg)` +
   `Lägesbild`, `Översikt`. Kan utökas med GSC-data + manuell stickprov.
2. **Brödtext-parsing:** kör Haiku 4.5 på `summary`/`description` för
   att extrahera platsnämn (orter, adresser). Returnera lista av kandidater.
3. **Geo-spridning:** geokoda kandidaterna och mät bounding box.
   Om diameter > X km (10? 25?) → flagga som "spridd".

Rendering-alternativ:

- **A. Polygon/bounding box** istället för cirkel — visa hela området.
- **B. Multi-marker** — en marker per extraherad plats, klustrade.
- **C. "Område"-pin** med distinkt ikon + tooltip "Flera platser i {län}".
- **D. Exkludera från karta** helt — visa bara i tidslinje/feed.
- **E. Hybrid:** primär pin på centroid + sekundära mindre markers på
  extraherade platser (om vi har hög konfidens).

## Teknisk realiserbarhet — statisk kartbild med flera markeringar

Undersökt 2026-05-13:

- **Tileserver-gl** (egen container på `kartbilder.brottsplatskartan.se`)
  stöder redan flera `&path=...`-params i samma URL — formatet finns där.
- **Laravel-koden är inte byggd för det.** `StaticMapUrlBuilder::circleUrl()`
  tar ett enda `CrimeEvent` och bygger 3 cirkel-lager (depth-effekt) runt
  dess enda lat/lng. Ingen method tar array av koordinater.
- **Route-formatet** `/k/v1/circle-{id}-{w}x{h}.jpg` (KartbildController)
  antar 1 event-ID.

Vad som krävs för multi-marker-stöd i statiska bilder:

1. Ny helper `StaticMapUrlBuilder::multiCircleUrl(array $coordinates, ...)`
   som loopar och adderar `path=` per punkt.
2. Ny route-variant, t.ex. `/k/v1/multi-{id1},{id2},{id3}-{w}x{h}.jpg`
   (eller hash-bas om många IDs).
3. URL-budget att hålla koll på: ~3 KB per marker × N. Vid 5+ markers
   närmar man sig nginx default `large_client_header_buffers` 32 KB —
   behöver verifieras / höjas.

Styling-configen för tileserver-gl (`basic-preview`-stil) bor bara på
prod-servern, inte i repot. Den är generisk path-rendering, så
multi-marker kräver ingen styling-ändring.

**Frontend (Leaflet)** har inget tekniskt hinder — Leaflet stöder
multi-marker out of the box. Det är bara den statiska bilden som behöver
nytt URL-format.

## Risker

- Falska positiva: Haiku kan extrahera platsnämn som inte är brottsplatser
  (t.ex. "polisen i Solna meddelar att i Sundbyberg…" → Solna är inte
  brottsplats).
- AI-kostnad om vi kör mot alla events. Begränsa till detekterade
  multi-plats-kandidater.
- Multi-marker per händelse bryter dagens 1:1-relation event↔marker —
  påverkar EventsMap-API, klustring, klickbeteende.
- Kan kollidera med #50 (Trafikverket-layer) i UX om vi adderar fler
  marker-typer.

## Volym-mätning (2026-05-27)

Prod-DB, 90d rullande (2026-02-26 → 2026-05-27):

| Mönster (`parsed_title LIKE`)      | Antal    | Per dygn |
| ---------------------------------- | -------- | -------- |
| `Sammanfattning natt%`             | 951      | 10.6     |
| `Sammanfattning kväll%`            | 122      | 1.4      |
| `Sammanfattning dag/morgon/vecka%` | 0        | –        |
| `Sammanfattning helg%`             | 1        | –        |
| **`Sammanfattning%`**              | **1075** | **11.9** |
| `Lägesbild%`                       | 0        | –        |
| `Översikt%`                        | 0        | –        |
| **Totalt events 90d**              | 5548     | 61.6     |

**Tolkning:**

- ~12 sammanfattnings-events/dygn = **19 % av all events-volym** — inte
  trivialt litet, motiverar åtgärd.
- "Sammanfattning natt" dominerar (88 % av subset).
- Lägesbild/Översikt = 0 → titel-regex på
  `^Sammanfattning (natt|kväll|kväll och natt|helg)` täcker ~100 % av
  kända multi-plats-events. Brödtext-parsing (Haiku) behövs bara för
  edge cases utan mätbar volym (t.ex. Gävleborg-trafikolyck-eventet).

## Rekommendation (post-mätning)

**Fas 1 — alt C "Område"-pin (regex-baserad):**

- Detektera via `parsed_title` regex (ingen AI, inget brödtext-parsing).
- Rendering: distinkt ikon ("område"-pin) + tooltip "Flera platser i {län}".
  Alternativt: ingen cirkel alls på den missvisande lat/lng, bara
  län-omfattande badge i händelselistan.
- Kostnad: timmar. Risk: liten. Påverkar inte EventsMap-API:s 1:1-relation.

**Fas 2 — alt E hybrid (villkorlig på Fas 1-utfall):**

- Haiku 4.5 extraherar platsnämn ur brödtext → multi-marker via ny
  `StaticMapUrlBuilder::multiCircleUrl()` + ny route-variant.
- Bygg bara om GA4 visar att Fas 1 inte räcker (fortsatt hög bounce /
  låg dwell på sammanfattnings-events).
- AI-kostnad: ~12 events/dygn × Haiku ≈ försumbart, men kräver
  URL-budget-verifiering (nginx `large_client_header_buffers` vid 5+ markers).

**Skippas:** alt B (full multi-marker utan hybrid) — bryter 1:1 utan
proportionerlig vinst. Alt D (exkludera helt) — förlorar geografisk
browsing helt.

## Confidence

medel — volym-mätning bekräftar att problemet är stort nog att åtgärda
och att regex-detektering räcker. Lösningsutrymmet rangordnat: Fas 1
(alt C) först, Fas 2 (alt E) bara vid mätbart kvarstående problem.

## Genomfört 2026-10-07 — Fas 1 (alt C)

Stickprov på prod (365 d) gav fyra titelvarianter: `Sammanfattning natt`
(3 891), `Sammanfattning kväll och natt` (1 131), `Sammanfattning helg` (2)
och `Sammanfattning eftermiddag` (1). Lokalt hade de flesta viewport-klass
`closest`, dvs. Polisens koordinat är en punkt — cirkeln blev en 150 m-prick
mitt i ett län. Det var den egentliga missvisningen.

- **Detektering:** `CrimeEvent::isMultiPlaceSummary()` — regex
  `^sammanfattning\s` på `parsed_title`. Ingen AI, ingen ny kolumn.
- **Statiska kartbilder:** ny `StaticMapUrlBuilder::areaUrl()` ritar en
  översikt (~150 km bred, zoom räknad från bildbredd och latitud, decimal-zoom
  som tileserver-gl tar) runt koordinaten **utan markering**. Ny kort-URL
  `/k/v1/area-{id}-{w}x{h}[@2x].jpg`. `getKortKartbildUrl()` byter
  `circle`/`circle-low`/`near` → `area` för sammanfattningar, och
  `getStaticImageSrc()` (API, eventsMap-popup, sitemaps) ger `areaUrl()`.
  Kartbilden finns alltså kvar överallt, den visar bara området i stället för
  en prick. `far` (Sverige-översikten) är orörd. Ny mode i URL:en i stället
  för v2-bump, så bara sammanfattningarnas bilder får nya URL:er och övriga
  ~500k kartbilders immutable-cache påverkas inte.
- **Bildtext på händelsesidan:** "Sammanfattning från flera platser i {län} —
  kartan visar området, inte en enskild plats." i stället för
  "Ungefärlig plats …". Alt-texten blir "Översiktskarta över {län} för
  sammanfattning natt från flera platser, {datum}".
- **Leaflet-kartan:** `/api/eventsMap` skickar `multi_place` + `lan`.
  Sammanfattningar får "område"-pin (`EventsMap-marker-icon--area`:
  genomskinlig, streckad kant, ingen puls) och popupen säger "Flera platser i
  {län}" i stället för platslistan. 1:1-relationen event↔marker är oförändrad.
- **Tester:** `tests/Unit/MultiPlaceSummaryTest.php`.

Inte gjort: Gävleborg-fallet (trafikolyckor i hela Hälsingland) fångas inte av
titel-regexen — det är Fas 2-materialet. Fas 2 byggs bara om GA4 visar att
Fas 1 inte räcker.

## Uppföljning 2026-10-07: områdescirkel (alt A)

Kartbilden utan markering upplevdes tom. Pär jämförde tre alternativ på en
riktig sammanfattning (Västra Götaland, event 510099): A stor svag cirkel
över länet, B länsgränsen, C prickar på orterna i texten. Valet blev **A**.
C fungerar bara för län som skriver orter i fast format ("02:17, misshandel
– Göteborg"); Södermanlands sammanfattningar nämner inga orter alls.

- `StaticMapUrlBuilder::areaUrl()`: med känt län en cirkel från
  `resources/geo/lanscirklar.json` (mitt = länets tyngdpunkt, radie = 90 %
  av länets händelseplatser). Utan län: som förut, ingen markering.
- Nytt läges-namn `/k/v1/omrade-…` — `area-…` låg ute några timmar med den
  tomma kartan och 301:an cachas `immutable` ett år. `area` finns kvar som
  alias.
- `KARTBILD_COLUMNS` fick `administrative_area_level_1`, cachenyckeln
  bumpad till `kartbild:coords:v2:`.
- `/api/eventsMap`: område-pinnen ligger i länets mitt i stället för på
  Polisens punkt.
- Länsgränserna (alt B) sparade som polygoner i
  `resources/geo/lansgranser.geojson` för framtida bruk — de inkluderar
  havsområdet. Se [docs/lansgeometri.md](../docs/lansgeometri.md).
