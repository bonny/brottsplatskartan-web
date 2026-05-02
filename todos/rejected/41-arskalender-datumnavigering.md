**Status:** aktiv
**Senast uppdaterad:** 2026-04-28

# Todo #41 — Datumnavigering som årskalender

## Sammanfattning

Idé: ersätt eller komplettera nuvarande månadsnav (`MonthNav`-länkar
prev/next) med en visuell årskalender — alla 12 månader i ett rutnät,
varje månad klickbar, med liten badge per månad som visar antalet
händelser. Användaren kan snabbt hoppa till valfri månad istället för
att klicka prev N gånger för att nå förra året.

## Bakgrund

Idag finns på Tier 1-månadsvyn (`/<stad>/handelser/{år}/{månad}`):

- `MonthNav--top` och `MonthNav--bottom` med ‹ Föregående / Nästa ›
- `parts/month-archive.blade.php` i högerspalt (lista på senaste månader)

Att navigera 18 månader bakåt = 18 klick. Och man ser inte var
händelse-volymen var hög (intressanta månader att browsa).

## Förslag

Ny komponent: `<x-year-calendar :year="..." :area="..." />` som
renderar 12 rutor i ett rutnät (3-4 kolumner mobil, 4-6 desktop):

```
┌─────┬─────┬─────┬─────┐
│ Jan │ Feb │ Mar │ Apr │
│  84 │  92 │  67 │  ·  │
├─────┼─────┼─────┼─────┤
│ Maj │ Jun │ Jul │ Aug │
└─────┴─────┴─────┴─────┘
```

- Varje cell länkar till `/<stad>/handelser/{år}/{månad}` om månaden
  har events. Tomma månader får en "punkt" i stället för antal.
- Heatmap-färg på bakgrund (mörk = mer events, ljus = färre) — relativ
  per år så det funkar oavsett volym.
- Pre/next-år-pilar runt rutnätet för att bläddra till äldre år.
- Aktuell månad markerad med ram.

Datakälla: en cachad `getMonthlyEventCountsForYear($area, $year)` som
returnerar `[1 => 84, 2 => 92, ...]`. Liknar
`Helper::getDailyEventCountsNearby` men aggregerat per månad. Cache
24h.

## Var visas det

- På `/<stad>/handelser/{år}/{månad}` — ersätter eller kompletterar
  `MonthNav` + `month-archive`-widget
- Kanske också på `/<stad>` startsidan som "alternativ ingång till
  arkivet" tillsammans med AI-månadssammanfattningen

## Risker

- **Volym-data per månad** kräver query — för varje stad/år. Cache 24h
  borde räcka eftersom historiska månader är immutabla
- **Mobil-layout** — 12 rutor i ett rutnät kan bli trångt. Behöver
  testas på smal viewport
- **CWV** — extra DOM-noder under fold påverkar inte LCP men kan
  trycka ner ad-units om vi inte är försiktiga
- **Heatmap-färg-logik** — relativ per år eller absolut? Om absolut
  ser ett glesare år (covid-period?) helt blekt ut

## Confidence

Medel — designidén är solid och välkänd UX-pattern (GitHub contribution
graph, Strava activity calendar). Implementation är ~1 dag. Värdet
beror på hur ofta användare faktiskt vill bläddra många månader bakåt
— bör verifieras mot GA4 (sessions med flera prev-klick i följd) innan
bygge.

## Beroenden

- Bygger på #25 (månadsvyer) och #33 (Tier 1-månadsroutes) som båda är klara
- Komplement till #27 Lager 3 AI-månadssammanfattning — kalendern blir
  ett naturligt sätt att hitta "intressanta" månader att läsa
  sammanfattning för
