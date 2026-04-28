**Status:** aktiv
**Senast uppdaterad:** 2026-04-28

# Todo #43 — Designbuggar på Tier 1-månadsvy

## Sammanfattning

Två konkreta designproblem på `/<stad>/handelser/{år}/{månad}` som
syns på prod, t.ex. https://brottsplatskartan.se/uppsala/handelser/2026/04:

1. **Ful grå ruta** runt "Snabba fakta" och "Hoppa till dag"
2. **Brutna ikoner** på översiktskartan

## Bakgrund

### 1. Grå ruta runt Snabba fakta + Hoppa till dag

Element: `<div class="Introtext Introtext--monthFacts">` och
`<nav class="MonthToc">` i `single-plats-month.blade.php`.

Sannolik orsak: `Introtext`-klassen styler dem som info-rutor med grå
bakgrund, men efter designsystem-konsolideringen 2026-04-28 (widget-
mönster + DataTable + RankedList + TypeBars) sticker det ut som
inkonsekvent. Andra sektioner använder `widget`-stilen (vit bg, gul
accent-border).

### 2. Brutna ikoner i kartan

Element: `parts/month-overview-map.blade.php` — Leaflet-karta med markers.

Sannolik orsak: Leaflet's default marker-ikoner går via en CSS-URL
som kanske inte längre serveras (Webpack-config? CDN-byte? Asset-
bundling-ändring?). Vanlig orsak: `L.Icon.Default.imagePath` är inte
konfigurerad så Leaflet letar efter `marker-icon.png` i fel sökväg.

## Förslag

### 1. Lös grå ruta

Två val:

**A) Konvertera till widget-mönster** — `<section class="widget">` +
`widget__title` för "Snabba fakta", `MonthToc` blir en kompakt
intro-rad utan rutbakgrund. Konsistens med resten av sidan.

**B) Behåll Introtext men matcha designsystemet** — vit bakgrund,
gul accent-border vänster (samma som widgets). Mindre invasiv.

Förslag: A. Sammanfattningen som visar antalet händelser kan flyttas
in i `monthly-summary`-komponenten (när AI-sammanfattning finns) eller
till en `widget` med `widget__title="📊 Statistik för månaden"`.

### 2. Fixa kartans ikoner

Inspekteras live på sidan:

```bash
curl -sI https://brottsplatskartan.se/leaflet/images/marker-icon.png
# eller var än Leaflet hämtar dem
```

Sannolikt fix:

```js
delete L.Icon.Default.prototype._getIconUrl;
L.Icon.Default.mergeOptions({
    iconRetinaUrl: '/images/leaflet/marker-icon-2x.png',
    iconUrl: '/images/leaflet/marker-icon.png',
    shadowUrl: '/images/leaflet/marker-shadow.png',
});
```

Eller kontrollera att Vite-bundling kopierar leaflet-assets till
`public/`. Kan ha brutits vid någon recent assets-ändring.

## Risker

- **Snabba fakta**-omgöring kan påverka FAQ/Dataset-schema som ligger
  inbäddat (synlig FAQ för AI Overviews-citation enligt kommentaren
  i `single-plats-month.blade.php`). Måste verifieras att schema-
  payloaden behålls även om visuella designen byts ut
- **Marker-ikoner** kan vara cachat hos användare — hard-reload-test
  efter fix krävs

## Confidence

Hög — båda är väldefinierade buggar. ~2-3h totalt.

## Beroenden

- Synergier med #27 (designsystem-konsolidering) — vi har redan
  widget/RankedList/TypeBars-mönster. Den här todon förlänger
  konsolideringen till månadsvyn också
