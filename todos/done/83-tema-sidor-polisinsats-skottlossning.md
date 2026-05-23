**Status:** klar 2026-05-23 — Fas 1+2 skrotade efter Steg 0-mätning; Fas 3 (URL-aliases) + sitemap-backfill för /helikopter+/brand+/inbrott deployade
**Senast uppdaterad:** 2026-05-23

# Todo #83 — Nya tema-sidor: /polisinsats + /skottlossning (+ URL-aliases)

## Utfall (2026-05-23)

**Fas 1 (/polisinsats landing) — skrotad.** Steg 0-mätning visade endast 15 events/30d i alla fält (title + teaser + content + synonymer) → ~0.5/dygn, långt under 3/dygn-gaten. En landing-sida skulle bli tunnare än stadssidorna och rankas under dem. GSC bekräftar att "polisinsats [stad]"-trafik redan landar på `/stockholm` (75c), `/goteborg` (41c) osv.; `/typ/polisinsats` (#72) fångar kategori-trafik. Extra landing tillför inget.

**Fas 2 (/skottlossning landing) — skrotad.** Endast **1 event/30d** matchar `parsed_title LIKE '%skottlossning%' OR '%skjutning%'`. Polisen kallar inte sina händelser för "skottlossning" i titeln (använder "Mord/dråp, försök", "Skadegörelse" osv.). Datan finns inte i den form folk söker.

**Fas 3 (URL-aliases) — deployad.** `/polishelikopter` → `/helikopter` (verifierat 301), `/polisaktion` + `/polispådrag` → `/typ/polisinsats` (verifierat 301). `/skjutning` → `/skottlossning` skippades — destinationen finns inte.

**Sitemap-backfill — deployad.** `/helikopter`, `/brand`, `/inbrott` tillagda i `GenerateSitemap.php $static`-arrayen. Fångar 3 200+ clicks/mån sammanlagt och saknades tidigare. Verifierat via `sitemap:generate` + curl `/sitemap-main.xml` (alla tre URL:er närvarande).

**PHPStan:** OK (0 errors, 127 filer).

Referensvärden från Steg 0 (events/30d):

| Tema                                | Events/30d | Per dygn   | Verdikt                               |
| ----------------------------------- | ---------- | ---------- | ------------------------------------- |
| brand (parsed_title LIKE)           | 147        | ~5/dygn    | ✅ Klarar (förklarar 1 568 clicks)    |
| helikopter (title+teaser+content)   | 24         | ~0.8/dygn  | ⚠️ Marginellt men teaser-bredd lyfter |
| polisinsats (alla fält + synonymer) | 15         | ~0.5/dygn  | ❌ Långt under 3/dygn                 |
| skottlossning + skjutning           | 1          | ~0.03/dygn | ❌ Praktiskt taget noll               |

**Lärdom:** Steg 0-mätning fungerade som planerat — filtrerade bort dum implementation innan kod skrevs. Reviewens insisterande på förmätningar betalade sig direkt.

## Sammanfattning

`/helikopter` (1 603 clicks / 18 103 impr / pos 6.6 senaste 28d) och `/brand` (1 568 clicks / 10 898 impr / pos 6.9) är två av sajtens absolut starkaste sidor — de fångar live-intent-trafik ("[topic] [stad] nu / idag / just nu") som annars splittras över stads-sidor eller enskilda gamla händelser.

GSC visar två obetjänade ämnen med likadant query-mönster:

1. **`/polisinsats`** — "polisinsats" 27c/10 046 impr/pos 8.1 + lång svans "polisinsats [stad] [idag]" (göteborg 31c, ingarö 27c, stockholm 25c, gräsmark 20c, sundbyberg-idag 13c, lilla essingen 13c, örebro-idag 10c, "pågående polisinsatser" 10c). 10k impressions/månad bara på huvudord, ingen relevant sida idag → trafiken hamnar på orelevant stadssida.
2. **`/skottlossning`** — "skottlossning stockholm idag" 34c/974 impr/pos 6.5 + svans "skottlossning [stad/område]" (limhamn, uppsala-idag, huddinge-idag, nacka-idag, frölunda-idag, göteborg-idag, helsingborg-idag). ~1 700 impr total skott-svans. Idag landar trafiken på `/stockholm` (40c) eller enskilda gamla händelser → dålig matchning.

**Polisinsats och skottlossning har olika risk-profiler** (AdSense brand-safety, query-volym, tom-dag-frekvens) och styrs nu som separata gates — inte en buntad plan.

## Bakgrund

**Befintliga liknande sidor och hur de byggs:**

- `routes/web.php:815` — `Route::get('/helikopter', [PlatsController::class, 'helicopter'])->name('helicopter')`
  → `PlatsController::helicopter` (rad 1354–1387): `limit(25)` + OR över `parsed_title|parsed_teaser|parsed_content`. Ingen paginering.
- `routes/web.php:559–606` — `/inbrott/{undersida?}` closure: `paginate(40)` på `parsed_title` only.
- `routes/web.php:611–661` — `/brand/{undersida?}` closure: samma mönster som inbrott.

**Konsekvens:** "kopiera mönstret" är otydligt — helikopter och brand är inte samma implementation. För polisinsats långa svans (10k impressions, många "polisinsats [stad]"-queries) krävs **paginering** för att få bredd-täckning. Default: `paginate(40)` på `parsed_title` (likt brand/inbrott).

**Saknad infrastruktur som behöver fixas i samma rotation:**

- `/helikopter`, `/brand`, `/inbrott` **saknas i sitemap** (`GenerateSitemap.php:45-54` listar bara `/, /handelser, /statistik, /lan, /plats, /typ, /vma, /om`). En "lägg till URL"-bullet räcker inte — måste in i `$static`-arrayen explicit.
- Schema.org `parts/collectionpage-jsonld.blade.php` finns men används inte på någon tema-sida idag.
- `parts/sitefooter.blade.php` rad 20–21 länkar `/brand` och `/inbrott` men inte `/helikopter` eller (kommande) `/polisinsats`.

**GSC-data 2026-04-25 → 2026-05-23 (28d):**

| Sida                           | Clicks | Impr   | CTR     | Pos  |
| ------------------------------ | ------ | ------ | ------- | ---- |
| `/helikopter`                  | 1 603  | 18 103 | 8.85 %  | 6.6  |
| `/brand/`                      | 1 568  | 10 898 | 14.39 % | 6.9  |
| `/inbrott/*`                   | 22     | 434    | ~5 %    | ~12  |
| (saknas: polisinsats huvudord) | —      | 10 046 | —       | 8.1  |
| (saknas: skottlossning-svans)  | —      | ~1 700 | —       | 6–12 |

**#72 (deployad 2026-05-13)** lade in `polisinsats` som **typ-alias** i URL:er (`/typ/polisinsats`). Den här todon (#83) bygger ovanpå med en separat **toppnivå-landing page** som drar query-intent direkt, likt `/helikopter` och `/brand`. Se "Cannibalisering-strategi" nedan för hur de skiljs åt.

## Förslag

### Steg 0 — Förmätningar före kod (~30 min)

Kvantifiera tom-dag-risken innan implementation. Båda sidorna ska klara ≥ 3 events/dygn median senaste 30d för att inte trigga soft-404 (jfr [[79-soft-404-idag-fallback]]).

```php
// tinker
CrimeEvent::where('parsed_title','like','%polisinsats%')
  ->where('created_at','>=',now()->subDays(30))
  ->selectRaw('DATE(created_at) d, count(*) c')
  ->groupBy('d')->orderBy('d')->get();

// samma för %skottlossning% och %skjutning%
```

Om median < 3 events/dygn → bygg "senaste N dagar"-fönster med fallback-text **innan** deploy. Om median ≥ 5 → standard listning räcker.

Bonus: kör `mcp__mcp-gsc__inspect_url_enhanced` på `/polisinsats` och `/skottlossning` för att se om Google har 404-historik som påverkar re-crawl-tid.

### Fas 1 — `/polisinsats` landing page

1. **Route** i `routes/web.php`: closure-mönster likt `/brand` (rad 611–661) — håll det enkelt.
2. **Query:** `paginate(40)` på `parsed_title LIKE '%polisinsats%'` **plus** `Dictionary`-kategori "Polisinsats/kommendering" (från #72). Verifiera med stickprov i tinker att unionen ger renare resultat än enbart titel.
3. **Vy:** Ny `resources/views/polisinsats.blade.php`. Lista + karta + per-stad-aggregat. Tom-fallback om Steg 0 visar < 3 events/dygn-median.
4. **Schema.org:** rendera `parts/collectionpage-jsonld.blade.php` — finns redan, bara include.
5. **Title/meta:** matcha topp-queries ("polisinsats", "pågående polisinsatser", "polisinsats [stad] idag"). H1 distinkt från `/typ/polisinsats` (se Cannibalisering-strategi).
6. **Sitemap:** lägg in `/polisinsats` i `GenerateSitemap.php $static`-arrayen — **plus backfilla `/helikopter`, `/brand`, `/inbrott`** i samma commit. Långt missade.
7. **Internlänkning:** lägg in i `parts/sitefooter.blade.php` rad 20–21-blocket. Lägg också "Se alla polisinsatser i Sverige →"-länk i stads-sidornas "polisen händelser [stad]"-modul (utan denna löser landing inte cannibaliseringen).

### Fas 2 — `/skottlossning` landing page (separat gate)

Splittas från Fas 1 — egen go/no-go efter Fas 1-mätning. Brand-safety-risk är högre här (se AdSense nedan).

1. Samma kod-mönster som Fas 1 (`paginate(40)`, schema, footer-länk).
2. **Query:** `parsed_title LIKE '%skottlossning%' OR '%skjutning%'` + kategori "Skottlossning, misstänkt" / "Skottlossning". Bekräfta taxonomin före kod.
3. **Brand-safety-gate:** deploya med `noindex` + **utan AdSense-script** på just denna sida i 14d. AdSense brand-safety-test före indexering. Kräver sid-specifik exclusion i `layouts/web.blade.php:198` (idag är `adsbygoogle.js` global) — eller villkorlig include i blade.
4. **Mät i AdSense → Sidwise:** revenue impact på sajten 14d efter deploy. Om RPM-fall på närliggande pages > 10 % → rollback / håll noindex.
5. **Indexera först** om brand-safety-mätningen är ren.

### Fas 3 — URL-aliases (301-redirects)

Per [[feedback_url_hacking]] (gäller stad-slug-varianter, **inte** synonym-redirects generellt):

- `/polishelikopter` → `/helikopter` ✅ (samma intent, samma sida)
- `/skjutning` → `/skottlossning` ✅ (samma intent)
- `/polisaktion`, `/polispådrag` → `/polisinsats` ✅ (samma intent)
- ~~`/eldsvåda` → `/brand`~~ ❌ struket — inte URL-hacking-mönstret, skapar URL-bloat utan mätbar vinst

Triviala 1-rads-routes. Deployas tillsammans med Fas 1.

## Cannibalisering-strategi mot `/typ/polisinsats` (#72)

Två alternativ utvärderade. **Vald: alternativ B.**

- **A — Konsolidera:** `/polisinsats` är auktoritär, `/typ/polisinsats` får `rel=canonical` till `/polisinsats`. Förkastad: tar bort tematik-prefix-mönstret från typ-systemet utan vinst.
- **B — Håll isär med tydlig differentiering (vald):**
    - `/typ/polisinsats` = ren event-listning av Dictionary-kategorin "Polisinsats/kommendering". Minimal text, snabb listning, lägre signaltyngd.
    - `/polisinsats` = bredare aggregat (titel-match + kategori), **med karta**, **per-stad-aggregat**, **förklarande intro-text** ("Vad är en polisinsats? Hur följer du pågående insatser?"). Mer auktoritär landing.
    - Distinkta H1 + meta-description. Cross-link mellan dem.

## Mål (GSC-mätning ~30–60d post-deploy)

- `/polisinsats`: nå pos < 5 på "polisinsats" + "pågående polisinsatser" + "polisinsats [stad]". Realistiskt fångst-mål: **500–1 500 clicks/månad** (jämfört med `/helikopter` 1 603 och `/brand` 1 568, men polisinsats-svansen är tunnare och konkurrerar med /typ/polisinsats + stadssidor).
- `/skottlossning`: nå pos ≤ 5 på "skottlossning [stad] idag"-svansen. Realistiskt: **100–300 clicks/månad**. Lägre absolutvinst — Fas 2 är värd det **bara om AdSense-gaten är ren**.

## Risker

- **Cannibalisering** mot stadssidor: "skottlossning stockholm idag" driver redan 40c till `/stockholm`. Internlänk från stadssida → landing är obligatorisk för att inte konkurrera utan att lösa.
- **Cannibalisering mot `/typ/polisinsats`** — hanteras via "Håll isär"-strategin ovan, men mätning krävs.
- **AdSense brand-safety på `/skottlossning`:** "skottlossning" är på svenska brand-blocklists post-gängskjutnings-debatten. Riskens scope = sidvis bidrate, inte hela sajten. Hanteras via Fas 2-gaten.
- **Tom-vid-låg-aktivitet:** lös via Steg 0-mätning + fallback. Helikopter-mönstret saknar fallback idag (`limit(25)` underflöder tyst).
- **Polisinsats är bred kategori:** stickprov efter deploy.
- **Helikopter/brand backports** (sitemap + schema + footer-länk) ska in i samma commit men kan skapa mätbrus i 28d post-deploy — separera om mätningen för #83 är blockerande.

## Confidence

**Medel-hög för Fas 1.** `/helikopter` och `/brand` är direkt bevis att mönstret fungerar. Polisinsats har 10k+ impressions/månad utan dedikerad sida = tydligt gap. Implementationsmässigt trivialt.

**Medel för Fas 2.** Skottlossning passar formen men AdSense-risken är reell. Vinst 100–300c/mån är på gränsen för arbete + risk.

**Hög för Fas 3.** 30 min arbete, ren upside.

## Beroenden

- Bygger på [[72-typ-polisinsats-alias]] (deployad 2026-05-13) för Dictionary-kategorin. Inte blockerande.
- Sidobaroll-todo att skapa: "Backfill schema.org + sitemap för befintliga tema-sidor (/helikopter, /brand, /inbrott)" — kan göras i samma commit som Fas 1 eller brytas ut.

## Mätplan

- **Före deploy:** snapshot GSC 28d för queries `polisinsats*`, `*polisinsats*`, `skottlossning*`, `*skottlossning*`, `skjutning*` (page, query, clicks, impr, ctr, pos). Plus AdSense Sidwise RPM för `/helikopter` och `/brand` (referensvärden för Fas 2-gaten).
- **T+14d:** indexerings-check (URL Inspection). Fas 2 brand-safety-mätning i AdSense Sidwise.
- **T+30d:** GSC `compare_search_periods` mot baseline. Mät både fångst (clicks till nya sidor) och rörelse i stadssidornas svans (cannibalisering) **och** rörelse i `/typ/polisinsats` (intern cannibalisering).
- **T+60d:** slutmätning + beslut om Fas 3-aliases gett mätbar effekt eller bara latrina.

## Öppna frågor / behöver mer data

- **Faktisk AdSense-RPM på `/helikopter` och `/brand` idag** — kritisk för Fas 2 go/no-go. Måste hämtas manuellt från AdSense-konsolen (Sidwise 90d) före Fas 2.
- **Time-to-index på sajten** — om typiskt > 21d är T+30d-mätning för tidigt.
- **Historisk 404 på `/polisinsats` och `/skottlossning`** — kollas via GSC URL Inspection före deploy.
