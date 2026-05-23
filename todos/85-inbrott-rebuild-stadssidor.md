**Status:** aktiv
**Senast uppdaterad:** 2026-05-23

# Todo #85 — /inbrott rebuild + /inbrott/{stad} stadssidor

## Sammanfattning

`/inbrott` får bara 22 c/mån trots 70× sämre prestanda än `/helikopter` (1 603 c) och `/brand` (1 568 c) i samma form. SEO-diagnosen (2026-05-23) visade att grundorsaken är **intent-mismatch + content-type-konflikt**: sidan blandar evergreen-rådgivning (grannsamverkan, larm-tips, statistik) med event-aggregat, vilket gör att Google klassar den som "artikel" istället för "live-data".

Quick-wins är redan deployade ([[83-tema-sidor-polisinsats-skottlossning]] sessionen): events visas först på start-sidan, 2017-statistik borta, rel=sponsored + de-dup Verisure på larmlänkar, schema.org tillagd.

Nästa steg: **full rebuild** till samma form som `/brand` + `/inbrott/{stad}` för top-städer.

## Bakgrund

GSC visar:

- Endast `/inbrott/senaste-inbrotten` (event-undersidan) får clicks (15 av 22). Rådgivnings-trädet rankar pos 10+.
- `inbrott stockholm` pos 2.0 (11c) — uppenbart quick win för stadssida.
- Långa svansen: inbrott höllviken, malmö, uppsala, borlänge, bromma, jönköping, södra sandby, töreboda, örebro, danderyd, eskilstuna, halmstad, helsingborg, lund, långedrag.

## Förslag

### Fas 1 — Rebalansera /inbrott till /brand-form

1. Title byts till "Senaste inbrotten i Sverige — karta & lista från Polisen" (likt brand).
2. Lägg in karta-component (parts/month-overview-map).
3. Skrota/flytta `/inbrott/fakta` (innehåll är stalt).
4. Behåll `/inbrott/grannsamverkan`, `/inbrott/drabbad`, `/inbrott/skydda-dig` som evergreen-undersidor.

### Fas 2 — /inbrott/{stad} top-15 städer

Stockholm, Malmö, Uppsala, Höllviken, Borlänge, Bromma, Jönköping, Södra Sandby, Töreboda, Örebro, Danderyd, Eskilstuna, Halmstad, Helsingborg, Lund.

### Fas 3 — Sitemap + internlänkning

Lägg in alla stadssidor i `GenerateSitemap.php`. Internlänkning från stadssidor.

## Risker

- `inbrott stockholm` är polysemantisk (kan betyda allmän nyhet, inte specifik händelse). Verifiera intent-fit.
- Vissa småorter har 0 inbrott på 30 dagar → behöver längre fönster.

## Confidence

**Hög för Fas 1** — direkt bevis från [[83]]-sessionen.
**Hög för Fas 2** — `inbrott stockholm` pos 2 är uppenbart quick win.

## Beroenden

- Bygger ovanpå QW-fixar deployade i [[83]]-sessionen.
- Kan dra nytta av [[79-soft-404-idag-fallback]]-mätning.
