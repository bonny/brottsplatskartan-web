**Status:** blockerad — allt utom nyckelrotationen (punkt 2) är fixat,
deployat och verifierat på prod
**Senast uppdaterad:** 2026-08-01

# 100 – Säkerhetsgranskning 2026-08-01

Genomgång av hela kodbasen (routes, controllers, modeller, Blade,
middleware, config) med fokus på oautentiserade skrivningar,
SQL-injection och XSS. Alla fynd nedan är verifierade praktiskt mot den
lokala Docker-miljön — inga gissningar.

**Ingen exploaterbar SQL-injection hittades.** All `whereRaw`/`selectRaw`
som tar emot request-data använder bindningar, och API:et är
parameteriserat rakt igenom.

Varje fix commit:as och pushas separat så den kan verifieras live på
prod innan nästa påbörjas.

## Åtgärdslista

- [x] **1. KRITISK — `/debug/phpinfo` läcker alla prod-hemligheter**
      _Fixad `be7802f`, deployad + verifierad på prod 2026-08-01 (404)._
      `routes/web.php:36` → `DebugController.php:30`. Publik route utan
      auth eller env-guard. Verifierat: prod svarar 200 med 104 kB
      phpinfo, som innehåller `APP_KEY`, `DB_PASSWORD`, `REDIS_PASSWORD`,
      `MAIL_PASSWORD`, `ANTHROPIC_ADMIN_KEY`.
      `APP_KEY` signerar sessions-cookies och används vid decrypt +
      unserialize → sessionsförfalskning och potentiell RCE.
      **Fix:** ta bort `/debug`-routen + `DebugController::debug()`.

- [ ] **2. KRITISK — Rotera samtliga läckta nycklar** _(manuell, efter #1)_
      `APP_KEY` (`key:generate`, loggar ut alla sessioner), DB-lösenord,
      Redis-lösenord, mail-lösenord, Anthropic-nyckel. Kom ihåg
      `up -d` + `config:cache` i rätt ordning enligt `prod-ops`.
      Kolla även access-loggarna bakåt efter träffar på `/debug/`.

- [x] **3. HÖG — Reflekterad XSS i `/debug/urls`**
      _Fixad `be7802f`, deployad + verifierad på prod 2026-08-01 (404)._
      `DebugController.php:69-73` echoar `$request->get('url')` rått.
      Verifierat lokalt: `?url=<img src=x onerror=alert(1)>` renderas
      oescapat. Åtgärdas av samma borttagning som #1.

- [x] **4. HÖG — Vem som helst kan posta nyhetsartiklar till valfri händelse**
      _Fixad, deployad + verifierad 2026-08-01: POST utan inloggning ger
      302 → `/login`, ingen rad skapas._
      `routes/web.php:453`. POST-routen saknar auth helt; formuläret
      ligger bakom `Auth::check()` men routen gör ingen kontroll.
      CSRF skyddar inte — angriparen hämtar sin egen token.
      Verifierat lokalt hela vägen: POST → 302 → rad i `newsarticles` →
      publikt renderad på händelsesidan. Prod svarar
      `allow: GET,HEAD,POST`.
      **Fix:** `->middleware('auth')`.

- [x] **5. HÖG — Lagrad XSS via `javascript:`-URL i nyhetslänk**
      _Fixad 2026-08-01. Validering vid POST **och** filter på
      renderingssidan i både `newsarticles.blade.php` och
      `place-news.blade.php` — de AI-matchade artiklarna kommer från
      externa RSS-flöden och täcks inte av POST-valideringen._
      `parts/crimeevent/newsarticles.blade.php:57` gör
      `href="{{ $item->url }}"`. Blade escapar tecken men validerar inte
      schema. Verifierat renderat:
      `href="javascript://evil.example/%0aalert(document.domain)"` —
      klick kör JS på domänen. (`title` escapas korrekt, den är inte
      vektorn.)
      **Fix:** validera `'url' => ['required', 'url', 'starts_with:http://,https://']`.

- [x] **6. HÖG — Permanent 500 på händelsesida via URL utan host**
      _Fixad 2026-08-01. Engångskollen körd mot både lokal DB och prod:
      0 rader med icke-http-URL i `newsarticles` och `news_articles` —
      ingen händelsesida är alltså trasig och inga `javascript:`-URL:er
      ligger lagrade sedan tidigare._
      `Newsarticle.php:89` gör `parse_url($url)['host']` utan `isset`.
      En postad URL utan host (`javascript:alert(1)`, `foo`) ger
      `Undefined array key "host"` → sidan är nere tills raden städas i
      DB. Utlöstes av misstag under testet.
      **Fix:** `parse_url($url, PHP_URL_HOST) ?: ''` + bail tidigt.
      Kör även engångskoll:
      `SELECT id, url FROM newsarticles WHERE url NOT LIKE 'http%'`.

- [x] **7. MEDEL — Oautentiserad manipulation av "Mest lästa"**
      _Fixad 2026-08-01. Egen namngiven limiter `pixel` (120/min per IP) +
      existenskontroll på event-id. Verifierat lokalt: exakt 120 släpps
      igenom, 0 rader skapade för okänt id._
      `PixelController.php:94-101`, CSRF-undantagen i
      `VerifyCsrfToken.php:19`. `POST /pixel` skriver en `crime_views`-rad
      för godtyckligt event-id, utan kontroll att eventet finns, utan
      dedup och utanför `api`-gruppens throttle.
      Full auth vore fel (det är en öppen trackingpixel).
      **Fix:** verifiera att `crime_event_id` existerar + `throttle:60,1`.

- [x] **8. MEDEL — Oautentiserad skrivning till publika "Vanliga sökningar"**
      _Fixad 2026-08-01. `show-setting` borttagen, throttle på routen._
      `PixelController.php:36-69` → `SearchController::getSearches()` →
      `adsenseSearch.blade.php:26`. Godtycklig text kan skrivas till
      settingen `searches3` och visas publikt efter två count-inkrement.
      Inte XSS (`stripTags` + `{{ }}`), men angriparstyrd indexerad text.
      Dessutom returnerar `show-setting=1` hela sökhistoriken — alla
      besökares sökfrågor 3 dygn bakåt.
      **Fix:** ta bort `show-setting`-grenen, throttla routen.

- [x] **9. MEDEL — Angriparstyrd text hamnar rå i JSON-LD**
      _Fixad 2026-08-01. `JSON_HEX_TAG` på alla åtta sinkar (de sju
      bladesarna + `CrimeEvent::buildLdJson`) och existenskontroll i
      län-grenen. Verifierat lokalt: `/plats/<skräp>-stockholms-lan` →
      404, `/plats/orminge-stockholms-lan` → 200, och samtliga
      ld+json-block parsar fortfarande som giltig JSON._
      `PlatsController.php:169` saknar den `abort(404)`-kontroll som
      finns i den andra grenen (rad 189), så
      `/plats/<godtyckligt>-stockholms-lan` ger 200. Texten hamnar sedan
      oescapad i `<script type="application/ld+json">` eftersom alla
      `json_encode` använder `JSON_UNESCAPED_SLASHES` — just den flaggan
      stänger av `\/`-escapingen som hindrar `</script>`-utbrytning.
      Inte exploaterbart idag: `/` kan inte finnas i path-segmentet
      (`%2F` ger 404, testat). Men skört, och genererar obegränsat med
      indexerbara skräpsidor.
      **Fix:** `JSON_HEX_TAG` i alla sju `json_encode`-anropen
      (`place-jsonld:41`, `collectionpage-jsonld:38`, `web.blade:110`,
      `breadcrumb:53`, `itemlist-jsonld:19`, `statistik:44`,
      `single-plats-month:134`) + `abort(404)` i län-grenen.

- [x] **10. LÅG — SQL-interpolering i ordlistan**
      _Fixad 2026-08-01. Verifierat att ordlisteuppslaget fortfarande
      matchar (5 ord på en testtext)._
      `Dictionary.php:99` interpolerar `$oneMatchedWord` direkt i
      `FIND_IN_SET`. Kommer från `dictionary`-tabellen, inte från request
      → inte exploaterbart idag, men enda `whereRaw` utan bindning.
      **Fix:** `whereRaw('FIND_IN_SET(?, ...)', [$oneMatchedWord])`.

- [x] **11. LÅG — `/debug-response-cache` exponerar konfiguration**
      _Fixad `be7802f`, deployad + verifierad på prod 2026-08-01 (404)._
      `routes/web.php:934`. Publik route som returnerar
      `app()->environment()`, responsecache-config och request-headers.
      **Fix:** ta bort.

- [x] **12. ~~LÅG — Session-cookie saknar `same_site`~~ — inget problem**
      Baserades på att nyckeln saknas i `config/session.php`. Kollade
      faktiska svarsheaders från prod i stället, och Laravel sätter redan
      rätt attribut:
      `laravel_session=...; path=/; secure; httponly; samesite=lax`.
      Ingen åtgärd behövs.

- [x] **13. HÖG — `X-Forwarded-For` var spoofbar → all rate limiting kunde kringgås**
      _Hittad 2026-08-01 när throttlingen i #7 skulle verifieras._
      Caddys `reverse_proxy` **appendar** till `X-Forwarded-For` i stället
      för att skriva över, och `TrustProxies::$proxies = '*'` gör att
      Symfony litar på hela kedjan och plockar den vänstra posten —
      alltså klientens egen header. `request()->ip()` var därmed
      angriparstyrd.
      Verifierat lokalt: efter 60 requests svarade `/pixel` 429, men med
      `X-Forwarded-For: 1.2.3.4` gick den direkt till 200 igen. Träffar
      inte bara pixel-throttlingen utan även `api`-gruppens
      `throttle:500,1` och Laravels login-throttle — alltså
      brute-force-skyddet på `/login`.
      **Fix:** `header_up X-Forwarded-For {remote_host}` i
      `deploy/Caddyfile`. Caddy är edge (ingen CDN framför), så
      `{remote_host}` är den verkliga klient-IP:n.
      _Verifierat på prod 2026-08-01: 65 POST mot `/pixel` med 65 olika
      spoofade `X-Forwarded-For` bröts ändå vid exakt 60 — throttlingen
      nycklar nu på verklig klient-IP._

### Lärdom: namnge alltid throttle-limitrar

`throttle:60,1` utan namn nycklar på `domain|ip` — **samma nyckel för
alla namnlösa throttles i appen**. Pixelkvoten delade alltså räknare med
`api`-gruppens `throttle:500,1`, så kartans `/api/eventsMap`-anrop från
samma besökare åt upp pixelkvoten. Löst med en namngiven limiter
(`RateLimiter::for('pixel', ...)` i `RouteServiceProvider`), som får eget
prefix och därmed egen bucket. Verifierat: 70 API-anrop påverkar inte
pixelkvoten.

## Kontrollerat och OK

- **SQL-injection:** ingen. `MCFStatistik.php:152` interpolerar en
  klasskonstant, inte input. API-filter (`area`, `location`, `type`) går
  via Eloquent-bindningar.
- **XSS i vanliga vyer:** `{!! !!}`-punkterna matar konstanter,
  admin-styrda Settings, polisdata eller
  `Str::markdown(..., ['html_input' => 'escape'])`.
  `Newsarticle::getEmbedMarkup()` är säker (tweet-id via `%1$d`,
  FB-URL via `htmlspecialchars` i dubbelciterat attribut).
- **`@section('title', ...)`** escapas av Laravel (`Factory::startSection`
  kör `e()`) — testat explicit, inte en XSS-väg trots att `@yield` är
  oescapat.
- **Path traversal / SSRF:** `KartbildController` är regex-låst med
  storleksgränser; `SitemapController::serveEventsYear` tar `(int)` med
  `[0-9]{4}`-constraint; `FeedController`s `file_get_contents` är inte
  routad.
- **RCE-primitiver:** inga träffar på `eval`/`exec`/`system`/
  `unserialize`/`extract` i `app/`, `routes/`, `config/`.
- **CORS:** `paths => ['api/*']`, `supports_credentials => false`.
- **JSONP** (`->withCallback()`): Symfony validerar callback-namnet.
- **Auth:** `register => false`, `/home` är `auth`-skyddad,
  admin-partialen är `Auth::check()`-gated.

## Kvar att göra

Bara **punkt 2** — rotera nycklarna. Allt annat är fixat, deployat och
verifierat på prod. Punkt 12 visade sig vara ett icke-problem.

## Ordning

1. #1 + #3 + #11 (ta bort debug-routes) — samma commit, deploya direkt
2. #2 rotera nycklar — manuellt, direkt efter att #1 är live
3. #4 + #5 + #6 (newsarticle-kedjan) — en commit
4. #7, #8 — pixel-endpoints
5. #9 — JSON-LD + 404-grenen
6. #10, #12 — härdning
