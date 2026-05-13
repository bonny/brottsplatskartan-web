**Status:** aktiv — utbruten från #76 senior-review (2026-05-13)
**Senast uppdaterad:** 2026-05-13

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
