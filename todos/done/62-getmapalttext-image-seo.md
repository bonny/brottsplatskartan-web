**Status:** klar 2026-05-01 — `getMapAltText($variant)` skriver om alt med event-typ + plats + datum (close) eller "Översiktskarta över Sverige…" (far). 4 blade-callers uppdaterade. PHPStan OK. Mätperiod 60d post-deploy mot baseline (image position ~45, CTR ~0.26 %).
**Senast uppdaterad:** 2026-05-01

# Todo #62 — Förbättra `getMapAltText()` för image-search-SEO

## Sammanfattning

`getMapAltText()` returnerar idag en generisk text som dessutom ljuger:
"Karta som med röd fyrkant ramar in {location}". Vi har bytt till
cirklar (#20), och alt-texten saknar event-typ och datum — de starkaste
SEO-signalerna för image search. Förbättra alt-text → större vinst för
image-CTR än vad slug i URL:n skulle ge (avfärdat i #55).

## Bakgrund

Nuvarande implementation (`app/CrimeEvent.php:1423`):

```php
public function getMapAltText(): string {
    return sprintf(
        'Karta som med röd fyrkant ramar in %1$s',
        $this->getLocationString()
    );
}
```

Två problem:

1. **Lögn:** "röd fyrkant" — vi ritar cirklar nu (#20).
2. **Tunn signal:** saknar event-typ ("brand", "trafikolycka") och
   datum, som är de specifika orden som driver image-search-trafik.

GSC-data 90d (2026-01-31 → 2026-04-30):

| Source | Clicks | Impressions | Avg position |
| ------ | ------ | ----------- | ------------ |
| WEB    | ~143k  | ~2.6 M      | ~10          |
| IMAGE  | ~470   | ~180 000    | ~45          |

Image-search-impressions har **dubblats** på 90d (~1 400/dag → ~3 000/dag),
sannolikt bieffekt av #30 (CWV) + #32 (schema). Position fast ~45 →
det finns plats att flytta upp om vi förbättrar relevanssignaler.

Top image-queries är alla event-specifika (`ida falkenberg försvunnen`,
`explosion älgö`, `ras i rödbo`). Sannolik största vinst: alt-text som
matchar dessa queries.

## Förslag

Ny alt-text-mall (close-bild):

```
"Karta över {händelsetyp} i {ort}, {kommun}, {datum}"
```

Exempel:

- `"Karta över brand i Hersby, Lidingö, 27 april 2026"`
- `"Karta över försvunnen person i Falkenberg, Hallands län, 15 februari 2026"`
- `"Karta över trafikolycka vid Klarebergsmotet, Göteborg, 20 mars 2026"`

För far-bild (översikt):

```
"Översiktskarta över Sverige som visar var {händelsetyp} inträffade i {kommun}"
```

Implementation:

```php
public function getMapAltText(string $variant = 'close'): string
{
    $type = $this->parsed_title ?? 'händelse';
    $location = $this->getLocationString();
    $date = $this->getParsedDateFormattedSwedish(); // "27 april 2026"

    if ($variant === 'far') {
        return "Översiktskarta över Sverige som visar var {$type} inträffade i {$location}";
    }

    return "Karta över {$type} i {$location}, {$date}";
}
```

Blade-callers (`card.blade.php`, `event-map-far.blade.php`,
`list-item.blade.php`, `events-box.blade.php`) får ny `$variant`-param
där det är en far-bild. Idag används samma alt för båda.

## Vinster

- **Image-search-position lyft:** ~45 → kanske ~25–30 om relevanssignal
  matchar queries bättre. Realistisk CTR-effekt: ~30–80 % lyft på image
  clicks.
- **Räkneexempel:** 470 clicks/90d × 1.5 = +235 clicks/90d. Marginellt i
  totalkvoten (~50k clicks/månad totalt) men gratis vinst.
- **Tillgänglighet (a11y):** skärmläsare får faktisk information om
  bilden — inte bara "röd fyrkant" som inte ens stämmer.
- **Ingen lögn-text** ("röd fyrkant" → faktisk beskrivning).

## Risker

- **Cache-invalidation av HTML:** alt-text är inbäddad i blade →
  Spatie Response Cache måste rensas vid deploy. Standard sweep, gör i
  samma deploy-fönster.
- **Lång alt-text:** Google rekommenderar max ~125 tecken. Räkna:
  `"Karta över försvunnen person i Falkenberg, Hallands län, 15 februari 2026"` =
  ~75 tecken. OK för längre platser. Brace-fall om location_string blir
  längre än 50 tecken — trunca eller skippa kommun.
- **Datum-format:** `getParsedDateFormattedSwedish()` finns inte säkert
  — verifiera/skapa. Format: "27 april 2026" (ej ISO).

## Confidence

**Medel.** Image-search-impact är trolig men osäker — Google viktar alt
högt men vi rankar redan på position 45 vilket är konkurrensutsatt
djupt. Implementation är trivial (~30 LOC).

## Beroenden

Inga hårda. Kan köras parallellt med #55 och #61.

## Mätning

GSC-jämförelse 30d före vs 30d efter deploy. Metric: position +
CTR på image-search för top-queries (`försvunnen person …`,
`brand …`, `explosion …`).

Anchor-baseline (att jämföra mot, 90d till 2026-04-30):

- Image clicks: ~470
- Image impressions: ~180 000
- Image CTR: ~0.26 %
- Image position: ~45

Mål: position < 35, CTR > 0.4 % efter 60d post-deploy.

## Nästa steg

1. Verifiera/skapa `getParsedDateFormattedSwedish()` (sv-locale,
   "27 april 2026"-format).
2. Refaktorera `getMapAltText($variant = 'close')`.
3. Uppdatera 4 blade-callers med rätt `$variant`.
4. Deploy + Spatie response-cache rensning.
5. GSC-mätperiod 60d, jämför mot baseline ovan.
