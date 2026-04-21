# Todo #3 – Konsolidering av crimeevent-kort (Blade-templates)

## Sammanfattning

Repot innehåller 8 olika "crimeevent-kort"-partials (+ ett i undermappen
`parts/crimeevent/newsarticles.blade.php` samt ett icke inräknat
`crimeevent-previousPartners.blade.php`). Majoriteten är *aktivt använda*
på produktion – enbart `crimeevent_v2.blade.php` är dött (refereras bara
från `design.blade.php` som är en testsida bakom `/design`).

Det finns två distinkta "familjer":

1. **Stora/detaljerade kort** (full karta, rubrik, brödtext): `crimeevent`,
   `crimeevent_v2`, `crimeevent-hero`, `crimeevent-hero-second`,
   `crimeevent-helicopter`.
2. **Kompakt listformat** (liten/ingen karta, kort meta): `crimeevent-small`,
   `crimeevent-city`, `crimeevent-mapless`.

Den verkliga konsolideringsvinsten ligger främst i att (a) radera
`crimeevent_v2`, (b) låta `hero`/`hero-second` dela ett gemensamt partial
styrt av storleks-parameter, och (c) slå ihop `crimeevent-small` /
`crimeevent-mapless` / `crimeevent-city` till ett lista-kort med flaggor.

## Användningsmatris

| Template | Används i | Status |
|---|---|---|
| `crimeevent.blade.php` | `single-event.blade.php`, `design.blade.php` | **Levande** – huvudkort för enstaka event och i design-test |
| `crimeevent_v2.blade.php` | `design.blade.php` | **Dött** – endast referens i testsida `/design` |
| `crimeevent-small.blade.php` | `city.blade.php`, `brand.blade.php`, `single-event.blade.php` (nära), `design.blade.php`, `inbrott.blade.php`, `single-typ.blade.php`, `parts/events-by-day.blade.php`, `parts/mostViewed.blade.php`, `mestLasta.blade.php` (x2), `parts/events-heroes.blade.php` | **Levande** – mest använda listkortet |
| `crimeevent-city.blade.php` | `design.blade.php` | **Troligen dött** – bara via design-sida. Inga prod-vyer @include:ar det |
| `crimeevent-mapless.blade.php` | `design.blade.php`, `errors/404.blade.php` (x2) | **Levande** – används i 404 |
| `crimeevent-hero.blade.php` | `design.blade.php`, `parts/events-heroes.blade.php` | **Levande** – startsidans hjältehändelse |
| `crimeevent-hero-second.blade.php` | `design.blade.php`, `parts/events-heroes.blade.php` | **Levande** – "andra" hjältehändelser på startsidan |
| `crimeevent-helicopter.blade.php` | `overview-helicopter.blade.php`, `design.blade.php` | **Levande** – helikopter-översikten |
| `crimeevent-previousPartners.blade.php` (inte med i ursprungslistan) | `previousPartners.blade.php` | **Levande** – legacy "tidigare partners"-sida |
| `parts/crimeevent/newsarticles.blade.php` | `crimeevent.blade.php` | Levande (sub-partial) |

Snabb verifiering att `crimeevent-city` är dött i produktion: inga andra
`@include` matchar förutom `design.blade.php`. Rekommendera att dubbelkolla
via responseloggar eller helt enkelt radera och observera.

## Duplicerings-analys

### Block 1 – Kartbild (statisk)
Förekommer i: `crimeevent`, `crimeevent_v2`, `crimeevent-helicopter`, och i
atom-form redan i `parts/atoms/event-map-far.blade.php` (används av
`hero`/`hero-second`).

Variationer: storlek (640×320 vs 426×320 vs två separata nära/fjärran), om
den lindas i `<a>`-länk, om `lazy`-attribut, klassnamn (`Event__mapImage`
vs helt egna `rounded-md`).

→ Bör lyftas till en parametriserad atom: `parts/atoms/event-map.blade.php`
med `['width', 'height', 'variant' => 'near|far|both', 'linked' => true]`.

### Block 2 – Rubrik + parsed_title
Förekommer i alla utom `crimeevent-small`/`mapless`/`city` (som använder
`getHeadline()` resp. `getDescriptionAsPlainText()` direkt).

Markup-skiljer sig mellan klassisk BEM (`Event__type`, `Event__teaser`)
och Tailwind (`text-2xl font-bold tracking-tight`). Hero-varianten är
Tailwind; `crimeevent`/`_v2` är BEM.

→ Ett gemensamt `parts/atoms/event-title.blade.php` (med
`['size' => 'hero|default|compact', 'linked' => bool]`) kan täcka alla stora
kort. Små lista-kort har för olik semantik för att dela.

### Block 3 – Datum/tid
`crimeevent`, `_v2`, `small`, `mapless` har snarlika `<time>`-block. Redan
delvis abstraherat i `parts/atoms/event-date.blade.php` (hero-varianterna).

→ Byt ut inline-datum i de stora korten mot `event-date`-atomen; utöka
atomen med `['showYmdOver24h' => true]`-parameter för att matcha v2.

### Block 4 – Meta/plats
`crimeevent` och `_v2` har nästan identisk `<p class="Event__meta">` med
`getLocationStringWithLinks` + datum. Enda skillnad: `_v2` stödjer
`skipLan`-flagga.

→ Konsolidera; låt en atom ta `['skipLan' => false]`.

### Block 5 – Li/article-wrapper
`crimeevent` har en bisarr dubbel-wrapping där `<li>` öppnas eller inte
beroende på `$overview`, och `</li>` stängs efter `</article>` i fel
ordning (troligen befintlig bugg, se rad 171–172). `crimeevent_v2` löser
samma problem enklare genom att alltid använda `<li>`. `small`/`mapless`/`city`
är `<li>` eller `<div>` beroende på kontext.

→ Dela upp ansvar: wrapparen (`<li>` vs `<article>` vs `<div>`) bestäms av
*anropsplatsen*, inte av partialen. Partials bör endast innehålla
innehållet.

## Konkret konsolideringsförslag

### Steg 1 – Lågrisk-städning (kan göras direkt)

1. **Radera `crimeevent_v2.blade.php`** + dess design-sektion. Dött i prod.
2. **Radera `crimeevent-city.blade.php`** om verifierat att inga
   dynamiska includes (t.ex. från config) använder det. Endast
   `design.blade.php` refererar.
3. Fixa **`<li>/</article>`-stängnings-buggen** i `crimeevent.blade.php`
   (rad 171–175) – `</li>` ligger före `</article>`.

### Steg 2 – Extrahera atomer

Skapa/utvidga:

- `parts/atoms/event-map.blade.php` – parametriserad statisk karta
  (ersätter `event-map-far` eller utökar den)
- `parts/atoms/event-title.blade.php` – parsed_title + headline
- `parts/atoms/event-date.blade.php` – utvidga befintlig
- `parts/atoms/event-meta.blade.php` – plats + datum (rad "Event__meta")

Låt befintliga kort successivt använda dessa atomer. Ingen
strukturell ändring av kort-filerna ännu, minimerar visuell risk.

### Steg 3 – Slå ihop kort-templates

**Stora kort:** En `parts/crimeevent-card.blade.php` som ersätter
`crimeevent` + `crimeevent-helicopter`, styrd av:
```
['event', 'variant' => 'single|overview', 'highlight' => null]
```
`highlight` kan vara helikopter-regexen (lyftes till helper-metod).

**Hero-kort:** En `parts/crimeevent-hero.blade.php` som slår ihop
`hero` + `hero-second` via `['size' => 'large|small']`.

**Lista-kort:** En `parts/crimeevent-listitem.blade.php` som ersätter
`small` + `mapless`, styrd av:
```
['event', 'mapImage' => 'near|far|none', 'detailed' => false]
```
Funktionellt är `mapless` = `mapImage=none`.

### Steg 4 – Flytta wrappers utåt

Låt varje anropsplats öppna/stänga `<li>`, så partials blir rena
content-blocks. Underlättar återanvändning i både `<ul>` och grid-layouts.

## Risker + fördelar

### Risker
- **Visuella regressioner**: BEM-klasser (`Event__*`, `ListEvent__*`) är
  spridda i `resources/sass/app.scss`. En ändring av markup måste
  verifieras mot alla `@include`-sidor. Särskilt `single-event`, `city`,
  `start`, `404`, `inbrott`.
- **SEO-regression**: `crimeevent.blade.php` är *det* stora kortet på
  `/handelse/{id}`. Att röra `<h1>`, `<time datetime>`, eller
  `getHeadline()` kan påverka rankning/rich snippets. Flytta ALDRIG
  strukturerade data utan att validera med Rich Results Test.
- **Cache-invalidering**: Spatie responsecache är Redis-baserad. Efter
  deploy måste `responsecache:clear` köras – annars serveras gamla
  HTML-svar. Samma för view-cache (`view:clear`). Värt att dokumentera
  i deploy-steget.
- **Dynamisk referens**: Om någon config/DB-setting pekar ut ett
  template-namn som sträng missar Grep det. Bedömning: osannolikt,
  men verifiera innan radering.
- **Dold kod i `parts/crimeevent/`**-undermappen (newsarticles)
  tas inte med här; ingen risk så länge `crimeevent.blade.php`
  behålls.

### Fördelar
- Mindre yta att underhålla (8→3 kortfiler + några atomer).
- Enhetlig prefix/klassnamngivning (BEM **eller** Tailwind, inte båda).
- Centraliserar kartbildsstorlekar → färre static-image-varianter
  → färre cache-keys i tile-servern → billigare drift.
- Enklare för LLM/ny utvecklare att hitta rätt partial.
- Fixar tyst bugg i `crimeevent.blade.php` (`</li>` före `</article>`).

## Öppna frågor

1. Ska vi behålla BEM-klasserna (`Event__*`, `ListEvent__*`) eller
   migrera allt till Tailwind (som hero-varianterna)? Blandning idag är
   rörig.
2. `crimeevent-city` – säkert att radera? Om todo #3 kräver grep mot
   config/DB, gör det först.
3. `crimeevent-previousPartners` ingick inte i ursprungslistan, men
   ligger i samma mapp. Ska den med i scope?
4. `design.blade.php` – ska den uppdateras parallellt som en
   "live style-guide", eller är den i praktiken död? (Rutten `/design`
   finns i `routes/web.php` men ingen länk in.)
5. Är parametriseringen via `isset($overview)`/`isset($single)` något
   vi behåller eller byter mot explicita named arguments
   (`@props(['variant' => 'overview'])` – kräver Blade components)?

## Status / nästa steg

**Status:** Analys klar. Ingen kod ändrad.

**Nästa steg (förslag i prioordning):**

1. Verifiera att `crimeevent-city` och `crimeevent_v2` är döda (sök i
   `database/`, `config/`, serverloggar).
2. Fixa `</li>`/`</article>`-ordningen i `crimeevent.blade.php` som en
   isolerad fix (separat commit, enkel att rollbacka).
3. Radera `crimeevent_v2` + `crimeevent-city` + tillhörande
   `design.blade.php`-sektioner.
4. Extrahera `event-map`-atom och låt minst `crimeevent` +
   `crimeevent-helicopter` använda den.
5. Slå ihop `hero` + `hero-second`.
6. Slå ihop `small` + `mapless`.
7. Kör visuell regressions-check (screenshot-diff) mot start/city/
   single-event/404 innan deploy.
8. Efter deploy: `responsecache:clear` + `view:clear`.

**Förväntad effekt:** 8 filer → 3–4 filer + 3–4 atomer, ~465 rader →
uppskattat ~300 rader netto. Ingen funktionell skillnad för slutanvändare.
