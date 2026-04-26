**Status:** klar 2026-04-26
**Senast uppdaterad:** 2026-04-26

# Todo #22 — Fixa intern länk till `/plats/stockholm`

## Utfört

`overview-helicopter.blade.php`-länkarna pekar nu på `route('city', ['city' => 'stockholm'])` istället för hardkodad `/plats/stockholm`.
Samtidigt: göteborg + malmö-länkarna i samma rad pekar nu på sina nya
`/{city}`-sidor (#24) — bara östersund och boden ligger kvar på
`/plats/`-format eftersom de inte är Tier 1.

## Problem

`resources/views/overview-helicopter.blade.php:60` har hardkodad länk
till `/plats/stockholm` som 301:ar till `/stockholm` via
`StockholmRedirectMiddleware`. Internt borde vi peka direkt på target.

## Åtgärd

Byt `/plats/stockholm` → `/stockholm` i overview-helicopter.blade.php.

Övriga städer (göteborg, malmö, östersund, boden) i samma rad har
fortfarande bara `/plats/`-format eftersom de inte har dedikerade
city-sidor (täcks av todo #24).

## Risk

Ingen — pekar bara direkt på target istället för via redirect.

## Status

Trivial 1-radersfix.
