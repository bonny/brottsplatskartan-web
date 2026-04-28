**Status:** klar 2026-04-28 — månadsnav polerad: titel "Månader", listraderna i widget-stil med år-rubrik mellan grupper, "Just nu"-CTA tonad ned till svag gul accent
**Senast uppdaterad:** 2026-04-28

# Todo #42 — Designa om månadsnav i högerspalten

## Sammanfattning

Månadsarkivet i högerspalten på Tier 1-städer (`parts/month-archive.blade.php`)
ser ofärdigt ut — listan är inte stilrent stilad och passar inte in i
sajtens designsystem. Behöver designrunda.

## Bakgrund

`parts/month-archive.blade.php` används i:

- `resources/views/city.blade.php` (`/<stad>` startsida — högerspalt)
- `resources/views/single-plats.blade.php` (`/plats/<plats>`)
- Eventuellt månadsvyer också (?)

Listar senaste N månader som klickbara länkar till
`/<stad>/handelser/{år}/{månad}` (Tier 1) eller motsvarande plats-route.

Användaren har påpekat att den är ful — sannolikt en ostylad `<ul>`
eller en lista som inte använder sajtens widget-mönster konsekvent.

## Förslag (tentativt — bekräfta först)

1. Inspektera nuvarande markup + styling
2. Konvertera till samma `widget` + `widget__title`-mönster som
   resten av högerspalten (search-ruta, brottsstatistik-länk, län-och-städer)
3. Använd `RankedList`-styling om numrering känns relevant, eller
   neutral lista med tydliga klickytor
4. Visa antalet händelser per månad som badge/text till höger om
   månadsnamnet (samma data som #41 årskalender skulle behöva — borde
   återanvändas)
5. Highlight på aktuell månad om man är på en månadsvy

## Risker

- **Cache-invalidering** — om vi visar antal events per månad, måste
  vi cacha + invalidera. Eller acceptera 24h fördröjning på counts
- **Konsistens** — mönstret bör matcha #27 Lager 3-designen (widget,
  RankedList, DataTable, TypeBars) som konsoliderades 2026-04-28

## Confidence

Hög — det är en ren designfråga, ingen ny datafråga. ~2-4h jobb.

## Beroenden

- Synergier med #41 (årskalender) — om vi bygger #41 borde vi besluta
  om #42 ersätts av kalendern eller blir ett komplement
