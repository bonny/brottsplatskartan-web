**Status:** klar 2026-04-26
**Senast uppdaterad:** 2026-04-26

# Todo 1 — Minska URLer och response-cache-påverkan

## Utfört

`shouldCacheRequest()` tillagd i `BrottsplatskartanCacheProfile` —
hybrid-strategi (E): `plats/*/handelser/*` och `lan/*/handelser/*`
cachas bara om datum är inom senaste 30 dagar. Arkivdatum servas live
från DB (queryn är snabb pga datum-index).

### Beslutsunderlag

GA + GSC-analys (2026-04-26) visade:

- **6 553 unika datum-URL:er** (4876 plats + 1677 län) hade trafik
  senaste 30 dagarna
- Varje URL drar 1–13 sessioner — **långa svansen, låg cache-hit-rate**
- Många rankar **top 1–3 i Google** på long-tail-queries
  (t.ex. `/lan/Örebro län/handelser/18-mars-2026` på position 1.6)
- Top 100 drar tillsammans ~700 klick + 7000 impressions/28d

### Slutsats

- Routerna **inte** borttagna (E över D) — har reellt SEO-värde
- `noindex` skippat — skulle döda ~700 klick/månad
- Hybrid-cache: senaste 30 dagar = cache, äldre = live
    - Cap från ~1M potentiella entries till ~10k (351 platser/län × 30 dagar)
    - Arkiv-URL:er servas fortfarande, bara utan cache

Verifierad med PHPStan level 5 (0 errors) + sanity-check att
`/plats/uppsala/handelser/{nytt|arkiv}` båda svarar 200.

Analys och beslutsunderlag inför åtgärd.

## Sammanfattning

Spatie Response Cache (Redis) kan växa okontrollerat eftersom två routes
exponerar en kombination av geografi × datum:

- `/plats/{plats}/handelser/{date}` (route-namn `platsDatum`)
- `/lan/{lan}/handelser/{date}` (route-namn `lanDate`)

Med 330 orter × 2 849 dagar respektive 21 län × 2 849 dagar ger det
teoretiskt ~1 000 000 cache-entries i Redis. I praktiken bara om de
crawlas/besökas — men botar (Google, GPTBot-liknande) hittar dem lätt
via prev/next-länkar i day-nav.

Rekommenderad huvudåtgärd: **exkludera dessa två route-patterns från
response cache** i `BrottsplatskartanCacheProfile::shouldCacheRequest()`
(snabbaste och säkraste åtgärden — ingen SEO-risk, inga trasiga
bokmärken). Därefter utvärdera om URL-mönstren alls ska finnas kvar
(separat beslut, högre risk).

## Nuläge (verifierat mot kod)

### Routes som existerar

`routes/web.php`:

- rad 108: `Route::get('/lan/{lan}/handelser/{date}', [LanController::class, 'day'])->name('lanDate');`
- rad 236–238: `Route::get('/plats/{plats}/handelser/{date}', [PlatsController::class, 'day'])->name('platsDatum');`

Båda routar in till respektive `day()`-metod som återanvänder samma
controller-kod som visningen utan datum. Det vill säga: att stryka
routen tar inte bort controller-logik, bara URL-yta.

### Cache-profil

`app/CacheProfiles/BrottsplatskartanCacheProfile.php` ärver från
`CacheAllSuccessfulGetRequests`. Den överrider bara
`cacheRequestUntil()` (TTL-beslut) och rör aldrig
`shouldCacheRequest()` — så i dagsläget cachas **alla** lyckade
GET-svar oavsett URL. Ingen mekanism finns för att exkludera ett
specifikt mönster.

TTL-logiken för `handelser/*` gäller bara top-level `/handelser/{date}`
(via `$request->is('handelser/*')`) — den matchar inte
`plats/*/handelser/*` eller `lan/*/handelser/*`, så dessa får default
30 minuter. Det gör paradoxalt nog problemet värre: stor bredd och
relativt kort TTL → entries rullar aldrig riktigt ut.

### Config

`config/responsecache.php`: `RESPONSE_CACHE_DRIVER=redis`, ingen
cache-tag satt (`'cache_tag' => ''`). Det innebär att
`responsecache:clear` är ett globalt flush på cache-stores — inte
mönsterselektivt.

### Intern länkning (verifierat via grep)

Länkar till `route("platsDatum")` / `route("lanDate")` skapas från PHP,
aldrig från Blade-templates (grep av `resources/views` gav 0 träffar):

- `app/Helper.php:62` — day-nav för län
- `app/Http/Controllers/PlatsController.php` — day-nav (prev/next) och
  canonical när datum är i URL (raderna 202, 215, 223, 484, 497, 505)
- `app/Http/Controllers/LanController.php` — day-nav och canonical
  (raderna 116, 128, 174)

Alltså: användaren klickar sig in via "prev/next dag"-pilarna på en
plats- eller länsida. När hon står på `/plats/stockholm` utan datum
genereras `platsDatum`-URL:er under huven.

### Sitemap

Ingen XML-sitemap finns (verifierat via grep: inga sitemap-views, inga
Feed/Sitemap-genererande controllers; bara `robots.txt` och
`Route::feeds()` för RSS). Alltså **exponeras inte** `platsDatum` /
`lanDate` explicit för sökmotorer. De indexeras bara om bot följer
länkarna i day-navet.

### Hasher

`app/ResponseCache/CustomRequestHasher.php` strippar redan `t`, `_`,
`nocache`, `timestamp` från query-strängar → ingen query-explosion för
dessa routes. Bra.

## Risker

### Om vi bara exkluderar från cache (rekommenderat första steg)

- Minimal SEO-risk: URL:er finns kvar, svarar precis som innan,
  bara inte cachade → något långsammare för botar som crawlar dem.
- Minimal användarrisk: befintliga bokmärken + inkommande länkar
  fungerar fortfarande.
- Möjligt prestandabortfall: de få sidor som faktiskt besökas upprepat
  (typ "gårdagens händelser i Stockholm") får inte längre 30-min-cache.
  Kan mitigeras med per-route application-cache (`Cache::remember`)
  i controllern.

### Om vi tar bort routerna (större ingrepp)

- Brutna interna länkar i day-navet: `Helper.php:62`, `PlatsController`
  raderna 202/215/484/497, `LanController` raderna 116/128. Måste
  skrivas om att peka på `/handelser/{date}` (global) eller tas bort.
- SEO-tapp: även om URL:erna inte ligger i sitemap så är de
  tredjeparts-indexerade efter 10+ år. Risk för 404 på träffar i
  Google → `noindex` + 301 är säkrare.
- Bokmärken / externa länkar bryts om vi svarar 404.
- Canonical-logiken i både `PlatsController` (rad 222–228) och
  `LanController` (rad 173–180) måste justeras så canonical pekar på
  fallback utan datum.

### Om vi bara sätter `noindex`

- Tar tid innan Google faktiskt avindexerar → cache-problemet kvarstår.
  Måste kombineras med cache-exkludering.

## Fördelar

### Bara cache-exkludering

- Direkt effekt: Redis slutar växa på dessa mönster. Svårast fallet
  (940k + 60k entries) elimineras.
- Reversibelt på 5 sekunder (ta bort en `if`).
- Ingen SEO-påverkan, inga brutna länkar.
- Kan implementeras utan deploy-risk (bara cache-profilen ändras).

### Ta bort URL:erna helt

- Enklare informationsarkitektur: en URL per plats + global datum-URL.
- Mindre duplicate content (för Google räknas `/plats/stockholm`,
  `/plats/stockholm/handelser/idag`, `/handelser/idag` som liknande).
- Mindre yta som AI-crawlers ska slå sig igenom.
- Kan förenkla framtida sitemap-arbete (todo 11).

## Öppna frågor till användaren

1. **Vill vi behålla URL-mönstren alls?** Är det viktigt att en besökare
   kan djuplänka till "Stockholm, 5 juni 2021" specifikt, eller räcker
   det med den globala `/handelser/5-juni-2021`-sidan?
2. **Trafik-data:** finns GA/Search Console-data på hur ofta
   `platsDatum`/`lanDate`-URL:er faktiskt besöks? Om <1% av trafiken →
   enkelt att ta bort helt. (Kopplar till todo 8 — GA MCP.)
3. **Search Console-indexering:** är dessa URL:er faktiskt indexerade
   idag? Rimligt att kolla innan man bestämmer sig för 410/404/301.
4. **Rankar vi på sökningar som matchar dessa URL:er?** T.ex.
   "händelser stockholm 5 juni 2021" — om ja, behåll + cache-exkludera.
   Om nej, ta bort.
5. **Vill vi stoppa bot-crawling** av day-navet samtidigt (via
   `rel=nofollow` på prev/next-pilarna)? Det minskar cache-trycket
   även om URL:erna finns kvar.

## Förbättringsförslag / alternativa lösningar

### A. Minimal (rekommenderad att göra nu)

Lägg till `shouldCacheRequest()` i `BrottsplatskartanCacheProfile`:

```php
public function shouldCacheRequest(Request $request): bool
{
    // Exkludera "plats × datum" och "län × datum"-kombinationerna
    // eftersom de kan generera >1M cache-entries i Redis.
    if ($request->is('plats/*/handelser/*') ||
        $request->is('lan/*/handelser/*')) {
        return false;
    }

    return parent::shouldCacheRequest($request);
}
```

Gör detta + rensa Redis en gång (`php artisan responsecache:clear` +
överväg `redis-cli FLUSHDB` om response-cachen ligger i egen DB).
Mät `DBSIZE` i redis efter 24h för att verifiera.

### B. Lägg till `rel=nofollow` på day-nav-pilar

I `resources/views/parts/*` där `$prevDayLink`/`$nextDayLink` renderas,
lägg till `rel="nofollow"`. Kombinera med (A) — minskar framtida
crawling. Låg risk, ingen UX-påverkan.

### C. Lägg till meta `noindex` på sidorna med datum

I `platsDatum` / `lanDate`-vyerna: om datum ≠ idag, sätt
`<meta name="robots" content="noindex,follow">`. Google slutar
långsamt indexera dem (veckor-månader). Kombinera gärna med (A).

### D. Ta bort routerna + 301 till fallback

```php
// Istället för:
// Route::get('/plats/{plats}/handelser/{date}', ...);
Route::get('/plats/{plats}/handelser/{date}', function ($plats, $date) {
    return redirect()->route('platsSingle', ['plats' => $plats], 301);
});
```

Och motsvarande för lan. Plus:

- Uppdatera `Helper::getLanPrevDaysNavInfo`-konsumenten
  (`LanController:116,128`) och `PlatsController:202,215,484,497` att
  länka till `/handelser/{date}` istället — men det tappar plats-
  filtreringen, så egentligen bör day-navet tas bort från plats-/
  länssidorna helt.
- Uppdatera canonical-logiken i båda controllers.

**Viktigt:** detta är ett större grepp och bör bara göras efter
GA-analys (fråga 2 + 4 ovan).

### E. Hybrid: behåll routerna men cacha bara "nära idag"

I `shouldCacheRequest()`: tillåt bara cache om datumet i URL:en är
inom senaste 7 dagarna. Gamla historiska datum-kombinationer cachas
inte (servas live från DB — queryn är snabb ändå eftersom
`crime_events` är indexerat på datum + plats).

```php
if ($request->is('plats/*/handelser/*') ||
    $request->is('lan/*/handelser/*')) {
    $date = $this->extractDateFromUrl($request->path());
    if (!$date || $date->diffInDays(now()) > 7) {
        return false;
    }
}
```

Minskar drastiskt från ~1M till max ~(330+21) × 7 ≈ 2 500 entries.
Relativt säkert alternativ om vi vill undvika (A):s fulla eliminering.

## Rekommenderad sekvens

1. **Nu:** implementera (A) + rensa Redis. Mät effekt.
2. **Nu:** lägg till (B) — `rel=nofollow` på day-nav. Nästan gratis.
3. **Inom vecka:** implementera (C) — `noindex` på historiska datum-
   sidor. Minskar AI/SEO-ytan.
4. **Senare, efter GA-data:** besluta om (D) full borttagning eller
   om (E) räcker som långsiktig lösning.

## Status / nästa steg

- [x] Verifiera att routerna finns och hur de länkas — klart
- [x] Verifiera att ingen sitemap exponerar dem — klart (ingen
      sitemap alls)
- [x] Verifiera att `shouldCacheRequest()` inte finns idag — klart
- [ ] **AVVAKTAR BESLUT:** implementera (A) — exkludera
      `plats/*/handelser/*` och `lan/*/handelser/*` från cache
- [ ] Efter (A): mät Redis `DBSIZE` före/efter, logga i
      `Brottsplatskartan log.md`
- [ ] Besluta om (B), (C) ska läggas till direkt
- [ ] Kopplad: todo 8 (GA MCP) kan leverera trafik-data för att
      välja mellan (A+C) och (D)
- [ ] Kopplad: todo 11 (SEO-audit) bör inkludera
      `platsDatum`/`lanDate`-indexering som audit-punkt

---

_Skapad: 2026-04-21_
