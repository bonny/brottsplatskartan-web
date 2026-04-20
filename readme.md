# Brottsplatskartan

![Deploy till Hetzner](https://github.com/bonny/brottsplatskartan-web/actions/workflows/deploy-hetzner.yml/badge.svg)

En sajt som visar [Polisens händelser](https://brottsplatskartan.se) lite finare och bättre. Fokus på karta.

## Lokal utveckling

```bash
docker compose up -d
```

Sajten finns på <http://brottsplatskartan.test:8350>.

Se [deploy/local-dev.md](deploy/local-dev.md) för full guide.

## Importera data

```bash
docker compose exec app php artisan crimeevents:fetch   # Polishändelser
docker compose exec app php artisan texttv:fetch        # TextTV-nyheter
```

## Uppdatera composer-paket

```bash
docker compose exec -u root app composer update <paketnamn>
```

## Dokumentation

- [AGENTS.md](AGENTS.md) — arkitektur + vanliga kommandon
- [deploy/local-dev.md](deploy/local-dev.md) — lokal Docker-setup
- [deploy/provision.md](deploy/provision.md) — provisionera ny Hetzner-server
- [docs/API.md](docs/API.md) — API-referens

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
