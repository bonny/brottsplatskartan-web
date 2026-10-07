**Status:** klar 2026-10-07 — widgeten jämförde hela slugen "husum-västernorrlands-län" mot `parsed_title_location`. Ny `Helper::splitPlatsSlugWithLan()` delar slugen, samma urval som månadssidan. Verifierat på prod: Husum okt 2023 visar 4 (`8d18f265`).
**Senast uppdaterad:** 2026-10-07
**Källa:** Inbox Brottsplatskartan (2026-10-04)

# Todo #104 — Månadsnav på platssidor visar 0 händelser

## Sammanfattning

Nav säger 0 men det finns händelser?
![[CleanShot 2026-05-18 at 09.14.07@2x 1.png]]

## Bakgrund

Skärmbilden (2026-05-18) är från
`/plats/husum-västernorrlands-län/handelser/2023/10`. Sidan säger "4
händelser i Husum i Västernorrlands Län under Oktober 2023" och listar dem,
men "Månader"-spalten till höger visar **0** för varje månad, även den
markerade Oktober 2023.

Antingen räknar navet på fel nyckel (plats + län vs bara plats) eller så
läser det en cache/aggregat som inte finns för platssidor.

## Förslag

1. Kontrollera om buggen finns kvar på prod.
2. Hitta komponenten för månadsnavet (jfr #42) och hur den räknar för
   `/plats/`-sidor jämfört med Tier 1-/län-sidor.

## Risker

## Confidence

medel — tydlig bugg, oklart om den redan rättats av senare ändringar.
