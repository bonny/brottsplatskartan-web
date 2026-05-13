**Status:** klar 2026-05-13 — diagnos visade att title/meta redan innehåller båda queries; ingen mekanisk fix att göra. Lågrisk H1-justering skjuts upp till efter mätperiod-stabilisering.
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

## Utfall 2026-05-13

### Steg 1 — landningssida (GSC 90d, query equals "aktuella brott")

| Page                                                    | Clicks | Impressions | CTR           | Position  |
| ------------------------------------------------------- | ------ | ----------- | ------------- | --------- |
| `/`                                                     | 50     | 13 702      | 0.36 %        | 4.6       |
| `/stockholm`                                            | 13     | 3 665       | 0.35 %        | 5.3       |
| `/lan/Örebro%20län` (case)                              | 11+9   | 327+199     | 3.36 / 4.52 % | 5.9 / 4.8 |
| Övriga (`/lan/Uppsala%20län`, `/plats/västerås`, m.fl.) | 1–2    | <100        | –             | –         |

Landningssidan är **startsidan `/`**. Det kolliderar med #76 Fas A
(cannibalisation-audit för "polisen händelser" på samma sida).

### Steg 2 — audit:a title + meta + h1 på `/`

```html
<title>Polisens händelser - aktuella brott & senaste blåljusen</title>
<meta
    name="description"
    content="Se senaste polishändelserna, blåljusen och brotten i hela Sverige.
  Trafikolyckor, inbrott, bränder och larm – per kommun och län, live på karta."
/>
<h1>Polisens händelser i hela Sverige</h1>
```

Introtexten (i `Setting::get('introtext-start')`):

> "Brottsplatskartan visar senaste nytt från Polisen, t.ex. vilka brott
> som skett eller rapporterats idag. **Aktuella brott** och händelser
> hämtas från Polisens RSS-flöden …"

### Slutsats — hypotesen i #77 var fel

Title och meta innehåller **redan** båda fraserna ("polisens händelser"

- "aktuella brott"). Ingen mekanisk word-match-fix finns att göra.

Den låga CTR:n (0.36 % vid pos 4.6, väntat ~6-8 %) måste därför bero på
någon av:

1. **SERP-konkurrens** — pos 4 på en bred informationsquery där topp-3
   (polisen.se / polisinfo.se?) tar nästan alla klick.
2. **Brand-mismatch** — "Brottsplatskartan" signalerar kanske inte
   "aktuell info" lika starkt som polisen.se.
3. **Snippet-utseende** — efter h1 kommer kartan direkt; ingen prosa
   som lockar i Googles preview.

### Möjlig framtida åtgärd (skjuten)

**Lågrisk h1-justering:** byt `<h1>Polisens händelser i hela Sverige</h1>`
till `<h1>Aktuella brott och polishändelser i hela Sverige</h1>` så h1
matchar båda queries. Möjlig marginell CTR-effekt, oklar storlek.

Skjuts upp av två skäl:

1. **För många samtidiga ändringar** — #72/#76/#50 deployade senaste
   veckan; Google måste få stabilisera sig innan vi mäter eller ändrar
   mer på `/`.
2. **#76 Fas A bör avgöra först** vilken sida som ska vara canonical för
   "polisen händelser" — om svaret är att `/` ska äga både queries kan
   h1-fixen göras parallellt med den åtgärden istället för isolerat.

Om CTR fortfarande är < 2 % efter mätperiod-stabilisering — återöppna
som ny todo med scope: h1 + snippet-prosa-injection.
