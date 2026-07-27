**Status:** aktiv — implementerad 2026-07-27 på branch `todo-90-ett-listformat`, ej merged. Slutgranskningens fynd åtgärdade 2026-07-27, se "Utfall". Öppen designfråga om radhöjd kvarstår (meta-raden).
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

Motivering — blast radius: `hero` finns bara på startsidan, medan
`list-item` redan renderas överallt. Att bygga vidare på `list-item` gör
startsidan lik resten av sajten istället för tvärtom, och tar bort en
komponent istället för att lägga till en.

**Fullständig konsumentlista.** En tidigare version av den här todon
räknade sju vyer. Det var fel — komponenten renderas av **13
templatelägen** i 30 anrop:

| Template              | Anrop | Kontext                                                        |
| --------------------- | ----- | -------------------------------------------------------------- |
| `parts/events-heroes` | 1     | startsidan, enda vyn som sätter `teaser`                       |
| `city`                | 1     | `/stockholm` m.fl., 25 rader                                   |
| `single-typ`          | 1     | `/typ/misshandel`, 10 rader                                    |
| `single-plats-month`  | 1     | månadsarkiv per plats                                          |
| `overview-helicopter` | 2     | `/helikopter`                                                  |
| `brand`               | 1     | `/brand`                                                       |
| `inbrott`             | 2     | `/inbrott`, **40 tumnaglar** — sajtens tyngsta bildsida        |
| `mestLasta`           | 2     | `/mest-last`                                                   |
| `trafik-detail`       | 1     | närliggande händelser på trafikhändelse                        |
| `trafik/lan`          | 1     | trafik per län                                                 |
| `parts/mostViewed`    | 1     | mest lästa-widget                                              |
| `parts/events-by-day` | 1     | inkluderas av `handelser`, `geo`, `single-lan`, `single-plats` |
| `single-event`        | 1     | enda vyn som renderar i `@section('sidebar')`                  |
| `errors/404`          | 2     | `showMap=false`-varianten                                      |
| `design`              | 11    | komponentgalleriet                                             |

`/inbrott` ingick **inte** i byteanalysen nedan (som räknade 17 bilder på
startsidan). Extrapolerat från samma mätning (8 369 B respektive 4 417 B
per bild) blir 40 tumnaglar ca 335 kB mot ca 177 kB förut, alltså ca
+158 kB på den sidan. Följ upp den separat om den blir tung.

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
storlek. Samtliga konsumerande vyer i tabellen ovan ska kontrolleras
visuellt innan commit — om någon blir för tung justeras propertyn där.

Ikon-overlayen är på som default, alltså i alla konsumerande vyer. Den är
rent additiv och kan inte försämra något som finns idag. Notera baksidan:
ikonen är 24 px fast, så en thumb under ~90 px gör att ikonen dominerar
kartbilden. Det är därför sidebaren stackar istället för att krympa
thumben, se "Utfall".

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

## Avsiktliga sidoeffekter

Två ändringar som är avsiktliga men lätta att missa i diffen.

### Alt-texten ändrades på alla listbilder utom `mapDistance="near"`

`list-item.blade.php` sätter `$altVariant = 'close'` när cirkelstilen är
på, oavsett `$mapDistance`. Förut fick default-grenen
`getMapAltText('far')` ("Översiktskarta över Sverige som visar…"); nu blir
det närbildsvarianten på alla vyer som inte skickar
`map-distance="near"` (alltså alla utom `single-event`).

Ändringen behålls: bilden **är** en närbildscirkel sedan
`PRECISION_RADIUS`-taket infördes, så närbildstexten är den sanna
beskrivningen. Den gamla texten beskrev en rektangelbild som inte längre
renderas för thumbnails.

### Deploy-risk: alla listbilder byter URL samtidigt

Både storleken (90×90 → 140×140) och destinations-URL:en (`padding`
0,35 → 0,6, radietak 1 500 m) ändras i samma deploy. Följden:

- Varje listbild blir en miss i nginx-tiles `proxy_cache` (2 GB LRU,
  `inactive=30d`) → kall render i `tileserver-gl` för hela svansen av
  händelser som visas i listor.
- Nya bilder är ca 2× större på disk, och de gamla 90×90-objekten ligger
  kvar upp till 30 dagar → mer eviction inom 2 GB-taket innan det
  stabiliseras.
- `tileserver`-containern har inga resursgränser, på en CX33 där Redis
  redan tar 3 av 8 GB.
- `proxy_cache_lock on` skyddar mot thundering herd per nyckel, så det
  blir en CPU-spik, inte ett avbrott.

**Rekommendation: deploya på lågtrafik och följ tileserver-CPU första
timmen** (`prod-health`-skillen, `docker stats tileserver`).

## Utfall (mätt 2026-07-27, efter slutgranskningens fixar)

Implementationen ligger på branch `todo-90-ett-listformat`. Full
verifieringsrapport: `tmp-90-verifiering/REPORT.md` (gitignorerad),
slutgranskningens åtgärder: `.superpowers/sdd/final-fix-report.md`.

**Formatmålet uppnått.** Ett format, en kolumn, kartbild 140×140 med
kategoriikon, rubrik och teaser clampade till 2 rader vardera,
float-layouten borta. `hero.blade.php` raderad. Partialen ger
`EventHero: 0 | ListEvent: 17 | excerpt: 17 | fetchpriority: 1 | h3: 17`
mot `EventHero: 9 | ListEvent: 8 | excerpt: 0` före. 34 tester gröna,
PHPStan rent.

**En regression fanns och är åtgärdad.** Formuleringen "inga
regressioner" i en tidigare version av det här avsnittet var fel.
`single-event` är den enda vyn som renderar `list-item` i
`@section('sidebar')`, och där är kolumnen 33 % av containern från 667 px.
Kartbilden är `flex: 0 0 var(--listevent-thumb)`, alltså okrympbar, så
textkolumnen kollapsade:

| Viewport | `.ListEvent` | Textkolumn före | Radhöjder före      | Textkolumn efter    |
| -------- | ------------ | --------------- | ------------------- | ------------------- |
| 700 px   | 194 px       | 46 px           | 176/196/200/216/237 | 194 px              |
| 768 px   | 216 px       | 68 px           | 160/172/176/196     | 216 px              |
| 900 px   | 260 px       | 112 px          | 156/172/176         | 260 px              |
| 1024 px  | 301 px       | 153 px          | 156/172             | 153 px (oförändrad) |
| 1280 px  | 385 px       | 237 px          | 156/172             | 237 px (oförändrad) |

Vid 68 px textkolumn blev rubrikerna 2 rader à 3–4 tecken och resten
klipptes av `overflow: hidden`. Listan var obrukbar mellan ~667 och
~1000 px.

Åtgärd: **container query** på `.MainSidebar` (`container-type:
inline-size`) — regeln följer kolumnens bredd, inte viewportens, eftersom
det är kolumnen som är trång. Under 310 px kolumnbredd stackas kartbilden
ovanför texten. Sida vid sida hade krävt en thumb på max 56 px vid 700 px
för att nå 130 px text, och då täcker den 24 px stora kategoriikonen
nästan halva kartbilden. Kostnad: sidebaren växer i bandet 667–955 px,
`docH` på single-event 4 819 → 5 572 px vid 700 px viewport. Accepterat.

**Startsidan tappade 9 `<h3>` och har fått dem tillbaka.** Gamla
`hero.blade.php` renderade `<h3 class="EventHero__title">` per kort;
`list-item` renderade rubriken som `<span>`, så `Mest läst` hade noll
rubrikelement för 17 händelser. Rubriken renderas nu som `<h3>` när propen
`teaser` är satt (bara startsidan gör det), med oförändrade klasser och
identisk höjd — 44 px, samma som spannet, så radhöjden är oförändrad.
Verifierat: partialen ger 17 `<h3>`, och sidebar- och kompaktlistor har
fortfarande noll.

**Specens typografi var inte implementerad.** Komponenten ärvde
`.widget__listItem__title` / `.widget__listItem__text` och fick därför
14,4/20,8/14,4 px istället för de beslutade 12/17/13 px. Det är alltså
inte bara prognosen som var fel — implementationen avvek också från
designen. Efter fixen (reglerna scopade till `.ListEvent` så kortvyn på
single-event inte påverkas):

| Del                 | Före     | Efter    |
| ------------------- | -------- | -------- |
| kategori            | 17,3 px  | 14,4 px  |
| rubrik (2 rader)    | 58,2 px  | 44,2 px  |
| teaser (2 rader)    | 38,9 px  | 38,9 px  |
| meta (2 rader)      | 40,3 px  | 36,4 px  |
| **textblock**       | 167,7 px | 146,9 px |
| **radhöjd**         | 184 px   | 163 px   |
| radhöjd m separator | 200 px   | 179 px   |

| 17 rader (390 px viewport) | Prognos i denna todo | Före fix              | Efter fix             |
| -------------------------- | -------------------- | --------------------- | --------------------- |
| Radhöjd                    | 156 px               | 184 px                | 163 px                |
| 17 rader                   | ca 2 650 px          | ca 3 384 px           | ca 3 027 px           |
| Besparing i sektionen      | −1 091 px (−29 %)    | ca −357 px (ca −10 %) | ca −714 px (ca −19 %) |

**Öppen designfråga kvarstår, men är nu liten.** Textblocket är 146,9 px,
alltså bara 6,9 px över kartbildens 140 px-golv. Meta-raden wrappar
fortfarande till 2 rader (36,4 px) på 195 px textkolumn. Klampas meta till
1 rad — precis som redan görs i den trånga sidebar-kontexten — blir
textblocket 128,7 px, alltså under golvet, och radhöjden landar på
konstant 156 px, exakt som prognosen. 17 rader blir då ca 2 908 px.
Det är en ren designfråga (tappar man information i meta?) och lämnas till
användaren.

**Bytevikt:** +65 kB för 17 thumbnails (73 → 138 kB), mot uppskattade
+80 kB. Under gränsen. `/inbrott` med sina 40 tumnaglar ingick inte i
mätningen, se konsumentlistan ovan.

**Kunde inte mätas lokalt:** startsidans egen `docH`. `Mest läst` bygger
på `crime_views` senaste 20 minuterna och lokal dev saknar live-trafik
(2 rader renderas lokalt). Mäts på prod efter deploy.

## Förväntad effekt (prognos före implementation — se "Utfall" för uppmätt)

Siffrorna nedan är planens prognos och behålls som spårbarhet. De är
**överträffade av verkligheten på fel sätt**: uppmätt radhöjd blev 163 px,
inte 156 px, se "Utfall".

- `Mest läst`-sektionen på mobil: **3 741 → ca 2 650 px**
- Hela startsidan: **11 559 → ca 10 500 px** (−9 %)
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

| Risk                                                      | Hantering                                                                                   |
| --------------------------------------------------------- | ------------------------------------------------------------------------------------------- |
| Tappad `fetchpriority` på första bilden → LCP-regression  | `first`-prop flyttas medvetet över; verifiera i PSI före/efter                              |
| +80 kB på mobil                                           | Acceptabelt mot #71:s mätning (1,37 MB total, tiles 5,1 %); följ upp                        |
| `padding`-höjningen ändrar alla kartbilder på hela sajten | Egen commit, visuell kontroll på single-event och overview-vyerna först                     |
| Alla listbilder blir cache-missar vid deploy              | Deploya lågtrafik, följ tileserver-CPU första timmen — se "Deploy-risk" ovan                |
| 140 px-thumben blir för tung på någon av de 13 vyerna     | CSS custom property → en rad att justera per kontext; sidebaren stackar via container query |
| Ikongrupperna missar nya polis-kategorier                 | Prefix-match + neutral fallback; `ovrigt` är redan 16 % och ska funka                       |
