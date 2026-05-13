**Status:** aktiv
**Senast uppdaterad:** 2026-05-13

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

Exempel-events att utgå från vid analys:

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

- Vi har redan AI-flöden (Haiku 4.5) för titel-rewrite (#10) och
  event↔nyhet-matchning (#63) — samma stack kan extrahera platsnämn
  ur brödtexten.
- Geocoding finns redan (#48 — Polisens JSON-API + bättre geocoding).
- Kartrendering: Leaflet på frontend, statiska kartbilder via egen
  tileserver (kartbilder.brottsplatskartan.se).

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

## Confidence

låg — problemet är tydligt men lösningsutrymmet stort. Behövs:
volym-mätning (hur många events/dag är "spridda"?), GA4/GSC-signal
(klagar användare? höga bounce på sammanfattnings-events?), och
A/B på rendering-val innan vi bygger.
