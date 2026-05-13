**Status:** aktiv — utbruten från #52 G + #73 fas 2 (2026-05-13)
**Senast uppdaterad:** 2026-05-13

# Todo #77 — "aktuella brott" CTR-fix

## Sammanfattning

Query "aktuella brott" rankar pos 4.1 (borderline topp-3) med
14 898 impressions/90d men bara 0.66 % CTR (≈ 99 klick). Position är OK
men CTR är klart under förväntan för pos 4 — sannolikt en title- eller
meta-fix räcker för att lyfta CTR till ~5 %, vilket ger ~650–750
klick/90d (~7× nuvarande).

Mikrojobb, ~30 min, hög confidence.

## Bakgrund

### Data från #52-baseline (2026-04-30)

- Query: "aktuella brott"
- Impressions/90d: 14 898
- Position: 4.1
- CTR: 0.66 %
- Klick/90d: ~99
- Vinst-uppskattning: ~2 140 clicks/90d om CTR lyfts till topp-3-nivå

### Sannolik orsak till låg CTR

Pos 4 normalt borde ge 6–8 % CTR. Vid 0.66 % har vi ett extremt CTR-glapp.
Hypoteser:

1. **Title matchar inte query** — vår landningssida för "aktuella brott"
   har förmodligen en generisk title som "Brottsplatskartan –
   polishändelser på karta" istället för något som innehåller frasen
   "aktuella brott".
2. **Meta description är dålig eller saknas** — Google plockar då
   slumpvis snippet som inte säljer klicket.
3. **Snippet konkurrerar dåligt** mot topp-3 — sannolikt polisen.se +
   polisinfo.se som har ord-för-ord-matchningar.

### Vilken sida rankar?

Behöver verifieras via `mcp__mcp-gsc__get_search_by_page_query` för
"aktuella brott". Sannolika kandidater:

- `/` (startsida)
- `/mest-last`
- `/typ/`-sida
- `/lan/`-sida

## Förslag

### Steg 1 — Identifiera landningssidan (5 min)

```
mcp__mcp-gsc__get_search_by_page_query
  query: "aktuella brott"
  period: 90d
```

### Steg 2 — Audit:a title + meta på den sidan (5 min)

- Aktuell title
- Aktuell meta description
- Är "aktuella brott" en del av frasen?

### Steg 3 — Optimera (15 min)

Föreslagen mall (anpassas till specifik sida):

```html
<title>Aktuella brott i Sverige – senaste polisanmälningarna på karta</title>
<meta
    name="description"
    content="Se aktuella brott från Polisen i hela Sverige.
  Brott, blåljus och händelser – live på karta från Polismyndigheten."
/>
```

Använd frasen "aktuella brott" i:

- Title (helst i början)
- Meta description (början eller mitten)
- H1 (om sidan har det)

### Steg 4 — Verifiering (5 min)

```bash
curl -s https://brottsplatskartan.se/<sida> | grep -E "<title>|<meta name=\"description\""
```

### Steg 5 — Mätning

Lägg uppföljning 30d post-deploy (~2026-06-13):

- "aktuella brott" CTR — mål ≥ 5 %
- Pos kan röra sig något (CTR-lyft kan trigga Google att lyfta pos)

## Risker

- **Pos kan falla** om vi ändrar title till något Google tycker är
  sämre matchad — låg risk men möjlig.
- **Spillover till andra queries** — om aktuell title används på en
  bred landningssida (t.ex. `/`), kan ändringen påverka många andra
  queries. Audit:a `mcp__mcp-gsc__get_search_by_page_query` på
  _sidan_ (inte query) för att se vilka queries den tar imp på innan
  ändring.
- **Synergi med #76** — om landningssidan är `/`, koordinera med #76
  Fas A (cannibalisation-audit för "polisen händelser") så vi inte
  optimerar mot ett mål och förstör för det andra.

## Confidence

**Hög.** Pos 4 med 0.66 % CTR är ett klassiskt title/meta-glapp.
Fixen är mekanisk, billig, och har bekant ROI-mönster. Risken är låg
om vi verifierar att sidan inte tar massor av andra queries.

## Beroenden

- **Bör inte göras innan #76 Fas A** om landningssidan är `/` (för att
  inte förstöra cannibalisation-audit).
- Inga andra beroenden.

## Nästa steg

1. Kör GSC-uppslag för att hitta landningssidan.
2. Om det är `/` — vänta på #76 Fas A.
3. Om det är annan sida — implementera direkt.
