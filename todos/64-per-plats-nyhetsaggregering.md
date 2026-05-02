**Status:** fas-2 implementerat 2026-05-02 — hybrid regex + AI-pipeline live, source-scope fallback, utökad vokabulär, dn-sthlm-källa, UI med "visa fler"-toggle. Mätperiod på precision + CTR pågår.
**Senast uppdaterad:** 2026-05-02

## Implementations-status fas 2 (2026-05-02)

Triggad av användarobservation: "/stockholm visar rätt få nyheter — finns
det inte fler i källorna?" Diagnos visade tre problem: (1) Stockholm-
stadsdelar (Bromma, Hornsgatan, Rålambshovsparken) saknas i `places`,
(2) blåljus-vokabulär hade luckor (böjningar, sammansatta ord, saknade
kategorier), (3) regex är fundamentalt för "smutsig" för all blåljus-
fångning. Lösningen blev en hybrid-pipeline: regex för det uppenbara,
AI för det subtila.

**Klart fas 2:**

- **Source-scope fallback:** `source_to_primary_place` i config — när en
  artikel från svt-stockholm m.fl. klassas som blåljus utan plats-match
  → koppla till källans primära kommun. Fångar Bromma-typen utan att
  lägga till stadsdelar i `places`. Täcker 22 lokala källor.
- **Utökad blåljus-vokabulär:**
    - Böjda former: poliser/poliserna, åtal/åtalas/åtalade, häktas,
      döms/dömdes, rånare, mordet, dödligt, snattade
    - Sammansatta ord: sprängdåd, bombdåd, mordbrand, knivattack,
      omkullkörd, frontalkrock
    - Nya kategorier: bedrägeri/bedrägerier/bluffannons/bluffmejl
      (ekobrott), attentat (terrorbrott), pyroman (mordbrand),
      skadegörelse/klotter/vandalism (egendomsbrott)
    - Modifierare: raket/raketer, granat/granater, kollision/
      kolliderade/kollidera, påkörd/påkörda
- **Ny RSS-källa:** `dn-sthlm` (DN Stockholm-sektion, scope=Stockholms
  län). Mitt i Stockholm undersöktes men har rotations-hash i URL —
  instabil. Expressen/Aftonbladet/SvD har inga publika Stockholm-
  section-feeds.
- **UI: höjd limit + "visa fler"-toggle:** `display_limit` 5 → 8,
  `display_limit_expanded` = 23. Helpern hämtar full expanded-limit i
  en query, blade slicear i visible/hidden — ingen extra DB-träff för
  utfällda artiklar. `<details>`-element, fungerar utan JS.
- **AI-klassifikation (todo #64 fas 2.5):** `App\Ai\Agents\NewsClassifier`
  med Haiku 4.5 + JSON schema (is_blaljus, kommun_names, category,
  confidence, reason). Schemaläggs 15,45 varje timme efter regex-passet.
  Fångar vad regex missar — bedrägerier, stadsdelar, böjda termer,
  sport-namn-förväxlingar (Hammarby IF ≠ Hammarby stadsdel).
  Idempotent via `news_articles.ai_classified_at`. ~$26/år vid 300
  art/dygn. Lokal smoke-test verifierade: Skogsbrand i Partille fångad
  trots att regex missade plats; sport/utrikes/näringsliv korrekt
  filtrerad.

**Filer fas 2:**

- `app/Ai/Agents/NewsClassifier.php` (ny)
- `app/Console/Commands/AiClassifyNewsArticles.php` (ny)
- `resources/views/ai/prompts/news-classify.blade.php` (ny)
- `database/migrations/2026_05_02_180000_add_ai_classified_to_news_articles.php` (ny)
- `app/Console/Commands/ClassifyNewsArticles.php` (utökad: source-scope fallback)
- `config/news-classification.php` (utökad: vokabulär + source_to_primary_place + display_limit_expanded)
- `config/news-feeds.php` (ny källa: dn-sthlm)
- `resources/views/parts/place-news.blade.php` (utökad: details-toggle)
- `app/Console/Kernel.php` (schemaläggning)

**Resultat efter fas 2 + reset på prod 2026-05-02:**

- 1699 artiklar klassade (1656 efter dn-sthlm-tillägg)
- 411 blåljus-träffar via regex (~24 %)
- 292 place_news-rader (från 189 → +55 % efter source-scope fallback)
- Stockholm specifikt: 0 → 15 kopplingar inom 72h
- AI-klassifikation kicked in 2026-05-02 18:30 UTC, första 20 art:
  6 AI-blåljus, 14 korrekt nej, 0 fel

**Commits fas 2** (alla 2026-05-02): 94dcf38, 3592cc2, 937431f, 6d5ba21, eae77d9.

## Implementations-status fas 1 (2026-05-01)

**Klart:**

- `place_news`-tabell + `news_articles.classified_at` (migration `2026_05_01_160000_create_place_news_table.php`)
- `app:news:classify`-command — regex-pass blåljus + plats (Unicode word-boundary), idempotent via `classified_at`
- Schemaläggning: cron `5,20,35,50 * * * *` (5 min efter fetch)
- `App\Models\PlaceNews`-modell
- `Helper::getLatestNewsForPlace()` med 10-min cache
- `parts/place-news.blade.php` partial — `rel="nofollow noopener external"`
- Inkluderad i `city.blade.php` (Tier 1) + `single-plats.blade.php` (idag-vy)

**Konfig:** `config/news-classification.php` — blåljus-termer, min plats-namn-längd (4), batch_size (2000), display_window (72h), display_limit (5).

**Initial körning:** 1013 artiklar bearbetade, 195 blåljus-träffar (~19 %), 132 place_news-rader, 66 distinkta platser.

**SVT Text-TV som källa (tillagd 2026-05-01):** `app:importera-texttv`
skriver nu också till `news_articles` med `source=svt-texttv`. Pages
hämtas både från `last_updated` och `most_read` endpoints (texttv.nu),
~10 sidor/körning, var 5:e min. Test: 10 sidor → 4 blåljus → 3
plats-kopplingar (varav 2 sanna, 1 false positive på kulturartikel
"död"-trigger).

**Filer:**

- `app/Console/Commands/ClassifyNewsArticles.php`
- `app/Models/PlaceNews.php`
- `app/Helper.php` (ny metod `getLatestNewsForPlace`)
- `config/news-classification.php`
- `database/migrations/2026_05_01_160000_create_place_news_table.php`
- `resources/views/parts/place-news.blade.php`
- `resources/views/city.blade.php` (include)
- `resources/views/single-plats.blade.php` (include)

## Kända brister + åtgärder

**Hanterade fas 2 (2026-05-02):**

- ~~Aggregator-summary-bias~~ — `title_only_sources` config med
  google-news-se i listan. Implementerat 2026-05-01.
- ~~Ingen län-disambiguering~~ — `source_to_lan` per källa filtrerar
  plats-träffar till källans scope-län. Implementerat 2026-05-01.
- ~~Stockholm-stadsdelar saknas~~ — löst via `source_to_primary_place`
  fallback (Bromma → Stockholm) + AI-pipeline som förstår stadsdels-
  semantik direkt. Inga stadsdelar tillagda i `places`-tabellen, vilket
  skulle introducerat false positives (Hammarby IF, Östermalm/Malmö).
- ~~Vokabulär-luckor~~ — utökad lista + AI som backup.

**Fas 3 — kvar att göra:**

- **UI på län-sidor (`/lan/...`):** `single-lan.blade.php` har inte
  partial:en än. Vänta in mätperiod (precision-stickprov 2026-05-15)
  innan utrullning till länsnivå.
- **Polisens egna RSS:** `polisen.se/aktuellt/rss/lokala-rss-floden/`
  borde ge polisens egna pressmeddelanden. Hög signal-till-brus.
  Undersöks separat om volym fortfarande upplevs låg efter fas 2.
- **Schemaläggning av `app:news:fetch-rss` mot källor med stora
  flöden:** vissa källor (Aftonbladet, DN-riks) har många artiklar
  per dygn — vi kan missa nya om RSS-feeden bara visar senaste 25.
  Fetcha oftare för dessa? Behöver mätning.

## Mätning

- **Precision:** stickprov 50 artiklar 2026-05-15 → mål >85 %.
- **CTR + dwell time:** GA4-jämförelse ortssidor med vs utan partial
  (rendrar bara om träffar finns) över 30d → 2026-05-31.
- **GSC-position:** ortssidor 30d-jämförelse mot baseline → 2026-05-31.

## Prio-beslut 2026-05-01

Jämförelse av nyhets-todos (#60/#63/#64) gav #64 högst värde på båda
axlarna:

- **SEO:** träffar redan-trafikerade ortssidor (~25k clicks/90d-potential
  identifierad i #52-baseline). Ingen pareto-bias — alla 6000 platser
  får samma behandling, long-tail träffas automatiskt. #63:s smala scope
  (top-50 events) ger bara ~20 % av event-clicks per egen SEO-research.
- **UX:** "blåljus i {ort}" → polis-data + medierapportering på samma
  sida = task completion, stickiness, USP.
- **Kostnad:** regex-klassifikation + ev. Haiku-fallback (~$0.10/dygn)
  vs #63:s GA4 + Haiku per event-artikel-par (~$0.25/dygn).

Tekniskt delar #64 mindre med #63 än det verkar — annan pipeline (regex
per artikel, inte AI-validering per event-artikel-par). Kan köras
parallellt utan att vänta på #63.

# Todo #64 — Per-plats nyhetsaggregering: "Senaste nyheter i {ort}"

## Sammanfattning

Visa lokala blåljus-relaterade nyheter på ort- och länssidor (t.ex.
`/uppsala`, `/lan/Stockholms-län`), inte bara på event-sidor. Detta
positionerar Brottsplatskartan som **the destination för blåljus-
bevakning per ort** — inte bara en spegling av Polisens RSS, utan en
hub som även länkar in mediarapporteringen för den platsen.

Kompletterar [#60](60-auto-lank-nyheter-ai-rss.md) (per-event) och
[#63](63-relaterade-nyheter-trafikprio.md) (trafikdriven per-event).
Olika UX, olika pipeline, olika SEO-träffyta — men återanvänder samma
RSS-källor från [`tmp-news-research/news-rss-tos-2026-05-01.md`](../tmp-news-research/news-rss-tos-2026-05-01.md).

## Bakgrund

- Ort- och länssidor är våra största trafikdrivare (per #52 GSC-baseline:
  ~25k clicks/90d potential identifierad i top-rankade ortssidor).
- Idag visar de bara polishändelser. En användare som söker "Uppsala
  blåljus" eller "brand i Stockholm" hamnar ofta hos oss — men får
  ingen kontext utöver Polisens egna texter.
- Lokala medier (SVT lokalt, Expressen GT/Kvällsposten, lokala dagstidningar)
  rapporterar mer ingående om större händelser. Att aggregera och länka
  in dessa per ort = unikt värde.

## Konkurrent-positionering

- **Aftonbladet/Expressen:** har "Stockholm just nu"-flöden, men inte
  geografiskt-precisa per ort.
- **SVT lokalt:** kommun-baserade men inte fokuserade på blåljus.
- **Krimkartan:** liknande aggregator-koncept, men annorlunda UX-modell.

Vi kan vara **enda destinationen** som kombinerar polishändelser
(strukturerad data) + lokal media-bevakning (länkar) + statistik (#37,
#38, #39) per ort. Det är ett starkt USP.

## Förslag

### Pipeline (mycket enklare än #60)

1. **Återanvänd RSS-fetcharen** från #60/#63 — samma `news_articles`-
   tabell.
2. **Klassifikations-pass** per artikel (inte AI-matchning per
   event-artikel-par):
    - **Steg 1: Är detta blåljus?** Regex på titel/summary mot termlista:
      brand, brott, polis, gripande, skottlossning, dödsfall, olycka,
      räddningstjänst, stulen, försvunnen, ras, översvämning, evakuering.
    - **Steg 2: Vilken/vilka platser nämns?** Matcha mot vår `Locations`-
      tabell (~6000 svenska ort-/kommun-/länsnamn). Snabb LIKE-sökning.
    - **Steg 3: Resultat** → koppla artikel till `place_id` (eller flera
      om både ort + län nämns).
3. **Cache i ny tabell** `place_news` (place_id, news_id, classified_at).
4. **Visning på ortssida:** sektion "Senaste nyheter i {ort}" med 3–5
   senaste artiklar (24–72h). Utöver befintliga polishändelser.
5. **Schemalagd:** klassifikation körs i samma cron som RSS-fetch
   (var 15:e min).

### Format på ortssida

```html
<section class="LocalNews">
    <h2>Senaste nyheter i Uppsala</h2>
    <ul>
        <li>
            <a rel="nofollow noopener" href="...">
                <img src="favicon.ico" /> Polisinsats efter knivhot — Uppsala
            </a>
            <small>SVT Uppsala · 2 timmar sen</small>
        </li>
        ...
    </ul>
    <p class="LocalNews__disclaimer">
        Externa nyhetslänkar — innehåll på respektive sajt.
    </p>
</section>
```

### Klassifikations-precision

- **Regex är enkel men "smutsig"** — t.ex. "brand-nytt" matchar fel.
  Lösning: kombinera 2 av 3 termer (huvudterm + plats + verb-mönster).
- **AI-fallback för gränsfall** (var 50:e artikel som passerar regex
  men är osäker) — Haiku, ~$0.10/dygn.
- **Manuell stickprov** första veckan — 50 artiklar, mät precision +
  recall. Mål: precision >85 %, recall >70 %.

### Kostnad

- RSS-fetch: gratis (samma som #60/#63).
- Klassifikation: regex är gratis, AI-fallback ~$0.10/dygn.
- Total: ~$0.10/dygn.

## Vinster

### SEO

- **Freshness på ort-sidor:** Google ser sidan ändras dagligen → ökad
  crawl-frekvens.
- **Outbound länkar till etablerade källor** stärker E-E-A-T på sidor
  som redan rankar.
- **Topical authority per ort:** "the page about blåljus i Uppsala" =
  starkare semantisk profil.
- **Ingen long-tail-asymmetri:** alla ort/läns-sidor får samma
  behandling, ingen pareto-bias.

### UX

- Användare som söker "blåljus Uppsala" får både polishändelser och
  media-bevakning på samma sida — task completion utan att lämna oss.
- **Stickiness:** sidan blir värd att besöka regelbundet, inte bara
  när man hittar dit via Google.
- Kombineras med [#59](59-vad-hander-nu-ruta.md) ("Vad händer nu"-ruta
  på startsidan) — samma datakälla.

### Synergier

- **#39 (MSB räddningsstatistik per kommun):** statistik + nyheter
  per ort = komplett bild.
- **#50 (Trafikverket live):** trafik + nyheter på samma plats-sida.
- **#59 ("Vad händer nu"):** kan visa toppen av per-plats-nyheterna
  på startsidan.
- **#27 (rikare innehåll på ort-sidor):** detta är nästa steg i samma
  riktning.

## Risker

- **Aggregator-misstolkning från Google:** för många outbound-länkar
  utan eget innehåll → "hub of news" / "thin aggregator"-flagga.
  Mitigerar: `rel="nofollow"`, tydlig sektion-rubrik, vår egen
  polishändelse-data är fortfarande sidans core.
- **Klassifikations-fel:** rapportera fel artikel som "brand i Uppsala"
  → pinsamt. Höj tröskeln, accepterar lägre recall.
- **Upphovsrätt:** samma juridiska grund som #60/#63 — titel + länk
  räcker (URL § 22 + Svensson C-466/12 + DSM art. 15).
- **Plats-ambiguitet:** "Stockholm" finns i 12 län. Behöver disambiguering
  (om artikel nämner "Stockholm" + "Solna" → koppla bara till Solna).

## Beslut att fatta

1. Implementeras **i stället för** eller **parallellt med** #63?
   Parallellt är troligast — olika UX, kompletterar varandra.
2. Ska klassifikationen vara regex-bara (snabbt, smutsigt) eller hybrid
   med AI-fallback?
3. Vilka platser ska börja? Top-20 mest besökta orter (Stockholm,
   Göteborg, Malmö, Uppsala, Västerås, …) eller alla 6000 från start?

## Confidence

**Hög** för värdebedömningen — per-plats har starkare SEO-träffyta
(ortssidor är redan populära) och bredare UX-vinst.

**Medel** för implementations-precisionen — klassifikation av "är detta
blåljus?" är inte trivialt utan att introducera fel-flaggor.

## Beroenden

- Återanvänder RSS-fetch + `news_articles`-tabell från #60/#63. Bygg
  RSS-fetchern i #63 så subsumerar både use-cases.
- Synergi med #59 (live-rutan), #50 (Trafikverket), #39 (MSB-statistik).

## Nästa steg

1. ~~**Bygg RSS-fetcharen som del av #63**~~ — **klar 2026-05-01**
   (commits `d25e91a` + `7589d57`). 29 källor, 880 art/körning, 90d
   retention. `news_articles`-tabellen samlar data klar för
   klassifikation.
2. **Klassifikations-prototyp:** regex-pass på 1 veckas RSS-data,
   manuell utvärdering på 100 artiklar (precision/recall).
3. **Pilot på 5 orter:** Stockholm, Göteborg, Uppsala, Malmö, Västerås.
   2 veckor mätperiod på CTR + dwell time + GSC position på ortssidor.
4. **Beslut:** scale upp till alla orter eller justera klassifikation?

## Inte i scope

- **Egen redaktionell text** — vi länkar bara, vi skriver inte.
- **Pushnotiser** vid nya nyheter per ort — separat feature.
- **Kommentarer/reaktioner** på media-länkar — out of scope.
