**Status:** aktiv
**Senast uppdaterad:** 2026-07-27

# Todo #91 — `getViewPortSizeAsString()`: viewport-summa 0 ger "veryfar"

Latent bugg hittad 2026-07-27 under planeringen av #90. Inget synligt fel
idag, men en fälla som slår till så fort en händelse utan viewport-fält
får koordinater.

## Buggen

`app/CrimeEvent.php`, `getViewPortSizeAsString()`:

```php
$size = $this->getViewportSize();

switch ($size) {
    case $size > 20:
        $sizeAsString = "veryfar";
        break;
    // ...
}
```

`switch ($size)` jämför `$size` mot **resultatet** av uttrycket
`$size > 20`, alltså mot en boolean. PHP:s `switch` använder lös
jämförelse (`==`), och `0 == false` är sant. En viewport-summa på exakt 0
matchar därför första caset och klassas som `"veryfar"` — trots att 0 är
den minsta möjliga storleken och borde ge `"closest"`.

Verifierat i containern:

```
span=0     summa=0     -> veryfar   (fel, ska vara closest)
span=0.01  summa=0.02  -> closest   (rätt)
span=0.04  summa=0.08  -> street    (rätt)
```

Bara exakt 0 träffas. Alla nollskilda värden faller igenom korrekt,
eftersom t.ex. `0.02 == false` är falskt.

## Omfattning på prod (2026-07-27)

| Mått                                    | Värde          |
| --------------------------------------- | -------------- |
| Totalt antal events                     | 333 460        |
| Viewport-summa exakt 0 (träffar buggen) | 2 165 (0,65 %) |
| ...av dem som har `location_lat` satt   | **0**          |

Ingen av de 2 165 har koordinater. `StaticMapUrlBuilder::circleUrl()`
returnerar tidigt när `location_lat` saknas, så ingen felaktig kartbild
renderas. Den enda nuvarande effekten är CSS-klassen
`Event--distance_veryfar` istället för `Event--distance_closest` på de
raderna.

**Slutsats: noll användarsynlig påverkan idag.** Därför inte inbakad i
#90 — den todon handlar om listformat, inte om precisionslogik.

## Risken

Så fort en av de 2 165 geokodas, eller så fort en ny händelse får
koordinater men lika viewport-gränser (`ne_lat == sw_lat` och
`ne_lng == sw_lng`), får den `veryfar` → ingen radie i
`PRECISION_RADIUS` → i dagens kod fallback till `closeUpUrl()` med en
rektangel över halva Sverige.

Efter #90 blir konsekvensen mildare för thumbnails, eftersom
`thumbRadius(null)` då ger takradien 1 500 m. Men storbilden på
single-event (`density='high'`) får fortfarande rektangeln.

## Föreslagen fix

Ett ord:

```php
switch (true) {
    case $size > 20:
        // ...
}
```

Med test som täcker `summa = 0 → closest` plus de fem övriga nivåerna.
Fixturerna finns redan skrivna i
[`docs/superpowers/plans/2026-07-27-ett-listformat-startsidan.md`](../docs/superpowers/plans/2026-07-27-ett-listformat-startsidan.md)
(Task 3, `StaticMapUrlBuilderThumbTest::event()`) och kan återanvändas.

Kräver `tests/`-harnessen från #90 Task 1, alltså enklast att göra efter
att #90 är merged.

## Beroende

**Blockerad av:** #90 Task 1 (phpunit-harnessen måste köra först).
