**Status:** aktiv (klar för implementation — empirisk analys gjord 2026-05-01)
**Senast uppdaterad:** 2026-05-01
**Källa:** Inbox Brottsplatskartan (2026-04-30)

# Todo #53 — Återaktivera presstalesperson-filter

## Sammanfattning

> https://brottsplatskartan.se/vastra-gotalands-lan/ovrigt-vastra-gotalands-lan-501252
> Händelser som denna borde inte komma med, iaf inte synas publikt.

URL:n ovan är en presstalesperson-notis som filtreras till `is_public=false`
av `ContentFilterService::isPressNotice()` — **men anropet är utkommenterat**
i `shouldBePublic()`. Det är fortfarande publikt synligt.

**Empirisk analys (2026-05-01) på lokal DB-kopia (501k events, 328k publika):**

- **180 events** skulle filtreras av nuvarande `isPressNotice()`-mönster (~0,05 % av publika).
- **2 av dessa är falska positiver** (~1,1 % FP-rate) — båda nattsammanfattningar
  med riktig händelsedata där `description` råkar börja med "Presstalesperson i tjänst".
- Den enda regex som skapar problemen är **`/presstalesperson.*tjänst/i`** (för bred).
- Tas det bort → **178 träffar, 0 false positives**. De övriga 5 mönstren
  täcker upp utan FP.

**Slutsats:** ingen andelsbaserad heuristik behövs. Lösningen är att avkommentera
filtret och ta bort 1 regex-rad.

## Bakgrund

I `app/Services/ContentFilterService.php` rad 23–29:

```php
// Presstalesperson-filtret är avaktiverat tills vidare.
// TODO: Återaktivera med bättre logik som bara filtrerar händelser
// som enbart handlar om presstalespersonens tillgänglighet och inte
// innehåller faktisk händelsedata (t.ex. sammanfattningar).
// if ($this->isPressNotice($event)) {
//     return false;
// }
```

Filtret stängdes av för att `/presstalesperson.*tjänst/i` matchar både:

- "Efter klockan 21:45 finns ingen presstalesperson i tjänst" (sann notis)
- "Presstalesperson i tjänst. Sammanfattning natt..." (nattsammanfattning med riktig data)

### Konkreta false positives identifierade

- **#497682** "05 mars 07.00, Sammanfattning natt, Västra Götalands län" —
  body 539 tecken (olaga hot Göteborg + brand Trollhättan).
  Description: "Presstalesperson i tjänst. Sammanfattning natt."
- **#497676** "05 mars 06.45, Sammanfattning natt, Hallands län" —
  body 487 tecken (misshandel Halmstad + rattfylleri Kungsbacka).
  Description: "Presstalesperson i tjänst. Nedan följer en sammanfattning..."

Av 37 803 publika nattsammanfattningar är detta de **enda två** som påverkas —
för att Polisens API råkat skriva exakt den frasen i description just dessa två gånger.

## Förslag

**Fas 1 (1–2 h):**

1. **Aktivera anropet** i `shouldBePublic()` (rad 27–29).
2. **Ta bort regex** `'/presstalesperson.*tjänst/i'` ur `$pressPatterns` (rad 61).
   De övriga 5 mönstren är tillräckligt specifika och täcker fallen.
3. **Kör dry-run** lokalt:
    ```bash
    docker compose exec app php artisan crimeevents:check-publicity --since=365
    ```
    Verifiera ~178 träffar, sticksprov 10 events (alla ska vara rena notiser
    med kort/tom body, inga "Sammanfattning natt"-titlar).
4. **PHPUnit-test** för regression. Test-infrastruktur är i princip tom
   (bara `ExampleTest.php`) — skapa `tests/Unit/Services/ContentFilterServiceTest.php`
   med fixturer för: ren press-notis, Sammanfattning natt med "Presstalesperson
   i tjänst" i description, vanlig händelse.
5. **Deploy + backfill prod:**
    ```bash
    ssh deploy@brottsplatskartan.se 'cd /opt/brottsplatskartan && \
      docker compose exec app php artisan crimeevents:check-publicity --apply --since=365'
    ```
6. **Rensa response-cache** så de gamla URL:erna ger 404:
    ```bash
    docker compose exec app php artisan responsecache:clear
    ```

**Fas 2 (om behov uppstår):** andelsbaserad logik. Inte behövd nu.

## Risker

- **Låg.** 0 FP i empirisk analys efter regex-borttagning. 178 events i prod-snapshot
  som markeras `is_public=false` — global scope döljer dem från alla publika
  vyer/sökningar/sitemap.
- Eventuella nya falska positiver kommer från framtida Polisen-API-formuleringar.
  Mitigering: behåll PHPUnit-test, lägg till nya fixturer om något smyger sig in.
- **`isPhoneNumberInfo()`** är aktiv och fångar pressnummer-info redan — ingen risk
  för dubbel-räkning, `shouldBePublic()` returnerar bara true/false.

## Confidence

**Hög.** Koden finns, kommandot finns, mönstren är empiriskt verifierade.
Implementation 1–2 h, deploy + backfill 30 min.

## Nästa steg

1. ~~Identifiera 5 representativa events~~ — gjort 2026-05-01.
2. Edita `ContentFilterService.php` (avkommentera + ta bort brett mönster).
3. Skapa `tests/Unit/Services/ContentFilterServiceTest.php`.
4. Lokal dry-run + sanity-check.
5. Commit + push → deploy.
6. Backfill prod + cache-clear.
