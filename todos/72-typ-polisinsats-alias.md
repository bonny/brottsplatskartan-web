**Status:** aktiv (utbruten från #52 åtgärd D, 2026-05-13)
**Senast uppdaterad:** 2026-05-13
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

### Steg 1 — Alias polisinsats → Polisinsats/kommendering (mikrojobb, ~30 min)

Lägg till i `/typ/{typ}`-routen, **före** parsed_title-query:

```php
// Alias: "polisinsats" → "Polisinsats/kommendering"
if (mb_strtolower($typ) === 'polisinsats') {
    $typ = 'Polisinsats/kommendering';
}
```

Eller WHERE-utvidgning:

```php
->where(function ($q) use ($typ) {
    $q->where("parsed_title", $typ);
    if (mb_strtolower($typ) === 'polisinsats') {
        $q->orWhere("parsed_title", "Polisinsats/kommendering");
    }
})
```

Första varianten är enklare; brödsmulor och canonical funkar via samma
typ-value.

### Steg 2 — Förbättra title/meta på `/typ/polisinsats` (synergi med C/#54)

`/typ/polisinsats` får default-titel från `single-typ.blade.php`. Hugg en
explicit title som matchar query-intent: t.ex. "Polisinsatser i Sverige —
senaste händelserna | Brottsplatskartan".

### Steg 3 — Mätning

- GSC: position + CTR för "polisinsats" 30/60/90d post-deploy.
- Slå ihop med #36-mönstret om vi vill ha gemensam rapport.

## Risker

- **Liten — befintlig route-logik täcker mönstret.** Aliasing finns redan
  för inbrott/brand.
- **AI-titel-rewrite för enskilda events** ("Polisinsats/kommendering"
  är vagt-titel-kandidat) — separat scope, hör hemma i #54 om vi utökar
  `isVagueTitle()` att täcka även detta mönster.

## Confidence

**Hög.** Trivial ändring i en redan etablerad route-logik, ~30 min inkl.
deploy. Risken är att mätningen ändå inte ger topp-3 — query-intentionen
för "polisinsats" är inte glasklart kommersiell, men 5.5k imp/90d på
pos 9.3 indikerar att Google ändå rankar oss precis utanför.

## Beroenden

- Bygger på #52 GSC-baseline (klar 2026-04-30).
- Synergi med #54 (trafikkontroll-titlar) — om vi utökar
  `isVagueTitle()` att täcka "Polisinsats/kommendering" får
  individuella event-sidor bättre titlar och `/typ/polisinsats`-listan
  bättre snippets.

## Nästa steg

1. Implementera alias i `/typ/{typ}`-routen.
2. Hugg title/meta på `/typ/polisinsats` (single-typ-bladen eller route-data).
3. Deploya, vänta GSC-data 30d.
4. Mät och stäng — eller utöka till fler kategorier (`misshandel`-slug-varianter etc.) om mönstret bär.
