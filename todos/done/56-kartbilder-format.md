**Status:** klar 2026-04-30 — implementerat som `srcset` med 1x + 2x på alla kartbild-`<img>`, og:image @2x. Format kvar som JPG (WebP-byte hade gjort filen 22 % större utan synbar skärpevinst).
**Senast uppdaterad:** 2026-04-30
**Källa:** Inbox Brottsplatskartan (2026-04-30)

# Todo #56 — Kartbilder: skarpare på retina (DPR-fix via @2x srcset)

## Sammanfattning

> kartbilderna använder jpg och ser lite blurriga/pixliga byta till png?

Inbox-stuben föreslog format-byte (JPG → PNG/WebP). **Mätning visade
att formatet inte var problemet** — den verkliga orsaken var att vi
serverade 1x-pixeltäthet till retina-skärmar. Lösningen blev `srcset`
med 1x + 2x på alla `<img>` + @2x-bild i og:image. Format kvar som JPG.

## Mätning före implementation (2026-04-30)

Test-URL `617x463` med 4-punkts polygon:

| Format                       |         Storlek | Visuell skärpa                                 |
| ---------------------------- | --------------: | ---------------------------------------------- |
| JPG                          |           59 KB | OK                                             |
| WebP (default tileserver-gl) |   72 KB (+22 %) | ≈ JPG, ingen synbar vinst                      |
| PNG                          | 234 KB (+296 %) | Pixel-perfekt men osynbar skillnad vid 617×463 |

`@2x`-versionen däremot:

| Variant                           | Storlek | Visuell skärpa                    |
| --------------------------------- | ------: | --------------------------------- |
| JPG 617×463 (1x)                  |   59 KB | Baseline                          |
| JPG 617×463 @2x (1234×926 native) |  187 KB | **Dramatiskt skarpare på retina** |

Slutsats: format-bytet hade gett oss större filer utan kvalitetsvinst.
DPR/retina-fixen är vad användarens "blurriga/pixliga" faktiskt syftade på.

## Implementation

### `app/Services/StaticMapUrlBuilder.php`

`circleUrl()`, `closeUpUrl()`, `farUrl()` tar nu en `int $scale = 1`-parameter
och lägger till `@2x` i URL-suffixet vid `$scale === 2`. Default-beteendet
oförändrat.

### `app/CrimeEvent.php`

`getStaticImageSrc*()`-metoderna hade redan `$scale`-parameter men slukade
den tyst — nu propageras den till `StaticMapUrlBuilder`.

### Blade-komponenter

Lägger till `srcset="src1x 1x, src2x 2x"` på alla `<img>`-element som
visar kartbild. Browsern auto-väljer rätt density. 1x-användare får 59 KB,
2x-användare får 187 KB.

Ändrade filer:

- `resources/views/components/crimeevent/card.blade.php` — 3 img-tags
- `resources/views/components/crimeevent/list-item.blade.php` — 2 img-tags
- `resources/views/components/events-box.blade.php` — 1 img (timeline-thumbnail)
- `resources/views/parts/atoms/event-map-far.blade.php` — 1 img
- `resources/views/single-event.blade.php` — `og:image` byttes mot @2x (sharing-previews stödjer inte srcset)

### `app/Http/Controllers/ApiController.php`

Inga ändringar — anropen passade redan `$scale = 2` som tidigare swäljs.
Nu honoreras värdet och API-konsumenter får @2x-URL:er automatiskt.

## Inte gjort

- **Format-byte JPG → WebP** — mätning visade att WebP är 22 % större
  vid default tileserver-gl-config utan synbar skärpevinst. Skippas.
  Skulle kunna omprövas om tileserver-gl-config tweaks (lägre WebP-
  kvalitet) ger mätbar bandbreddsvinst — separat todo i så fall.
- **Schema.org NewsArticle-bilder (1200×675, 800×600, 640×640)** kvar
  som 1x. Schema declarerar dimensionerna och Google's rich result
  förväntar att served pixels matchar. @2x här hade riskerat att
  triggera mismatch-flagga.

## Mätning post-deploy

Efter deploy:

1. Bekräfta i Lighthouse att LCP inte regrederar (kartbild är ofta LCP-
   element). Förväntan: 1x-mobiles oförändrad, 2x-mobiles laddar 187 KB
   istället för 59 KB → liten LCP-bump (kanske +200 ms 4G).
2. Verifiera visuellt på retina-MacBook + iPhone att kartbilderna är
   skarpa.
3. CWV-mätperiod 30 d. Om LCP P75 försämras > 200 ms på mobil → överväg
   att skala 2x-versionen ner till 1.5x (mellanting).

## Relaterat

- #50 (kortare kartbild-URL:er via proxy-route) — nu med 2 URL:er per
  bild blir HTML-tyngd-argumentet starkare. Synergin förtydligad.
