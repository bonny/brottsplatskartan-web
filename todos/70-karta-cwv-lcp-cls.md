**Status:** aktiv — fix delvis lyckad på prod 2026-05-12. Desktop −64 % (klar). Mobile oförändrad — behöver iteration #2.
**Senast uppdaterad:** 2026-05-12

# Todo #70 — `/karta` CWV: CLS-rotorsak = oreserverad map-höjd

## Sammanfattning

Lighthouse mobile på `https://brottsplatskartan.se/karta` 2026-05-12 gav
Performance score 53; uppföljande Playwright-mätning med
`web-vitals@4`-attribution pinpointade rotorsaken till **CLS**:

- **CLS-rot (desktop, 1366×900):** `div.EventsMap.EventsMap--city.leaflet-container`
  står för 1,1012 av total CLS (3 shifts). Det är kart-containern själv
  som expanderar/skiftar vid Leaflet-init eftersom den saknar reserverad
  höjd. Layer-toggle-texten "Trafikinfo (Trafikverket)" är synlig i
  attribution-stringen men är bara text-innehåll i samma container —
  inte separat shift-källa.
- **CLS-rot (mobile, Pixel 7-emulering):** `footer.SiteFooter` hoppar
  med 0,2620 när `body.map-is-expanded` läggs till — sekundär effekt av
  samma underliggande problem (kart-containerns slut-höjd ändrar
  document flow när `is-expanded`-klassen aktiveras).
- **LCP** (Playwright): mobile 1,2 s / desktop 0,2 s — **good** i båda.
  LCP-element = `div.EventsMap` (Leaflet-kartan). LCP-resourcen är
  `img/share-img-blur.jpg`. Mobile `resourceLoadDelay = 465 ms` — halva
  LCP-budgeten är wait innan resourcen börjar laddas.
- **FCP / TTFB**: båda **good** (FCP 0,6–0,2 s, TTFB 0,1 s).

**Lighthouse-versionen** rapporterade LCP 6,5 s + CLS 0,884 — mer
aggressiv än Playwrights körning (samma 4×CPU-throttling, 1,6 Mbps).
Skillnaden beror sannolikt på Lighthouse-instansens första-besök-
karaktär (kall cache, inga consent-cookies) vs Playwright-körningen som
hade nätverket varmt. Field-data via PSI saknas (kvot slut 2026-05-12).

Verifierat i samma Lighthouse-körning: Trafikverket-layern (default OFF,
lazy) gör inga init-requests. Bara `/api/eventsMap` polisen-feed (15,8 KB)
laddas vid initial paint. Detta är därför inte en regression från
Fas 1 utan ett pre-existing problem.

Råmätning + screenshots i `tmp-cwv/`:

- `tmp-cwv/measure-karta-webvitals.mjs` — Playwright + web-vitals
  attribution-skript (mobile+desktop)
- `tmp-cwv/brottsplatskartan_se_karta_mobile_*.png` + `..._webvitals.json`
- `tmp-cwv/brottsplatskartan_se_karta_desktop_*.png` + `..._webvitals.json`

## Bakgrund

`/karta` är fullskärms-kartsida med stor `EventsMap`-komponent som
LCP-element. Sidan har inte ingått i CWV Fas 1 (#30 fokuserade på
startsidan + ortsidor). Field-data saknas i denna mätning eftersom
PageSpeed Insights API:t var kvotslut — bara lab-data i denna analys.

Soak-mätningen i #50 plockade upp problemet eftersom CWV-regression
var en av Fas 1-gates.

Tidigare CWV-arbete och baseline finns i `tmp-cwv/`.

## Fix (implementerad 2026-05-12, lokalt)

Den verkliga rotorsaken visade sig **inte** vara höjden utan **positions-toggling**:
`.EventsMap.is-expanded { position: fixed !important; ... }`-regeln läggs på av
JS direkt efter Leaflet-mount, vilket flyttar elementet från `position: static`
i normal flow till `position: fixed`. Det är en stor position-shift, inte en
höjd-shift. Höjd-fix på 70dvh → calc(100dvh - vars) är sekundär.

### Implementerad fix

1. **`public/css/events-map.css`**: speglar `.is-expanded`-regeln redan på
   `.EventsMap[data-events-map-size="fullscreen"]`. Initial paint får
   position/höjd som matchar slut-värdet. Lade också rimliga DEFAULT-värden
   på root-vars (`--header-elms-height: 80px` desktop / `120px` mobile) så
   höjden inte ändras dramatiskt när JS sätter de exakta värdena.
2. **`resources/views/layouts/web.blade.php`**: `<body class="… @yield('bodyClass')">`
   så enskilda vyer kan addera body-klasser.
3. **`resources/views/sverigekartan.blade.php`**: `@section('bodyClass', 'map-is-expanded')`
   så body har `position: fixed` redan från första paint på `/karta` —
   eliminerar mobile-footer-shift som triggades av JS-toggle.

### Resultat — lokal mätning (dev, har phpdebugbar)

| Viewport | Före (CLS) | Efter (CLS) | Δ |
|----------|-----------:|------------:|--:|
| Mobile (Pixel 7, 4×CPU, 1,6 Mbps) | 0,262 | 0,062 | −77 % |
| Desktop (1366×900) | 0,579 | 0,003 | −99,6 % |

### Resultat — prod-mätning post-deploy 2026-05-12

| Viewport | Före (CLS) | Efter (CLS) | Δ | Återstående källa |
|----------|-----------:|------------:|--:|-------------------|
| Mobile | 0,262 | **0,262** | **0 %** | `footer.SiteFooter` |
| Desktop | 0,579 | **0,211** | **−64 %** | `footer.SiteFooter` |

**Desktop lyckad** — `.EventsMap`-shifts borta (var 1,10 i 3 shifts före).
Kvarvarande shift = footer.

**Mobile oförändrad** — lokala 0,062-resultatet var en artefakt av
debugbar-layout (debugbar tar plats överst → andra elements positioner
ändras). På prod (utan debugbar) finns inte den artefakten, och footer-
shift kvarstår.

Min hypotes om body.map-is-expanded server-side gick inte hem på mobile.
Möjliga orsaker:

- AdSense FundingChoices laddas async → ändrar layout efter consent-paint
- `dvh` ändras på mobile när URL-bar gömmer/visar sig under load
- `--header-elms-height`-default (120px) matchar inte exakt vad JS räknar
  fram → liten initial-shift på footer
- CSS `body.map-is-expanded { position: fixed }` tar inte hänsyn till
  notification-bar/sitebar/header som har egen `position: fixed`

LCP oförändrad (good i båda viewports — 1,2 s mobile, 0,2 s desktop).

### Restpunkter

- ✅ Deployad till prod 2026-05-12 (commit 4866c81).
- ✅ Mätt mot prod post-deploy — desktop OK, mobile kvar.
- **Mobile iteration #2 behövs** för footer-shift. Möjliga vägar:
    1. Lägg footer i `<aside>` med `position: fixed; bottom: 0` på
       fullscreen-kartan — tar footer ur flow helt.
    2. Reservera plats för footer (`min-height: <footer-höjd>`) på
       container så footer inte hoppar när content omfördelas.
    3. Kolla om `body.map-is-expanded`-CSS-cascade slår innan första
       paint (kanske `<style>`-inline i `<head>` behövs istället för
       extern stylesheet).
    4. Verifiera mobile med `dvh` → testa `vh` istället för att
       eliminera URL-bar-dynamics.
- Vänta 28d för CrUX field-data via PSI (när kvot återkommit).

## Förslag — gammal (förflyttat ovanför till "Fix")

### Primär fix (CLS) — reservera höjd på `.EventsMap`

`div.EventsMap` är källan till 100 % av desktop-CLS och driver
mobile-footer-shiften indirekt. Fixen är CSS-only och låg-risk:

```css
.EventsMap {
    min-height: 70vh; /* eller fast pixel-höjd som matchar förväntad slut-storlek */
}

@media (min-width: 768px) {
    .EventsMap {
        min-height: 80vh;
    }
}
```

`body.map-is-expanded` får `.EventsMap` att växa ytterligare — om
det är full-skärm bör värdet matcha `100vh` minus header/footer-höjd
så `is-expanded`-klassen inte triggar shift.

Verifiera efter fix: kör om `tmp-cwv/measure-karta-webvitals.mjs` mobile
+ desktop. Mål: CLS < 0,1 i båda viewports.

### Sekundär (LCP) — om Playwrights LCP håller efter CLS-fix

LCP är "good" i Playwright men Lighthouse hävdar 6,5 s i sämre
nätverk. `resourceLoadDelay` på mobile var 465 ms — kan adresseras med:

- `<link rel="preconnect" href="https://kartbilder.brottsplatskartan.se">`
  i `<head>` (om vi behöver MapTiles tidigare).
- Lazy-decode optimering på `img/share-img-blur.jpg` (LCP-resource).

Avvakta CrUX/PSI-field-data 28 d efter CLS-fix innan vi ändrar något
för LCP — den kan redan vara "good" på field om Lighthouse-mätningen
var en outlier.

### Mätning

- Kör Lighthouse mobile baseline → fix → kör om. Diff i samma mall
  som `tmp-cwv/`-jämförelser (`*_mobile.json` vs `*_mobile_after.json`).
- Verifiera i CrUX/PageSpeed Insights field-data efter 28d soak — det
  är `p75` som påverkar GSC och söka.

## Risker

- `min-height` på `.EventsMap` kan se konstigt ut om vi har sidor där
  kartan är embed:ad i sidopanel (kolla `EventsMap--city`-modifier:n och
  ev. andra varianter innan vi sätter global regel).
- Om vi sätter `min-height: 70vh` på mobile och device-pixel-ratio är
  hög, kan kartans content-area bli mindre än förväntat → testa på
  Pixel 7-emulering och iPhone 13-storlek.
- AdSense Funding Choices visas senare i flödet — om consent-popup
  triggar shift efter CLS-fix måste även den hanteras separat
  (`position: fixed` på `.fc-dialog-container`).

## Confidence

medel — LCP-fixen kräver experimentation (Leaflet är JS-init-tung).
CLS-fixen är straightforward men kräver att vi accepterar `position:
fixed` på consent-overlay (vilket AdSense kan flagga som "intrusive
interstitial" om felgjort).

## Beroenden

- Fristående från #50 (Fas 1+2+3 ändrar inte `/karta` CWV).
- Eventuell synergi med #66 (`/k/v1/` kartbilder, immutable) om
  preload av tile-domän.

## Inte i scope

- CWV på andra kartsidor (`/lan/{lan}/karta`, etc.) — separat sweep.
- Refactor av `EventsMap`-komponenten.
- Byte av kart-bibliotek.
