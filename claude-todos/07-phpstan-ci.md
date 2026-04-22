# Todo #7 — PHPStan-errors och CI-integration

## Sammanfattning

Larastan 3.x är installerad på level 5 med `composer analyse`-script, men körs
inte i CI och rapporterar i dagsläget **75 errors**. Föreslagen väg:
generera baseline, integrera PHPStan i en ny GitHub Actions-workflow, och
introducera Laravel Pint parallellt som kodstandard-check. Åtgärda sedan
baseline stegvis.

## Nuläge

### `phpstan.neon`
- Inkluderar `vendor/nunomaduro/larastan/extension.neon`
- `paths: app`
- `level: 5`
- Ignorerar `#PHPDoc tag @var#` (matchar inte längre — genererar eget fel)
- `excludePaths: ./*/*/FileToBeExcluded.php` (placeholder, används inte)

### `composer.json`-scripts
- `analyse` → `phpstan analyse --memory-limit=2G`
- `analyse:baseline` → `phpstan analyse --memory-limit=2G --generate-baseline`
- `test` → `phpunit`
- Ingen Laravel Pint installerad.

### GitHub Actions
- Endast `.github/workflows/deploy-hetzner.yml` (SSH-deploy vid push till
  `main`). Ingen CI för test, analys eller kodstandard.

### Faktisk error-count (`composer analyse` körd i app-containern)

**75 errors**. Fördelning per identifierare:

| Antal | Identifierare |
|---|---|
| 13 | `property.phpDocType` (t.ex. `$fillable`, `$hidden`, `$casts` inte kovarianta) |
| 9  | `property.notFound` |
| 8  | `return.missing` |
| 8  | `class.notFound` |
| 7  | `argument.type` |
| 4  | `variable.undefined` |
| 3  | `return.type` |
| 3  | `method.notFound` (bl.a. `DistanceInterface::flat()`, `Collection::load()`) |
| 2  | `nullCoalesce.variable` |
| 2  | `larastan.noUnnecessaryCollectionCall` |
| 2  | `empty.variable` |
| 1  | `larastan.noEnvCallsOutsideOfConfig` |
| 1  | `identical.alwaysTrue`, `identical.alwaysFalse`, `booleanAnd.alwaysFalse`, m.fl. |
| + övriga | ~8 diverse |

Dessutom: `Ignored error pattern #PHPDoc tag @var# was not matched` — gamla
ignorelistan är inaktuell och bör tas bort (eller ersättas med
`reportUnmatchedIgnoredErrors: false`).

**Värdefulla fynd** bland errors: `class.notFound`, `variable.undefined`,
`method.notFound`, `argument.type`, `alwaysTrue/alwaysFalse` — flera av dessa
är sannolikt riktiga buggar eller död kod som bör åtgärdas före baseline.

## Strategi — baseline + step-by-step (rekommenderat)

Fix-first (alla 75) är orealistiskt utan att blockera annat arbete. Därför:

1. **Triagera & fixa "farliga" fel först** (uppskattning ~20 errors):
   - `class.notFound` (8)
   - `variable.undefined` (4)
   - `method.notFound` (3)
   - `alwaysTrue/alwaysFalse`/`booleanAnd.alwaysFalse` (3)
   - `larastan.noEnvCallsOutsideOfConfig` (1)
   - `argument.type` där det är uppenbar bugg.

2. **Generera baseline för resten**:
   ```
   composer analyse:baseline
   ```
   Det ger `phpstan-baseline.neon` som inkluderas i `phpstan.neon`:
   ```neon
   includes:
       - ./vendor/nunomaduro/larastan/extension.neon
       - ./phpstan-baseline.neon
   ```

3. **Krymp baseline över tid** — sätt regel att baseline aldrig får växa, och
   beta av en kategori per PR (börja med `property.phpDocType` i Eloquent-modeller
   via `@property`/`$casts` som metod).

4. **Städa phpstan.neon**:
   - Ta bort `FileToBeExcluded.php`-exclude.
   - Ta bort `#PHPDoc tag @var#` (triggar nu error).
   - Lägg till `reportUnmatchedIgnoredErrors: false` eller `treatPhpDocTypesAsCertain: false` efter behov.

Alternativ (ej rekommenderat nu): höja till level 6/7 vore attraktivt men
level 5 + baseline är redan en rimlig tröskel; höjning tas efter att
baseline krympt markant.

## Föreslagen CI-workflow

Ny fil: `.github/workflows/ci.yml`

```yaml
name: CI

on:
  pull_request:
  push:
    branches: [main]

jobs:
  static-analysis:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
          extensions: bcmath, exif, gd, redis, zip
          coverage: none
          tools: composer:v2
      - uses: actions/cache@v4
        with:
          path: vendor
          key: composer-${{ hashFiles('composer.lock') }}
      - run: composer install --no-interaction --no-progress --prefer-dist
      - run: composer analyse -- --error-format=github --no-progress

  code-style:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
          tools: composer:v2
      - run: composer install --no-interaction --no-progress --prefer-dist
      - run: vendor/bin/pint --test

  tests:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
          extensions: bcmath, exif, gd, redis, zip, pdo_sqlite
      - run: composer install --no-interaction --no-progress --prefer-dist
      - run: cp .env.example .env && php artisan key:generate
      - run: composer test
```

Format `--error-format=github` ger inline-annoteringar i PR.

Separat workflow från deploy så att CI kan blockera merge (via required check)
utan att röra deploy-pipelinen.

## Pint-plan

Laravel Pint är en officiell kodstandard-fixer (PHP-CS-Fixer-wrapper) och
passar bra ihop med PHPStan.

1. Installera:
   ```
   composer require --dev laravel/pint
   ```
2. Konfig `pint.json` i rooten (preset `laravel` är default; `psr12` också ett
   alternativ):
   ```json
   {
     "preset": "laravel",
     "exclude": ["bootstrap/cache", "storage"]
   }
   ```
3. Scripts i `composer.json`:
   ```
   "lint": "pint",
   "lint:test": "pint --test"
   ```
4. Första körning: `composer lint` → en stor "Pint: initial formattering"-commit
   separat från funktionella ändringar, för att hålla diff-brus borta.
5. CI kör `pint --test` (se workflow ovan) — failar om formattering avviker.

Bonus: koppla Pint till pre-commit via t.ex. `lint-staged` eller ren git hook,
men inte nödvändigt initialt.

## Risker

- **Falsk säkerhet**: baseline kan växa i det tysta. Mitigera genom att
  commit:a baseline och göra regeln "PR får inte öka antal rader i
  baseline".
- **CI-brus**: level 5 + Laravel + äldre kod ger en hel del phpdoc-stör som
  inte är faktiska buggar. Baseline tar udden av det; reportera bara nya
  fel.
- **Pint initial diff**: en första auto-format kan dölja blame-historik.
  Lös med separat commit och lägg `.git-blame-ignore-revs`.
- **Tidsåtgång CI**: PHPStan på level 5 tar ~30–60s; acceptabelt. Cacha
  `tmp`-katalog: lägg `tmpDir: .phpstan-cache` i `phpstan.neon` + cacha i
  Actions.
- **Tester i CI kan vara rödfärgade initialt** om testsviten inte är
  grön — kan lanseras som allow-failure tills dess.

## Fördelar

- Tidig upptäckt av verkliga buggar (redan identifierade: `undefined variable`,
  `class.notFound`, dead-code-villkor).
- Trygghet vid refaktorering — särskilt i Eloquent-/service-lagret.
- Pint eliminerar kodstandard-diskussioner i review.
- Snabbare feedback i PR än via lokal körning (Docker-upp-krav försvinner).
- Fungerar som "gate" innan deploy.

## Öppna frågor

- Ska `tests/` analyseras också? (För närvarande bara `app/`.) Rekommendation:
  lägg till `tests/` i samma svep, ofta bugg-dense.
- Vilken PHP-version i CI? Produktion kör `serversideup/php:8.4`. Bör
  matcha — byt `php-version: '8.2'` till `'8.4'` i workflows för att fånga
  version-specifika ändringar.
- Vill vi ha test-jobbet grönt från start eller tillåta red? Kräver först
  kontroll av aktuell testsvit.
- Ska PHPStan-nivån höjas stegvis (6 → 7) efter att baseline krympts? Bra
  långsiktigt mål.
- Köra PHPStan mot hela `config/`, `routes/`, `database/` också?

## Status / nästa steg

Status: **klar 2026-04-22**.

Genomfört:

1. **`phpstan.neon` uppdaterad** med best practice: `tmpDir`,
   `treatPhpDocTypesAsCertain: false`, `reportUnmatchedIgnoredErrors: false`,
   `checkModelProperties: false`. Gamla `ignoreErrors: #@var#`-patternen och
   placeholder-exclude bortagna. Baseline-fil inkluderas.
2. **~30 errors fixade direkt** (77 → 47): `Blog.php` nullCoalesce,
   `Authenticate::redirectTo` explicit null-return, `ListEvents` (cast till int,
   `$events` init), `CreateAISummaries`/`ImportVMAAlerts` return int,
   `DebugController::debug` return null, `VMAAlertsController::import` rätt
   return-type, `EventMarkdownRenderer` `@var Newsarticle` för foreach,
   redundant `?? ''` på `Str::markdown`.
3. **Baseline genererad** för resterande 47 fel → `phpstan-baseline.neon`
   (mest `property.phpDocType`-covariance på Kernel/Middleware/Models som
   kräver större arbete, samt gammal `TweetCrimes.php` med borttaget
   atymic/twitter-paket).
4. **CI-workflow tillagd** — `.github/workflows/ci.yml` kör `composer analyse`
   med `--error-format=github` för inline-annoteringar i PR. PHP 8.4 matchar
   produktion. Cache för composer och PHPStan.

### Vidare arbete

- Pint kan läggas till senare (separat jobb i `ci.yml` + initial
  formatting-commit + `.git-blame-ignore-revs`).
- Krymp baseline över tid: fixa t.ex. `TweetCrimes.php` (återinstallera paket
  eller ta bort kommandot), `property.phpDocType` i Kernel/Middleware
  (byt `array` → `array<int, string>` i PHPDoc).
- Höj level 5 → 6 → 7 när baseline krympt markant.

Föreslagen ordning för genomförande:

1. Fixa de ~20 "farliga" errors (riktiga buggar) — separat PR.
2. `composer analyse:baseline` → commit:a `phpstan-baseline.neon`.
3. Städa `phpstan.neon` (ta bort inaktuell ignoreError + placeholder-exclude,
   lägg till `tmpDir`).
4. Installera Pint, kör initial format i separat commit, lägg till
   `.git-blame-ignore-revs`.
5. Lägg till `.github/workflows/ci.yml` med analyse + pint + ev. tests.
6. Sätt required checks i GitHub på `main`.
7. Löpande: krymp baseline, sikta på level 6 inom X månader.
