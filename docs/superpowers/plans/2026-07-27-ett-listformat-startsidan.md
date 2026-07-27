# Ett listformat på startsidan — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ersätt de tre händelseformaten på startsidan (`hero size="large"`, `hero size="small"`, `list-item`) med ett enda format — 140 px cirkel-kartbild med kategoriikon, en kolumn, CSS-clampad text — så listan får konstant radhöjd och blir överskådlig.

**Architecture:** `list-item.blade.php` blir det enda händelseformatet och `hero.blade.php` tas bort. Två rena PHP-funktioner byggs först med tester (`CrimeEvent::getIconGroup()` och radietaket i `StaticMapUrlBuilder`), därefter en ny ikonkomponent, sedan `list-item` med flex-layout istället för float, och sist rivs `hero` + dess CSS ut. Verifiering av de sju konsumerande sidorna ligger som eget sista steg.

**Tech Stack:** Laravel 13, PHP 8.4.20 i containern (composer kräver ^8.3), Blade-komponenter, PHPUnit 11, handskriven CSS i `public/css/styles.css` (INTE Laravel Mix — se Global Constraints), Docker Compose lokalt.

Spec: [`todos/90-ett-listformat-startsidan.md`](../../../todos/90-ett-listformat-startsidan.md)

## Global Constraints

- **Allt på svenska** — kodkommentarer, commit-meddelanden, dokumentation, användarsynlig text. Projektregel i `AGENTS.md`.
- **`public/css/styles.css` redigeras direkt.** Den är tracked i git och byggs INTE av Laravel Mix. Mix kompilerar bara `resources/sass/app.scss` → `public/css/app.css`, som layouten inte laddar. Kör alltså inte `npm run dev` för CSS-ändringar.
- **`composer analyse` (Larastan/PHPStan level 5) ska köras efter varje PHP-ändring.** Nya fel ska fixas eller motiverat läggas i `phpstan-baseline.neon`. Ingen CI kör detta.
- **Prettier på alla `.md`-ändringar:** `npx --yes prettier --write <fil>`.
- **Kartbilder får inte tas bort.** Tjänsten heter Brottsplatskartan; kartan bär varumärket. Gör den större eller bättre, aldrig borta.
- **Cache-rensning efter Blade/CSS-ändring:** `docker compose exec app php artisan view:clear` och hård reload i browsern (`styles.css` cache-bustas via `filemtime`, så filändring räcker där).
- **Radhöjden ska vara konstant.** Text clampas med CSS `line-clamp`, aldrig med teckenlängd. Teckenbaserad trunkering ger olika radantal beroende på glyfbredd.

## Mätt utgångsläge (2026-07-27)

Referensvärden att jämföra mot i Task 7:

| Mått                                                 | Värde     |
| ---------------------------------------------------- | --------- |
| `Mest läst`-sektionen, mobil 390 px                  | 3 741 px  |
| Hela startsidan, mobil 390 px                        | 11 559 px |
| `.ListEvent`-bredd vid 390 px viewport               | 358 px    |
| Textkolumn med 140 px thumb + 12 px gap              | 206 px    |
| Bredaste vanliga ordet ("Sammanfattning", 17 px/600) | 132 px    |
| Sidor/session från `/`, mobil (GA4 28 d)             | 2,86      |

Textkolumnen på 206 px rymmer det bredaste vanliga ordet med marginal. Det är därför `EventHero`s stackningsregel för `<480 px` kan tas bort: den behövdes för `--small`-varianten som låg **två-i-bredd** (47 % av 358 px = 168 px minus 140 px thumb = 28 px text), inte för thumb-storleken i sig.

---

## File Structure

| Fil                                                         | Ansvar                                                  | Task |
| ----------------------------------------------------------- | ------------------------------------------------------- | ---- |
| `tests/TestCase.php`                                        | Modern PHPUnit-bas för Laravel 13                       | 1    |
| `composer.json`                                             | PSR-4-autoload för `Tests\`                             | 1    |
| `tests/Unit/CrimeEventIconGroupTest.php`                    | Tester för kategori→ikongrupp                           | 2    |
| `app/CrimeEvent.php`                                        | `getIconGroup()` + `ICON_GROUPS`-mappning               | 2    |
| `tests/Unit/StaticMapUrlBuilderThumbTest.php`               | Tester för radietak + padding                           | 3    |
| `app/Services/StaticMapUrlBuilder.php`                      | Radietak för thumbs, padding per densitet               | 3    |
| `resources/views/components/crimeevent/icon.blade.php`      | SVG-ikon per grupp (ny)                                 | 4    |
| `resources/views/components/crimeevent/list-item.blade.php` | Det enda händelseformatet                               | 5    |
| `public/css/styles.css`                                     | `.ListEvent` flex-layout, clamps, ikonfärger            | 5, 6 |
| `resources/views/parts/events-heroes.blade.php`             | En loop istället för tre block                          | 6    |
| `resources/views/components/crimeevent/hero.blade.php`      | **Tas bort**                                            | 6    |
| `resources/views/design.blade.php`                          | Komponentgalleri — hero-exempel bort, teaser-variant in | 6    |

---

### Task 1: Få PHPUnit att köra

Testsuiten är trasig. Verifierat mot körande container 2026-07-27, PHPUnit 11.5.55 / PHP 8.4.20:

```
$ docker compose exec -T app ./vendor/bin/phpunit
Cannot open bootstrap script "/var/www/html/bootstrap/autoload.php"
```

Phpunit startar alltså inte alls. `phpunit.xml` är kvar i Laravel 5-format och behöver skrivas om helt, inte lappas:

- `bootstrap="bootstrap/autoload.php"` — filen togs bort ur Laravel i 5.5, ska vara `vendor/autoload.php`
- `backupStaticAttributes`, `convertErrorsToExceptions`, `convertNoticesToExceptions`, `convertWarningsToExceptions` — alla borttagna i PHPUnit 10
- `<filter><whitelist>` — borttaget i PHPUnit 10, ersatt av `<source>`
- `QUEUE_DRIVER` — heter `QUEUE_CONNECTION` sedan Laravel 5.7

Med bootstrap-vägen tillfälligt rättad syns nästa fel:

```
$ docker compose exec -T app ./vendor/bin/phpunit --no-configuration --bootstrap vendor/autoload.php tests/
1) ExampleTest::testBasicExample
Error: Call to undefined method ExampleTest::visit()
```

Alltså tre saker att fixa: `phpunit.xml`, den döda `ExampleTest`, och `TestCase` som ligger i global namespace utan PSR-4.

Detta är en medveten avvikelse från projektets nuvarande praxis (ingen CI, bara `composer analyse`). Motivering: `getIconGroup()` är ren mappningslogik med 65 möjliga indata och tre kända fallgropar — precis det tester är billigast och mest värda för. Blade/CSS-delarna testas visuellt i Task 7, inte med enhetstester.

**Files:**

- Modify: `phpunit.xml` (skriv om)
- Modify: `composer.json:73-77` (autoload-dev)
- Modify: `tests/TestCase.php` (skriv om)
- Delete: `tests/ExampleTest.php`
- Create: `tests/Unit/.gitkeep`

**Interfaces:**

- Consumes: inget
- Produces: basklassen `Tests\TestCase` som Task 2 och 3 ärver från. Katalogen `tests/Unit/`.

- [ ] **Step 1: Bekräfta utgångsläget**

```bash
docker compose exec -T app ./vendor/bin/phpunit
```

Förväntat, exakt: `Cannot open bootstrap script "/var/www/html/bootstrap/autoload.php"`.

- [ ] **Step 2: Skriv om `phpunit.xml`**

Ersätt hela filen:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="vendor/autoload.php"
         colors="true">
    <testsuites>
        <testsuite name="Brottsplatskartan">
            <directory suffix="Test.php">tests</directory>
        </testsuite>
    </testsuites>
    <source>
        <include>
            <directory>app</directory>
        </include>
    </source>
    <php>
        <env name="APP_ENV" value="testing"/>
        <env name="CACHE_DRIVER" value="array"/>
        <env name="SESSION_DRIVER" value="array"/>
        <env name="QUEUE_CONNECTION" value="sync"/>
    </php>
</phpunit>
```

- [ ] **Step 3: Ta bort den döda testfilen**

```bash
git rm tests/ExampleTest.php
```

- [ ] **Step 4: Skriv om `tests/TestCase.php`**

```php
<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Skapar applikationen för testkörningen.
     */
    public function createApplication(): \Illuminate\Foundation\Application
    {
        $app = require __DIR__ . '/../bootstrap/app.php';

        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

        return $app;
    }
}
```

- [ ] **Step 5: Lägg PSR-4-autoload för `Tests\` i `composer.json`**

Ersätt `autoload-dev`-blocket (rad 73–77) med:

```json
    "autoload-dev": {
        "psr-4": {
            "Tests\\": "tests/"
        }
    },
```

- [ ] **Step 6: Skapa `tests/Unit/` och regenerera autoload**

```bash
mkdir -p tests/Unit && touch tests/Unit/.gitkeep
docker compose exec -u root app composer dump-autoload
```

- [ ] **Step 7: Verifiera att suiten kör grönt (0 tester)**

```bash
docker compose exec -T app ./vendor/bin/phpunit
```

Förväntat: `OK (0 tests, 0 assertions)` eller `No tests executed!` — inga fel eller fatals.

- [ ] **Step 8: Commit**

```bash
git add phpunit.xml composer.json tests/
git commit -m "test #90: modernisera phpunit-harnessen (Laravel 13, PSR-4)"
```

---

### Task 2: `CrimeEvent::getIconGroup()`

Mappar `parsed_title` till en av åtta ikongrupper. 65 distinkta värden förekommer på 30 dagar, så mappningen görs med substring-matchning i **prioritetsordning** — inte exakta träffar — så nya polis-kategorier hamnar rätt automatiskt.

Ordningen är inte kosmetisk. Tre fallgropar:

- `"Mordbrand"` innehåller både `"mord"` (→ `vald`) och `"brand"` (→ `brand`). Specen säger `brand`, alltså måste `brand` prövas **före** `vald`.
- `"Rattfylleri"` innehåller `"fylleri"` (→ `person`). Specen säger `trafik`, alltså måste `trafik` prövas **före** `person`.
- `"Trafikolycka"` innehåller `"olycka"` (→ `olycka`). Specen säger `trafik`, alltså `trafik` före `olycka`.

Ordningen `trafik → sammanfattning → brand → vald → stold → person → olycka` löser alla tre.

**Files:**

- Create: `tests/Unit/CrimeEventIconGroupTest.php`
- Modify: `app/CrimeEvent.php` (ny const + ny metod)

**Interfaces:**

- Consumes: `Tests\TestCase` från Task 1
- Produces: `CrimeEvent::getIconGroup(): string` som returnerar exakt en av `'trafik'`, `'sammanfattning'`, `'brand'`, `'vald'`, `'stold'`, `'person'`, `'olycka'`, `'ovrigt'`. Task 4 och 5 konsumerar dessa strängar som CSS-klassuffix och som `$group`-prop.

- [ ] **Step 1: Skriv det fallerande testet**

Skapa `tests/Unit/CrimeEventIconGroupTest.php`:

```php
<?php

namespace Tests\Unit;

use App\CrimeEvent;
use Tests\TestCase;

class CrimeEventIconGroupTest extends TestCase
{
    private function grupp(?string $parsedTitle): string
    {
        $event = new CrimeEvent();
        $event->parsed_title = $parsedTitle;

        return $event->getIconGroup();
    }

    /**
     * @return array<string, array{0: ?string, 1: string}>
     */
    public static function kategoriProvider(): array
    {
        return [
            // Trafik — 34 % av volymen
            'trafikolycka'            => ['Trafikolycka', 'trafik'],
            'trafikolycka personskada' => ['Trafikolycka,  personskada', 'trafik'],
            'trafikkontroll'          => ['Trafikkontroll', 'trafik'],
            'trafikbrott'             => ['Trafikbrott', 'trafik'],

            // Fallgrop: "Rattfylleri" innehåller "fylleri" (→ person)
            'rattfylleri blir trafik' => ['Rattfylleri', 'trafik'],

            // Sammanfattning — 22 %
            'sammanfattning natt'     => ['Sammanfattning natt', 'sammanfattning'],
            'sammanfattning kvall'    => ['Sammanfattning kväll och natt', 'sammanfattning'],

            // Brand — 7 %
            'brand'                   => ['Brand', 'brand'],

            // Fallgrop: "Mordbrand" innehåller "mord" (→ vald)
            'mordbrand blir brand'    => ['Mordbrand', 'brand'],

            // Vald — 9 %
            'misshandel'              => ['Misshandel', 'vald'],
            'ran'                     => ['Rån', 'vald'],
            'mord utan brand'         => ['Mord/dråp, försök', 'vald'],

            // Stold — 5 %
            'stold'                   => ['Stöld', 'stold'],
            'stold inbrott'           => ['Stöld/inbrott', 'stold'],
            'skadegorelse'            => ['Skadegörelse', 'stold'],

            // Person — 4 %
            'forsvunnen person'       => ['Försvunnen person', 'person'],
            'fylleri utan ratt'       => ['Fylleri', 'person'],

            // Olycka — 3 %
            'arbetsplatsolycka'       => ['Arbetsplatsolycka', 'olycka'],

            // Fallback — ~16 %
            'knivlagen'               => ['Knivlagen', 'ovrigt'],
            'vapenlagen'              => ['Vapenlagen', 'ovrigt'],
            'bedrageri'               => ['Bedrägeri', 'ovrigt'],
            'information'             => ['Information', 'ovrigt'],
            'null ger fallback'       => [null, 'ovrigt'],
            'tom strang ger fallback' => ['', 'ovrigt'],
        ];
    }

    /**
     * @dataProvider kategoriProvider
     */
    public function test_kategori_mappas_till_ratt_ikongrupp(?string $parsedTitle, string $forvantad): void
    {
        $this->assertSame($forvantad, $this->grupp($parsedTitle));
    }

    public function test_gruppen_ar_alltid_en_kand_grupp(): void
    {
        $kanda = ['trafik', 'sammanfattning', 'brand', 'vald', 'stold', 'person', 'olycka', 'ovrigt'];

        foreach (['Trafikolycka', 'Mordbrand', 'Rattfylleri', 'Knivlagen', 'Något Helt Nytt'] as $titel) {
            $this->assertContains($this->grupp($titel), $kanda, "Okänd grupp för: {$titel}");
        }
    }
}
```

- [ ] **Step 2: Kör testet och verifiera att det fallerar**

```bash
docker compose exec -T app ./vendor/bin/phpunit tests/Unit/CrimeEventIconGroupTest.php
```

Förväntat: FAIL med `Call to undefined method App\CrimeEvent::getIconGroup()`.

- [ ] **Step 3: Implementera `ICON_GROUPS` + `getIconGroup()`**

Lägg i `app/CrimeEvent.php`, direkt före `getKortKartbildUrl()` (rad 176):

```php
    /**
     * Kategori → ikongrupp. 65 distinkta parsed_title förekommer på 30 dagar,
     * så vi matchar på substring i prioritetsordning istället för exakta
     * värden — nya polis-kategorier hamnar då rätt automatiskt.
     *
     * ORDNINGEN ÄR SIGNIFIKANT. Tre överlapp måste lösas av ordningen:
     *   - "Mordbrand" matchar både "mord" och "brand" → brand före vald
     *   - "Rattfylleri" matchar "fylleri" → trafik före person
     *   - "Trafikolycka" matchar "olycka" → trafik före olycka
     *
     * @var array<string, array<int, string>>
     */
    private const ICON_GROUPS = [
        'trafik'         => ['trafikolycka', 'trafikkontroll', 'trafikbrott', 'rattfylleri', 'olovlig körning'],
        'sammanfattning' => ['sammanfattning'],
        'brand'          => ['brand'],
        'vald'           => ['misshandel', 'rån', 'våldtäkt', 'mord', 'dråp', 'olaga hot'],
        'stold'          => ['stöld', 'inbrott', 'skadegörelse'],
        'person'         => ['försvunnen person', 'fylleri', 'omhändertagande'],
        'olycka'         => ['arbetsplatsolycka', 'sjöolycka', 'olycka'],
    ];

    /**
     * Ikongrupp för händelsen, används av <x-crimeevent.icon> (todo #90).
     * Returnerar 'ovrigt' när inget matchar — det är ~16 % av volymen, så
     * fallbacken ska vara en neutral ikon och inte läsas som ett fel.
     */
    public function getIconGroup(): string
    {
        $title = mb_strtolower($this->parsed_title ?? '');

        if ($title === '') {
            return 'ovrigt';
        }

        foreach (self::ICON_GROUPS as $group => $needles) {
            foreach ($needles as $needle) {
                if (str_contains($title, $needle)) {
                    return $group;
                }
            }
        }

        return 'ovrigt';
    }
```

- [ ] **Step 4: Kör testet och verifiera grönt**

```bash
docker compose exec -T app ./vendor/bin/phpunit tests/Unit/CrimeEventIconGroupTest.php
```

Förväntat: `OK (25 tests, ...)` — alla gröna.

- [ ] **Step 5: Kör statisk analys**

```bash
docker compose exec app composer analyse
```

Förväntat: inga NYA fel jämfört med `phpstan-baseline.neon`.

- [ ] **Step 6: Commit**

```bash
git add tests/Unit/CrimeEventIconGroupTest.php app/CrimeEvent.php
git commit -m "feat #90: CrimeEvent::getIconGroup() mappar kategori till ikongrupp"
```

---

### Task 3: Radietak och padding för thumbnails

Två fixar i `StaticMapUrlBuilder`, båda **bara för `$density === 'low'`** (thumbnails). Storbilderna på single-event kör `density='high'` och ska inte röras alls.

1. **Radietak 1 500 m.** `lan`-precision (5,2 % av volymen) har 5 000 m radie, vilket auto-zoomar ut till ren skog utan ortnamn. `far`/`veryfar` (0,9 %) saknar radie helt och faller igenom till `closeUpUrl()` som ritar en **rektangel** — annat visuellt språk mitt i en cirkel-lista.
2. **Padding 0,35 → 0,6.** Cirkeln fyller idag nästan hela rutan, så ortnamnen klipps vid bildkanten ("Jukkasjär…").

**Avvikelse från specen:** specen skrev att `padding` höjs generellt. Här höjs den bara för thumbnails. Det fixar klippet där det syns (thumbs) utan att röra en enda storbild, alltså strikt lägre risk. Specens riskrad om "visuell kontroll på single-event" blir därmed inte tillämplig.

**Files:**

- Create: `tests/Unit/StaticMapUrlBuilderThumbTest.php`
- Modify: `app/Services/StaticMapUrlBuilder.php:26-31` (ny const), `:45-70` (`circleUrl()`)

**Interfaces:**

- Consumes: `Tests\TestCase` från Task 1
- Produces: `StaticMapUrlBuilder::thumbRadius(?int $radius): int` — publik så den kan testas isolerat. `circleUrl()` behåller sin befintliga signatur `(CrimeEvent $event, int $width = 320, int $height = 320, int $scale = 1, string $density = 'high'): string`.

- [ ] **Step 1: Skriv det fallerande testet**

Skapa `tests/Unit/StaticMapUrlBuilderThumbTest.php`:

```php
<?php

namespace Tests\Unit;

use App\CrimeEvent;
use App\Services\StaticMapUrlBuilder;
use Tests\TestCase;

class StaticMapUrlBuilderThumbTest extends TestCase
{
    private StaticMapUrlBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = new StaticMapUrlBuilder();
    }

    public function test_lan_radie_kapas_till_takvardet(): void
    {
        // lan är 5 000 m i PRECISION_RADIUS — ska kapas till 1 500.
        $this->assertSame(1500, $this->builder->thumbRadius(5000));
    }

    public function test_mindre_radie_lamnas_orord(): void
    {
        // closest (150), street (400) och town (1 500) ligger under taket.
        $this->assertSame(150, $this->builder->thumbRadius(150));
        $this->assertSame(400, $this->builder->thumbRadius(400));
        $this->assertSame(1500, $this->builder->thumbRadius(1500));
    }

    public function test_saknad_radie_far_takvardet(): void
    {
        // far/veryfar saknar radie helt — ska få taket istället för att
        // falla igenom till closeUpUrl() och dess rektangel.
        $this->assertSame(1500, $this->builder->thumbRadius(null));
    }

    public function test_thumbnail_far_hogre_padding(): void
    {
        $url = $this->builder->circleUrl($this->event(), 140, 140, 1, 'low');

        $this->assertStringContainsString('padding=0.6', $url);
        $this->assertStringNotContainsString('padding=0.35', $url);
    }

    public function test_storbild_behaller_ursprunglig_padding(): void
    {
        $url = $this->builder->circleUrl($this->event(), 617, 463, 1, 'high');

        $this->assertStringContainsString('padding=0.35', $url);
    }

    public function test_thumbnail_ritar_cirkel_for_veryfar(): void
    {
        // veryfar saknar radie i PRECISION_RADIUS. Förr gav det closeUpUrl()
        // (rektangel); nu ska det bli en cirkel-URL med path-parametrar.
        $url = $this->builder->circleUrl($this->event('veryfar'), 140, 140, 1, 'low');

        $this->assertStringContainsString('path=', $url);
        $this->assertStringContainsString('static/auto/140x140.jpg', $url);
        $this->assertStringContainsString('padding=0.6', $url);
    }

    public function test_precisionsfixturerna_ger_forvantad_niva(): void
    {
        // Skyddsnät: om getViewportSize()-trösklarna ändras ska det här
        // testet falla, inte de andra på ett förvirrande sätt.
        $this->assertSame('closest', $this->event('closest')->getViewPortSizeAsString());
        $this->assertSame('street', $this->event('street')->getViewPortSizeAsString());
        $this->assertSame('town', $this->event('town')->getViewPortSizeAsString());
        $this->assertSame('lan', $this->event('lan')->getViewPortSizeAsString());
        $this->assertSame('veryfar', $this->event('veryfar')->getViewPortSizeAsString());
    }

    /**
     * Precisionen räknas ut av getViewportSize() som ren aritmetik på
     * viewport-fälten: (ne_lat - sw_lat) + (ne_lng - sw_lng). Trösklarna i
     * getViewPortSizeAsString() är >20 veryfar, >6 far, >0.8 lan, >0.1 town,
     * >0.05 street, annars closest.
     *
     * OBS: spannet för 'closest' måste vara skilt från noll. Summan exakt 0
     * ger "veryfar" på grund av en bugg i getViewPortSizeAsString() —
     * `switch ($size)` jämför $size mot booleanen `$size > 20`, och PHP:s
     * lösa jämförelse gör att `0 == false` är sant. Buggen är latent i prod
     * (2 165 events träffas men ingen av dem har koordinat, så ingen
     * kartbild renderas) och spåras i todo #91, inte här.
     */
    private function event(string $precision = 'closest'): CrimeEvent
    {
        $span = match ($precision) {
            'veryfar' => 13.0,  // summa 26
            'far'     => 4.0,   // summa 8
            'lan'     => 0.5,   // summa 1.0
            'town'    => 0.1,   // summa 0.2
            'street'  => 0.04,  // summa 0.08
            'closest' => 0.01,  // summa 0.02 — se kommentaren ovan, inte 0
        };

        $event = new CrimeEvent();
        $event->id = 123456;
        $event->location_lat = 59.3293;
        $event->location_lng = 18.0686;
        $event->viewport_southwest_lat = 59.3293 - $span / 2;
        $event->viewport_northeast_lat = 59.3293 + $span / 2;
        $event->viewport_southwest_lng = 18.0686 - $span / 2;
        $event->viewport_northeast_lng = 18.0686 + $span / 2;

        return $event;
    }
}
```

- [ ] **Step 2: Kör testet och verifiera att det fallerar**

```bash
docker compose exec -T app ./vendor/bin/phpunit tests/Unit/StaticMapUrlBuilderThumbTest.php
```

Förväntat: FAIL med `Call to undefined method App\Services\StaticMapUrlBuilder::thumbRadius()`.

- [ ] **Step 3: Lägg till konstanten och `thumbRadius()`**

I `app/Services/StaticMapUrlBuilder.php`, direkt efter `PRECISION_RADIUS`-konstanten (efter rad 31):

```php
    /**
     * Takradie för thumbnails (todo #90). `lan` är 5 000 m vilket auto-zoomar
     * ut till ren skog i 140 px, och far/veryfar saknar radie helt och föll
     * förr igenom till closeUpUrl() som ritar en rektangel — annat visuellt
     * språk mitt i en lista med cirklar.
     *
     * Cirkeln blir mindre sann för de ~6 % som kapas: den visar ett snävare
     * område än precisionen motiverar. Avsiktligt — alternativet för
     * far-fallet var en rektangel över halva Sverige, vilket inte är
     * ärligare, bara oläsligt. getMapAltText() och bildtexten på
     * single-event bär det egentliga "ungefär här"-förbehållet.
     */
    private const THUMB_MAX_RADIUS_METERS = 1500;

    /**
     * Radie att använda för en thumbnail: kapad vid takvärdet, och
     * takvärdet även när precisionen saknas helt.
     */
    public function thumbRadius(?int $radius): int
    {
        return min($radius ?? self::THUMB_MAX_RADIUS_METERS, self::THUMB_MAX_RADIUS_METERS);
    }
```

- [ ] **Step 4: Koppla in taket och paddingen i `circleUrl()`**

I `circleUrl()`, ersätt raderna som idag lyder:

```php
        $radius = self::PRECISION_RADIUS[$event->getViewPortSizeAsString()] ?? null;
        if ($radius === null) {
            return $this->closeUpUrl($event, $width, $height, $scale);
        }
```

med:

```php
        $radius = self::PRECISION_RADIUS[$event->getViewPortSizeAsString()] ?? null;

        if ($density === 'low') {
            // Thumbnails: kapa radien och ge cirkeln en garanterad radie även
            // när precisionen saknas, så vi aldrig hamnar i closeUpUrl():s
            // rektangel mitt i en cirkel-lista (todo #90).
            $radius = $this->thumbRadius($radius);
        } elseif ($radius === null) {
            return $this->closeUpUrl($event, $width, $height, $scale);
        }
```

Och ersätt padding-raden:

```php
        $params = ['latlng=1', 'padding=0.35'];
```

med:

```php
        // Högre padding på thumbnails så cirkeln inte fyller rutan och
        // ortnamnen slutar klippas vid bildkanten. Storbilder är oförändrade.
        $params = ['latlng=1', 'padding=' . ($density === 'low' ? '0.6' : '0.35')];
```

- [ ] **Step 5: Kör testet och verifiera grönt**

```bash
docker compose exec -T app ./vendor/bin/phpunit tests/Unit/StaticMapUrlBuilderThumbTest.php
```

Förväntat: `OK (7 tests, ...)`.

- [ ] **Step 6: Titta på de faktiska bilderna före/efter**

```bash
mkdir -p tmp-90-kartbilder && cd tmp-90-kartbilder
# Före-bilder finns redan hämtade i .playwright-mcp/th-*.jpg (padding=0.35).
# Hämta efter-bilder från lokal dev för samma precisionsnivåer:
for id in 506839 506835 506838 506836 506815; do
  curl -sSL -o "efter-$id.jpg" "http://brottsplatskartan.test:8350/k/v1/circle-low-$id-140x140@2x.jpg"
done
ls -la
```

Verifiera visuellt: ortnamnen ska ligga innanför bildkanten (inte klippta), och `506836` (`lan`) + `506815` (`far`) ska nu visa en **cirkel**, inte en rektangel eller ren skog. Om lokal DB saknar de id:na — välj egna via `docker compose exec app php artisan tinker` och `App\CrimeEvent::whereNotNull('location_lat')->latest()->limit(20)->get()`.

- [ ] **Step 7: Kör statisk analys**

```bash
docker compose exec app composer analyse
```

Förväntat: inga nya fel.

- [ ] **Step 8: Commit**

```bash
git add tests/Unit/StaticMapUrlBuilderThumbTest.php app/Services/StaticMapUrlBuilder.php
git commit -m "fix #90: radietak 1500m + padding 0.6 för kart-thumbnails"
```

---

### Task 4: Ikonkomponent

En 24 px SVG per ikongrupp. Ingen extern ikonuppsättning, inga extra requests — glyferna är enkla geometriska former som renderar identiskt överallt och är läsbara i 24 px.

**Files:**

- Create: `resources/views/components/crimeevent/icon.blade.php`
- Modify: `resources/views/design.blade.php` (lägg till ikongalleri)

**Interfaces:**

- Consumes: `CrimeEvent::getIconGroup()` från Task 2 (anropas av föräldern, komponenten tar emot resultatet som `$group`)
- Produces: `<x-crimeevent.icon :group="$group" />` som renderar en `<svg>` med klassen `ListEvent__iconSvg`. Task 5 använder den inuti `.ListEvent__icon`.

- [ ] **Step 1: Skapa komponenten**

Skapa `resources/views/components/crimeevent/icon.blade.php`:

```blade
@props([
    // Ikongrupp från CrimeEvent::getIconGroup() (todo #90).
    'group' => 'ovrigt',
])

@php
    // Enkla geometriska glyfer istället för en extern ikonuppsättning:
    // läsbara i 24 px, inga extra requests, renderar identiskt överallt.
    $paths = [
        'trafik' => '<path d="M5 15h14v-3l-1.5-4H6.5L5 12v3z"/><circle cx="7.5" cy="16.5" r="1.5"/><circle cx="16.5" cy="16.5" r="1.5"/>',
        'sammanfattning' => '<rect x="4" y="6" width="16" height="2" rx="1"/><rect x="4" y="11" width="16" height="2" rx="1"/><rect x="4" y="16" width="10" height="2" rx="1"/>',
        'brand' => '<path d="M12 3c2 3 5 5 5 9a5 5 0 0 1-10 0c0-2 1-3 2-4 0 1 .5 2 1.5 2C12 10 11 6 12 3z"/>',
        'vald' => '<path d="M12 4 2 20h20L12 4zm-1 5h2v6h-2V9zm0 8h2v2h-2v-2z"/>',
        'stold' => '<path d="M8 10V8a4 4 0 0 1 8 0v2h1a1 1 0 0 1 1 1v7a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1v-7a1 1 0 0 1 1-1h1zm2 0h4V8a2 2 0 0 0-4 0v2z"/>',
        'person' => '<circle cx="12" cy="8" r="3.5"/><path d="M5 20a7 7 0 0 1 14 0z"/>',
        'olycka' => '<path d="M10 3h4v7h7v4h-7v7h-4v-7H3v-4h7z"/>',
        'ovrigt' => '<circle cx="12" cy="12" r="8.5" fill="none" stroke="currentColor" stroke-width="2"/><rect x="11" y="10.5" width="2" height="6.5" rx="1"/><circle cx="12" cy="7.5" r="1.25"/>',
    ];

    $key = isset($paths[$group]) ? $group : 'ovrigt';
@endphp

<svg class="ListEvent__iconSvg" viewBox="0 0 24 24" width="16" height="16"
    fill="currentColor" aria-hidden="true" focusable="false">
    {!! $paths[$key] !!}
</svg>
```

- [ ] **Step 2: Lägg ett ikongalleri i `design.blade.php`**

Lägg in före den avslutande delen av `@section('content')` i `resources/views/design.blade.php`:

```blade
    {{-- Ikongrupper (todo #90) --}}
    <section class="u-margin-top-double">
        <h3><code>&lt;x-crimeevent.icon /&gt;</code> — alla grupper</h3>
        <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
            @foreach (['trafik', 'sammanfattning', 'brand', 'vald', 'stold', 'person', 'olycka', 'ovrigt'] as $grupp)
                <span style="display: inline-flex; align-items: center; gap: 0.35rem;">
                    <span class="ListEvent__icon ListEvent__icon--{{ $grupp }}">
                        <x-crimeevent.icon :group="$grupp" />
                    </span>
                    <code>{{ $grupp }}</code>
                </span>
            @endforeach
        </div>
    </section>
```

- [ ] **Step 3: Rensa view-cachen och titta på galleriet**

```bash
docker compose exec app php artisan view:clear
open http://brottsplatskartan.test:8350/design
```

Förväntat: åtta ikoner renderar. De är ännu ofärgade och oformade — `.ListEvent__icon`-klasserna kommer i Task 5. Verifiera bara att varje `<svg>` faktiskt ritar en igenkännbar form och att ingen är tom.

- [ ] **Step 4: Commit**

```bash
git add resources/views/components/crimeevent/icon.blade.php resources/views/design.blade.php
git commit -m "feat #90: ikonkomponent för de åtta kategorigrupperna"
```

---

### Task 5: `list-item` blir det enda formatet

Skriv om `list-item.blade.php`: 140 px thumb, kategoriikon, teaser bakom prop, `first` för LCP, och de två identiska kartbild-grenarna slås samman. CSS-layouten går från `float` till `flex`.

**Namnfälla:** klassen `.ListEvent__teaser` används idag för **rubriken**, inte för en teaser. Den nya teasern får därför klassen `.ListEvent__excerpt`. Byt inte namn på den befintliga — `.widget__listItem__title` och `.widget__listItems--city`-reglerna hänger på nuvarande struktur.

**Files:**

- Modify: `resources/views/components/crimeevent/list-item.blade.php` (skriv om)
- Modify: `public/css/styles.css:1046-1066` (`.ListEvent`-blocket), `:1-31` (två nya färgvariabler)

**Interfaces:**

- Consumes: `CrimeEvent::getIconGroup()` (Task 2), `<x-crimeevent.icon>` (Task 4), `getKortKartbildUrl(string $mode, int $width, int $height, int $scale = 1, bool $absolute = false): string`, `getParsedContentTeaser($length = 160)`, `getMapAltText(string $variant = 'close'): string`, `hasMapImage()`
- Produces: `<x-crimeevent.list-item :event="$event" detailed teaser :first="$loop->first" />`. Befintliga props `detailed`, `mapDistance`, `showMap` behålls oförändrade — `showMap=false` används av `resources/views/errors/404.blade.php`.

- [ ] **Step 1: Skriv om komponenten**

Ersätt hela innehållet i `resources/views/components/crimeevent/list-item.blade.php`:

```blade
@props([
    'event',
    'detailed' => false,
    'mapDistance' => null,
    'showMap' => true,
    // Teaser under rubriken, clampad till 2 rader. Startsidan sätter den;
    // övriga vyer är oförändrade (todo #90).
    'teaser' => false,
    // Första kortet i listan — loading=eager + fetchpriority=high för LCP.
    // Måste passas explicit eftersom $loop inte ärvs in i komponenten.
    'first' => false,
])

@php
    $showThumb = $showMap && $event->hasMapImage();
    $isFirst = (bool) $first;

    // Thumb-storleken styrs av --listevent-thumb i styles.css. 140 här är
    // renderad storlek; srcset ger 2x för retina.
    $thumbPx = 140;

    if ($showThumb) {
        // Cirkel-stilen är default (TILESERVER_MAP_STYLE=circle) och ger samma
        // bild oavsett $mapDistance — därför en gren istället för de två
        // identiska som fanns före todo #90.
        $useCircleStyle = config('services.tileserver.map_style') === 'circle';

        if ($useCircleStyle) {
            $thumbSrc = $event->getKortKartbildUrl('circle-low', $thumbPx, $thumbPx);
            $thumbSrc2x = $event->getKortKartbildUrl('circle-low', $thumbPx, $thumbPx, 2);
            $altVariant = 'close';
        } elseif ($mapDistance === 'near') {
            $thumbSrc = $event->getStaticImageSrc($thumbPx, $thumbPx);
            $thumbSrc2x = $event->getStaticImageSrc($thumbPx, $thumbPx, 2);
            $altVariant = 'close';
        } else {
            $thumbSrc = $event->getStaticImageSrcFar($thumbPx, $thumbPx);
            $thumbSrc2x = $event->getStaticImageSrcFar($thumbPx, $thumbPx, 2);
            $altVariant = 'far';
        }
    }

    $iconGroup = $event->getIconGroup();
@endphp

<li
    class="
        ListEvent
        widget__listItem
        @if (isset($event->location_geometry_type)) Event--distance_{{ $event->getViewPortSizeAsString() }} @endif
    "
>
    @if ($showThumb)
        <a class="ListEvent__imageLink" href="{{ $event->getPermalink() }}">
            <img
                loading="{{ $isFirst ? 'eager' : 'lazy' }}"
                @if ($isFirst) fetchpriority="high" @endif
                alt="{{ $event->getMapAltText($altVariant) }}"
                class="ListEvent__image"
                src="{{ $thumbSrc }}"
                srcset="{{ $thumbSrc }} 1x, {{ $thumbSrc2x }} 2x"
                width="{{ $thumbPx }}"
                height="{{ $thumbPx }}"
            />
            <span class="ListEvent__icon ListEvent__icon--{{ $iconGroup }}">
                <x-crimeevent.icon :group="$iconGroup" />
            </span>
        </a>
    @endif

    <div class="ListEvent__body">
        <div class="ListEvent__title">
            <a class="ListEvent__titleLink" href="{{ $event->getPermalink() }}">
                @if ($detailed)
                    <span class="Event__parsedTitle Event__type">{{ $event->parsed_title }}</span>
                @endif
                <span class="ListEvent__teaser widget__listItem__title">{!! $event->getHeadline() !!}</span>
            </a>
        </div>

        @if ($teaser)
            <div class="ListEvent__excerpt">
                {{-- Generös teckenlängd; CSS clampar till exakt 2 rader så
                     radhöjden blir konstant oavsett glyfbredd. --}}
                {!! $event->getParsedContentTeaser(220) !!}
            </div>
        @endif

        <div class="ListEvent__meta widget__listItem__text">
            <p>
                <span class="ListEvent__dateHuman">
                    <time class="Event__dateHuman__time"
                        title="Tidpunkt då Polisen anger att händelsen inträffat"
                        datetime="{{ $event->getParsedDateISO8601() }}">
                        {{ $event->getParsedDateFormattedForHumans() }}
                    </time>
                    &middot; {{ $event->getLocationString(includePrioLocations: true, includeParsedTitleLocation: true, includeAdministrativeAreaLevel1Locations: false) }}
                </span>
            </p>
        </div>
    </div>
</li>
```

- [ ] **Step 2: Lägg till två färgvariabler**

I `public/css/styles.css`, i `:root`-blocket, efter `--color-yellow: #ffcc33;`:

```css
/* Kategoriikoner (todo #90) — orange för brand, violett för stöld.
       Övriga grupper återanvänder befintliga variabler. */
--color-orange: #e8710a;
--color-violet: #6b4fa8;
```

- [ ] **Step 3: Ersätt `.ListEvent`-CSS:en med flex-layout**

Ersätt raderna 1046–1066 i `public/css/styles.css` (blocket `.ListEvent` … `.ListEvent__image`) med:

```css
/* ----------------------------------------------------------------------
   ListEvent — det enda händelseformatet (todo #90).

   En rad: 140 px kartbild med kategoriikon i nedre vänstra hörnet, sedan
   kategori, rubrik (max 2 rader), teaser (max 2 rader) och meta.

   Rubrik och teaser clampas med line-clamp, INTE med teckenlängd —
   teckenbaserad trunkering ger olika radantal beroende på glyfbredd, och
   konstant radhöjd är hela poängen med formatet.

   Ingen stackning på små skärmar. Vid 390 px viewport är raden 358 px, så
   en 140 px thumb lämnar 206 px textkolumn och det bredaste vanliga ordet
   ("Sammanfattning", 17 px/600) är 132 px. Den gamla stackningsregeln
   behövdes för EventHero--small som låg två-i-bredd: 47 % av 358 px minus
   140 px thumb = 28 px text. Enkolumn har inte det problemet.
   ---------------------------------------------------------------------- */
.ListEvent {
    --listevent-thumb: 140px;
    display: flex;
    gap: var(--default-margin-half);
    align-items: flex-start;
    flex-basis: 100%;
}

.ListEvent__imageLink {
    position: relative;
    flex: 0 0 var(--listevent-thumb);
    width: var(--listevent-thumb);
    height: var(--listevent-thumb);
    overflow: hidden;
    border-radius: var(--border-radius-normal);
    line-height: 0;
}

.ListEvent__image {
    display: block;
    width: 100%;
    height: 100%;
    object-fit: cover;
}

/* Kategoriikon som overlay i kartbildens nedre vänstra hörn. Underordnad
   kartan — den ska svara "vad hände" utan att konkurrera om blicken. */
.ListEvent__icon {
    position: absolute;
    bottom: 6px;
    left: 6px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    color: #fff;
    background: var(--color-gray-1);
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.35);
}

.ListEvent__iconSvg {
    display: block;
}

.ListEvent__icon--trafik {
    background: var(--color-blue-police);
}
.ListEvent__icon--sammanfattning {
    background: var(--color-gray-3);
}
.ListEvent__icon--brand {
    background: var(--color-orange);
}
.ListEvent__icon--vald {
    background: var(--color-red-3);
}
.ListEvent__icon--stold {
    background: var(--color-violet);
}
.ListEvent__icon--person {
    background: var(--color-blue-police-active);
}
.ListEvent__icon--olycka {
    background: var(--color-red-2);
}
.ListEvent__icon--ovrigt {
    background: var(--color-gray-1);
}

.ListEvent__body {
    flex: 1 1 auto;
    min-width: 0;
}

.ListEvent__titleLink {
    display: block;
}

/* .ListEvent__teaser är RUBRIKEN (historiskt namn) — clampas till 2 rader. */
.ListEvent__teaser {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    line-clamp: 2;
    overflow: hidden;
    /* Svenska sammansatta ord ("industriområde") får brytas snyggare när
       kolumnen är trång, istället för ord-mitt-brott. */
    overflow-wrap: anywhere;
    hyphens: auto;
}

/* Den faktiska teasern — separat klass eftersom __teaser är upptaget. */
.ListEvent__excerpt {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    line-clamp: 2;
    overflow: hidden;
    margin-top: 0.15rem;
    font-size: var(--font-size-small);
    line-height: 1.35;
    color: var(--color-gray-1);
}

.ListEvent__excerpt p {
    margin: 0;
}
```

- [ ] **Step 4: Verifiera på `/design`**

```bash
docker compose exec app php artisan view:clear
open http://brottsplatskartan.test:8350/design
```

Förväntat: `list-item`-exemplen visar 140 px kartbild med färgad ikon i nedre vänstra hörnet. `:show-map="false"`-varianten visar text utan bild och utan tomt indrag. Ikongalleriet från Task 4 är nu färgat.

- [ ] **Step 5: Kör statisk analys**

```bash
docker compose exec app composer analyse
```

Förväntat: inga nya fel.

- [ ] **Step 6: Commit**

```bash
git add resources/views/components/crimeevent/list-item.blade.php public/css/styles.css
git commit -m "feat #90: list-item blir enda formatet — 140px thumb, ikon, teaser-prop"
```

---

### Task 6: Riv ut `hero`

Startsidan går från tre block till en loop, `hero.blade.php` tas bort, och `EventHero`-CSS:en (inklusive stackningsregeln som inte längre behövs) raderas.

**Files:**

- Modify: `resources/views/parts/events-heroes.blade.php` (skriv om)
- Delete: `resources/views/components/crimeevent/hero.blade.php`
- Modify: `public/css/styles.css:766-872` (ta bort hela `.EventHero`-blocket)
- Modify: `resources/views/design.blade.php` (ta bort hero-exemplen, lägg till teaser-variant)

**Interfaces:**

- Consumes: `<x-crimeevent.list-item>` med props `detailed`, `teaser`, `first` från Task 5
- Produces: inget nytt

- [ ] **Step 1: Skriv om `events-heroes.blade.php`**

Ersätt hela innehållet i `resources/views/parts/events-heroes.blade.php`:

```blade
{{--
Händelselistan på startsidan. Ett format för alla poster (todo #90) —
tidigare 3 large + 6 small två-i-bredd + 8 list-item, vilket bytte raster
två gånger i samma lista.
--}}

@php
    // Antal händelser att visa. Att ändra antalet är ett SEO-beslut om
    // intern länkning, inte ett formatbeslut — hålls därför oförändrat.
    $numEventsToShow = 17;

    if (empty($eventsMostViewedRecentlyCrimeEvents)) {
        return;
    }

    $eventsToShow = $eventsMostViewedRecentlyCrimeEvents->take($numEventsToShow);
@endphp

@if ($eventsToShow->count())
    <ul class="widget__listItems">
        @foreach ($eventsToShow as $event)
            <x-crimeevent.list-item
                :event="$event"
                detailed
                teaser
                :first="$loop->first"
            />
        @endforeach
    </ul>
@endif
```

- [ ] **Step 2: Ta bort hero-komponenten**

```bash
git rm resources/views/components/crimeevent/hero.blade.php
```

- [ ] **Step 3: Ta bort `EventHero`-CSS:en**

**Använd inte radnummer här.** Task 5 lade två variabler i `:root` och skrev om `.ListEvent`-blocket, så alla radnummer i `styles.css` har flyttat sig sedan planen skrevs. Hitta blocket istället:

```bash
grep -n "^\.EventHero {" public/css/styles.css
grep -n "MobileCollapse — viker ihop" public/css/styles.css
```

Radera allt från raden `.EventHero {` till och med raden före kommentarblocket `/* ---------- MobileCollapse — viker ihop ... */`. Det inkluderar `@media (max-width: 480px)`-regeln som stackar thumben — den behövs inte längre, eftersom den fanns för `EventHero--small` som låg två-i-bredd.

Verifiera att blocket före det borttagna är `.LanListing__events b` och att inget annat försvann:

```bash
grep -c "" public/css/styles.css   # antal rader kvar
git diff --stat public/css/styles.css
```

Förväntat: `git diff --stat` visar ~107 borttagna rader och 0 tillagda i detta steg.

Verifiera att inga referenser blev kvar:

```bash
grep -rn "EventHero" public/css/styles.css resources/views/
```

Förväntat: ingen output.

- [ ] **Step 4: Städa `design.blade.php`**

Ta bort de två hero-sektionerna (`{{-- 3. x-crimeevent.hero (size=large) --}}` och `{{-- 4. x-crimeevent.hero (size=small) --}}` med deras `<section>`-innehåll), och lägg till en teaser-variant efter `list-item (detailed)`-exemplet:

```blade
    {{-- x-crimeevent.list-item med teaser (startsidans variant, todo #90) --}}
    <section class="u-margin-top-double">
        <h3><code>&lt;x-crimeevent.list-item detailed teaser /&gt;</code></h3>
        <div class="widget">
            <ul class="widget__listItems">
                <x-crimeevent.list-item :event="$event" detailed teaser :first="true" />
                <x-crimeevent.list-item :event="$event" detailed teaser />
            </ul>
        </div>
    </section>
```

- [ ] **Step 5: Verifiera startsidan lokalt**

```bash
docker compose exec app php artisan view:clear
docker compose exec app php artisan cache:clear
open http://brottsplatskartan.test:8350/
```

Förväntat: `Mest läst`-listan har 17 identiska rader, en kolumn, ingen storleksväxling, ingen tvåkolumnssektion. Första bilden har `loading="eager"` — kontrollera i devtools att `fetchpriority="high"` finns på den och bara på den.

- [ ] **Step 6: Commit**

```bash
git add resources/views/parts/events-heroes.blade.php resources/views/design.blade.php public/css/styles.css resources/views/components/crimeevent/hero.blade.php
git commit -m "refactor #90: ta bort hero-komponenten, startsidan kör ett format"
```

---

### Task 7: Verifiera de sju konsumerande sidorna och mät

`list-item` renderas på sju vyer utöver startsidan. Alla får 140 px thumb och kategoriikon samtidigt. Specen kräver visuell kontroll innan detta anses klart — det är inte gjort tidigare i planen.

**Files:**

- Modify (villkorligt): `public/css/styles.css` — per-vy-override av `--listevent-thumb` om någon sida blir för tung
- Create: `tmp-90-verifiering/REPORT.md`

**Interfaces:**

- Consumes: allt från Task 1–6
- Produces: mätrapport + eventuella CSS-overrides

- [ ] **Step 1: Gå igenom alla sju vyer visuellt**

```bash
open http://brottsplatskartan.test:8350/handelser
open http://brottsplatskartan.test:8350/stockholm
open http://brottsplatskartan.test:8350/typ/misshandel
open http://brottsplatskartan.test:8350/helikopter
open http://brottsplatskartan.test:8350/brand
open http://brottsplatskartan.test:8350/nagon-url-som-inte-finns
```

Plus en enskild händelse (`/handelse/...`, hämta en URL från startsidan) för `mapDistance="near"`-varianten.

För varje vy, kontrollera: radhöjden är konstant, ikonen krockar inte med kartbildens innehåll, och sidan blev inte orimligt lång. Anteckna i `tmp-90-verifiering/REPORT.md` per vy: OK eller vad som behöver justeras.

- [ ] **Step 2: Lägg override om någon vy blev för tung**

Bara om Step 1 hittade problem. Exempel för stadssidan:

```css
/* Stadssidan listar många fler händelser än startsidan — mindre thumb
   där så sidan inte blir orimligt lång (todo #90). */
.widget__listItems--city .ListEvent {
    --listevent-thumb: 96px;
}
```

Om inga problem hittades: hoppa detta steg och notera det i rapporten.

- [ ] **Step 3: Mät sidhöjd och radhöjd mot utgångsläget**

Kör i browserns devtools-konsol på `http://brottsplatskartan.test:8350/` vid 390 px viewport:

```javascript
const rader = [...document.querySelectorAll(".ListEvent")].map((el) =>
    Math.round(el.getBoundingClientRect().height),
);
console.log({
    docHeight: document.documentElement.scrollHeight,
    antalRader: rader.length,
    radhojder: [...new Set(rader)].sort((a, b) => a - b),
    listEventBredd: Math.round(
        document.querySelector(".ListEvent").getBoundingClientRect().width,
    ),
});
```

Förväntat: `radhojder` innehåller **ett eller två** distinkta värden (poster utan bild är kortare). `docHeight` ska ha sjunkit från 11 559. Skriv in faktiska värden i rapporten — om `radhojder` har fler än två värden clampar inte CSS:en som tänkt och Task 5 behöver rättas.

- [ ] **Step 3b: Mät kartbildernas bytevikt**

Specens riskrad om "+80 kB på mobil" är en uppskattning, inte en mätning. Verifiera den faktiska siffran i samma devtools-konsol:

```javascript
const kartbilder = performance
    .getEntriesByType("resource")
    .filter((r) => r.name.includes("/k/v1/"));
console.log({
    antal: kartbilder.length,
    totalKB: Math.round(
        kartbilder.reduce((s, r) => s + (r.encodedBodySize || 0), 0) / 1024,
    ),
    störstaKB: Math.round(
        Math.max(...kartbilder.map((r) => r.encodedBodySize || 0)) / 1024,
    ),
});
```

Referens före ändringen: 90 px-thumbs på `circle-low` låg på 5–9 kB styck. Om totalen överstiger 300 kB — notera det i rapporten som en öppen fråga för uppföljning, det är då mer än uppskattningen och värt ett eget beslut om thumb-storleken.

- [ ] **Step 4: Skriv rapporten**

Skapa `tmp-90-verifiering/REPORT.md` med: mätvärden före/efter (tabellen under "Mätt utgångsläge" som jämförelse), resultat per vy från Step 1, eventuella overrides från Step 2, och om `fetchpriority` sitter rätt.

```bash
npx --yes prettier --write tmp-90-verifiering/REPORT.md
```

- [ ] **Step 5: Kontrollera att rapportmappen är gitignored**

```bash
git check-ignore -v tmp-90-verifiering/REPORT.md
```

Förväntat: en träff på `tmp-*`-mönstret. Om inte — lägg `tmp-90-verifiering/` i `.gitignore` (research-data ska ligga i gitignored `tmp-*`-mapp i projektroten, inte committas).

- [ ] **Step 6: Uppdatera todon**

I `todos/90-ett-listformat-startsidan.md`: sätt `**Status:** klar 2026-XX-XX`, `**Senast uppdaterad:**` till dagens datum, lägg in de uppmätta efter-värdena, och flytta filen till `todos/done/`. Uppdatera raden i `todo.md` så den hamnar i rätt tabell.

```bash
git mv todos/90-ett-listformat-startsidan.md todos/done/
npx --yes prettier --write todo.md todos/done/90-ett-listformat-startsidan.md
```

- [ ] **Step 7: Commit**

```bash
git add todo.md todos/done/90-ett-listformat-startsidan.md public/css/styles.css .gitignore
git commit -m "docs #90: verifiering av sju vyer + mätvärden, todon stängd"
```

---

## Efter merge — mätning

Deploy sker automatiskt vid `git push main`. Hypotesen mäts **30 dagar** efter deploy:

- **Primärt:** sidor/session från `/` på mobil. Utgångsvärde **2,86** (GA4 28 d, 2026-06-29–2026-07-26).
- **Sekundärt:** mobil `docH` (från 11 559 px) och LCP. LCP får inte försämras — `first`-propen i Task 5 är den kritiska detaljen.

Lägg en rad i `todo.md` under "Uppföljningar — datum att komma ihåg" med mätdatum när deployen är gjord.
