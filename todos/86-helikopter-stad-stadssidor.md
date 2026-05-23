**Status:** aktiv
**Senast uppdaterad:** 2026-05-23

# Todo #86 — /helikopter/{stad} stadssidor

## Sammanfattning

`/helikopter` (1 603 c/mån, pos 6.6) har stor outexploaterad svans i stads-queries: `helikopter över stockholm nu` 597 impr, `helikopter hässelby nu` 235, `helikopter över täby nu` 474, `helikopter upplands väsby idag` 479, `helikopter i haninge idag` 279, `helikopter sollentuna nu` 147, `helikopter jakobsberg nu` 128, `helikopter vallentuna nu` 63, `helikopter över lidingö nu` 52, `helikopter mölndal`.

Bygg `/helikopter/{stad}` enligt samma mönster som /brand/{stad} (todo #84). Förväntad lyft: 300–600 c/mån.

## Bakgrund

- Kommer ur SEO-analysen 2026-05-23 ([[83-tema-sidor-polisinsats-skottlossning]] efterspel).
- Server-side filter på Stockholm/Göteborg/Malmö är redan implementerat i `PlatsController::helicopter` (QW8, deployad 2026-05-23) som inline-sektion på `/helikopter`. Stadssidor är nästa steg — egna URL:er som kan rankas separat.

## Förslag

### Fas 1 — Top-3 städer

`/helikopter/stockholm`, `/helikopter/goteborg`, `/helikopter/malmo` — replikerar inline-bucketing till egna URL:er som kan ranka separat. Förväntad lyft: 100–200 c/mån.

### Fas 2 — Stockholms-förorter

Hässelby, Jakobsberg, Vallentuna, Sollentuna, Lidingö, Täby, Upplands Väsby, Haninge. Stora svans-queries med pos 4–10 idag.

### Fas 3 — Övriga

Mölndal, Östersund, Boden (där polisens helikoptrar är baserade).

## Risker

- Få events per småstad → tom-sida-risk. Fix: använd `last 90 days`-fönster på stadssidor (helikopter-events är glesare än brand).
- Cannibalisering mot huvudsidan `/helikopter` (1 603 c). Mät post-deploy att huvudsidan inte tappar mer än vinsten på stadssidor.

## Confidence

**Medel-hög** — GSC visar tydlig svans men event-frekvens per stad är låg (24 events/30d totalt enligt [[83]] Steg 0). Behöver lång tidsfönster + fallback.

## Beroenden

- Bygger ovanpå QW8 (server-side bucketing) deployad 2026-05-23.
