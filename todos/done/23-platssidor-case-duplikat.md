**Status:** klar 2026-04-26
**Senast uppdaterad:** 2026-04-26

# Todo #23 — Case-duplikat på platssidor splittrar SEO-värde

## Utfört

- `PlatsController::day()` har case-redirect: versaler → gemener via
  301 till `route('platsSingle' | 'platsDatum')`. Verifierat:
  `/plats/Västerås` → `/plats/västerås` (301).
- `sitefooter.blade.php` ändrad: alla 10 städer i "10 största
  städerna"-listan använde tidigare `Stockholm`, `Malmö`, `Göteborg`
  m.fl. som plats-slug. Nu: Tier 1 pekar på `route('city', …)`,
  övriga (västerås, örebro, linköping, jönköping, norrköping) på
  lowercase-slugs.
- Effekt: bot-crawlare följer fottern och får direkt rätt URL utan
  301-hopp; PageRank konsolideras.

## Problem

GA-data visar att samma stad får trafik på flera URL:er pga case-skillnader:

| Stad        |            lowercase URL |         Capitalized URL | Total |
| ----------- | -----------------------: | ----------------------: | ----: |
| Malmö       |       `/plats/malmö` 621 |      `/plats/Malmö` 145 |   766 |
| Göteborg    |    `/plats/göteborg` 461 |   `/plats/Göteborg` 123 |   584 |
| Helsingborg | `/plats/helsingborg` 308 | `/plats/Helsingborg` 72 |   380 |

Google rankar de två versionerna separat — splittrar PageRank och CTR.
GSC-data confirmar att stora städer rankar position 7-10 trots stora
impression-volymer (1397+ impressions för "polisen händelser malmö").

## Åtgärd

Lägg till lowercase-redirect i `PlatsController::day()` (motsvarande
hur `CityController::show()` redan gör det):

```php
$normalizedSlug = mb_strtolower($plats);
if ($plats !== $normalizedSlug) {
    return redirect()->route('platsSingle', ['plats' => $normalizedSlug], 301);
}
```

## Risk

Låg. Befintliga bokmärken funkar (301 är permanent + cachebar).
Inkommande länkar konsolideras till lowercase-versionen vilket
förbättrar SEO.

## Status

Liten ändring, hög ROI. Implementeras parallellt med #24.
