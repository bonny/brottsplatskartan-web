**Status:** aktiv — låg prio, UX-fix
**Senast uppdaterad:** 2026-05-25

# Todo #87 — Datum-gruppering i Tier 1-händelselistan (Idag/Igår/Tidigare denna vecka)

## Sammanfattning

Lägg in visuella rubriker ("Idag", "Igår", "Tidigare denna vecka", "Tidigare denna månad") i händelselistan på Tier 1-städernas startsidor (`/{stad}`). Idag visas 25 events från senaste 30 dagarna sorterade nyast först — men ingen visuell separator mellan dagens och äldre events.

## Bakgrund

Utbruten från **#79 senior-review + SEO-utlåtande 2026-05-25**.

Mätning 2026-05-25 (35 datapunkter, 5 städer × 7d) visade:

- Stockholm/Göteborg: 0 % 0-event-dagar
- Malmö: 14 % 0-event-dagar
- Helsingborg: 14 % 0-event-dagar, 6/7 dagar har **exakt 1 event**
- Uppsala: 29 % 0-event-dagar

Sidan är aldrig tom (30d rullande, 25 events visas) — så soft-404-risken från #79 är låg.
SEO-subagent rekommenderade **behåll "idag"-titeln** (bevisad CTR-vinst per #76) men
föreslog datum-gruppering som "ärligare UX för Helsingborg/Uppsala-typ-dagar utan att
röra det som driver klick i SERP".

## Förslag

I `resources/views/city.blade.php` (eller delvy för händelselistan), gruppera events
före rendering på datum-bucket:

```php
$buckets = [
    'Idag'              => [],
    'Igår'              => [],
    'Tidigare denna vecka' => [],
    'Tidigare denna månad' => [],
];
$today = Carbon::today();
foreach ($events as $event) {
    $d = $event->parsed_date->startOfDay();
    if ($d->equalTo($today))             $buckets['Idag'][] = $event;
    elseif ($d->equalTo($today->copy()->subDay())) $buckets['Igår'][] = $event;
    elseif ($d->gte($today->copy()->subDays(7)))   $buckets['Tidigare denna vecka'][] = $event;
    else                                            $buckets['Tidigare denna månad'][] = $event;
}
```

Rendera en `<h3>`-rubrik per icke-tom bucket. Behåll länkarna till månads-arkivet
nedanför listan som idag.

Edge case: när "Idag" är tom, börja listan med "Igår" — användaren ser direkt att
inget hänt idag utan att sidan känns trasig.

## Risker

- **Schema/SEO-impact:** dela inte upp i flera `<article>`/`<section>` på ett sätt
  som bryter befintlig ItemList-schema. Verifiera med Rich Results test efter deploy.
- **CTR-regression:** inget — title/meta är oförändrat.
- **Mobile:** rubriker tar vertikalt space; testa att above-the-fold inte trycks ner.

## Confidence

**Hög-medel.** UX-vinst är tydlig. Implementation ~20-40 rader Blade+PHP, ingen
ny query. Trolig insats: 1-2h. Lågt värde för Stockholm/Göteborg (de har sällan
0-dagar och har många events per dag), högt för Helsingborg/Uppsala.

## Beroenden

- Ingen — kan göras isolerat.
- Komplementär till **#79** (som SEO-review rekommenderade att avfärda).
