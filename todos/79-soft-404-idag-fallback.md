**Status:** aktiv — utbruten från #76 senior-review (2026-05-13)
**Senast uppdaterad:** 2026-05-13

# Todo #79 — Soft-404-fallback för "idag"-titlar med 0 events

## Sammanfattning

Tier 1-stadssidor har nu `"Polisen händelser <Stad> idag"` i title.
När en stad har 0 events under aktuellt dygn (sker sällan men händer
nattetid för Helsingborg/Uppsala) renderas en sida med tids-modifier
i title men tom huvud-yta — Google's helpful-content-classifier (mars 2024) flaggar det som soft-404.

## Bakgrund

Senior-SEO-review av #76 Fas B identifierade detta som **kritiskare än
brand-suffix**: "Google's helpful-content-classifier flaggar 'thin/empty
pages med tids-modifier i title' extremt hårt sedan mars 2024. Om Malmö
har 0 events 03:00 och Googlebot crawlar då, brännmärks sidan."

Sannolikhet per stad (uppskattning, behöver mätas):

- Stockholm: ~0 % (10×-volym)
- Göteborg: ~0–1 %
- Malmö: ~1–3 %
- Helsingborg: ~5–10 % (nattetid)
- Uppsala: ~5–10 % (nattetid)

Googlebot crawlar enligt loggar mest under svensk daytime → riskytan
är mindre än värsta-fall, men inte noll.

## Förslag

### Steg 1 — Mät faktisk frekvens (1h)

Innan vi bygger fallback, kvantifiera. Logga antal events de senaste
24h per Tier 1-stad i en CLI:

```bash
docker compose exec app php artisan tinker
>>> CrimeEvent::whereCity('Helsingborg')
    ->where('created_at', '>=', now()->subHours(24))
    ->count();
```

Kör för alla 5 städer × 7 dagar (=35 datapunkter). Om alla > 5 events
→ skip-bygg, problemet är teoretiskt. Om någon stad ofta = 0
→ implementera Steg 2.

### Steg 2 — Dynamisk title-fallback (om behövs)

I `CityController::show`, beräkna events-count för dagen. Om 0:

```php
$titleSuffix = $eventCountToday === 0 ? '' : ' idag';
$pageTitle = "Polisen händelser {$displayName}{$titleSuffix} – brott, olyckor och larm";
$description = $eventCountToday === 0
    ? "Polisens senaste händelser i {$displayName} – brott, trafikolyckor och larm. Aggregerat live från Polismyndigheten."
    : "Alla polisens händelser i {$displayName} idag på karta – brott, ...";
```

Title-mall i config blir då template med token (`{stad}{idag-suffix}`)
istället för pre-renderad sträng. Lite mer komplexitet — motivation
beror på Steg 1-mätning.

### Steg 3 — Fallback-content om events-listan är tom

Om listan är tom: visa "Inga händelser i {Stad} de senaste 24 timmarna.
Se senaste 7 dagarna istället: [link]". Aldrig en helt blank yta.

## Risker

- **Premature optimization** om Steg 1 visar att alla städer alltid har
  events. YAGNI per memory.
- **Title-instability** kan förvirra Google's caching av rich-snippets.
  Bara ändra title om vi konsistent har/inte har events under en hel
  cache-TTL (2h).

## Confidence

**Medel-låg.** Senior-review säger problemet är hårt straffat, men
faktisk frekvens av 0-event-dagar är okänd. Steg 1 (mätning) är
hög-confidence och bör köras innan Steg 2/3 byggs.

## Beroenden

- **#76 Fas B** klar — "idag" finns nu i Tier 1 titles.
- Kan köras oberoende av #76 Fas A (cannibalisering) och #80.
