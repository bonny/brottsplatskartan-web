**Status:** aktiv — blockerad på beslut + AdSense-kontoåtkomst
**Senast uppdaterad:** 2026-07-27

# Todo #92 — Annonsplacering: manuella enheter i stället för enbart Auto Ads

Från AdSense-granskningen 2026-07-27. Kräver beslut och åtgärder i
AdSense-kontot som inte kan göras från repot.

## Nuläge

`resources/views/layouts/web.blade.php` laddar `adsbygoogle.js` med
`client=ca-pub-1689239266452655`. Det finns **inte en enda**
`<ins class="adsbygoogle">` i någon template — hela monetiseringen av en
sida som är ~11 000 px hög på mobil är överlämnad till Googles
Auto Ads-algoritm.

Konsekvenser:

- Ingen kontroll över position, format eller CLS.
- Auto Ads är dåliga på att hitta insättningspunkter i innehåll som ligger
  i widget-strukturer — vilket är exakt sajtens struktur.
- Enda observerade placeringen var en reserverad 1440×280 (desktop) /
  375×375 (mobil) ovanför H1.

## Uppmätt

**1 791 px tom sidokolumn på desktop.** Sidebaren spänner y 566→4232
(3 666 px) men sista widgeten (TextTV) slutar på 2 441. Två hela
skärmhöjder död yta i desktopens mest värdefulla annonsposition.

## Förslag i prioritetsordning

1. **In-content mobil** — responsiv enhet efter första och tredje raden i
   `parts/events-heroes.blade.php`. Högst viewability på sajten; det är där
   folk faktiskt läser. Mobil är 74 % av den riktiga trafiken.
2. **Desktop sidebar sticky** — 300×600 med `position: sticky; top: 80px`
   efter `<x-text-tv-box />` i `start.blade.php`.
3. **Mellan sektioner** — en enhet mellan `Mest läst`-widgeten och
   `.cols`-blocket.
4. **Anchor** — behåll, och flytta topp-annonsen dit i stället för att
   reservera 280/375 px i flödet (se #93).

Sätt alltid explicit `min-height` på slotarna så CLS inte straffar oss när
Auto Ads injicerar.

## Blockerat på

- **Slot-ID:n måste skapas i AdSense-kontot** — kan inte göras härifrån.
- Beslut om hur många enheter som är rimligt mot användarupplevelsen.

## Mätbar hypotes

RPM och viewability per placering, samt att CLS inte försämras. Jämför mot
nuvarande Auto Ads-utfall som baslinje innan något ändras.
