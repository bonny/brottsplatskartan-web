**Status:** aktiv (skissad — kompletterar #60/#63, kan köras parallellt)
**Senast uppdaterad:** 2026-05-01

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

1. **Bygg RSS-fetcharen som del av #63** — den är gemensam grund.
2. **Klassifikations-prototyp:** regex-pass på 1 veckas RSS-data,
   manuell utvärdering på 100 artiklar (precision/recall).
3. **Pilot på 5 orter:** Stockholm, Göteborg, Uppsala, Malmö, Västerås.
   2 veckor mätperiod på CTR + dwell time + GSC position på ortssidor.
4. **Beslut:** scale upp till alla orter eller justera klassifikation?

## Inte i scope

- **Egen redaktionell text** — vi länkar bara, vi skriver inte.
- **Pushnotiser** vid nya nyheter per ort — separat feature.
- **Kommentarer/reaktioner** på media-länkar — out of scope.
