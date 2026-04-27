**Status:** klar 2026-04-27 — implementerat + verifierat lokalt
**Senast uppdaterad:** 2026-04-27

# Todo #35 — 301:a `/lan/Uppsala län` → `/uppsala`

## Problem

`https://brottsplatskartan.se/lan/Uppsala%20l%C3%A4n` returnerar 200 OK
(visar län-vyn) istället för att 301:a vidare till `/uppsala`.

Stockholm har redan denna redirect och resultatet var bra för
SEO + besök:

```php
// app/Http/Middleware/CityRedirectMiddleware.php:33-37
'lan/stockholm'           => 'stockholm',
'lan/stockholms-lan'      => 'stockholm',
'lan/Stockholms län'      => 'stockholm',
'lan/stockholms%20lan'    => 'stockholm',
'lan/Stockholm%20County'  => 'stockholm',
```

Uppsala län är samma dynamik som Stockholms län:
- Uppsala stad är dominant kommun (~170k av ~390k läns-invånare = ~43 %)
- Län-sidan och stad-sidan konkurrerar om samma queries
  ("polishändelser uppsala", "blåljus uppsala")
- Två tunna sidor som splittar SEO-equity → konsolidera till en

## Vilka län qualificerar för samma mönster?

Bara Uppsala. De andra Tier 1-städerna har län där en redirect
vore vilseledande:

| Stad        | Län                  | Stad-andel | Redirect rätt? |
| ----------- | -------------------- | ---------- | -------------- |
| Stockholm   | Stockholms län       | ~42 %      | ✅ klart        |
| Uppsala     | Uppsala län          | ~43 %      | ✅ #35 fixar    |
| Malmö       | Skåne län            | ~21 %      | ❌ Skåne har Helsingborg/Lund/Kristianstad |
| Göteborg    | Västra Götalands län | ~33 %      | ❌ VG har Borås/Trollhättan/Skövde |
| Helsingborg | Skåne län            | ~10 %      | ❌ Helsingborg är inte dominant i Skåne |

## Implementation

Två rader i `CityRedirectMiddleware::REDIRECTS`:

```php
// Uppsala län (todo #35)
'lan/uppsala-lan'     => 'uppsala',
'lan/Uppsala län'     => 'uppsala',
'lan/uppsala%20lan'   => 'uppsala',
```

## Risker

| Risk                                | Mitigering                                  |
| ----------------------------------- | ------------------------------------------- |
| Existing SEO-equity på län-sidan    | 301 bevarar equity (~80 % förflyttas)       |
| Användare som hade län-bookmark     | 301 är transparent i webbläsaren            |
| Test-coverage bryts                 | Kör `composer test` om suite finns         |

## Verifiering efter deploy

```bash
curl -sIL "https://brottsplatskartan.se/lan/Uppsala%20l%C3%A4n"
# Förväntat: 301 → /uppsala → 200
```

GSC: mät impressions/klick på `/uppsala` 4v post-deploy mot
föregående 4v.

## Confidence

**Hög.** Direkt återanvändning av Stockholm-mönstret som bevisligen
fungerade. Trivial implementation, låg risk.
