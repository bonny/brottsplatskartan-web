**Status:** aktiv (designfas — kräver pilot innan full rollout)
**Senast uppdaterad:** 2026-04-26 (efter design-review — vecko-strukturen borttagen, öppna frågor tillagda)
**Confidence:** Medel

# Todo #25 — Månadsvyer istället för dagsvyer (plats/län-routes)

## TL;DR

Migrera `/plats/{plats}/handelser/{datum}` och `/lan/{lan}/handelser/{datum}`
från dagsvyer (~1.3M potentiella URL:er) till månadsvyer (~42 000) med
synliga dag-sektioner och dag-anchors. Behåll top-level
`/handelser/{datum}` som dagsvy. Pilota mot Uppsala i 30 dagar innan
full rollout — strategin är inte självklar, har reella risker för
AdSense-intäkter och soft-404 på småorter.

---

## Problem

Datum-routerna för plats × datum och län × datum genererar URL-
explosion:

- 350 platser × 365 dagar × ~10 års data ≈ **1.3M potentiella URL:er**
- 21 län × samma = ~76 000 potentiella URL:er

Faktisk situation idag (2026-04-26, från GA + GSC):

- **Pageviews/session ~1.3** på `/handelser`-prefixet — folk landar,
  klickar in på _ett_ event, lämnar
- **Sidor är magra** — bara en datumsorterad lista, ingen översiktskarta,
  ingen statistik, inget kontextualiserande innehåll
- **GSC**: ingen söker proaktivt på specifika datum — top datum-query
  fick 2 klick/månad. Datum-URL:er rankar via "[brott] [plats] [år]"-
  queries där datum är _kontextuellt_, inte primärt
- **6 553 unika datum-URL:er** har faktiskt trafik senaste 30 dagar.
  Den långa svansen är gigantisk.

---

## Beslut: vad vi gör

### URL-struktur

```
/plats/{plats}/handelser/{år}/{månad}    (4-siffrigt år, 2-siffrigt månad)
/lan/{lan}/handelser/{år}/{månad}
```

Exempel: `/plats/uppsala/handelser/2026/04`

Top-level `/handelser/{datum}` (utan plats/län-prefix) **behålls** —
99% av "dagens händelser"-trafiken landar där och fungerar bra.

### Innehåll på månadssidan

Ordning uppifrån (alla synliga från start, inga kollapsade element):

1. **H1**: "Polishändelser i {Plats}, {månad} {år}"
2. **Översiktskarta** — Leaflet med alla event för månaden klustrade
3. **Statistik-block** — "Vanligaste brottstyperna denna månad" + ev.
   jämförelse med föregående månad
4. **Innehållsförteckning** ("Hoppa till dag")
   — anchor-lista över dagar med events, alltid synlig (inte hopfällbar)
5. **Dag-sektioner** med `<h2 id="2026-04-15">`, alltid expanderade
6. **Föregående/nästa månad-nav** + "Hoppa till år"-dropdown längst ner
7. **Schema.org `WebPage` + `BreadcrumbList`**

### Anchor-strategi

```
/plats/uppsala/handelser/2026/04
├─ #2026-04-13  (h2)
├─ #2026-04-14
├─ #2026-04-15
└─ ...
```

Inga vecko-sektioner — vecko-numrering ger ingen UX-vinst för en
besökare som söker händelser i en specifik månad, och cross-month-veckor
(där vecka 14 sträcker sig in i mars och april) skapar onödiga
beslut. Dag-anchors räcker.

Anchors indexeras inte som separata URL:er. Värdet är **inte** SERP-
deeplinks (Google har dragit ner på dem sedan 2023 — räkna 0–5% CTR-
lyft, inte dubblat). Värdet är **UX för innehållsförteckning** —
användaren scrollar inte i blindo. 301:er till anchors bevarar inte
ranking-equity per dag (Google strippar fragment vid 301-tracking) —
hela equity flyttas till månads-URL:n.

`scroll-margin-top` måste matcha **summa** av sticky-element: nav
(~60px) + cookiebanner (~80px) + sticky ad-unit (~100px) = ~240px.
Mät i devtools innan deploy.

---

## Beslut: vad vi INTE gör

- **Inga `<details>`/kollapsade sektioner.** Google devalverar dolt
  innehåll. AdSense räknar inte annonser inuti stängt `<details>`
  som viewable. iOS Safari har scroll-buggar med dynamiskt expanderat
  innehåll.
- **Inga vecko-vyer som egna URL:er.** Granulariteten i URL-form ger
  marginellt SEO-värde mot kostnaden i URL-yta. Vecko-anchors räcker.
- **Ingen migration av top-level `/handelser/{datum}`.** Den fungerar.
  Bara plats/län-routerna flyttas.

---

## Tomma och magra månader (kritisk policy)

Småorter (Hofors, Härjedalen, Dals-Ed) har 0–2 events per månad även
historiskt. Med ~42 000 månadsvyer är ett konservativt estimat att
**5–15% är 0-event eller 1-event-sidor**. Behandlade fel drar de ner
hela domänens kvalitetssignal i Google + triggar AdSense policy-
varningar för "low-value content".

| Antal events i månad | Action                                                     |
| -------------------- | ---------------------------------------------------------- |
| 0                    | **410 Gone** (eller 301 till plats-sidan utan datum)       |
| 1–2                  | `<meta robots="noindex,follow">` + ingen AdSense-rendering |
| 3+                   | Full rendering, indexerbar, AdSense aktiverad              |

Tröskeln "≥ 3" är hypotes. Validera mot CPM-data efter pilot. Möjlig
justering till "≥ 5" om tomma sidor visar sig vara skadliga.

AdSense-rendering ska alltid villkorlig: `@if($events->count() >= 3)`
runt ad-slots, inte bara på sidan generellt.

---

## URL-format: beslut + alternativ

Förslaget är `/plats/{plats}/handelser/{år}/{månad}` (hierarkiskt).
Innan implementation **utvärdera mot CTR-data** för existerande URL:er
i GSC. Om alternativt format visar tydligt högre CTR i SERP — byt.

| Format                                       | För                               | Mot                           |
| -------------------------------------------- | --------------------------------- | ----------------------------- |
| `/plats/uppsala/handelser/2026/04` (förslag) | Hierarkiskt, lätt att parsea      | Inga sökord i URL-segmentet   |
| `/plats/uppsala/handelser/april-2026`        | Månadens namn syns i SERP-snippet | Behöver slug-parsning         |
| `/plats/uppsala/april-2026`                  | Kortast, ren                      | Bryter `/handelser/`-struktur |
| `/handelser-uppsala-april-2026`              | Maximalt sökord-rikt              | Helt ny URL-rotnivå           |

Format-beslutet **dokumenteras med motivering** i todo-filen innan
kod skrivs. Beslut: \_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_ (fyll i).

---

## 301-strategi (ETT hopp, inte kedja)

Befintliga svenska datum-URL:er som `/plats/uppsala/handelser/15-april-2026`
ska 301:as direkt till månadsvyn med dag-anchor:

```
/plats/uppsala/handelser/15-april-2026
  → 301 → /plats/uppsala/handelser/2026/04#2026-04-15
```

**Krav:** verifiera att inga befintliga middleware (RedirectOldPages
m.fl.) lägger på extra hopp. Allt ska gå i ETT 301. Google förkortar
ofta kedjor men ibland ignorerar dem — riskera inte det.

Day-nav på plats/län-sidor (i `Helper.php`, `PlatsController`,
`LanController`) skrivs om från dagshopp till månadshopp.

---

## Mobil + paginering

80% av trafiken är mobil. Storstadssidor (Stockholm/Göteborg/Malmö)
får 500–2000 events per månad — initial render måste vara hanterbar.

**Server-side paginering inom månadsvyn:**

- 50 events per sida, querystring `?p=2`
- Sida 1 är canonical (utan param eller `?p=1`)
- Sida 2+ får `<meta robots="noindex,follow">` (paginering är UX-
  feature, inte ranking-yta)
- Dag-anchors finns på alla sidor — om en användare landar på
  `#2026-04-15` och dagen ligger på sida 2, redirectar routern till
  rätt sida (`?p=2#2026-04-15`)
- Översiktskartan visar **alla** månadens events oavsett sida —
  pagineringen gäller bara dag-listorna under
- Föregående/nästa-länkar mellan sidor med `rel="prev"` / `rel="next"`
  (deprecated av Google men respekteras av Bing och AI-crawlers)

**Mobil-prototyp INNAN implementation.** Verifiera scroll-to-anchor
beteende på iOS Safari + Android Chrome med faktisk sticky-header-
höjd inkl. cookiebanner och ad-units.

---

## AdSense-strategi

Brottsplatskartan har AdSense som intäktskälla. Månadsvy-migrationen
har **icke-trivial risk** för intäktssänkning som måste mätas.

### Risker

- **Färre pageviews/session.** Om månadsvyn är "färdig" (allt
  innehåll på en sida) klickar användaren inte vidare → färre ad-
  impressions per session. Kompenseras kanske av högre dwell-time
  → högre RPM, men det är inte garanterat.
- **0-event-sidor får inte servera annonser** (policy-risk, se ovan).
- **Viewability** av annonser placerade nere på sidan är låg på mobil.

### Strategi

- **Ad-slots placeras strategiskt:** mellan översiktskarta och vecko-
  ToC, mellan vecko-sektioner (max var 2:e), inte längst ner
- **Inga annonser på 0-event eller 1–2-event-sidor**
- **Mät RPM/session före och efter pilot.** Om månads-RPM <
  dags-RPM × 1.3 (kompensationsfaktor för färre PV): rulla tillbaka

---

## Pilot + KPI:er för rollback

Implementera först bara för **EN plats** (förslag: Uppsala — medelstad,
inte for liten, inte gigantisk). Mät 30 dagar innan rollout till alla.

### KPI:er och rollback-tröskel

| Metric                       | Baseline (mät innan) | Tröskel för rollback          |
| ---------------------------- | -------------------- | ----------------------------- |
| Total clicks från Google     | (mät dag 0)          | < 90% av baseline             |
| Indexerade pages för platsen | (via GSC)            | < 80% av baseline             |
| PV/session på platsens sidor | ~1.3                 | < 1.0                         |
| RPM per session (AdSense)    | (mät dag 0)          | < dags-RPM × 1.3              |
| Mobile bounce rate           | (mät dag 0)          | > +10 percentage points       |
| Soft-404-rapport i GSC       | 0                    | > 5 nya soft-404 från piloten |

Om någon tröskel triggas: rulla tillbaka piloten. Investigera. Iterera.

### Rollback-mekanism

Behåll dagsvys-routen i kod i 6 månader efter migration. Den
301:ar bara till månadsvy under normal drift, men kan reaktiveras
genom att avmarkera 301-logiken om rollback krävs.

---

## Implementation-ordning

Strikt sekventiell — gå inte vidare till nästa steg innan föregående
verifieras.

1. **URL-format-beslut** + dokumentation i denna todo
2. **Mobil-prototyp** (HTML-only, utan backend) — verifiera
   landningsupplevelse + scroll-to-anchor på iOS/Android
3. **Backend-route + controller-metod + Blade-vy** för EN plats
   (Uppsala). Inkl. paginering, tomhantering, AdSense-villkor.
4. **301-redirect-logik** för Uppsala dagsvyer → månadsvy.
   Verifiera ETT hopp via `curl -ILv`.
5. **Performance-test** — månadsquery på `crime_events`. Indextest
   för `(plats, parsed_date)`-kombination.
6. **Sitemap-uppdatering** för Uppsala — månadsvyer in, dagsvyer
   ut. Submit till GSC.
7. **Schema.org JSON-LD** — WebPage + BreadcrumbList.
   Validera i Google Rich Results-test.
8. **Pilot-deploy + baseline-mätning** dag 0
9. **30 dagars soak.** Daglig KPI-koll. Rollback om tröskel triggas.
10. **Full rollout** — alla platser + län. Behåll Uppsala som
    benchmark om något oroväckande dyker upp.

---

## Schema.org-skiss

```json
{
    "@context": "https://schema.org",
    "@type": "WebPage",
    "name": "Polishändelser i Uppsala, april 2026",
    "url": "https://brottsplatskartan.se/plats/uppsala/handelser/2026/04",
    "description": "...",
    "isPartOf": {
        "@type": "WebSite",
        "url": "https://brottsplatskartan.se/"
    }
}
```

Plus `BreadcrumbList` (Hem › Uppsala › April 2026).

`hasPart` med `WebPageElement` per dag övervägdes och avfärdades —
Google har aldrig visat schema-equity för dag-nivå-sektioner i SERP.
Inert markup, bara ballast.

---

## Risker — sammanfattning

| Risk                                  | Mitigering                                         |
| ------------------------------------- | -------------------------------------------------- |
| Soft-404 på tomma månader             | 410/noindex policy enligt event-tröskel            |
| AdSense policy-violation              | Villkorlig ad-rendering på event-tröskel           |
| 301-kedjor                            | Verifiera ETT hopp för alla legacy-format          |
| Mobil scroll-to-anchor-buggar         | Prototyp-test innan implementation                 |
| Storstadssidor med 1000+ events       | Server-side paginering, sida 2+ noindex            |
| Färre PV/session → ad-impression-tapp | Mätplan + rollback-tröskel + ad-placement-strategi |
| Ranking-rörelser (2–8 veckor)         | Behåll dagsvys-route 6 månader för rollback        |
| Fel URL-format                        | CTR-dataanalys innan beslut                        |

---

## Öppna frågor (review 2026-04-26)

Punkter som kom upp under design-review och behöver lösas innan
implementation startar.

1. **RPM-tröskel saknar empirisk grund.** Tröskeln "månads-RPM <
   dags-RPM × 1.3" är gripen ur luften. Mät idag faktisk RPM/session
   per URL-typ separat (dagsvyer vs. plats-startsidor) och använd
   delta som benchmark.
2. **Pilot på en plats validerar inte alla scenarier.** Uppsala
   (medel) testar inte storstad (Stockholm 1000+ events/månad →
   paginering bryter UX) eller småort (Hofors 0–2 events → 410-tröskeln).
   Överväg pilot på 3 platser parallellt: Uppsala + Hofors + Stockholm.
3. **URL-format-beslutet är öppet** men listas som steg 1 i
   implementation-ordningen. Bestäm utifrån GSC CTR-data innan kod
   skrivs. `/handelser/april-2026` ger snippet-fördel jämfört med
   `/handelser/2026/04`.
4. **Index-strategi för månads-query.** `WHERE plats = X AND
   MONTH(parsed_date) = 4 AND YEAR(parsed_date) = 2026` triggar full
   scan på funktionsanvändning. Kör range:
   `WHERE parsed_date BETWEEN '2026-04-01' AND '2026-04-30 23:59:59'`
   med composite-index `(plats, parsed_date)`. Dokumentera explicit
   i steg 5.
5. **Hypotesen om PV/session är osäker — kan slå åt fel håll.**
   Argumentet "rikare sidor → längre dwell-time → bättre RPM" är
   plausibelt men en "färdig" månadsvy ger användaren mindre
   incitament att klicka vidare → linjär nedgång i ad-impressions
   per session. Acceptera att utfallet är en empirisk fråga, inte en
   given vinst.
6. **Rollback kräver feature-flag.** "Behåll dagsvys-route i 6 mån"
   behöver konkret omkopplingsmekanism, t.ex.
   `MONTHLY_VIEWS_ENABLED`-config med per-plats override. Specificera
   innan steg 3.
7. **KPI-tröskel PV/session är för generös.** Från 1.3 till 1.0 =
   −23 %. Bör vara striktare (−10 %, dvs <1.17) för att fånga
   problem tidigt.
8. **#29-överlapp.** #29 markerar redan gamla datum-routes som
   `noindex,follow` (>30d). När månadsvyer deployas blir gamla
   dagsvyer 301:ade — #29:s noindex-logik blir moot för dem. Synka
   så vi inte dubbel-arbetar. Ta också hänsyn till att "indexerade
   pages för platsen" som rollback-tröskel blir mindre meningsfull
   (vi vill ju inte ha de gamla URL:erna indexerade).

## Confidence-nivå

**Medel.** Strategin är troligt rätt men inte självklart. Underliggande
SEO-principen (rikare sidor > tunna, färre URL:er > URL-explosion) är
välbeprövad sedan ~2020. Risken ligger i implementering, inte strategi.

Pilot-mätningen avgör om hypotesen håller mot Brottsplatskartans
specifika trafikmönster och AdSense-baserade affärsmodell.
