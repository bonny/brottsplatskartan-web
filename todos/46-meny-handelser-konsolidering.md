**Status:** aktiv
**Senast uppdaterad:** 2026-04-29

# Todo #46 — Slå samman Händelser, Senaste och Mest lästa i huvudmenyn

Importerad från GitHub-issue [#76](https://github.com/bonny/brottsplatskartan-web/issues/76).

## Sammanfattning

Huvudmenyn har idag fem toppnivå-poster (Händelser, Senaste, Mest lästa,
Nära, Sverigekartan) som egentligen alla är olika vyer av samma underliggande
data. Förslag: kollapsa till en "Händelser"-toppost med undermeny.

## Bakgrund

Nuvarande meny:

- Händelser
- Senaste
- Mest lästa
- Nära
- Sverigekartan

Föreslagen struktur:

- Händelser
    - Senaste
    - Mest lästa
    - På kartan
    - Nära mig
    - Sök händelser

Föreslagna URL:er:

- `/` startsida
- `/handelser/senaste`
- `/handelser/mest-lasta`
- `/handelser/nara-mig`
- `/handelser/karta`

## Förslag

Behöver designbeslut: dropdown vs egen landningssida `/handelser` med
kort-grid till undervyer? Tänk även på mobil där dropdown är klumpig.

URL-omskrivningar kräver redirects från gamla paths för SEO.

## Risker

- SEO: alla nuvarande URL:er måste 301-redirectas, annars tappar vi indexering
- Mobilmeny blir djupare
- "Sverigekartan" finns inte i förslaget — vart tar den vägen?

## Confidence

låg — kräver design + redirect-strategi innan implementation
