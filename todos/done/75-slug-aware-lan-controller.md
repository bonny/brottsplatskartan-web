**Status:** klar 2026-05-13 — slug-form (`/lan/skane-lan`) är nu canonical. Display-form + engelska alias 301:as till slug. Alla 21 län verifierade. PHPStan grönt. Helper::lanSlug() centraliserar slug-genereringen.
**Senast uppdaterad:** 2026-05-13

# Todo #75 — Slug-aware LanController (fixa trasig slug-rendering)

## Sammanfattning

Sitemap och cache-warmer skapar redan `/lan/{slug}`-URL:er
(t.ex. `/lan/skane-lan`, `/lan/vastra-gotalands-lan`), men
`LanController::day()` hanterar inte slug-form korrekt. Resultatet är
att slug-URLen renderas med fel display-namn ("vastra gotalands lan"
utan å) i title, meta, canonical, schema.org och alla intern-länkar.

Detta är en aktiv SEO-bugg — inte en feature som saknas — och bedöms
som fas 2:s största single SEO-vinst (~1 350+ clicks/90d enligt
#52 F).

## Bakgrund

### Aktuellt beteende (verifierat 2026-05-13 lokalt)

`curl /lan/vastra-gotalands-lan` returnerar 200 OK med:

- `<title>`: "Brott och händelser från Polisen i **vastra gotalands lan**"
- `meta description`: "i vastra gotalands lan är: …"
- `canonical`: `/lan/vastra%20gotalands%20lan` (URL-encoded mellanslag, fel form)
- Schema.org `AdministrativeArea.name`: "vastra gotalands lan"
- Alla `MonthArchive__link`-länkar: `/lan/vastra%20gotalands%20lan/handelser/2024/06`

### Orsak

`app/Http/Controllers/LanController.php:84`:

```php
$lan = str_replace('-', ' ', $lan);
```

Det här tar slug "vastra-gotalands-lan" → "vastra gotalands lan" och
används direkt i `where('administrative_area_level_1', $lan)`. Detta
matchar inte "Västra Götalands län" i DB — men sidan renderar ändå
(förmodligen via Helper-lookup som hittar en tom result-set + fallback)
med slug-strängen som display-namn.

### Vad som finns för att lösa det

`App\Helper::resolveLanDisplayName($slug)` (rad 324) gör redan
slug→display-mappningen ("skane-lan" → "Skåne län"). Den används från
Helper-context men inte i LanController.

Sitemap (`GenerateSitemap.php:66`) och warmer (`WarmCache.php:45`)
använder `Str::slug($lanName)` — så slug-form är den **avsedda**
formen utåt, men controllern är fortfarande på display-form.

### SEO-impact

Från `tmp-konkurrent-analys/04-seo.md` + #52 baseline:

- `/lan/Skåne län` rankar pos 8.1 på "polisen händelser" (3 151 imp/90d)
- `/lan/Västra Götalands län` pos 7.6 (1 499 imp/90d)
- #52 F skattar ~1 350+ clicks/90d-potential bara från län-URL-fixen
- Cannibalisation: Google ser sannolikt slug-form (sitemap) + display-form
  (intern-länkar + legacy) som olika sidor och rankar sämre på båda

## Förslag

### Steg 1 — Resolve slug → display-name i Controller

I `LanController::day()` (och `month()`, `listLan` om relevant):

```php
public function day(Request $request, $lan, $date = null)
{
    // Acceptera både slug-form ("skane-lan") och display-form ("Skåne län")
    $lanDisplayName = \App\Helper::resolveLanDisplayName($lan) ?? $lan;
    $lanSlug = \Illuminate\Support\Str::slug($lanDisplayName);

    // Resten av controllern använder $lanDisplayName för UI/SEO och
    // $lanDisplayName i where('administrative_area_level_1', …).
    // Variabel $lan tas bort (eller behålls bara som input-binding).
    …
}
```

### Steg 2 — 301 display-form → slug-form

Lägg till en redirect så att `/lan/Skåne län` och `/lan/Skåne%20län` 301:as
till `/lan/skane-lan`. Antagligen i `CityRedirectMiddleware` eller som ny
middleware. Konsoliderar SEO-equity på en canonical-URL.

Måste tänkas igenom: `/lan/Skåne län/handelser/2024/06` ska 301:a till
`/lan/skane-lan/handelser/2024/06` (bevara path-suffix).

### Steg 3 — Canonical + schema.org + intern-länkar

Säkra att `route('lanSingle', ['lan' => …])`-anrop använder slug-form
i `parts/sitefooter.blade.php`, `parts/lan-and-cities.blade.php`,
`statistik.blade.php` etc. Använd `Str::slug($lanName)` (samma som
sitemap) eller en helper `lanRoute($lanName)`.

### Steg 4 — Verifiering

- `curl /lan/skane-lan` → title "i Skåne län", canonical `/lan/skane-lan`
- `curl /lan/Skåne län` → 301 → `/lan/skane-lan`
- `curl /lan/Skåne%20län/handelser/2024/06` → 301 → `/lan/skane-lan/handelser/2024/06`
- Alla 21 län testas (helst via en kort feature-test)
- `composer analyse` grönt
- Stickprov i Caddy-loggar efter deploy: leta efter nya 404:er

### Steg 5 — Mätning

Lägg en uppföljning i `todo.md` för 30d post-deploy (typ 2026-06-13):
GSC-jämförelse på lan-URL-queries — siktar på position-lyft från ~7–8
till ~5–6 på `polisen händelser <län>`-varianter.

## Risker

- **Befintliga external backlinks** pekar på display-form. 301:a löser
  det men kräver att vi verkligen behåller 301-kedjan stabil.
- **Cache-keys kan vara baserade på URL** — Spatie response cache + Redis
  kan ha både slug-form och display-form i cachen. Trolig `cache:clear`
  och `responsecache:clear` efter deploy.
- **Edge case "Stockholms län"** — redan 301:as via #35 till `/stockholm`
  (CityRedirectMiddleware). Säkerställ att slug-fixen inte bryter den
  prioriteringen. Samma för "Uppsala län" → `/uppsala`.
- **`route('lanSingle', ['lan' => $name])`-anrop** i hela kodbasen
  behöver auditeras — om någon skickar in display-form fortsätter den
  pre-301-routen och får en extra hop.

## Confidence

**Hög.** Buggen är konkret och reproducerbar, lösningen är liten
(använd befintlig `resolveLanDisplayName`), GSC-data backar upp värdet,
och risken är hanterbar med 301 + cache-clear. Estimat: 2–4h kod +
testning.

## Beroenden

- Synergi med #52 F (lan-URL legacy-format) — denna todo löser den punkten.
- Bygger på #35 (Stockholm/Uppsala-redirect-mönstret) — använder samma
  approach fast för slug-form istället för city-konsolidering.
- Synergi med #29 (indexerade pages) — om Google har båda formerna
  indexerade, faller den andra ur efter 301-kedjan stabiliserar sig.

## Nästa steg

1. Bekräfta att `Helper::resolveLanDisplayName()` täcker alla 21 län
   (kolla `getAllLanWithStats()` mot mappen).
2. Implementera Steg 1–3 i en branch.
3. Test lokalt med `curl` mot alla 21 län.
4. Deploy + cache-clear.
5. Lägg uppföljning 2026-06-13 i todo.md.

## Genomförande 2026-05-13

Pre-existing kontext: fafbb28 (commit innan denna session) hade redan
löst 404 + engelska-alias-validering i `LanController`, men 301:ade i
fel riktning (slug → display istället för tvärtom).

**Ändringar:**

1. **`Helper::lanSlug($displayName)`** — ny statisk metod, tunn wrapper
   runt `Str::slug()` för att markera intentionen och centralisera om
   strategin ändras.
2. **`LanController::day()` + `month()`** — flippade 301-logiken:
   slug-form blir canonical, display-form och engelska alias 301:as till
   slug. Använder ny `$lanSlug`-variabel för route-anrop, behåller `$lan`
   (display-form) för DB-queries och UI.
3. **Hot-path callsites uppdaterade** så de skickar slug och slipper
   extra 301-hop:
    - `app/CrimeEvent.php:501` (event-kort)
    - `app/Helper.php:394` (`getLanPrevDaysNavInfo`)
    - `app/Http/Controllers/PlatsController.php:287, 543, 838` (breadcrumbs)
    - `resources/views/parts/sitefooter.blade.php` (footer-länkar)
    - `resources/views/parts/lan-and-cities.blade.php` (sidebar-widget)
    - `resources/views/overview-lan.blade.php` (län-översikt + JSON-LD)
    - `resources/views/single-plats-month.blade.php` ("Alla händelser i …")
4. **Resterande callsites** (`RedirectOldPages.php`, `errors/404.blade.php`,
   `trafik/lan.blade.php`, `routes/web.php`-closures) skickar fortfarande
   display-form — de fungerar via 301 men sparar inte hop. Pragmatiskt
   OK eftersom de är låg-trafik. Kan refaktoreras senare vid behov.

### Verifiering

```
/lan/skane-lan                           → 200 OK (canonical)
/lan/Skåne län                           → 301 → /lan/skane-lan
/lan/Skåne County                        → 301 → /lan/skane-lan
/lan/vastra-gotalands-lan                → 200 OK, title "i Västra Götalands län" (med å)
/lan/skane-lan/handelser/2024/06         → 200 OK
/lan/Skåne län/handelser/2024/06         → 301 → /lan/skane-lan/handelser/2024/06
/lan/okant-lan-foobar                    → 404
/lan/stockholms-lan                      → 301 → /stockholm (CityRedirectMiddleware, #35 bevarat)
/lan/uppsala-lan                         → 301 → /uppsala (CityRedirectMiddleware, #35 bevarat)
```

Alla 21 län testade (19 returnerar 200 direkt, 2 — Stockholm/Uppsala —
301:as till stadsida som förväntat).

PHPStan grönt (123/123).

### Uppföljning

Lägg in i `todo.md`s "Uppföljningar"-sektion: **2026-06-13** — GSC-
jämförelse på `/lan/...`-queries efter deploy. Mål: position-lyft från
~7–8 till ~5–6 på "polisen händelser <län>"-varianter. Räknat
potentiell vinst per #52 F: ~1 350+ clicks/90d.
