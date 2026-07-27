**Status:** aktiv — implementerad 2026-07-27 på branch `todo-90-ett-listformat`, ej merged. Öppen designfråga om radhöjd, se "Utfall".
**Senast uppdaterad:** 2026-07-27

# Todo #90 — Ett listformat för händelser på startsidan

Startsidan visar idag samma sorts händelse i tre olika format i en och
samma lista. Det gör listan svår att överblicka och gör radhöjden
oförutsägbar. Den här todon ersätter alla tre med ett format.

## Nuläge

`parts/events-heroes.blade.php` delar upp 17 händelser i tre block:

| Block | Komponent            | Thumb   | Rubrik | Teaser     | Antal |
| ----- | -------------------- | ------- | ------ | ---------- | ----- |
| 1     | `hero size="large"`  | 240×160 | 20 px  | 110 tecken | 3     |
| 2     | `hero size="small"`  | 160×110 | 16 px  | 70 tecken  | 6     |
| 3     | `list-item detailed` | 90×90   | 16 px  | ingen      | 8     |

Block 2 renderas dessutom två-i-bredd, så rastret byter kolumnantal
mitt i listan och sedan tillbaka igen.

Två följdproblem:

- **Två kartstilar krockar.** `hero` hämtar bilden med stilen `'far'`,
  `list-item` med `'circle-low'`. Eftersom `TILESERVER_MAP_STYLE`
  defaultar till `circle` visar samma lista både rosa rektanglar och
  rosa cirklar.
- **Ojämn vänsterkant.** Flera poster i block 3 saknar bild men behåller
  indraget.

Mätning 2026-07-27: `Mest läst`-sektionen är 3 741 px på mobil (390 px
viewport), hela startsidan 11 559 px. GA4 28 dagar: startsidan har 8 700
landningar varav 74 % mobil, och 2,86 sidor/session på mobil.

## Beslut

### Format

En rad, identisk på alla viewports, med fast höjd:

```
┌──────────┐ TRAFIKKONTROLL          ← Event__type, 12 px, röd, uppercase
│          │ Trafikkontroller i      ← rubrik, 17 px/600, max 2 rader
│  Söder-  │ Norrbotten – rattfylleri
│  tälje   │ Vid kontroller på riks- ← teaser, 14 px, max 2 rader
│    ●     │ väg 97 kontrollerades…
│┌──┐      │ 1 dag sedan · Luleå     ← meta, 13 px, grå
└│🚗│──────┘
 └──┘
   ↑ kategoriikon, 24 px, nedre vänstra hörnet
```

- Kartbild **140×140**, cirkel-stilen (`circle-low`)
- Rubrik max 2 rader, teaser max 2 rader — **klampat med CSS
  `line-clamp`, inte med teckenlängd**. Teckenbaserad trunkering ger
  olika radantal beroende på glyfbredd; CSS-clamp ger samma höjd varje
  gång. Det är det som gör radhöjden konstant, vilket är hela poängen
  med övningen.
- Radhöjd ~156 px, driven av kartbilden. Två teaser-rader finns för att
  textblocket ska fylla ut den höjden, inte för att teasern behöver två
  rader.
- **En kolumn på alla viewports.** Ingen breakpoint, ingen tvetydig
  läsordning.

### Kartbilden

Kartbilden behålls och görs större. Tjänsten heter Brottsplatskartan —
kartan ska bära varumärket, inte ligga som textur bakom en ikon.

Mätt precisionsfördelning på prod, 30 dagar (1 646 händelser):

| Precision | Andel  | Tumnagel                               |
| --------- | ------ | -------------------------------------- |
| `closest` | 49,9 % | tät zoom, gatunät syns                 |
| `town`    | 31,9 % | ortnamn syns                           |
| `street`  | 12,2 % | gatunät syns                           |
| `lan`     | 5,2 %  | 5 km-cirkel, oftast landskap utan namn |
| `far`     | 0,9 %  | faller tillbaka på rektangel-bild      |

**94 % av kartbilderna fungerar redan.** De ser ut som brus på sajten av
tre andra skäl, och alla tre åtgärdas här:

1. De renderas i 90 px. Vid 140 px läser ortnamnet utan ansträngning.
2. `padding=0.35` i `StaticMapUrlBuilder::circleUrl()` gör att cirkeln
   fyller nästan hela rutan, så ortnamnen klipps vid bildkanten
   ("Jukkasjär…"). Höjs till **0,6**.
3. De 6 % ser ut som något helt annat. `far`/`veryfar` faller tillbaka
   på `closeUpUrl()` som ritar en **rektangel** — annat visuellt språk
   mitt i en lista med cirklar.

    Konkret: `PRECISION_RADIUS` i `StaticMapUrlBuilder` får ett tak på
    **1 500 m** (samma som `town`) när `$density === 'low'`, dvs bara för
    thumbnails. `lan` går då från 5 000 → 1 500 m och `far`/`veryfar`
    får 1 500 m istället för att falla igenom till `closeUpUrl()`.
    Storbilderna på single-event påverkas inte, eftersom de kör
    `density='high'`.

    Cirkeln blir då mindre sann för de 6 % — den visar ett snävare område
    än precisionen motiverar. Det är avsiktligt: för `far`-fallet är
    alternativet idag en rektangel över halva Sverige, vilket inte är
    ärligare, bara oläsligt. Radien är ändå redan en approximation, och
    `getMapAltText()` samt bildtexten på single-event bär det egentliga
    "ungefär här"-förbehållet.

Kostnad: 140@2x är 280 px istället för 180 px, ungefär +80 kB över 17
tumnaglar på mobil.

### Kategoriikon

En 24 px SVG-ikon i kartbildens nedre vänstra hörn, som absolut
positionerad overlay. Ingen ny bildpipeline, inga extra requests.
Underordnad kartan — den ska svara "vad hände" utan att konkurrera om
uppmärksamheten.

65 distinkta `parsed_title` förekommer på 30 dagar, men de faller i
grupper. `CrimeEvent::getIconGroup()` mappar med prefix-/substring-match
så nya polis-kategorier hamnar rätt automatiskt:

| Grupp            | Matchar                                                  | Andel |
| ---------------- | -------------------------------------------------------- | ----- |
| `trafik`         | Trafikolycka\*, Trafikkontroll, Trafikbrott, Rattfylleri | 34 %  |
| `sammanfattning` | Sammanfattning\*                                         | 22 %  |
| `vald`           | Misshandel, Rån, Våldtäkt, Mord/dråp                     | 9 %   |
| `brand`          | Brand, Mordbrand                                         | 7 %   |
| `stold`          | Stöld\*, Inbrott, Skadegörelse                           | 5 %   |
| `person`         | Försvunnen person, Fylleri, Omhändertagande              | 4 %   |
| `olycka`         | Arbetsplatsolycka, Sjöolycka                             | 3 %   |
| `ovrigt`         | fallback                                                 | ~16 % |

Fallbacken träffar var sjätte post och måste därför vara en neutral ikon
som inte läses som ett fel.

## Komponentarkitektur

`list-item.blade.php` blir det enda formatet. `hero.blade.php` tas bort,
och `size`-grenarna i `events-heroes.blade.php` försvinner med den.

Motivering — blast radius: `list-item` används redan på `city`,
`single-typ`, `single-plats-month`, `overview-helicopter`, `brand`,
`single-event` och `errors/404`. `hero` finns bara på startsidan. Att
bygga vidare på `list-item` gör startsidan lik resten av sajten istället
för tvärtom, och tar bort en komponent istället för att lägga till en.

Nya props på `list-item`:

- `teaser` (default `false`) — startsidan sätter den. Övriga sidor är
  oförändrade tills det finns skäl att mäta där också.
- `first` (default `false`) — flyttas över från `hero`. Sätter
  `loading="eager"` + `fetchpriority="high"` på första bilden. Den är
  LCP-elementet; tappas den blir det en mätbar regression.

Befintliga props `detailed`, `mapDistance` och `showMap` behålls
(`showMap=false` används av `errors/404.blade.php`).

Thumb-storleken sätts som **en CSS custom property** på `.ListEvent`
(`--listevent-thumb: 140px`), inte som en Blade-prop. En källa till
sanning, och samma komponent renderar på alla sidor så alla får samma
storlek. De sex andra sidorna ska kontrolleras visuellt innan commit —
om någon blir för tung justeras propertyn där.

Ikon-overlayen är på som default, alltså även på de sex andra sidorna.
Den är rent additiv och kan inte försämra något som finns idag.

`events-heroes.blade.php` blir en loop över 17 poster istället för tre
block med `slice()`.

### Cleanup i samma svep

`list-item` har två grenar för `mapDistance === 'near'` respektive
default. Båda anropar `getKortKartbildUrl('circle-low', …)` när
cirkel-stilen är på — de skiljer sig bara i den icke-cirkel-fallback som
inte används i praktiken. Grenarna slås samman.

## Utanför scope

- **Antalet poster.** 17 behålls. Att skära i antalet är ett SEO-beslut
  om intern länkning, inte ett formatbeslut, och hör i en egen todo.
- **Dubblerade listor.** Samma händelser visas idag i tickern, i
  `Mest läst` och i `Senaste händelserna`/`Mest lästa händelserna`
  längst ner. Egen todo.
- **Att vika ihop innehåll.** Redan avfärdat i #71: fyra efter-fix-commits
  (`4c20209`, `b7ace2e`, `9390924`, `be5d7a2`) plockade bort samtliga
  `MobileCollapse`-toggles med motiveringen att intern länkning inte ska
  gömmas för SEO. Vägen till överskådlighet går via densitet och
  konsekvens, inte via toggles.

## Utfall (mätt 2026-07-27)

Implementationen ligger på branch `todo-90-ett-listformat`, commits
`4ce639c..9f336b1`. Full verifieringsrapport:
`tmp-90-verifiering/REPORT.md` (gitignorerad).

**Formatmålet uppnått.** Ett format, en kolumn, kartbild 140×140 med
kategoriikon, rubrik och teaser clampade till 2 rader vardera,
float-layouten borta. `hero.blade.php` raderad. Partialen ger
`EventHero: 0 | ListEvent: 17 | excerpt: 17 | fetchpriority: 1` mot
`EventHero: 9 | ListEvent: 8 | excerpt: 0` före. 34 tester gröna,
PHPStan rent, inga regressioner på de sju konsumerande vyerna.

**Höjdmålet till stor del inte uppnått.** Med teasern påslagen blir
textblocket 168 px, alltså 28 px högre än kartbildens 140 px, så
radhöjden drivs av texten:

|                       | Prognos i denna todo | Uppmätt                    |
| --------------------- | -------------------- | -------------------------- |
| Radhöjd               | 156 px               | 184 px (200 med separator) |
| 17 rader              | ~2 650 px            | ~3 384 px                  |
| Besparing i sektionen | −1 091 px (−29 %)    | ~~−350 px (~~−9 %)         |

Prognosen antog att två teaser-rader skulle fylla ut kartbildens höjd. De
överfyller den. Två saker driver överskottet:

1. Meta-raden wrappar till 2 rader (40 px) även för korta strängar, för
   att textkolumnen bara är ~195–210 px. Clampa meta till 1 rad → −20 px.
2. Teasern på 2 rader kostar 39 px. En rad → −20 px.

Endera åtgärden ger radhöjd ~164 px; båda ger ~144 px, alltså under
kartbildens golv och därmed 156 px konstant igen. **Öppen fråga till
användaren** — koden gör vad designen beslutade, det är prognosen som var
fel.

**Bytevikt:** +65 kB för 17 thumbnails (73 → 138 kB), mot uppskattade
+80 kB. Under gränsen.

**Kunde inte mätas lokalt:** startsidans egen `docH`. `Mest läst` bygger
på `crime_views` senaste 20 minuterna och lokal dev saknar live-trafik.
Mäts på prod efter deploy.

## Förväntad effekt

- `Mest läst`-sektionen på mobil: **3 741 → ~2 650 px**
- Hela startsidan: **11 559 → ~10 500 px** (−9 %)
- Rektangel-vs-cirkel-krocken försvinner när `'far'`-stilen försvinner
  med `hero`
- En komponent färre att underhålla

## Mätbar hypotes

**Sidor/session från `/` på mobil, idag 2,86** (GA4, 28 dagar,
2026-06-29–2026-07-26). Det är talet som säger om överskådligheten blev
bättre eller bara sidan kortare.

Sekundärt: mobil `docH` (idag 11 559 px) och LCP. LCP får inte försämras
— `first`-propen är den kritiska detaljen där.

Mätperiod 30 dagar efter deploy.

## Risker

| Risk                                                      | Hantering                                                               |
| --------------------------------------------------------- | ----------------------------------------------------------------------- |
| Tappad `fetchpriority` på första bilden → LCP-regression  | `first`-prop flyttas medvetet över; verifiera i PSI före/efter          |
| +80 kB på mobil                                           | Acceptabelt mot #71:s mätning (1,37 MB total, tiles 5,1 %); följ upp    |
| `padding`-höjningen ändrar alla kartbilder på hela sajten | Egen commit, visuell kontroll på single-event och overview-vyerna först |
| 140 px-thumben blir för tung på de sex andra sidorna      | CSS custom property → en rad att justera per sida                       |
| Ikongrupperna missar nya polis-kategorier                 | Prefix-match + neutral fallback; `ovrigt` är redan 16 % och ska funka   |
