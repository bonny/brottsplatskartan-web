**Status:** aktiv
**Senast uppdaterad:** 2026-04-24

# Todo #19 — /mest-last: filtrera bort gamla händelser

## Problem

Sidan `/mest-last` (`MestLastController@index`) visar "Mest lästa
händelserna per dag" senaste 7 dagarna. Listan innehåller ibland
väldigt gamla events — en händelse från t.ex. 2021 kan fortfarande
få visningar idag och hamna i toppen för "Mest lästa 23 april 2026".

Det är missvisande: besökaren förväntar sig **färska** händelser under
rubriken "Mest lästa [datum]", inte gamla arkiv-events som råkar
dra trafik.

## Nuläge (verifierat mot kod)

`app/Helper.php:1055-1102` — `getMostViewedEvents($date, $limit)`:

- Filtrerar på `crime_views.created_at` (visnings-datum) inom ett
  dygn.
- **Inget** filter på event-ålder (`crime_events.created_at` /
  `parsed_date`).
- Event JOIN:as via eager load men utan WHERE-filter på eventets ålder.

`app/Helper.php:1112-1146` — `getMostViewedEventsRecently($minutes, $limit)`:

- Samma logik men senaste N minuter av visningar.
- Används för "Mest lästa nyligen"-boxen ovanför per-dag-listorna.
- Samma problem: gamla events dyker upp om de visas nu.

## Åtgärd

Lägg till WHERE-filter på eventets ålder i båda helper-metoderna:

```php
// I getMostViewedEvents, efter groupBy:
->whereHas('crimeEvent', function ($q) {
    $q->where('created_at', '>=', Carbon::now()->subDays(3));
})
```

Eller enklare: JOIN mot `crime_events` och filtrera där
(billigare än `whereHas`-subquery).

### Tröskel

- **Förslag: 3 dagar.** Rimligt fönster för "färska" nyheter utan att
  tomma listor uppstår vid långsamma dagar.
- Alternativt: 1 dag = strikt "dagsfärskt", men risk för tomma listor
  nattetid.
- Gör tröskeln till konstant/config så den är lätt att justera.

### Tom-dag-hantering

`MestLastController` har redan `$days->reject(empty)` (rad 53) — så
dagar utan träffar försvinner automatiskt. Bra.

Om filtret gör att **alla** 7 dagar blir tomma: fallback till att visa
"Mest lästa nyligen"-boxen ensam. Redan i layouten.

## Risker

- **Tomma listor** om få färska events finns kombinerat med få
  visningar. Mitigering: visa ändå "Mest lästa nyligen" som fallback.
- **Cache-invalidation:** cache-keys (`getMostViewedEvents:V1:D...`)
  behöver bumpas till V2 så gamla cachade listor (med gamla events)
  rensas. Alternativt bara vänta ut TTL (27 min).
- **Query-prestanda:** extra WHERE på `crime_events.created_at` är
  indexerat — billigt.

## Öppna frågor

- Ska `getMostViewedEventsRecently` ha samma 3-dagars-filter eller
  strängare (1 dag)? Den boxen heter ju "nyligen".
- Vill vi exponera tröskeln som query-param (`?days=7` osv)? Nej,
  overkill — hårdkoda.

## Status / nästa steg

1. Lägg till event-ålder-filter i båda helper-metoderna
2. Bumpa cache-version (V1 → V2)
3. Verifiera lokalt att listan inte längre innehåller gamla events
4. Deploy
