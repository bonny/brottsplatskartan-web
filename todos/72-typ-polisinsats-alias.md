**Status:** implementerad lokalt 2026-05-13 — väntar deploy + 30d GSC-mätning
**Senast uppdaterad:** 2026-05-13 (implementation klar, rökstest ✓, PHPStan ✓)
**Källa:** #52 GSC-monitor — åtgärd D

# Todo #72 — Egen `/typ/polisinsats` (alias för Polisinsats/kommendering)

## Sammanfattning

GSC-baseline 2026-04-30 (#52): queryn **"polisinsats"** har 5 520 imp/90d
på pos 9.3 men landar fel (eller 404) eftersom det inte finns någon sida
som matchar exakt slug `polisinsats`. Potential ~790 extra klick/90d om
queryn flyttas till topp-3.

## Bakgrund

- `/typ/{typ}` (`routes/web.php:217`) är dynamisk — matchar mot
  `crime_events.parsed_title` exakt.
- I DB finns **"Polisinsats/kommendering"** med 688 events. Inget event
  har `parsed_title = "polisinsats"` ensamt.
- `/typ/polisinsats` → **404 idag**.
- `/typ/Polisinsats/kommendering` funkar men URL-formen är ful pga
  slashen (URL-encodas till `%2F`).
- Routen har redan ett mönster för aliasing — `inbrottSlugs` och
  `brand`-slugs `redirect()` till dedikerade routes (`routes/web.php:223–244`).

## Förslag

### Steg 0 — Scope-koll innan implementation

Kör innan deploy så vi vet om alias-arrayen ska designas för flera entries direkt:

```sql
SELECT parsed_title, COUNT(*) AS n
FROM crime_events
WHERE parsed_title LIKE '%/%'
GROUP BY parsed_title
ORDER BY n DESC;
```

Om bara `Polisinsats/kommendering` är värt något → fortsätt enligt nedan.
Om fler slash-titles har volym → bygg ut alias-mappen direkt och kör en
gemensam GSC-mätning.

### Steg 1 — Alias polisinsats → Polisinsats/kommendering (mikrojobb, ~30 min)

Lägg till i `/typ/{typ}`-routen, **före** parsed_title-query, med separat
canonical-slug så vi kan styra både query och URL-form korrekt:

```php
// Slash-aliases: ren slug i URL, slash-värdet i DB
$typeAliases = [
    'polisinsats' => 'Polisinsats/kommendering',
];

$slug = mb_strtolower($typ);
$canonicalSlug = $slug;            // används till canonical + breadcrumb-länk
$displayTitle = $typ;              // visningsnamn i breadcrumb / H1

if (isset($typeAliases[$slug])) {
    $typ = $typeAliases[$slug];    // DB-query använder fulla värdet
    $displayTitle = 'Polisinsatser'; // snyggt visningsnamn
}
```

Använd `$canonicalSlug` i `route("typeSingle", …)` för breadcrumb-länk
och `<link rel="canonical">` så att canonical alltid pekar på den **rena**
URL:en `/typ/polisinsats` — inte den fula `/typ/Polisinsats%2Fkommendering`.

### Steg 2 — 301 från slash-varianten till ren slug

`/typ/Polisinsats/kommendering` funkar idag (URL-encoded slash) och är
sannolikt redan indexerad. Lägg in **301 redirect** till `/typ/polisinsats`
överst i routen så vi inte serverar två URL:er med samma innehåll
(dup-content-mönstret från #29):

```php
if (mb_strtolower($typ) === 'polisinsats/kommendering') {
    return redirect('/typ/polisinsats', 301);
}
```

### Steg 3 — Breadcrumb + title/meta

`routes/web.php:258` använder `e($typ)` rått i breadcrumben. Efter alias-
reassign blir det `Polisinsats/kommendering` med encodad slash i länken.
Använd `$displayTitle` (visning) och `$canonicalSlug` (länk):

```php
$breadcrumbs->addCrumb(e($displayTitle), route("typeSingle", ["typ" => $canonicalSlug]));
```

Plus explicit title som matchar query-intent: t.ex. "Polisinsatser i
Sverige — senaste händelserna | Brottsplatskartan" (synergi med C/#54).

### Steg 4 — Mätning

- GSC: position + CTR för "polisinsats" 30/60/90d post-deploy.
- Slå ihop med #36-mönstret om vi vill ha gemensam rapport.

### Småfix (passar att fixa när vi ändå rör koden)

`routes/web.php:237` — variabeln för brand-slugs heter fortfarande
`$inbrottSlugs` (copy-paste-rester). Döp om till `$brandSlugs`.

## Risker

- **Liten — befintlig route-logik täcker mönstret.** Aliasing finns redan
  för inbrott/brand (men det är dedikerade routes, inte detta — vårt
  alias stannar på `typeSingle`).
- **Intent-mismatch:** "polisinsats" som query kan vara informationssök
  ("vad är en polisinsats" → Wikipedia) lika gärna som lokal-händelse-sök
  ("polisinsats Malmö idag"). 5.5k imp på pos 9.3 visar att Google iaf
  hittar oss relevanta, men CTR-lyftet kan bli mindre än 790/90d om
  Wikipedia-intent dominerar.
- **AI-titel-rewrite för enskilda events** ("Polisinsats/kommendering"
  är vagt-titel-kandidat) — separat scope, hör hemma i #54 om vi utökar
  `isVagueTitle()` att täcka även detta mönster.

## Confidence

**Hög.** Trivial ändring i en redan etablerad route-logik, ~30 min inkl.
deploy (något mer om Steg 0 visar fler slash-titles att ta in). Risken är
att mätningen ändå inte ger topp-3 — query-intentionen för "polisinsats"
är inte glasklart kommersiell, men 5.5k imp/90d på pos 9.3 indikerar att
Google ändå rankar oss precis utanför.

## Beroenden

- Bygger på #52 GSC-baseline (klar 2026-04-30).
- Synergi med #54 (trafikkontroll-titlar) — om vi utökar
  `isVagueTitle()` att täcka "Polisinsats/kommendering" får
  individuella event-sidor bättre titlar och `/typ/polisinsats`-listan
  bättre snippets.

## Nästa steg

1. Kör Steg 0-query (slash-titles i DB) — avgör om scope ska utökas.
2. Implementera alias-map + canonical-slug i `/typ/{typ}`-routen (Steg 1).
3. Lägg till 301 från slash-varianten till ren slug (Steg 2).
4. Uppdatera breadcrumb + title/meta (Steg 3) — använd `$displayTitle` / `$canonicalSlug`.
5. Småfix: döp om `$inbrottSlugs` → `$brandSlugs` på rad 237.
6. Deploya, vänta GSC-data 30d.
7. Mät och stäng — eller utöka till fler kategorier (`misshandel`-slug-varianter etc.) om mönstret bär.
