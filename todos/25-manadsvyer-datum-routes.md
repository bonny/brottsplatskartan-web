**Status:** aktiv (designfas)
**Senast uppdaterad:** 2026-04-26

# Todo #25 — Månadsvyer istället för dagsvyer

## Problem

Datum-routerna (`/handelser/{date}`, `/plats/*/handelser/{date}`,
`/lan/*/handelser/{date}`) genererar ~1M potentiella URL:er. Cache
har redan begränsats (todo #1) men URL-explosionen i sig är ett
SEO/UX-problem:

- **GSC visar att ingen söker proaktivt på specifika datum**
  ("misshandel gällivare 2026" toppade datum-queries med 2 klick)
- **Pageviews/session ~1.3** på `/handelser`-prefixet — folk landar,
  klickar in på ETT event, lämnar
- **Sidor är magra** — bara en lista av dagens händelser, ingen
  översiktskarta eller statistik
- **Indexerbar yta är gigantisk** — Google måste välja vilka av
  ~120 000 datum-URL:er per plats som är värda

## Förslag — månadsvyer

Ersätt dagsvyer med månadsvyer för plats/län-kombinationer:

| Format  | URL-mönster                             | Antal URL:er           |
| ------- | --------------------------------------- | ---------------------- |
| Idag    | `/plats/{plats}/handelser/{date}`       | 350 × 365 × 10 = ~1.3M |
| Förslag | `/plats/{plats}/handelser/{år}/{månad}` | 350 × 12 × 10 ≈ 42 000 |

### Top-level `/handelser/{date}` behålls

Top-level är där 99% av datum-trafiken ligger ("polisen händelser idag"
och liknande). Behåll dagsvyer på `/handelser/{date}`. Endast plats/län-
kombinationerna flyttar till månads-format.

## Innehåll på månadssida

Mer värdefullt innehåll = bättre SEO + UX:

1. **Översiktskarta** överst — Leaflet med alla event för månaden klustrade
2. **Statistik** — "Vanligaste brottstyperna denna månad i Uppsala"
3. **Händelser-list** — som idag men för hela månaden, paginerad
4. **Tidslinje** — datum-grouped i kollapsbara dagblock
5. **Föregående/nästa månad-nav** + "Hoppa till år"-dropdown
6. **Schema.org Dataset/CollectionPage** — fler rich-result-möjligheter

## Migration / 301-strategi

- `/plats/uppsala/handelser/2026-04-25` → 301 till
  `/plats/uppsala/handelser/2026/04` (månaden datumet ligger i)
- Bevarar inkommande länkar + indexerade URL:er
- Google avindexerar dagsvyer över ~3 månader, månadsvyer ärver
  PageRank via 301

## Risker

- **Stort grepp.** Ny route + controller-metod + ny Blade-vy + 301-
  logik + sitemap-uppdatering + cache-invalidering. Inte 1 commit.
- **Ranking-rörelser:** kortsiktigt (2-8 veckor) kan rankings dippa
  medan Google omindexerar. Långsiktigt vinst förväntad.
- **Day-nav på plats/län-sidor:** måste skrivas om till månadshopp
  istället för dagshopp.
- **Cache-strategi:** månadsvyer är värda att cacha aggressivt
  (innehållet ändras inte efter månadens slut). Senaste månad bör
  ha kort TTL.

## Plan / nästa steg

1. **Bekräfta URL-strategi** med ägare (Pär) — `/plats/{plats}/handelser/{år}/{månad}` eller annat format?
2. **Prototype** — bygg månadsvyn för EN plats lokalt, testa UX
3. **Performance-test** — månadsquery på `crime_events` (kräver
   indextest, datum + plats är vanlig kombination)
4. **Implementera 301-redirect** från dagsvy → månadsvy
5. **Uppdatera sitemap** för att lista månader istället för dagar
6. **Day-nav-redesign** — föregående/nästa månad istället för dag
7. **Soak ~30 dagar** på prod, mät SEO-effekt

## Risk-mitigering

- Behåll dagsvys-route i kod (bara 301:ar till månad) i 6 månader
  innan den tas bort helt — back-compat för bokmärken/externa länkar
- Schema.org Markup — använd både CollectionPage + ItemList för
  månadsvyn
- Lägg `<meta name="robots" content="noindex">` på dagsvyn under
  övergångsperioden om vi vill snabbare avindexering

## Status

Designfas. Implementation kräver dedikerad session — för stort för
att rusha. Flagga: prio 1 enligt ägare (SEO + UX är affärskritiskt).
