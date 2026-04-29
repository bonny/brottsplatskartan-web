**Status:** aktiv
**Senast uppdaterad:** 2026-04-29

# Todo #47 — Slå ihop stad-URLs (plats vs plats+län vs län/kommun)

Importerad från GitHub-issue [#68](https://github.com/bonny/brottsplatskartan-web/issues/68).

## Sammanfattning

En stad har idag flera nästan-identiska URL:er. Otydligt vilken som är
primär och leder till duplikatinnehåll och utspätt SEO-värde.

## Bakgrund

**Linköping**:

- `/plats/linköping-östergötlands-län`
- `/plats/linköping`

**Stockholm**:

- `/plats/stockholm`
- `/plats/stockholm-stockholms-län`
- `/lan/Stockholms län`

Plus event-URL:er som innehåller stadsnamn (`/stockholms-lan/...246754`).

## Förslag

Definiera kanonisk URL per stad och 301-redirecta resten dit. Viktig sida
med otydligt fokus — kandidat för Tier 1-städernas redirect-mönster
(jfr #35 Uppsala-redirect).

Rangordning kanoniska URL:er:

1. `/{city}` (Tier 1 — malmö, göteborg, helsingborg, uppsala redan klart i #24)
2. För övriga: bestäm `/plats/{slug}` ELLER `/plats/{slug}-{lan}` som primär

Relaterat: #23 (case-redirect på `/plats/{plats}`), #35 (Uppsala-mönstret).

## Risker

- Måste karta nuvarande inlänkar och GSC-trafik per variant innan redirect
- Risk att förlora rankings tillfälligt vid stora redirect-svep

## Confidence

medel — mönstret är etablerat (#23, #35), men scope (alla städer) gör det
till större jobb än Uppsala-piloten
