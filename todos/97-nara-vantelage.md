**Status:** aktiv
**Senast uppdaterad:** 2026-07-27

# Todo #97 — Väntestadiet på /nara-hitta-plats

`/nara-hitta-plats` är en mellanlandning vars enda uppgift är att köra
`navigator.geolocation.getCurrentPosition()` och sedan redirecta till
`/nara?lat=&lng=` eller `/nara?error=1`. Kommentaren i filen motiverar den
med att "AMP does not allow js".

## Problemet

Under "Vänta, hämtar din position ..." finns ingenting. Sidan har ingen
layout: ingen header, ingen nav, ingen footer, ingen logga. **Det är
sajtens enda sida där en användare kan bli inlåst.**

Värre: `getCurrentPosition()` anropas **utan `timeout`-option**. Svarar
användaren inte på behörighetsdialogen — lägger undan telefonen, tvekar,
har systemdialogen bakom appen — hänger sidan för alltid.

## Vad som INTE är problemet

Fallback-innehåll vid fel finns redan. `geo.blade.php` inkluderar
`parts.mostViewed` och `parts.latestEvents` när `error=1`, och
`/nara?error=1` levererar H1, mest lästa, senast inrapporterade samt
läns- och stadslistor — 774 länkar uppmätt på prod. GPS som **misslyckas**
är alltså redan hanterat. Gapet är väntan, inte felvägen.

## Beslut

1. **Sajtens header, inte hela layouten.** Sidan förblir fristående men
   inkluderar `parts.siteheader` (72 rader ren markup, noll script, noll
   includes) och `styles.css`. `layouts.web` drar in CMP, annonser och
   Leaflet — meningslöst på en sida som redirectar efter någon sekund, och
   CMP-dialogen skulle lägga sig över GPS-prompten.
2. **Timeout på geolokaliseringen:**
   `{ timeout: 8000, maximumAge: 60000, enableHighAccuracy: false }`.
   Timeouten fyrar felcallbacken med kod 3 → `/nara?error=1` i stället för
   att hänga. `maximumAge` gör återbesök omedelbara. Hög precision behövs
   inte — koordinaten avrundas ändå till två decimaler.
   Åtta sekunder: en fix tar typiskt 1–3 s när behörighet finns. Den långa
   svansen är behörighetsdialogen, som går i användarens takt och täcks av
   punkt 3.
3. **Synlig utväg:** "Tar det för lång tid? Visa de senaste händelserna" →
   `/nara?error=1`. Den som tvekar behöver aldrig vänta ut timeouten.
   Laddtexten får `role="status"` så skärmläsare annonserar den.

## Följdfix i samma svep

`parts/latestEvents.blade.php` renderar fortfarande `<amp-carousel>`. AMP
är borta ur projektet, så elementet är okänt för webbläsaren och får
`display: inline` utan karusell-beteende. Uppmätt på prod: **3 642 px hög
med 20 barn** — sektionen "Senast inrapporterade händelserna" är en
ostylad stapel på exakt den sida vi nu skickar folk till.

Byts till `<x-crimeevent.list-item detailed />` i en `ul.widget__listItems`,
alltså samma mönster som `parts/mostViewed.blade.php` redan använder och
samma format som #90 etablerade.

Dessutom: `Helper::getLatestEvents(int $count = 5)` ignorerar sitt `$count`
och returnerar alltid 20, och cache-nyckeln saknar `$count`. Det är
förklaringen till de 20 barnen. Enda anroparen är `GeoController:104`, så
den kan rättas utan risk.

## Mätbar effekt

- Väntan blir bunden till 8 s i stället för obegränsad.
- Sidan får en väg ut (logga + nav + explicit länk).
- `/nara?error=1` blir kortare och läsbar när amp-stapeln försvinner.
