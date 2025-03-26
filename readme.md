# Brottsplatskartan

![Deploy till live](https://github.com/bonny/brottsplatskartan-web/workflows/Deploy%20to%20live/badge.svg)

En sajt som visar [Polisens händelser](https://brottsplatskartan.se) lite finare och bättre. Fokus på karta.

## Lokal utveckling

`./artisan serve`

Och besök sedan [http://localhost:8000](http://localhost:8000).

## Importera data

Kör ett jobb för att var femte minut hämta data från Polisen.

`./artisan schedule:work`

## Uppdatera composer-paket

`composer update <paketnamn> --ignore-platform-req=ext-redis`

## Exempel

Några exempel på sidor som sajten har. Både län och enskilda platser (gator, städer, osv.) finns.

-   [Händelser från Polisen i Stockholm](https://brottsplatskartan.se/plats/stockholm) och i hela [Stockholms län](https://brottsplatskartan.se/lan/Stockholms%20l%C3%A4n).

-   [Händelser från Polisen i Malmö](https://brottsplatskartan.se/plats/Malmö).
-   [Inbrott som hänt nyligen](https://brottsplatskartan.se/inbrott/senaste-inbrotten).

## Om datan

Alla uppgifter hämtas från [Polisens hemsida](https://polisen.se/Aktuellt/RSS/Lokala-RSS-floden/).

## Nyheter

Sidan visar även de senaste och mest lästa text-tv-nyheterna från vår "systersajt" [texttv.nu](https://texttv.nu).

Populära Text TV-sidor:

-   [Sida 100 - Nyheter](https://texttv.nu/100)
-   [Sida 300 - Sport](https://texttv.nu/300)
-   [Sida 377 - Målservice](https://texttv.nu/377)
-   [Sida 700 - Innehåll](https://texttv.nu/700)
