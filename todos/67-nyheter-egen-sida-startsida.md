**Status:** aktiv
**Senast uppdaterad:** 2026-05-02

# Todo #67 — Nyheter: egen flik/sida och/eller på startsidan

## Sammanfattning

Vi har RSS-grund (#63) och per-plats-aggregering (#64) live, men nyheterna
är idag bara synliga inbäddat på ort-/platsidor. Borde nyheter också ha en
egen samlingsvy (t.ex. `/nyheter` eller meny-flik) och/eller en synlig modul
på startsidan (Krimkartan-känsla, jfr #59)?

## Bakgrund

- #63 deployade RSS-pipelinen 2026-05-01 (29 källor, 90d retention).
- #64 fas 1 deployad samma dag — classify-command + UI på city/plats. 1013
  artiklar, 195 blåljus-träffar, 132 place-news-kopplingar.
- I dag exponeras nyheter bara via plats-/ort-vyer. Användare som kommer in
  på startsidan eller toppmenyn ser dem inte alls.
- #59 ("Vad händer nu"-ruta) är en angränsande idé för startsidan men siktar
  på events, inte nyhetsartiklar.
- #46 (meny-konsolidering) berör övergripande navigation — om vi lägger till
  en nyhetsflik bör det ske i samma meny-pass.

## Förslag

Två spår att utvärdera (kan vara additiva):

1. **Egen sida `/nyheter`** — kronologisk lista över alla blåljus-klassade
   artiklar (med filter på län/plats?). Indexerbar? Bör övervägas mot SEO/
   thin-content-risken (#29). Eventuellt nofollow tills volym/kvalitet
   verifieras.
2. **Modul på startsidan** — kompakt "Senaste nyheter" eller blandat med
   events ("Vad händer nu"-ruta). Bygger på #59-mönstret. Inga nya routes,
   bara extra block på `/`.

Beslutspunkter:

- Ska nyhetsflik finnas i toppmenyn? Beror på #46.
- Indexerbarhet av `/nyheter`-listan — risk för thin/duplikat (källan har
  redan artikeln). Kanonisering till källan?
- Vilken klassning visas? Bara blåljus-träffar, eller bredare?
- Hur hanteras kvaliteten — RSS-källor varierar i relevans.

## Risker

- **SEO-thin-content:** lista som mest pekar utåt → låg dwell, hög bounce,
  riskerar #29:s noindex-arbete.
- **Brand-mismatch:** vi är en karta för polisens händelser, inte en
  nyhetsaggregator — riskerar förvirra användare/Google om vad sajten är.
- **Underhåll:** RSS-källor dör/byter format; sidan kräver kontinuerlig
  klassningskvalitet (mätning kommer från #64 2026-05-15).

## Confidence

låg — kräver beslut om scope (sida vs modul vs båda), indexerbarhet, och
synergi med #46 (meny) och #59 (startsida-modul). Bra att ta efter #64:s
precision-stickprov 2026-05-15 så vi vet om klassningen håller.
