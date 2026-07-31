**Status:** aktiv — implementerad 2026-07-31, ej deployad. Uppföljning 2026-08-07.
**Senast uppdaterad:** 2026-07-31

# Todo #99 — Komprimera responscachen + undanta sitemaps

## Sammanfattning

Responscachen lagrade okomprimerad HTML i Redis och stod för **~78 % av
minnet**. Förbrukningen låg på 1,93 GB med peak 3,15 GB mot ett tak på
3,00 GB, vilket gav **135 979 evictions på 26 timmar** — cachen slets ut i
förtid och trafik gick på DB i onödan.

Åtgärd: phpredis inbyggda ZSTD-komprimering (konfiguration, ingen egen
kod), plus exkludering av sitemaps som cachades dubbelt.

## Bakgrund

Upptäckt under felsökning av nertid 2026-07-31. Nertiden hade en annan
orsak (Caddy-omstart vid varje deploy, se separat fynd i `prod-ops`-skillen)
— men mätningen av Redis blottade det här.

Mätt på prod 2026-07-31 (5 000 samplade nycklar, `MEMORY USAGE` per nyckel):

| Vad                 | Andel av minnet | Snittstorlek |
| ------------------- | --------------- | ------------ |
| Responscache (HTML) | ~78 %           | 67 KB/post   |
| `getWordsInText`    | ~7 %            | 7,6 KB       |
| `ldjson`            | ~4 %            | 3,4 KB       |
| Övrigt              | ~11 %           | <1 KB        |

Responscachen: ~20 000 poster, median 56 KB, p90 112 KB, största **26 MB**
(en sitemap).

## Förslag

### 1. phpredis inbyggda ZSTD-komprimering

Två rader i `config/database.php` under `redis.options`. **Ingen egen kod.**

```php
'options' => [
    'compression' => Redis::COMPRESSION_ZSTD,
    'compression_level' => 3,
    'pack_ignore_numbers' => true,
],
```

Första ansatsen var en egen `CompressedJsonSerializer` (gzip nivå 1) via
Spaties `Serializer`-interface. Den skrotades när det visade sig att
phpredis kan detta inbyggt och att Laravel har förstklassigt stöd för det.
Mätt på riktig sid-HTML (223 KB) i containern:

| Metod               | Lagrat  | Kvot | SET     | GET     |
| ------------------- | ------- | ---- | ------- | ------- |
| gzip nivå 1 (egen)  | 41,6 KB | 5,4× | 0,62 ms | 0,24 ms |
| phpredis LZF        | 55,1 KB | 4,1× | 0,22 ms | 0,20 ms |
| **phpredis ZSTD 3** | 36,6 KB | 6,1× | 0,34 ms | 0,13 ms |
| phpredis ZSTD 9     | 33,7 KB | 6,6× | 1,45 ms | 0,13 ms |
| phpredis LZ4        | 53,0 KB | 4,2× | 0,16 ms | 0,07 ms |

ZSTD 3 slår den egna serializern på varje axel — bättre kvot, snabbare
skrivning, dubbelt så snabb läsning. Nivå 9 ger bara 8 % bättre kvot men
fyrdubblar skrivtiden.

Till skillnad från serializern gäller detta **hela** cachen, inte bara
responscachen — även `getWordsInText` (7 %) och `ldjson` (4 %) krymper.

**Redis-servern själv kan inte detta.** Verifierat med `CONFIG GET`:
`rdbcompression` gäller bara RDB-dumpen på disk, `list-compress-depth`
bara list-typen. Strängvärden lagras alltid rått i minnet.

### 1b. ⚠️ `pack_ignore_numbers` är inte valfritt

Utan den komprimeras även numeriska värden, och då returnerar `INCRBY`
**false**. Laravels `RateLimiter` bygger på `Cache::increment()`, så
`throttle:500,1` på API:t och `throttle:6,1` på verifieringen hade slutat
räkna — helt tyst, utan felmeddelande.

Uppmätt 2026-07-31:

| Läge                           | INCRBY     |
| ------------------------------ | ---------- |
| utan komprimering              | OK (1 → 2) |
| ZSTD 3                         | **false**  |
| ZSTD 3 + `pack_ignore_numbers` | OK (1 → 2) |

`tests/Unit/RedisCompressionConfigTest.php` låser fast kombinationen så
ingen råkar ta bort flaggan.

### 1c. Bakåtkompatibilitet — asymmetrisk

- **Slå PÅ:** säkert. Befintliga okomprimerade nycklar läses korrekt
  (verifierat — phpredis faller tillbaka).
- **Slå AV igen:** _inte_ säkert. Nycklar skrivna under tiden blir binärt
  skräp för en okomprimerad klient. Självläker eftersom allt har TTL
  (190 685 av 190 688 nycklar, snitt ~2 dygn), men inte omedelbart.

Vid rollback: räkna med degraderad cache tills nycklarna löpt ut, eller
kör `responsecache:clear` direkt efter.

### 1d. Är det beprövat? Webbresearch 2026-07-31

Ja, men bevisen är indirekta — ingen publicerad Laravel-fallstudie med
före/efter-siffror hittades.

- **Starkaste beviset:** Laravel skrev en riktad fix.
  [PR #54221](https://github.com/laravel/framework/pull/54221) ("Fix Cache
  component to be aware of phpredis serialization and compression
  settings") slogs in i 11.x 2025-01-17 och fixade dubbelserialisering +
  EVAL-parametrar. Underhållare fixar inte funktioner ingen använder.
  **Vi kör 13.9.0, så fixen är med** — det förklarar varför låsen mäts
  som fungerande (se Risker).
- Problemet var långlivat:
  [issue #36337](https://github.com/laravel/framework/issues/36337) om
  eval + serializer/komprimering låg öppet i flera år.
- **Officiella dokumentationen** listar komprimering som förstklassigt
  alternativ men varken rekommenderar eller varnar. Exemplet använder
  `COMPRESSION_LZ4` — rimligt när läslatens är viktigast; vårt problem är
  minne, därav ZSTD.
- [Object Cache Pro](https://objectcache.pro/docs/data-encoding)
  rekommenderar uttryckligen **zstd när målet är att minimera minne**,
  lz4 när läslatens väger tyngst. (WordPress-världen, inte Laravel.)
- **Horizon går sönder med komprimering**
  ([issue #1462](https://github.com/laravel/horizon/issues/1462)) — berör
  oss inte, Horizon är inte installerat (verifierat).
- **`pack_ignore_numbers` är odokumenterat överallt:** noll träffar i
  laravel/framework, noll i phpredis issues, och den saknas i phpredis
  README och i Laravels dokumentation. Vår egen mätning (1b) är enda
  belägget. Det är den enskilt minst upptrampade delen av ändringen.

### 1e. Flusha cachen vid deploy — gör det medvetet

Object Cache Pro skriver att man **måste flusha hela Redis** när
serializer eller komprimering ändras, annars riskeras korrupt data. Det
går emot vår mätning (1c), där gamla okomprimerade nycklar lästes korrekt.

Tolkning: rådet täcker även serializer-byten, som genuint bryter
läsningar, och Laravel-fixen ovan krävde uttryckligen en flush. Vi byter
bara komprimering — den beniga delmängden — och phpredis fallback är
verifierad.

**Men luta dig inte på fallbacken när auktoritativa källor säger flusha.**
`deploy/deploy.sh` kör redan `responsecache:clear` (täcker ~78 %). Kör
dessutom en engångs-`cache:clear` direkt efter den här deployen:

```bash
ssh deploy@brottsplatskartan.se
cd /opt/brottsplatskartan
docker compose exec -T app php artisan cache:clear
```

Kostar en kall cache i några minuter och tar bort hela frågan.

### 2. Undanta sitemaps från responscachen

Sitemaps cachas **redan** i Redis av `sitemap:generate` och servas därifrån
av `SitemapController::serveCached()`. Responscachen lagrade en andra kopia
av samma XML — största posten 26 MB, ensam ~1,4 % av hela Redis-taket.
Exkludering kostar ingen omrendering.

### 3. Sidofynd: phpunit.xml:s testisolering var verkningslös

Upptäcktes när ett test som borde ha fallerat blev grönt. `<env>`-raderna i
`phpunit.xml` skrevs över av containerns riktiga miljövariabler, eftersom
Laravels `env()` läser `$_SERVER` **före** `$_ENV` och PHPUnits `<env>` bara
sätter `$_ENV`. Testerna körde mot **dev-MariaDB och dev-Redis**, inte
sqlite/array — trots kommentaren som varnade för exakt det.

Fixat genom att byta `<env>` → `<server ... force="true">`.

## Utfall (lokalt, verifierat i faktiska kodvägen)

Startsidan hämtad via riktig HTTP-request, cachenyckeln läst ur Redis med
en okomprimerad klient:

```
lagrat i Redis:  12 742 bytes
första 4 bytes:  28b52ffd   <- ZSTD:s magic number
```

Samma sida med den skrotade gzip-serializern: 14 279 bytes. ZSTD är alltså
~11 % mindre i praktiken också.

Rate limiting verifierad genom hela stacken:

```
Cache::increment  ->  2, 3          (OK)
API throttle      ->  X-RateLimit-Remaining: 499, 498, 497
```

**Prognos prod:** responscachen ~1,5 GB → ~250 MB, plus att övriga cacher
krymper. Total förbrukning från 1,93 GB till uppskattningsvis ~500 MB.
Evictions bör gå till noll.

## Risker

- **Låg, men med en skarp kant.** Komprimeringen är en dokumenterad
  Laravel-inställning, inte egen kod. Kanten är `pack_ignore_numbers`
  (se 1b) — utan den dör rate limiting tyst. Testet skyddar mot det.
- Rollback är asymmetrisk (se 1c).
- **Lua-script mot cachade värden: verifierat, inte antaget.** Vi kör inga
  egna `eval`/Lua-anrop, men Laravel gör det — varje
  `->withoutOverlapping()` i `app/Console/Kernel.php` (19 st) tar ett
  Redis-lås, och `RedisLock::release()` jämför det lagrade ägarvärdet mot
  `ARGV[1]` i Lua. phpredis packar **inte** eval-argument, så en naiv
  jämförelse hade alltid slagit fel och låsen aldrig släppts.
  Laravel hanterar det: `Illuminate\Cache\PhpRedisLock::release()` kör
  `$this->redis->pack([$this->owner])` först. Uppmätt lokalt med ZSTD 3
  påslaget: `lock()->get()` → true, `release()` → true, nyckeln borta.
  `RedisStore::add()` skriver via Lua och lagrar därmed okomprimerat —
  läsningen fungerar ändå eftersom phpredis faller tillbaka till rådata
  när dekomprimering misslyckas (verifierat med både sträng- och
  array-värden).
- CPU: ZSTD 3 kostar 0,34 ms att packa och 0,13 ms att packa upp, alltså
  mindre än den skrotade gzip-varianten på båda hållen. Vid uppmätt trafik
  (8,3–8,6 req/s) är det långt under 0,3 % av en kärna. Netto
  **besparing**, eftersom varje undviken eviction sparar en 122 ms
  omrendering.
- Debuggbarhet: värden i Redis är nu binära. `redis-cli GET` visar skräp;
  läs via en klient med komprimering påslagen, eller via `artisan tinker`.

## Uppföljning 2026-08-07 (en vecka efter deploy)

Kör på prod och jämför mot utgångsvärdena:

```bash
ssh deploy@brottsplatskartan.se
cd /opt/brottsplatskartan
docker compose exec -T app php artisan redis:health
```

| Mått         | Före (2026-07-31) | Mål      |
| ------------ | ----------------- | -------- |
| Använt minne | 1,93 GB           | < 800 MB |
| Peak / max   | 105,1 %           | < 40 %   |
| Evicted keys | 135 979 / 26 h    | 0        |
| Hit rate     | 95,2 %            | ≥ 96 %   |

Kolla även att inga cache-/serialiseringsfel dykt upp sedan deploy:

```bash
docker compose exec -T app sh -c \
  'grep -icE "CouldNotUnserialize|Could not serve cached|unserialize\(\)" storage/logs/laravel.log'
```

Och att rate limiting fortfarande räknar (skulle `pack_ignore_numbers`
tappas bort dör den tyst):

```bash
curl -sD- -o /dev/null "https://brottsplatskartan.se/api/events?limit=1" | grep -i ratelimit
```

Om allt håller: överväg att **sänka** `maxmemory` från 3 GB och ge RAM:et
till annat, hellre än att låta cachen växa in i utrymmet.

## Confidence

**Hög** — komprimeringsgrad och CPU-kostnad är uppmätta på riktiga
payloads, inte uppskattade. Verifierat i faktisk HTTP-kodväg mot Redis
(ZSTD-magic i lagrad nyckel) och rate limiting testad genom hela stacken.
Den enda egentliga osäkerheten är exakt hur mycket prod-minnet sjunker,
eftersom sidmixen varierar.
