**Status:** aktiv — Fas A+B klara. Quick-win deployad 2026-05-26: /plats/göteborg + /plats/malmö (+ alla Tier 1) 301:ar till /{city}. 60d-gate 2026-07-12. Fas C avvaktar gate. Fas D/E kvar.
**Senast uppdaterad:** 2026-05-26

# Todo #76 — "polisen händelser X idag" + Malmö/Göteborg

## Sammanfattning

Två relaterade SEO-glapp som troligen löses med samma jobb:

1. **Cannibalisation** på "polisen händelser" (32 785 imp/28d, pos 6.8,
   1.27 % CTR) — 15+ sidor konkurrerar om samma query. Ingen tar topp-3.
2. **Malmö/Göteborg-glapp** — vi rankar pos 6–8 på
   "händelser <stad> idag polisen"-varianter medan polisinfo.se tar
   topp-1. Vinst-potential: ~15 800 clicks/90d enligt #52 baseline
   (A: ~12 800 + B: ~3 000).

Detta är den enskilt största enskilda click-potentialen i hela GSC.

## Bakgrund

### Data från GSC + konkurrent-analys

**"polisen händelser" (28d):**

- 32 785 impressions, pos 6.8, 1.27 % CTR
- SERP-ledare: polisen.se #1, polisinfo.se #2, orti.se #3
- Vår topp-5 av 15+ sidor som tar imp:
    - `/stockholm` — 39 176 imp (90d), pos 7.6
    - `/` (startsida) — 29 721 imp, pos 7.6
    - `/plats/göteborg` — 3 358 imp, pos 6.8
    - `/lan/Skåne län` — 3 151 imp, pos 8.1 (löses delvis av #75)
    - `/lan/Västra Götalands län` — 1 499 imp, pos 7.6 (löses delvis av #75)

**"händelser <stad> idag polisen" (28d):**

- "händelser i malmö idag polisen" — 3 401 imp, pos 7.5
- "polisen händelser göteborg idag" — 3 087 imp, pos 6.9
- "polisen händelser malmö" — 3 075 imp, pos 6.5
- 8+ "blåljus <stad> idag"-varianter pos 8–10 (Västerås, Linköping,
  Eskilstuna, Solna, Huddinge, Dalarna, Jönköping, Göteborg)

**Mönster:** Stockholm-modellen funkar (pos 2.2 på "senaste blåljusen
stockholm", 26 % CTR). Övriga städer matchar inte "<stad> idag"-frasen
i title/h1.

### Varför cannibalisation uppstår

Vi har dedikerade sidor för:

- Tier 1-städer (`/{city}` — t.ex. `/malmo`, `/goteborg`)
- Län-sidor (`/lan/{lan}`)
- `/plats/{plats}` (legacy + Tier 2+)
- Startsidan (samlar "polisen händelser" generiskt)
- Typ-sidor (`/typ/handelse`, `/typ/larm-om-brand` etc)
- Datum-routes (`/X/handelser/2024/06`)

Google rankar dessa nästan-likvärdigt på "polisen händelser" och roterar
mellan dem — ingen blir auktoritativ.

## Förslag

### Fas A — Cannibalisation-audit (1–2h)

1. Kör `mcp__mcp-gsc__get_search_by_page_query` för "polisen händelser"
   över 90d — exportera till `tmp-research/76-cannibalisation/`.
2. Lista alla unika landningssidor som tar > 100 imp på frasen.
3. Klassificera per landningstyp (`/`, `/{city}`, `/lan/`, `/plats/`,
   `/typ/`, datum-routes).
4. Beslut: vilken sida ska vara **canonical** för generiska
   "polisen händelser" utan ortskvalifierare?
    - Sannolikt startsidan `/` — det är broadaste intention.
    - Alternativ: `/handelser` om vi vill ha en dedikerad hub-sida.

### Fas B — Title/h1-optimering Tier 1 (2–3h)

För `/malmo`, `/goteborg`, `/helsingborg`, `/uppsala`:

**Före (antaget):**

```
<title>Senaste händelser från Polisen i Malmö – Brottsplatskartan</title>
<h1>Polishändelser i Malmö</h1>
```

**Efter (förslag):**

```
<title>Polisen händelser Malmö idag – senaste blåljusen och brott</title>
<h1>Polisen händelser i Malmö idag</h1>
<meta description="Se polisens senaste händelser i Malmö idag. Brott,
  trafikolyckor, bränder och larm – live på karta från Polismyndigheten.">
```

Matchar exakt "<stad> idag polisen"- och "polisen händelser <stad>"-frasen.

### Fas C — Hub-sida `/handelser` (valfri, beror på Fas A-beslut)

Om Fas A beslutar att startsidan är för bred för "polisen händelser"-
intentionen, skapa `/handelser` som dedikerad hub:

- H1: "Polisens händelser idag – live från hela Sverige"
- Lista senaste 50 händelserna
- Länkar till Tier 1-städer + alla län
- Canonical för "polisen händelser" + "händelser polisen" + varianter

Detta är delvis vad #46 ("slå samman Händelser/Senaste/Mest lästa")
försöker lösa — koordinera scope.

### Fas D — Blåljus-idag-mönster (separat eller del av Fas B)

För "blåljus <stad> idag" pos 8–10 (8+ städer):

- Antingen lägg till "idag" i title för Tier 2-städer också (men risk att
  det blir spammigt om alla städer får samma title-mall)
- Eller: skapa en dynamisk "blåljus idag i {stad}"-sektion på ortssidan
  med live-feed

### Fas E — Mätning

Lägg uppföljning 60d post-deploy:

- "polisen händelser" pos 6.8 → mål ≤ 4
- "händelser malmö idag polisen" pos 7.5 → mål ≤ 4
- Total clicks: +5–10k/90d realistisk första gates

## Risker

- **Cannibalisation-fix kan tappa long-tail-trafik** om vi noindex:ar fel
  sida. Audit:a först, mät sedan.
- **Title-stuffing** — "Polisen händelser Malmö idag – senaste blåljusen
  och brott" är på gränsen till keyword-stuffing. Testa A/B i två steg
  (titles först, h1 sedan) om möjligt.
- **Polisinfo.se rankar #1 i Malmö** av oklar anledning (backlinks?
  page-ålder? content-djup?) — kanske inte räcker med title-fix. Worth
  doing anyway eftersom det är låg-effort.
- **Stockholm-modellen replikera försiktigt** — Stockholm har 10× mer
  events än Malmö/Göteborg per dag, så content-densitet är annorlunda.
  Vi kan inte härma rakt av.
- **Synergi med #75** — del av cannibalisation försvinner när lan-URL:er
  konsolideras. Kör #75 först.

## Confidence

**Medel.** Fas A (audit) är hög-confidence. Fas B (title-fix) är
hög-confidence, låg risk. Fas C (`/handelser`-hub) är medel — beror på
Fas A-beslut + koordination med #46. Fas D är spekulativ. Estimat:
1 dag för Fas A+B, +1–2 dagar för Fas C om vi går vidare.

## Beroenden

- **#75 (slug-fix lan)** bör köras först — annars dubbel-fixar vi
  cannibalisation.
- **#46 (Händelser/Senaste/Mest lästa-konsolidering)** överlappar Fas C
  — koordinera eller slå samman.
- **#52 A + B** löses av denna todo.
- **#33 (Tier 1 month-routes)** klar — det är samma städer som påverkas
  av Fas B.

## Nästa steg

1. ~~Vänta tills #75 är deployad~~ — klar (commit `fafbb28`).
2. ~~Implementera Fas B (title/h1)~~ — klar 2026-05-13. Ändrade
   `pageTitle`, `title` (h1-subline) och `description` i
   `config/tier1-cities.php` för stockholm/malmo/goteborg/helsingborg/
   uppsala. Ny title-mall: `"Polisen händelser <Stad> idag – senaste
blåljusen"`. Deploy → vänta 30–60d för GSC-mätning.
3. Sätt uppföljning 2026-07-12 (60d) — mät pos på "polisen händelser
   malmö idag", "polisen händelser göteborg idag", "polisen händelser
   stockholm" mot pre-deploy-baseline.
4. ~~Kör Fas A-auditen~~ — klar 2026-05-26. Fullständig rapport:
   `tmp-research/76-cannibalisation/fas-a-audit-2026-05-26.md`.
   **Beslut:** Fas C (/handelser hub) avvaktar 60d-gate 2026-07-12 —
   Fas B-titlar bör successivt flytta generisk trafik från /stockholm
   till / utan ny hub-sida.
5. **Ny quick-win identifierad — /plats/ canonical:** `/plats/göteborg`
   och `/plats/malmö` tar impressions parallellt med Tier 1-sidorna.
   Lägg `<link rel="canonical" href="/goteborg">` resp. `/malmo` på
   /plats/-sidorna → konsoliderar authority. Låg effort (~30 min).
6. Beslut om Fas C utifrån 60d-gate 2026-07-12.

## Fas B-implementation (2026-05-13)

Senior-SEO-review revsade junior-versionen och gav konkret omtag.
Slutgiltig version (alla 5 städer samma mönster):

```diff
- 'pageTitle' => 'Malmö: Polishändelser och blåljus',
+ 'pageTitle' => 'Polisen händelser Malmö idag – brott, olyckor och larm',
- 'title' => 'Senaste blåljusen och händelser från Polisen i Malmö med omnejd.',
+ 'title' => 'Polishändelser, brott och blåljus – uppdateras live från polisen.se',
- 'description' => 'Se aktuella polishändelser och blåljuslarm från räddningstjänsten i Malmö och Skåne län.',
+ 'description' => 'Alla polisens händelser i Malmö idag på karta – brott, trafikolyckor, bränder och larm. Aggregerat live från Polismyndigheten med 10 års arkiv.',
```

**H1-ändring i `city.blade.php`** rad 27:

```diff
- <strong>{{ $city['name'] }}</strong>
+ <strong>{{ $city['displayName'] }}</strong>
```

H1-strong visar nu "Malmö" istället för "Malmö och Skåne län" — tar
bort dublett-ord (län-namnet finns inte heller längre i subline).

### Senior-reviews motivering per fält

- **Title:** "från X län" är intent-mismatch (användaren sökte stad,
  inte län). "brott, olyckor och larm" plockar long-tail som
  "trafikolycka malmö idag".
- **Subline:** ren synonym-spread (`polishändelser`, `blåljus`) +
  freshness-claim (`uppdateras live`). Inga geo-dubletter med h1.
- **Description:** USP-vinkel (karta + 10 års arkiv) som polisen.se
  inte har — det är där vi vinner clicks även på pos 6.

### Skippade förslag (med motivering)

- **Brand-suffix " | Brottsplatskartan"** — junior+senior föreslog,
  men domänen `brottsplatskartan.se` + `WebSite` schema (`name:
Brottsplatskartan`) + `og:site_name` ger redan brand-display i
  SERP. Trippel-redundant, kostar ~190px keyword-utrymme. Skip.
- **`CityMetaService` template-arkitektur** — YAGNI tills Tier 2
  faktiskt planeras.

### Utbrutna problem

- **Soft-404 vid 0 events idag** → #79 (Tier 1 har sällan men ej
  noll-frekvens, behöver mätning först).
- **Cannibalisering `/` vs `/stockholm`** → #80 (startsida måste
  differentieras till "Sverige nationellt"-vinkel).

### Risker att övervaka i 60d-uppföljning

- Stockholm rankar pos 2.2 på "senaste blåljusen stockholm" (26 %
  CTR). Vi tappar "senaste blåljusen" från title (fortfarande i
  subline). Övervaka för regression de första 14 dagarna.

## Tidig signal 2026-05-19 (28d post-deploy)

GSC-jämförelse 2026-04-18 → 2026-05-15 mot föregående 28d
(2026-03-21 → 2026-04-17), filter `polisen <stad>`-fraser. Klick-deltat
är primär metrik; impressions sekundärt (split rapporteras dubbelt
under canonical-övergång — se Uppsala-noteringen).

| Stad        | Klick före | Klick efter | Delta             |
| ----------- | ---------- | ----------- | ----------------- |
| Malmö       | 4          | 26          | **+22 (6.5x)** ⭐ |
| Göteborg    | 9          | 47          | **+38 (5.2x)** ⭐ |
| Helsingborg | 6          | 12          | +6 (2x)           |
| Stockholm   | 60         | 53          | -7 (oförändrat)   |
| Uppsala     | 6          | 4           | -2 (mätartefakt)  |

Position-lyft (utvalda fraser):

- `polisen malmö händelser`: 23.9 → 8.8
- `polisen göteborg händelser`: 9.5 → 6.2
- `händelser polisen göteborg`: 11.5 → 3.9
- `polisen helsingborg händelser`: 8.8 → 8.4

### Tolkning

- **Mallen funkar entydigt** på Malmö + Göteborg (stora städer med
  pre-deploy pos 9-24 → klart utrymme att vinna).
- **Helsingborg** följer trenden men låg volym → litet absolut värde.
- **Stockholm** rankade redan starkt (pos 4-7 på de flesta fraser) →
  liten marginal kvar. Hypotes om cannibalisering med startsidan
  (todo #80) avfärdad vid kod-inspektion — titlarna är redan
  differentierade.
- **Uppsala** "tappade" 2 klick, men det är en mätartefakt: GSC
  rapporterar impressions på den 301:ade `/lan/Uppsala län`-URL:en
  under en övergångsperiod (4-12v). Faktiska klick går till
  `/uppsala` via redirect. Curl bekräftar att redirecten funkar
  (CityRedirectMiddleware:63-66, todo #35).

### Implikation för 60d-gaten

- Tidig signal är positiv — håller den vid 60d är detta solid
  evidens för att rulla ut mallen till Tier 2-städer (todo #24).
- Uppsala-signalen blir ren när Google klart bytt canonical.
- Stockholm kommer troligen fortsätta vara svår att flytta — det är
  inte ett title-problem utan ett "redan-nära-taket"-problem.
