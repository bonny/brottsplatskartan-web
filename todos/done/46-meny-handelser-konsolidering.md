**Status:** klar 2026-05-17 — minimal-fix deployad lokalt: 301 `/handelser/` → `/`, 301 `/datum/` → `/` (ingen kedja), "Senaste" dolt från menyn, 5 interna `route('handelser')` → `route('start')`. Full menyomarbetning parkerad tills #76 är klart + 4–6v mätning.
**Senast uppdaterad:** 2026-05-17

# Todo #46 — Slå samman Händelser, Senaste och Mest lästa i huvudmenyn

Importerad från GitHub-issue [#76](https://github.com/bonny/brottsplatskartan-web/issues/76).

## Sammanfattning

Huvudmenyn har idag fem toppnivå-poster (Händelser, Senaste, Mest lästa,
Nära, Sverigekartan) som egentligen alla är olika vyer av samma underliggande
data. Förslag: kollapsa till en "Händelser"-toppost med undermeny.

## Bakgrund

Nuvarande meny:

- Händelser
- Senaste
- Mest lästa
- Nära
- Sverigekartan

Föreslagen struktur:

- Händelser
    - Senaste
    - Mest lästa
    - På kartan
    - Nära mig
    - Sök händelser

Föreslagna URL:er:

- `/` startsida
- `/handelser/senaste`
- `/handelser/mest-lasta`
- `/handelser/nara-mig`
- `/handelser/karta`

## Förslag

Behöver designbeslut: dropdown vs egen landningssida `/handelser` med
kort-grid till undervyer? Tänk även på mobil där dropdown är klumpig.

URL-omskrivningar kräver redirects från gamla paths för SEO.

## Risker

- SEO: alla nuvarande URL:er måste 301-redirectas, annars tappar vi indexering
- Mobilmeny blir djupare
- "Sverigekartan" finns inte i förslaget — vart tar den vägen?

## Nuvarande URL-mapping (för referens)

Menyn har egentligen **sex** toppnivå-poster (inte fem som ursprungstexten sa) —
"Län" är också kvar. Routar idag (`routes/web.php`, `resources/views/parts/siteheader.blade.php`):

| Meny          | Route-namn      | URL                 | Controller              |
| ------------- | --------------- | ------------------- | ----------------------- |
| Händelser     | `start`         | `/`                 | StartController@day     |
| Senaste       | `handelser`     | `/handelser/`       | StartController@day     |
| Mest lästa    | `mostRead`      | `/mest-last`        | (MestLastaController)   |
| Län           | `lanOverview`   | `/lan`              | LanController           |
| Nära          | `geoDetect`     | `/nara-hitta-plats` | GeoController           |
| Sverigekartan | `sverigekartan` | `/karta`            | FullScreenMapController |

Notera: "Händelser" och "Senaste" visar i praktiken **samma data** (StartController@day).
Det är där den största konsolideringsvinsten finns — inte i att gömma Sverigekartan.

## SEO-review

**Risker:**

1. **Keyword-kannibalisering är redan ett problem.** `/` och `/handelser/` är
   nästan duplicat (samma controller-method). Kolla Search Console: rankas de
   för samma queries? Om ja → kanonisera den ena till den andra **innan**
   menyn rörs. (Gör quick-fix nu: rel=canonical från `/handelser/` → `/` om
   start-controllern levererar samma resultat.)
2. **Redirects är icke-förhandlingsbart.** Allt som idag har inkommande länkar
   eller indexering måste 301:as. Befintliga redirects (`/sverigekartan/` → `/karta`,
   `/mest-lasta/` → `/mest-last`) visar att mönstret är etablerat.
   Verifiera med GSC vad som faktiskt rankar innan något döps om.
3. **Internt länk-flöde.** Toppnivåer i menyn får mer PageRank än
   undermenyer. Om "Sverigekartan" göms i en dropdown tappar `/karta`
   internlänk-styrkan — kolla om sidan rankar för "sverigekarta",
   "brottskarta" m.fl. via GSC innan beslut.
4. **Landningssida `/handelser` behöver substans.** En sida som bara är
   navigationskort = "thin content" enligt Google. Måste innehålla
   verkligt material (senaste händelser-preview, statistik, karta-preview)
   för att rankas och inte AdSense-flaggas.
5. **Sitemap + interna länkar** måste uppdateras parallellt med redirects.

**Möjligheter:**

1. **`/handelser` kan bli en stark hub-sida.** Ett rikt landningskort för
   queryn "polisens händelser" / "händelser polisen" — som idag splittras
   mellan `/` och `/handelser/`.
2. **Tydligare URL-struktur** (`/handelser/senaste`, `/handelser/karta`,
   `/handelser/nara-mig`) ger semantisk hierarki som Google gillar och
   som breadcrumbs/schema.org kan utnyttja.

**Förarbete (gör innan implementation):**

- GSC: dra topp-50 sidor och topp-50 queries för paths som matchar
  menyn — se vad som faktiskt rankar och vad som riskeras.
- Identifiera de 3–5 viktigaste URL:erna att skydda → bygg redirect-mappen
  utifrån dem.

## AdSense-review

**Risker:**

1. **Pageviews kan tappa eller stiga — det beror på flow-design.**
    - Dropdown utan landningssida → färre pageviews (en URL byts mot
      hover-meny).
    - Landningssida `/handelser` med kort-grid → en extra pageview per
      navigeringskedja (bra för intäkter, om sidan inte irriterar).
      GA4: jämför nuvarande pageviews/session för menyposter innan/efter.
2. **Thin-content-risk på landningssida.** AdSense-policy kräver
   "substantial original content". En ren navigationssida kan få
   sänkt eCPM eller avstängda enheter. Måste innehålla riktigt innehåll
   (event-feed, statistik, karta-preview, ledtext).
3. **Karta + ads = dålig viewability.** Om kartan flyttas in i `/handelser`
   (se nedan) får ovanförvik-annonsen konkurrens om utrymmet.
   Interaktiva canvas/leaflet-vyer ger ofta låg viewability → låg eCPM.
4. **Core Web Vitals.** En tung landningssida (karta + listor + ads)
   riskerar LCP/INP-regression. AdSense Auto-ads + Leaflet tillsammans
   är historiskt en CLS/LCP-fälla — mät innan rollout.

**Möjligheter:**

- En välbyggd `/handelser`-hub kan bära **2–3 annonsenheter** (in-content
  och sidebar) bättre än de tunna meny-måltavlorna idag.
- Konsolidering minskar antalet "lågkvalitets-thin"-paths som drar ner
  domänens AdSense-kvalitetspoäng (inte officiellt dokumenterat men
  observerat — färre tunna sidor = bättre snittintäkt).

## Ska kartan flyttas in i denna vy?

**Frågan:** ska `/karta` (Sverigekartan, FullScreenMapController) byggas
in i `/handelser`-landningssidan istället för att vara en egen subvy?

**Argument för (lägga in karta i `/handelser`):**

- Karta är visuellt slagkraftig → bra hero på en hub-sida.
- Stärker substansen → undgår thin-content-stämpel (SEO + AdSense).
- En enda destination för "händelser nu" istället för två tankesteg.

**Argument emot (behåll `/karta` separat):**

- **Prestanda.** Leaflet + tile-fetch + events-pin-rendering är dyrt.
  Det drabbar **alla** som landar på `/handelser` även om de bara vill
  ha en lista. Kostar LCP, INP och AdSense-viewability.
- **Befintlig SEO-equity.** `/karta` (+ 301 från `/sverigekartan/`) har
  troligen redan indexering och rankar för "brottskarta", "sverigekarta
  polisen" osv. Att slå ihop till `/handelser` raserar separat rankning
  om inte canonical/redirect-strategin är vattentät.
- **Användarintention skiljer sig.** "Visa karta" och "visa lista" är
  två olika jobb — sammanslagning förvirrar mer än hjälper.
- **AdSense-policy.** Helskärmskartor med ads är gråzon (ads får inte
  täcka funktionell UI). Separat `/karta` ger fri designyta utan
  ads-restriktioner.

**Rekommendation:** **Nej — behåll `/karta` som egen topp-vy.** Skälen
(korrigerade efter extern review — se sektion längst ner):

1. **Topical separation.** `/karta` rankar för "brottskarta"/"brott karta"
   — en separat query-familj från "händelser"-clustret. Att slå ihop
   URL-targets för olika query-intentioner är klassisk SEO-anti-pattern.
2. **Olika user intent.** Visa karta ≠ läsa lista.
3. **CTR-upside via title-optimering.** 48 clicks @ pos 3-4 har clear
   uppsida som inte rörs av menyflytten.
4. **Discover-eligibility.** Distinkt URL bevarar Discover-rankning
   (Google Discover gillar fokuserade sidor).

(Notera: "internlänk-flöde från toppmeny" — som jag motiverade med
tidigare — är överskattat 2026; det är _antalet_ inkommande interna
länkar som spelar roll, inte DOM-position.)

För `/handelser`-landningssida-frågan: **bygg INTE en sådan hub-sida.**
`/` rankar redan pos 7.7 för "polisen händelser" (25 331 impressions/90d)
— en ny `/handelser`-hub splittrar signalen ytterligare. Att förbättra
title/H1 på `/` (= #76-arbetet) löser problemet utan URL-fragmentering.

## GSC-baseline (90d, dragen 2026-05-17)

Råa dumpar: [`tmp-gsc-46/gsc-90d-meny-queries.md`](../tmp-gsc-46/gsc-90d-meny-queries.md)
(gitignored under `tmp-*`).

| Menypost      | URL                 | Clicks/90d | Impressions |   CTR | Karaktär                                            |
| ------------- | ------------------- | ---------: | ----------: | ----: | --------------------------------------------------- |
| Händelser     | `/`                 |   **3540** |      62 868 | 5.63% | Brand-king + alla kategori-queries                  |
| Senaste       | `/handelser/`       |      **0** |           — |     — | Indexeras inte med trailing slash                   |
| Senaste       | `/handelser`        |     **23** |         284 | 8.10% | Random long-tail, ingen samlad intention            |
| Mest lästa    | `/mest-last`        |     **30** |         379 | 7.92% | Allt är flashback-long-tail (pos 8–15)              |
| Län           | `/lan`              |        261 |       4 280 | 6.10% | **258/261 = brand-spillover** ("brottsplatskartan") |
| Nära          | `/nara-hitta-plats` |     **17** |         775 | 2.19% | 14/17 = brand-spillover; rankar inte "nära mig"     |
| Sverigekartan | `/karta`            |     **48** |         512 | 9.38% | Rankar #3–4 på "brottskarta"/"brott karta"          |

### Konkreta slutsatser för konsolideringen

1. **"Senaste" kan tas bort eller döljas i undermeny utan SEO-risk.**
   `/handelser/` har **0** organic clicks. `/handelser` (utan slash) får
   23 clicks/90d på random long-tail som ändå borde landa på `/`,
   stadssidor eller plats-sidor. Den dubblerar `/` (samma controller) och
   är ett kannibaliserings-problem mer än ett ranking-värde.
    - **Quick win innan menyändring:** sätt rel=canonical från `/handelser`
      → `/` (eller 301:a `/handelser` → `/` om vi inte behöver datum-aliasing).

2. **"Mest lästa" kan flyttas till undermeny utan SEO-risk.**
   30 clicks/90d, allt är flashback-long-tail. Sidan rankar inte för sin
   egen intention. Bibehåll URL:en men låt den leva som undersida.

3. **"Nära" kan flyttas till undermeny — eller designas om.**
   17 clicks/90d, varav 14 är brand-spillover. Sidan rankar inte för
   "X nära mig"-mönstret (pos 8–20) trots tydlig intention — separat
   SEO-uppgift värd egen todo om vi vill ta de queries:erna.

4. **`/karta` ska INTE flyttas till undermeny.**
   Rankar genuint för "brottskarta" (#4.1, 35 clicks) och "brott karta"
   (#3.2, 12 clicks) — det är inte brand-spillover. Position 3–4 = clear
   CTR-upside. Att minska internlänk-flödet riskerar att rasera den
   rankningen. Bekräftar tidigare rekommendation: **behåll `/karta` som
   egen topp-meny eller minst egen subvy med stark internlänkning.**

5. **"Län" har inget eget organic-värde — bara brand-spillover.**
   Toppmeny-platsen är "slöseri". Bör flyttas till undermeny eller en
   sektion längre ner. Den egentliga länstrafiken landar på `/lan/{lan}`,
   inte `/lan`.

6. **Verkliga SEO-vinsten finns INTE i menyn.** "polisen händelser"-clustret
   har >30k impressions/28d över hela domänen och rankar pos 5–8. Det är
   där pengarna finns. Menyändringen är UX-hygien + intern länkstruktur,
   inte trafikökning. Den stora trafikvinsten kräver title/H1-arbete på
   `/`, `/stockholm`, `/goteborg`, `/malmo` etc — vilket är vad #76 jobbar
   med.

### Föreslagen menystruktur (uppdaterad efter GSC-data)

```
- Händelser  (= /)        ← bibehåll topp, det här är hubben för 3540 clicks
    - Senaste  (= /)      ← samma sida, eller dropdown-shortcut
    - Mest lästa (= /mest-last)
    - Nära mig (= /nara-hitta-plats eller redesign)
- Sverigekartan (= /karta) ← BEHÅLLS som egen toppost (skydda ranking)
- Län  (= /lan/...)        ← dropdown med top-5 län; ta bort /lan från toppen
```

(Tidigare alternativ "gör en `/handelser`-hub med substans" är **strukket**
efter extern review — splittrar "polisen händelser"-signalen som redan
landar på `/`. Se Second opinion-sektionen nedan.)

## Second opinion (extern SEO/AdSense-review 2026-05-17)

En oberoende subagent-review utmanade analysen ovan. Sammanfattning av
korrigeringar och tillägg som faktiskt påverkar planen:

### Korrigeringar av ursprunglig analys

1. **`/handelser/` → `/` ska vara 301, inte rel=canonical.** Google
   ignorerar ofta canonical mellan nästan-duplikat när sidorna delar
   controller men inte URL-mönster (datum-aliaset `/handelser/{date}`
   finns på `routes/web.php:113`). Rätt fix: 301 från exakt `/handelser/`
   (no-date, strikt end-anchor) → `/`, behåll `/handelser/{date}` som
   datum-arkiv.
2. **"Toppmeny = mer PageRank" är föråldrat 2026.** Det som spelar roll
   är _antalet_ inkommande interna länkar + anchor text, inte DOM-position.
   Kart-rekommendationen är fortfarande rätt, men motiveringen är
   topical separation + Discover-eligibility (se uppdaterad kart-sektion).
3. **Hub-förslaget för `/handelser` är inkonsekvent — struket.** Tidigare
   föreslogs både "bygg `/handelser`-hub" och "menyändringen löser inte
   'polisen händelser'-clustret" i samma dokument. Hub-förslaget är fel:
   `/` rankar redan för queryn, en ny hub splittrar signalen.
4. **`/lan`-borttagning är farligare än antaget.** 258/261 brand-clicks
   = Google har klassat `/lan` som relevant brand-target. Helt borttagen
   kan trigga re-evaluation av `/lan/{lan}`-undersidor. **Behåll
   åtminstone footer-länk** eller redirect till annan länk-juice-mottagare.

### Nya åtgärder/förarbete som missades

- **Discover-rapport ur GSC** — sajten har sannolikt signifikant
  Discover-volym. Sök-rapporten täcker inte Discover.
- **CWV-baseline (PSI) på alla 6 menyposter** — before/after-data.
- **Mobil först.** >60 % trafik. Dropdown fungerar inte på mobil — det
  blir hamburger med nästling. Designa mobil först.
- **Schema.org BreadcrumbList JSON-LD** på nya undersidor — annars
  tappas sitelinks i SERP.
- **Sitemap-disciplin:** gamla URL:er ligger kvar med 410-status några
  veckor (crawl-instruktion) innan de tas bort.
- **Response cache-invalidation:** `responsecache:clear` i deploy-rutinen
  när menyn ändras — annars gammal HTML i 2–30 min.
- **A/B-test är inte möjligt server-side** pga Spatie Response Cache.
  Acceptera full rollout, inget experiment.

### Prioritering (viktigaste insikten)

**Detta är fel fråga att lösa nu.** Subagentens kärnpunkt:

- **#76 ("polisen händelser"-clustret):** pos 7.7 → pos 5 på 25 331
  impressions vid CTR 0.86 % → 2.5 % ≈ **+400 clicks/månad bara från
  queryn på `/`**. 4–5x menyposternas totala trafik tillsammans.
- **#46 menyändringen** rör ~70 organic clicks/90d totalt — ROI är låg
  och redirect-risk är reell.

**Reviderad väg framåt:**

1. **Parkera #46 tills #76 är klart + 4–6 v mätning.**
2. **Minimal-fix nu** (om något): 301 från `/handelser/` (no-date,
   strikt end-anchor) → `/` + dölj "Senaste" från menyn. Inget annat.
3. Full menykonsolidering = ren UX-hygien, görs när #76-frukten är skördad.

## Confidence

**hög** på diagnosen (GSC-data + extern review samstämmiga).
**medel** på timing (parkera vs minimal-fix nu är en prio-fråga som
användaren behöver besluta).

Kvarvarande beslut innan implementation:

- Vänta på #76 eller köra minimal-fix `/handelser/` → `/` nu?
- Mobile-first menydesign + BreadcrumbList-schema
- Discover-rapport + CWV-baseline som extra förarbete
