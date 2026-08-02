# Indexeringströskel för platssidor — implementationsplan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Platssidor med färre än 5 händelser (all-time) sätts till noindex, och sidor som klarar tröskeln visar alltid innehåll i stället för "Inga händelser har rapporterats".

**Architecture:** En ny cachad "senaste N händelser"-query per gren i `PlatsController::day()` ersätter dagens `exists()`-kontroll och ger tre saker samtidigt: antalet (tröskeln), innehåll att falla tillbaka på, och 404-kontrollen. Ingen migration, ingen ny tabell, inget schemalagt jobb.

**Tech Stack:** Laravel, Eloquent, Redis (`Cache::remember`), Blade. Docker lokalt.

**Spec:** [`docs/superpowers/specs/2026-08-02-plats-indexeringstroskel-design.md`](../specs/2026-08-02-plats-indexeringstroskel-design.md)

## Global Constraints

- Gren: `plats-indexeringstroskel` (redan skapad, specen ligger som `63e3374`).
- Tröskel: **5 händelser (all-time)**. Hämtgräns: **10**.
- **Inga automatiska tester.** Verifiering sker på renderad sida (användarbeslut 2026-08-02).
- All kod, kommentarer och dokumentation **på svenska** (AGENTS.md).
- `composer analyse` (PHPStan level 5) ska köras efter PHP-ändringar och vara ren.
- Villkoren i de nya queryna måste spegla dagens `exists()`-kontroller exakt — annars ändras vilka platser som 404:ar.
- Månadsvyn `month()` får inte röras. Den har redan sin egen grind på rad 500.
- Lokal sajt: `http://brottsplatskartan.test:8350`. Prod: `https://brottsplatskartan.se`.

---

## Filstruktur

| Fil                                        | Ansvar                                                        |
| ------------------------------------------ | ------------------------------------------------------------- |
| `app/Http/Controllers/PlatsController.php` | Allt. Två nya konstanter, två nya hämtmetoder, ändrad `day()` |
| `tmp-plats-troskel/verifiera.sh`           | Verifieringsskript (gitignorerat, ej del av leveransen)       |
| `.gitignore`                               | Rad för `/tmp-plats-troskel/`                                 |

Inga vy-ändringar. `$eventsByDay` byggs redan från `$events` och grupperar per dag, så fallback-händelser från spridda datum renderas korrekt av befintlig Blade.

---

### Task 1: Verifieringsskript och utgångsvärden

**Files:**

- Create: `tmp-plats-troskel/verifiera.sh`
- Modify: `.gitignore`

**Interfaces:**

- Produces: `tmp-plats-troskel/verifiera.sh <basurl>` — skriver en rad per test-URL med HTTP-kod, robots-värde och antal ord i `<main>`. Används av Task 2, 3 och 4.

- [ ] **Step 1: Lägg till gitignore-rad**

Lägg raden `/tmp-plats-troskel/` sist i blocket med övriga `tmp-`-rader i `.gitignore` (runt rad 49).

- [ ] **Step 2: Skapa verifieringsskriptet**

```bash
#!/usr/bin/env bash
# Verifierar indexeringströskeln på platssidor.
# Användning: ./tmp-plats-troskel/verifiera.sh https://brottsplatskartan.se
set -euo pipefail

BAS="${1:?Ange basurl, t.ex. http://brottsplatskartan.test:8350}"

# Fallen från specen. Format: beskrivning|url-path
FALL=(
  "1 händelse, ska bli noindex|/plats/7%20eleven"
  "4 händelser, ska bli noindex|/plats/abborrv%C3%A4gen"
  "5 händelser 4 år gamla, ska bli fallback|/plats/%C3%A5bj%C3%B6rnsgatan"
  "58 händelser färska, ska vara oförändrad|/plats/kviberg"
  "finns inte, ska vara 404|/plats/en-plats-xyz"
)

printf '%-42s %-5s %-8s %s\n' "FALL" "HTTP" "ROBOTS" "ORD"
for rad in "${FALL[@]}"; do
  beskrivning="${rad%%|*}"
  sokvag="${rad##*|}"
  tmpfil="$(mktemp)"
  kod="$(curl -sS -o "$tmpfil" -w '%{http_code}' --max-time 30 "${BAS}${sokvag}")"
  las="$(python3 - "$tmpfil" <<'PY'
import re, sys, html
s = open(sys.argv[1], encoding='utf-8', errors='replace').read()
m = re.search(r'<meta name="robots" content="([^"]*)"', s)
robots = 'noindex' if (m and 'noindex' in m.group(1)) else ('index' if m else '-')
main = re.search(r'<main class="MainContent">(.*?)</main>', s, re.S)
ord_antal = len(re.sub(r'\s+', ' ', html.unescape(re.sub(r'<[^>]+>', ' ', main.group(1)))).split()) if main else 0
print(f'{robots} {ord_antal}')
PY
)"
  rm -f "$tmpfil"
  printf '%-42s %-5s %-8s %s\n' "$beskrivning" "$kod" $las
done
```

- [ ] **Step 3: Gör skriptet körbart och kör mot lokalt**

Kör:

```bash
chmod +x tmp-plats-troskel/verifiera.sh
./tmp-plats-troskel/verifiera.sh http://brottsplatskartan.test:8350
```

Förväntat (utgångsvärdet, före ändringen — alla platssidor är `index` och tomma):

```
FALL                                       HTTP  ROBOTS   ORD
1 händelse, ska bli noindex                200   index    18
4 händelser, ska bli noindex               200   index    17
5 händelser 4 år gamla, ska bli fallback   200   index    17
58 händelser färska, ska vara oförändrad   200   index    29
finns inte, ska vara 404                   404   index    273
```

Ordantalet kan avvika någon enstaka enhet lokalt eftersom lokal data är en kopia från en annan tidpunkt. Det som ska stämma är `HTTP`-kolumnen och att alla platssidor är `index`.

- [ ] **Step 4: Committa**

```bash
git add .gitignore
git commit -m "chore: gitignorera tmp-plats-troskel"
```

Skriptet självt committas inte — mappen är gitignorerad.

---

### Task 2: Hämtmetoder och tröskel i den vanliga grenen

**Files:**

- Modify: `app/Http/Controllers/PlatsController.php` (konstanter överst i klassen, rad ~21; ny metod efter `getEventsInPlatsUncached` som slutar rad 1107; `day()` rad 198–214)

**Interfaces:**

- Consumes: `tmp-plats-troskel/verifiera.sh` från Task 1.
- Produces:
    - `PlatsController::INDEXERAS_FRAN_ANTAL_HANDELSER` (int, 5)
    - `PlatsController::SENASTE_LIMIT` (int, 10)
    - `getSenasteEventsInPlats(string $plats, int $limit): Collection`
    - Lokal variabel `$platsArIndexerbar` (bool) i `day()`, som Task 3 och 4 bygger vidare på.

- [ ] **Step 1: Lägg till konstanterna**

Direkt efter `class PlatsController extends Controller {` (rad 20–21):

```php
    /**
     * Antal händelser (all-time) en plats behöver för att sidan ska
     * indexeras. Under tröskeln blir sidan noindex,follow.
     *
     * Why: 31 477 av 32 275 platssidor renderade tom sida men svarade 200
     * — soft-404 i stor skala. 65 % av platsnamnen är gatunamn med ≤4
     * händelser, ofta från 2016–2019. Se
     * docs/superpowers/specs/2026-08-02-plats-indexeringstroskel-design.md
     */
    private const INDEXERAS_FRAN_ANTAL_HANDELSER = 5;

    /**
     * Hur många av de senaste händelserna vi hämtar. Används både för att
     * avgöra antalet (tröskeln) och som innehåll när datumfönstret är tomt.
     */
    private const SENASTE_LIMIT = 10;
```

- [ ] **Step 2: Lägg till hämtmetoden**

Efter `getEventsInPlatsUncached()` (som slutar på rad 1107):

```php
    /**
     * De senaste händelserna för en plats, oavsett datum.
     *
     * Ger tre saker på en gång: antalet (för indexeringströskeln),
     * innehåll att falla tillbaka på när datumfönstret är tomt, och
     * 404-kontrollen — tom collection betyder att platsen inte finns.
     *
     * Villkoren speglar exakt den exists()-kontroll metoden ersatte.
     * Ändras de ändras vilka platser som 404:ar.
     *
     * Cachas 24 h — en plats all-time-lista ändras sällan, och när nya
     * händelser kommer in plockas de upp av datumfönstret (60 s TTL) som
     * då inte längre är tomt.
     */
    public function getSenasteEventsInPlats(string $plats, int $limit): Collection
    {
        // locations har 445 203 rader med tomt namn. Tom sträng skulle
        // matcha 88 % av tabellen — släpp aldrig in den i queryn.
        if ($plats === '') {
            return collect();
        }

        $cacheKey = 'getSenasteEventsInPlats:' . md5("{$plats}:{$limit}");
        $cacheTTL = 24 * 60 * 60;

        return Cache::remember($cacheKey, $cacheTTL, function () use ($plats, $limit) {
            return CrimeEvent::orderBy('created_at', 'desc')
                ->where(function ($query) use ($plats) {
                    $query->where('parsed_title_location', $plats);
                    $query->orWhere('administrative_area_level_2', $plats);
                    $query->orWhereHas('locations', function ($query) use ($plats) {
                        $query->where('name', '=', $plats);
                    });
                })
                ->with('locations')
                ->limit($limit)
                ->get();
        });
    }
```

- [ ] **Step 3: Initiera variablerna före grenarna**

Direkt före `if ($foundMatchingLan) {` (rad 156), lägg till:

```php
        // Hämta alltid minst tröskeln — annars går antalet inte att avgöra.
        $senasteLimit = max(self::SENASTE_LIMIT, self::INDEXERAS_FRAN_ANTAL_HANDELSER);

        // Default true så län-grenen beter sig som förut tills den kopplas
        // in (Task 3).
        $platsArIndexerbar = true;
```

- [ ] **Step 4: Koppla in i den vanliga grenen**

Ersätt hela `exists()`-blocket i else-grenen (rad 200–214, från kommentaren `// Om inga events för vald period, kolla om något finns alls.` till och med det avslutande `}` för `if (!$events->count())`) med:

```php
            // Senaste händelserna oavsett datum. Ger tröskeln, fallback
            // och 404-kontrollen i ett svep — ersätter tidigare exists().
            $senaste = $this->getSenasteEventsInPlats($plats, $senasteLimit);

            if ($senaste->isEmpty()) {
                abort(404);
            }

            $platsArIndexerbar = $senaste->count() >= self::INDEXERAS_FRAN_ANTAL_HANDELSER;

            // Fallback bara på den datumlösa URL:en. På en explicit
            // datum-URL vore det vilseledande att visa andra dagars
            // händelser under det datumets rubrik.
            if ($events->isEmpty() && $dateOriginalFromArg === null) {
                $events = $senaste;
            }
```

- [ ] **Step 5: Koppla in i `$data`**

I `$data`-arrayen, byt raden

```php
            'robotsNoindex' => \App\Helper::shouldNoindexForDateRoute($dateOriginalFromArg, $date['date']),
```

mot

```php
            // Två oberoende skäl till noindex: platsen är för tunn, eller
            // datum-routen är för gammal (befintlig regel).
            'robotsNoindex' => !$platsArIndexerbar
                || \App\Helper::shouldNoindexForDateRoute($dateOriginalFromArg, $date['date']),
```

- [ ] **Step 6: Rensa cache och verifiera på sidan**

Kör:

```bash
docker compose exec app php artisan cache:clear
docker compose exec app php artisan view:clear
./tmp-plats-troskel/verifiera.sh http://brottsplatskartan.test:8350
```

Förväntat:

```
FALL                                       HTTP  ROBOTS   ORD
1 händelse, ska bli noindex                200   noindex  >17
4 händelser, ska bli noindex               200   noindex  >17
5 händelser 4 år gamla, ska bli fallback   200   index    >17
58 händelser färska, ska vara oförändrad   200   index    29
finns inte, ska vara 404                   404   -        -
```

De tre första ska nu ha **fler ord** än utgångsvärdet 17 — det är fallbacken som slagit till. Är ordantalet kvar på 17 renderas fortfarande "Inga händelser": kontrollera att `$events = $senaste;` faktiskt körs, dvs att `$dateOriginalFromArg` är `null` på den datumlösa URL:en.

- [ ] **Step 7: Kör statisk analys**

```bash
docker compose exec app composer analyse
```

Förväntat: inga nya fel jämfört med `phpstan-baseline.neon`.

- [ ] **Step 8: Committa**

```bash
git add app/Http/Controllers/PlatsController.php
git commit -m "feat: indexeringströskel och fallback för platssidor

Platser med färre än 5 händelser (all-time) blir noindex. Sidor som
klarar tröskeln visar de senaste händelserna i stället för tom sida.
En cachad query ger tröskel, fallback och 404-kontroll — ersätter
exists()-kontrollen."
```

---

### Task 3: Samma sak i län-grenen

**Files:**

- Modify: `app/Http/Controllers/PlatsController.php` (ny metod efter `getEventsInPlatsWithLanUncached` som slutar rad 1000; `day()` rad 161–181)

**Interfaces:**

- Consumes: `INDEXERAS_FRAN_ANTAL_HANDELSER`, `SENASTE_LIMIT`, `$senasteLimit`, `$platsArIndexerbar` från Task 2.
- Produces: `getSenasteEventsInPlatsWithLan(string $platsWithoutLan, string $oneLanName, int $limit): Collection`

- [ ] **Step 1: Lägg till hämtmetoden för län-grenen**

Efter `getEventsInPlatsWithLanUncached()` (som slutar på rad 1000):

```php
    /**
     * De senaste händelserna för en plats i ett visst län, oavsett datum.
     * Län-grenens motsvarighet till getSenasteEventsInPlats().
     *
     * OBS: villkoren speglar exists()-kontrollen den ersatte, vilken
     * inkluderar administrative_area_level_2. Datumfönstrets query
     * (getEventsInPlatsWithLanUncached) gör INTE det. Skillnaden är
     * medveten — 404-beteendet måste vara oförändrat, och fallbacken blir
     * då konsekvent med den vanliga grenen.
     */
    public function getSenasteEventsInPlatsWithLan(string $platsWithoutLan, string $oneLanName, int $limit): Collection
    {
        if ($platsWithoutLan === '') {
            return collect();
        }

        $cacheKey = 'getSenasteEventsInPlatsWithLan:' . md5("{$platsWithoutLan}:{$oneLanName}:{$limit}");
        $cacheTTL = 24 * 60 * 60;

        return Cache::remember($cacheKey, $cacheTTL, function () use ($platsWithoutLan, $oneLanName, $limit) {
            return CrimeEvent::orderBy('created_at', 'desc')
                ->where('administrative_area_level_1', $oneLanName)
                ->where(function ($query) use ($platsWithoutLan) {
                    $query->where('parsed_title_location', $platsWithoutLan);
                    $query->orWhere('administrative_area_level_2', $platsWithoutLan);
                    $query->orWhereHas('locations', function ($query) use ($platsWithoutLan) {
                        $query->where('name', '=', $platsWithoutLan);
                    });
                })
                ->with('locations')
                ->limit($limit)
                ->get();
        });
    }
```

- [ ] **Step 2: Koppla in i län-grenen**

Ersätt `exists()`-blocket i `if ($foundMatchingLan)`-grenen (rad 161–181, från kommentaren `// Om inga events för vald period, kolla om något finns alls.` till och med det avslutande `}` för `if (!$events->count())`) med:

```php
            // Senaste händelserna oavsett datum — tröskel, fallback och
            // 404-kontroll i ett svep. Ersätter tidigare exists(), som
            // lades till i #100 för att stoppa indexerbara skräpsidor.
            $senaste = $this->getSenasteEventsInPlatsWithLan($platsWithoutLan, $matchingLanName, $senasteLimit);

            if ($senaste->isEmpty()) {
                abort(404);
            }

            $platsArIndexerbar = $senaste->count() >= self::INDEXERAS_FRAN_ANTAL_HANDELSER;

            if ($events->isEmpty() && $dateOriginalFromArg === null) {
                $events = $senaste;
            }
```

**Viktigt:** detta måste ligga före raden som skriver om `$plats` till `"X i Y län"` (rad ~187). `$senaste` hämtas med `$platsWithoutLan`, inte det formaterade namnet.

- [ ] **Step 3: Ta bort default-kommentaren**

I Task 2 Step 3 lades `$platsArIndexerbar = true;` in med kommentaren "tills den kopplas in (Task 3)". Byt kommentaren mot:

```php
        // Sätts i respektive gren nedan.
        $platsArIndexerbar = true;
```

- [ ] **Step 4: Verifiera län-grenen på sidan**

Kör:

```bash
docker compose exec app php artisan cache:clear
for u in "/plats/kviberg-v%C3%A4stra-g%C3%B6talands-lan" "/plats/en-plats-xyz-stockholms-lan"; do
  printf '%-50s ' "$u"
  curl -sS -o /tmp/v.html -w 'HTTP %{http_code}  ' --max-time 30 "http://brottsplatskartan.test:8350$u"
  grep -oE '<meta name="robots" content="[^"]*"' /tmp/v.html | head -1 || echo "(ingen robots-tagg)"
done
```

Förväntat: den påhittade platsen ger `HTTP 404`. Den riktiga ger `HTTP 200`. Notera vilket robots-värde den riktiga får och att det stämmer med hur många händelser platsen har.

- [ ] **Step 5: Kör hela verifieringsskriptet igen**

```bash
./tmp-plats-troskel/verifiera.sh http://brottsplatskartan.test:8350
```

Förväntat: **oförändrat** mot Task 2 Step 6 — den vanliga grenen får inte ha påverkats av län-ändringen.

- [ ] **Step 6: Statisk analys och commit**

```bash
docker compose exec app composer analyse
git add app/Http/Controllers/PlatsController.php
git commit -m "feat: indexeringströskel även för plats-med-län-grenen"
```

---

### Task 4: Svep, deploy och verifiering på prod

**Files:**

- Create: `tmp-plats-troskel/svep.sh`

**Interfaces:**

- Consumes: allt från Task 2 och 3.

- [ ] **Step 1: Skapa svep-skriptet**

Fem URL:er kan missa systematiska fel. Detta mäter fördelningen över ett slumpat urval.

```bash
#!/usr/bin/env bash
# Svepet: mäter noindex/index-fördelningen över slumpade platssidor.
# Användning: ./tmp-plats-troskel/svep.sh <basurl> [antal]
set -euo pipefail

BAS="${1:?Ange basurl}"
ANTAL="${2:-200}"

# Slumpade platsnamn från lokala databasen.
NAMN="$(docker compose exec -T app php artisan tinker --execute="
foreach (DB::select('SELECT name FROM locations WHERE name <> \"\" GROUP BY name ORDER BY RAND() LIMIT ${ANTAL}') as \$r) { echo \$r->name . PHP_EOL; }
" 2>/dev/null | grep -v DEPRECATED | grep -v '^\s*$')"

antal_noindex=0
antal_index=0
antal_404=0
antal_ovrigt=0

while IFS= read -r namn; do
  [ -z "$namn" ] && continue
  sokvag="/plats/$(python3 -c "import urllib.parse,sys;print(urllib.parse.quote(sys.argv[1],safe=''))" "$namn")"
  tmpfil="$(mktemp)"
  kod="$(curl -sS -o "$tmpfil" -w '%{http_code}' --max-time 30 "${BAS}${sokvag}" || echo 000)"
  if [ "$kod" = "404" ]; then
    antal_404=$((antal_404 + 1))
  elif [ "$kod" = "200" ]; then
    if grep -q 'name="robots" content="noindex' "$tmpfil"; then
      antal_noindex=$((antal_noindex + 1))
    else
      antal_index=$((antal_index + 1))
    fi
  else
    antal_ovrigt=$((antal_ovrigt + 1))
  fi
  rm -f "$tmpfil"
done <<< "$NAMN"

totalt=$((antal_noindex + antal_index))
echo "noindex: $antal_noindex"
echo "index:   $antal_index"
echo "404:     $antal_404"
echo "övrigt:  $antal_ovrigt"
[ "$totalt" -gt 0 ] && echo "andel noindex: $((100 * antal_noindex / totalt)) %"
```

- [ ] **Step 2: Kör svepet lokalt**

```bash
chmod +x tmp-plats-troskel/svep.sh
./tmp-plats-troskel/svep.sh http://brottsplatskartan.test:8350 200
```

Förväntat: andel noindex nära **64 %** (specens uppmätta 64,4 %). Ligger den under ~55 % eller över ~75 % är tröskeln inte inkopplad som avsett — felsök innan deploy.

Antalet 404 ska vara **0 eller mycket lågt**. Alla namn kommer från `locations`, så de finns per definition — många 404:or betyder att den nya queryn inte speglar den gamla `exists()`-kontrollen, vilket är planens största regressionsrisk.

- [ ] **Step 3: Deploya**

```bash
git push -u origin plats-indexeringstroskel
```

Merge till `main` sker enligt vanlig rutin. Deploy går automatiskt via GitHub Actions vid push till `main` (AGENTS.md).

- [ ] **Step 4: Rensa responscachen på prod**

Responscachen håller sidor i 30 minuter — utan detta serveras gamla svar och verifieringen blir missvisande.

```bash
ssh deploy@brottsplatskartan.se 'cd /opt/brottsplatskartan && docker compose exec -T app php artisan responsecache:clear'
```

- [ ] **Step 5: Verifiera på prod**

```bash
./tmp-plats-troskel/verifiera.sh https://brottsplatskartan.se
./tmp-plats-troskel/svep.sh https://brottsplatskartan.se 200
```

Förväntat: samma utfall som lokalt. Jämför mot utgångsvärdena i specens verifieringstabell.

- [ ] **Step 6: Dokumentera utfallet**

Lägg till en kort resultatsektion sist i specen med uppmätt fördelning från svepet på prod (noindex/index/404) och datum. Kör prettier:

```bash
npx --yes prettier --write docs/superpowers/specs/2026-08-02-plats-indexeringstroskel-design.md
git add docs/superpowers/specs/2026-08-02-plats-indexeringstroskel-design.md
git commit -m "docs: uppmätt utfall efter deploy av platströskeln"
```

- [ ] **Step 7: Lägg upp uppföljning**

Lägg till en rad i `todo.md` under "Uppföljningar — datum att komma ihåg":

| Datum      | Åtgärd                                                                                                                                                                      | Todo |
| ---------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ---- |
| 2026-09-01 | Platströskeln 30 d: GSC "Exkluderad av noindex-tagg" ska ha stigit med ~20 800, och soft-404-rapporten sjunkit. Kontrollera att klick på kvarvarande platssidor inte fallit | —    |

**OBS:** detta kräver att `mcp-gsc` fungerar igen — den var nere 2026-08-01 (`Connection closed`). Fungerar den inte vid uppföljningen, notera det och skjut fram datumet i stället för att stänga uppföljningen.

---

## Egengranskning

**Spec-täckning:**

| Spec-krav                                             | Task                                       |
| ----------------------------------------------------- | ------------------------------------------ |
| Tröskel 5 händelser all-time                          | 2                                          |
| Fallback till senaste oavsett ålder                   | 2, 3                                       |
| Båda grenarna i `day()`                               | 2, 3                                       |
| `robotsNoindex` OR:as med `shouldNoindexForDateRoute` | 2                                          |
| Fallback bara på datumlös URL                         | 2, 3                                       |
| `$limit = max(...)`-vakt                              | 2 (Step 3)                                 |
| Tom-sträng-vakt, båda grenarna                        | 2, 3                                       |
| 24 h cache-TTL                                        | 2, 3                                       |
| Villkoren speglar `exists()`                          | 2, 3; verifieras av 404-räkningen i Task 4 |
| `month()` orörd                                       | — (ingen task rör den)                     |
| Verifiering på sida, inga tester                      | 1, 2, 3, 4                                 |
| `responsecache:clear` vid deploy                      | 4 (Step 4)                                 |

**Kända avvikelser mot specen:**

- Specen skrev `getSenasteEventsInPlats($plats, self::SENASTE_LIMIT)`. Planen skickar `$senasteLimit` (= `max(SENASTE_LIMIT, INDEXERAS_FRAN_ANTAL_HANDELSER)`), vilket är den vakt specen beskriver i sitt konstant-block. Planen är den korrekta formen.
- Specen nämnde inte att län-grenens `exists()` inkluderar `administrative_area_level_2` medan dess datumfönster-query inte gör det. Task 3 Step 1 dokumenterar avvikelsen i koden och behåller `exists()`-varianten, eftersom 404-beteendet väger tyngst.
