**Status:** aktiv
**Senast uppdaterad:** 2026-06-10

# Todo #88 — CityController: dagsvy→månadsvy-301 för Tier 1-städer

## Sammanfattning

`PlatsController` och `LanController` 301:ar `handelser/{date}` →
`handelser/{year}/{month}#{date}` när `Helper::isInMonthlyViewsPilot()` är aktiv
(#25). `CityController` (Tier 1-routerna `/{city}/handelser/{date}`, t.ex.
`/stockholm/handelser/...`) har **ingen motsvarande 301-logik** — dagsvyerna ger
fortfarande 200 i Tier 1-städerna. Resultat: duplikat-innehåll mellan dagsvy och
månadsvy för Stockholm/Göteborg/Malmö/Helsingborg.

Verifierat kvar 2026-06-10 efter full rollout (`MONTHLY_VIEWS_PILOT='all'`):
`/stockholm/handelser/1-juni-2026` → **200** (borde 301:a till
`/stockholm/handelser/2026/06#2026-06-01`).

## Bakgrund

Identifierad som "Öppen punkt" i [#25](done eller aktiv: todos/25-manadsvyer-datum-routes.md).
`'all'`-flaggan löser den INTE — gapet är att CityControllers `day()`-metod
saknar 301-grenen som finns i `LanController::day()` / `PlatsController::day()`
(se `LanController.php` rad ~67–78: `$rawDateArg && !$isToday &&
isInMonthlyViewsPilot($lan)` → `redirect($monthUrl, 301)`).

Inte blockerande — månadsvyerna fungerar och rankar (gate PASS 2026-06-10), men
duplikaten kan späda ranking-equity och är en tunn-sidor-källa (#29-tema).

## Förslag

Spegla 301-logiken från `LanController::day()` in i `CityController::day()`:

1. När `$rawDateArg && !$isToday && isInMonthlyViewsPilot($citySlug)` → 301 till
   `cityMonth`-routen (`/{city}/handelser/{year}/{month}#{Y-m-d}`).
2. Använd `route('cityMonth', [...])` (routen finns redan, `routes/web.php:960`).
3. Verifiera: `/stockholm/handelser/1-juni-2026` → 301 → `…/2026/06#2026-06-01`;
   dagens datum stannar 200; månadsvy-mål 200.
4. `composer analyse` (PHPStan level 5) grön.

Trivial — en `if`-gren + en route-helper, ~10 rader. Spegla exakt befintligt
mönster så beteendet är konsekvent över de tre controllers.

## Risker

- **Anchor/scroll-marginal** — samma `scroll-margin-top`-krav som #25 (summan av
  sticky-nav, cookiebanner och ad-slot). Redan löst för Plats/Lan; CityController
  återanvänder samma månadsvy-vy så ingen ny risk.
- **Cache** — `responsecache:clear` efter deploy så gamla 200-svar på Tier 1-
  dagsvyer inte serveras vidare.

## Confidence

hög — välavgränsad spegling av existerande, testat mönster. Beroende på [[#25]].
