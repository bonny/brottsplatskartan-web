**Status:** arkiverad/uppskjuten 2026-05-13 — premature; sammanslås troligen med #76 Fas A när det startas. Se "Review 2026-05-13" nedan. **Rättelse 2026-05-19:** grundpremissen (identiska titlar/H1) stämmer inte — se "Rättelse 2026-05-19".
**Senast uppdaterad:** 2026-05-19

# Todo #80 — Cannibalisering startsida `/` vs `/stockholm` på "polisen händelser"

## Sammanfattning

Senior-SEO-review av #76 Fas B identifierade att vi nu förvärrar
cannibalisering: både `/` och `/stockholm` rankar pos 7.6 på "polisen
händelser" och båda har efter Fas B identisk title-mall
("Polisen händelser X idag – brott, olyckor och larm"). Google väljer
en, demoter den andra.

GSC pre-deploy:

- `/stockholm` — 39 176 imp/90d, pos 7.6
- `/` (startsida) — 29 721 imp/90d, pos 7.6
- 13+ andra sidor får också impressions på samma fras

## Förslag

### Differentiera intent-axeln, inte ord-axeln

Senior: "**Startsidan ska äga 'Sverige/nationellt'**, Stockholm äger
**stad+län-noden**."

**Föreslagen ny startsido-title:**

```
Polisen händelser i Sverige idag – live från hela landet
```

(eller liknande nationell vinkel)

**Stockholm behåller** `Polisen händelser Stockholm idag – brott,
olyckor och larm` — geografiskt avgränsad.

### Steg 1 — Audit nuvarande startsido-meta (30 min)

1. Läs `app/Http/Controllers/StartController.php` rad 88 + 281 där
   `pageTitle` sätts.
2. Notera nuvarande title/meta för startsidan.
3. Kontrollera om startsidan har `showTitleTagline` aktiverat (det har
   den enligt grep — då blir " → Brottsplatskartan" suffix automatiskt).

### Steg 2 — Differentierad startsido-title (30 min)

Skissförslag:

```
Polisen händelser i Sverige – live på karta från hela landet
Description: Se polisens senaste händelser i Sverige idag – brott,
trafikolyckor, bränder och larm. Live på karta från Polismyndigheten.
```

Avsiktligt:

- "Sverige" istället för stadsnamn → ingen lexical-överlap med
  Tier 1-städer
- "live på karta från hela landet" → unik value-prop
- INTE "polisen händelser idag" generiskt — vi vill att
  search-intent-användaren `polisen händelser` ska se startsidan när
  de inte specificerat ort

### Steg 3 — Övervaka rotation under 4–6v post-deploy

Mät i GSC:

- Tappar startsidan imp på "polisen händelser malmö/göteborg/uppsala
  /stockholm/helsingborg"? (önskat: ja, eftersom de tas av city-sidor)
- Tar startsidan imp på "polisen händelser sverige", "polisen händelser
  idag", "polishändelser" generiskt? (önskat: ja)
- Tappar `/stockholm` rank på "polisen händelser stockholm"? (önskat:
  nej — bör snarare stiga)

## Risker

- **Startsidan rankar redan pos 7.6 på generiska "polisen händelser"** —
  ändring kan tappa rank tillfälligt under re-evaluation (2–4 veckor).
- **Startsidan har många backlinks** — title-ändring där har högre
  vikt än city-sidor.
- **Identisk problem för Plats-sidor** (`/plats/{plats}`) — `/plats/
göteborg` rankar på "polisen händelser" pos 6.8, 3 358 imp/90d.
  Fas A-auditen i #76 hanterar detta — den här todon är bara `/` vs
  `/stockholm`.

## Confidence

**Medel.** Senior-review-insikten är hög-confidence, men risken att
röra startsidan utan A/B-test är icke-trivial. Kanske kör som
canary: ändra startsidan först, vänta 14 dagar, mät rotation innan
vi ändrar Stockholm.

## Beroenden

- **#76 Fas B** klar — Tier 1-städer har nu identiska titlar med
  startsidan, så cannibaliseringsrisken är skarpare.
- **#76 Fas A** (cannibalisation-audit) — överlappar denna todo;
  möjligen mergea ihop när Fas A startas.
- **#46** (Händelser/Senaste/Mest lästa-konsolidering) — relaterad
  hub-sida-diskussion.

## Review 2026-05-13 — varför arkiverad

Egen kritisk review identifierade flera svagheter som gör att todon
inte bör startas i nuvarande form:

1. **Diffust förhållande till #76 Fas A.** Fas A är auditen över ALLA
   15+ sidor som rankar på "polisen händelser". #80 plockar ut en
   delmängd (`/` vs `/stockholm`) och riskerar dubbelarbete eller
   motstridig fix om den körs separat.
2. **Saknar GSC-baseline för "Sverige"-modifierade fraser.** Förslaget
   är att startsidan ska äga `polisen händelser sverige`. Men har vi
   imp på den frasen idag? Om volymen är 0 så pivoterar vi från en
   pos 7.6-fras med 30k imp till en fras med 0 imp — regression
   maskerad som strategi.
3. **Plats-sidors roll otydlig.** `/plats/göteborg` är en lika stor
   cannibaliseringskälla som `/stockholm` (3 358 imp/90d, pos 6.8).
   Delegerades till "Fas A" utan tydlig ägare.
4. **Stockholm-Fas-B är obevisad.** Om Stockholm tappar rank under
   60d-mätningen (uppföljning 2026-07-12), så är #80:s grundpremiss
   ("stadssidor äger stad-noden") fel.
5. **"Live från hela landet" är inte ett SEO-keyword.** Det är
   marknadsförings-copy. Att byta startsidans title kan tappa
   matching mot "polisen händelser" generiskt utan att vinna något.
6. **Canary-strategin omvänd.** Filen säger "ändra startsidan först,
   sen Stockholm". Men Stockholm är redan ändrad i Fas B.
7. **Confidence övervärderad.** "Medel" sattes på senior-review-
   insikten, inte på implementationsplanen som är skissartad.

### Avblockning

Återöppna när:

1. #76 Fas B 60d-mätning klar (2026-07-12) → vet om Stockholm-pivot
   funkar.
2. #76 Fas A audit klar → vet hur cannibaliseringen ser ut totalt
   över alla 15+ sidor.
3. GSC-baseline körd för "polisen händelser sverige", "polisen
   händelser hela landet", "polisens händelser idag" → vet om en
   pivot till "Sverige nationellt" har matching-potential alls.

I praktiken: denna todo bör sammanslås med #76 Fas A som en av flera
besluts-output, inte handhanteras separat.

## Rättelse 2026-05-19

Grundpremissen var fel. Vid faktisk inspektion av koden (efter Fas
B-deploy) skiljer sig titel och H1 mellan startsidan och Tier 1-städer
betydligt — de var aldrig identiska:

**Startsidan `/`** (`StartController.php:76` + `start.blade.php:44`):

- title: `Polisens händelser - aktuella brott & senaste blåljusen`
- H1: `Polisens händelser i hela Sverige`

**`/stockholm`** (`config/tier1-cities.php:26-28` + `city.blade.php:26-29`):

- title: `Polisen händelser Stockholm idag – brott, olyckor och larm`
- H1: `Polishändelser i Stockholm` + subline `Brott, blåljus och larm – uppdateras live från polisen.se`

Intent-axeln är **redan differentierad** ("i hela Sverige" vs
"Stockholm idag") — vilket var #80:s föreslagna fix. Senior-review-
påståendet att Fas B skulle göra dem identiska byggde på en
felaktig läsning av commiten (4581ef6 rörde bara `tier1-cities.php`,
inte startsidan).

### Konsekvens

- **#80 är inte bara premature utan delvis lösning-utan-problem.**
  Den cannibalisering som syns på `polisen händelser` kan finnas,
  men inte via identiska titlar.
- **Stockholm-stagnationen** (klick 60 → 53 i 28d-fönstret 2026-04-18
  → 2026-05-15, trots position-lyft) har troligen andra orsaker:
    - Stockholm rankade redan starkt → liten vinstmarginal
    - "polisen stockholm senaste nytt" är brand-trafik (73 % CTR,
      pos 4.6 — folk söker direkt på oss, inte generiskt)
    - Kortare 28d-fönster = mer brus
- **Behåll arkiverad.** Återöppna bara om #76 Fas A-auditen visar
  riktig SERP-rotation mellan `/` och `/stockholm` på en specifik
  fras (inte bara delad ord-matchning).

### Tier 1-rollout: faktiska resultat 28d efter Fas B (2026-04-18 → 2026-05-15)

| Stad        | Klick före | Klick efter | Förändring     |
| ----------- | ---------- | ----------- | -------------- |
| Malmö       | 4          | 26          | **+22 (6.5x)** |
| Göteborg    | 9          | 47          | **+38 (5.2x)** |
| Helsingborg | 6          | 12          | +6 (2x)        |
| Stockholm   | 60         | 53          | -7             |
| Uppsala     | 6          | 4           | -2             |

Mönster: stora städer med svag pre-ranking (Malmö, Göteborg) gynnas
mest. Stockholm + Uppsala saknar lyft — men inte pga cannibalisering
från `/`, snarare för att de redan var nära taket (Stockholm) eller
har för låg total volym för signifikans (Uppsala).
