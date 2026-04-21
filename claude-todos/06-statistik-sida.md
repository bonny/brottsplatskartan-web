# Todo #6 — Flytta Brottsstatistik till egen /statistik-sida

**Status: Klar 2026-04-21.** `/statistik` finns med 14d-graf, topp-brottstyper,
län-topplista, topp-dagar och totalsiffra. Brottsstatistik-rutan ersatt med
CTA-puff på startsida, län-, stads- och händelsesidor. V2 (per-timme-mönster,
21 län-grafer, datum-filter) är ej planerat.


## Sammanfattning

Brottsstatistik-rutan på startsidan (14-dagars stapeldiagram för hela Sverige)
föreslås flyttas till en egen sida på `/statistik`. Syftet är en renare
startsida med fokus på karta + senaste händelser, samtidigt som statistiken
får egen yta för djupare visualisering och evergreen SEO-trafik på queries
som "brottsstatistik sverige".

## Nuläge

- Rutan renderas i `resources/views/start.blade.php` rad 82–90 i `@section('sidebar')`.
- Data byggs av `App\Helper::getStatsChartHtml('home')` — returnerar färdig
  HTML-sträng (charts.css `<table class="charts-css column ...">`).
- Implementation i `app/Helper.php`:
  - `getStatsChartHtml($lan)` — wrappar `buildStatsChartHtml` i
    `Cache::flexible` med TTL 15 min / SWR 25 min (rad 28–32).
  - `getHomeStats($lan)` — 14 senaste dagarna (Sverige totalt), cachas i
    2 h under nyckel `lan-homestats-home` (rad 135–165).
  - `getLanStats($lan)` — per län, cachas 15 min (rad 105–127).
  - `getAllLanWithStats()` — 21 län × 3 perioder (idag/7d/30d), redan
    request-memoiserad + Redis-cachad (rad 183–264).
- Används också i:
  - `StartController::index` rad 82 och rad 269 (datum-variant).
  - `CityController` rad 107.
  - `LanController` rad 238 (`chartHtml` för länssida).
- Frontend-stack: **charts.css** (pure CSS). Ingen Chart.js/D3 i bundlen.
  `public/css/charts.min.css` laddas redan globalt.

## Designförslag för /statistik

Sektioner uppifrån och ned:

1. **Hero / intro** — kort ingress, sista uppdaterings-tid, total i DB.
2. **14-dagars Sverige-graf** (återanvänd `getStatsChartHtml('home')`).
3. **30-dagars graf** — ny helper `getHomeStats('home', 30)` (parametrisera
   dagantal) eller ny `getHomeStats30Days`.
4. **Topp 10 brottstyper senaste 7 dagarna** — group by `parsed_title`
   (finns liknande query i `overview-typer`). Stapeldiagram + länkar till
   respektive typ-sida (`/handelser/typ/...`).
5. **Län-grid** — 21 små stapelgrafer (14 dagar per län) eller en sorterad
   topplista med `numEvents.last7days` från `getAllLanWithStats()`. Lättaste
   v1: topplista + länk till respektive `/lan/{namn}` där grafen redan finns.
6. **Rekord-sektion** — topp 5 dagar med flest händelser totalt (enkel query
   ORDER BY count DESC). Cachas 24h.
7. **Per-timme-mönster** (v2, valfritt) — genomsnitt per timme på dygnet
   senaste 30 dagarna. Visar när på dygnet mest rapporteras.

### Teknisk struktur

- Route: `Route::get('/statistik', [StatisticsController::class, 'index'])->name('statistics');`
- Controller: `app/Http/Controllers/StatisticsController.php`
- Vy: `resources/views/statistics/index.blade.php` (eller
  `resources/views/statistik.blade.php` för att matcha konvention).
- Återanvänd `Helper::getStatsChartHtml('home')` för 14d-grafen.
- Lägg nya helpers i `Helper.php` (t.ex. `getTopCrimeTypes($days)`,
  `getTopDays($limit)`, `getHourlyPattern()`).
- Sitemap: lägg till `statistik` i sitemap-genereringen.

## Cache-strategi

Matcha befintligt mönster:

- **Response-cache:** `Spatie\ResponseCache` med 15–30 min TTL (liknar andra
  sidor). Hela HTML-responsen cachas i Redis — statistik-sidan kommer ha
  samma innehåll för alla besökare, perfekt kandidat.
- **Data-cache (intern):** `Cache::flexible` med [15 min fresh, 25 min SWR]
  för nya helpers. Skälen i `Helper.php` rad 22–23 gäller även här — SWR
  ger snabb respons även när cache gått ut.
- **Rekord-data (topp-dagar)** — 24h TTL, ändras långsamt.
- **Purge:** Samma mekanism som övriga sidor — response-cachen rensas via
  `responsecache:clear` vid deploy (eller automatiskt i AUTORUN).

Uppskattad cache-kostnad: ~5–10 Redis-nycklar à <50 KB = försumbar belastning.
Data-queries kör sällan (cache-miss var 15 min i värsta fall).

## Risker

- **Trafik-tapp från startsidan:** Rutan kan vara uppskattad av återbesökare
  som snabbt vill se aktivitetsnivå. Minskad synlighet kan sänka sidvisningar
  på `/statistik` jämfört med dagens inbäddning. Mitigeras genom CTA-länk på
  startsidan (se öppna frågor).
- **SEO:** Ny sida rankar inte över en natt. Kräver intern-länkning från
  start/footer + ev. från `/lan/*`-sidorna.
- **Underhållsskuld:** Ny controller + vy = mer yta. Håll sidan minimal i v1.
- **Charts.css-limit:** Snygga kombografer (t.ex. stacked per län) kan bli
  klumpiga i charts.css. Om ambitionsnivån ökar kan Chart.js behövas →
  JS-bundle växer.
- **Cache-stampede vid deploy:** Om response-cache töms och sidan är tung att
  bygga (många queries) kan första request bli långsam. `Cache::flexible`
  på underliggande data mildrar detta.

## Fördelar

- Renare startsida — mer fokus på karta och senaste händelser (den primära
  produkten).
- Evergreen SEO-innehåll — "brottsstatistik", "antal brott i sverige", län-
  jämförelser är värdefulla long-tail queries.
- Ger plats att utveckla statistik-ambitionen utan att trötta ut startsidan.
- Egen URL gör det delbart och länkbart (t.ex. från media/bloggar).
- Kan utökas stegvis utan att röra startsidan igen.

## Öppna frågor

- **Behålla något på startsidan?** Alternativ:
  - (a) Ta bort helt. Renaste.
  - (b) Behåll rutan men förminska till en "mini"-variant + länk till
    `/statistik` för full vy.
  - (c) Ersätt med en one-liner: "Senaste dygnet: N händelser · se all
    statistik →". Minimal yta, behåller dynamik.
  - **Förslag:** alternativ (c). Värdefull mikrostatistik kvar + tydlig CTA.
- **Footer eller nav?** Statistik passar bäst i footer + ev. i main-nav under
  "Utforska". Huvudnav är redan fullpackad.
- **Per-län grid i v1 eller v2?** 21 små grafer ger visuell punch men kan
  tynga sidan. Förslag: v1 = topplista (tabell), v2 = grafer.
- **URL-slug:** `/statistik` (svenskt, matchar projektets övriga sluggar som
  `/handelser`, `/sverigekartan`). Bekräfta att inget befintligt route kolliderar.
- **Filter?** Behövs datum-intervallväljare eller är senaste 14/30 dagarna ok
  i v1? Rekommendation: fasta perioder i v1, filter i v2.

## Status / nästa steg

- [ ] Beslut: alternativ a/b/c för startsidan.
- [ ] Godkänn design-outline (sektioner 1–5 som v1-scope, 6–7 som v2).
- [ ] Skapa `StatisticsController` + route `/statistik`.
- [ ] Skapa vy `resources/views/statistik.blade.php`.
- [ ] Parametrisera `Helper::getHomeStats` så `$days` kan skickas in, eller
      skapa `getHomeStats30Days`.
- [ ] Lägg till `getTopCrimeTypes($days)` i `Helper.php` (cache 1h).
- [ ] Lägg till `getTopDays($limit)` i `Helper.php` (cache 24h).
- [ ] Lägg till response-cache-middleware på route (15 min).
- [ ] Uppdatera `start.blade.php` enligt valt alternativ (a/b/c).
- [ ] Lägg länk i footer (`resources/views/layouts/...` eller parts).
- [ ] Uppdatera sitemap-genereringen.
- [ ] Logga i `Brottsplatskartan log.md` när live.
