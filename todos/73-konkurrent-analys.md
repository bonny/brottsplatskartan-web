**Status:** fas 1+3 klara 2026-05-13. IP-beslut: behåll helt öppet. Nästa: bryt ut inspirations-todos (statistik-narrativ, bevaka-funktion, rättsblogg) + LICENSE-mikrotodo.
**Senast uppdaterad:** 2026-05-13

# Todo #73 — Konkurrent-analys + utvärdera privat repo

## Sammanfattning

På sistone har det dykt upp fler sajter med liknande upplägg (brottskartor /
händelseaggregering / nyhetsaggregering kring polisdata). Behov av två delar:

1. **Konkurrent-analys** — kartlägg vilka sajter som finns, vad de gör bra,
   vad de gör som vi inte gör, och vad vi kan inspireras av (UX, feature,
   datakällor, SEO-vinklar).
2. **Utvärdera om GitHub-repot ska stängas (private)** — koden är idag öppen
   på `github.com/bonny/brottsplatskartan-web`. Oro: konkurrenter kan
   ta hela arkitekturen + smarta delar (platsutdragning, AI-titel-rewrite,
   cache-strategi, kartbildspipeline, etc) utan kostnad.

## Bakgrund

### Värdefulla delar i koden vi inte vill ge bort

- **Platsutdragning ur fritext** — `app/Services/`-logik som identifierar
  ort/kommun/län i Polisens textsammanfattningar. Resultat av ~10 års
  iteration mot edge-cases. Konkurrent som klonar detta sparar månader.
- **AI-titel-rewrite + vague-detection** (#10) — `isVagueTitle()` +
  Claude-prompt-engineering för att skriva om Polisens vaga titlar.
  Stor SEO-effekt — vi mäter just nu CTR-impact i GSC (#36).
- **Hybrid regex + AI-klassning för nyhetsmatchning** (#63/#64) — vilka
  artiklar hör till vilken plats/händelse. Tunad för svensk media.
- **Kartbildspipeline** — egen tileserver-gl + planetiler + `/k/v1/`-URL:er,
  immutable cache och cirkel-overlay-rendering.
- **Cache-strategi** — Spatie response cache + warmup-jobb + datum-route-
  exkludering + redis maxmemory-policy.
- **SEO-arkitektur** — datum-routes, Tier 1-städer, schema.org-sweep,
  noindex-policy, sitemap-strategi, GSC-mätning av specifika åtgärder.

### Argument för att hålla repot öppet

- Hosted in EU + open source är en **trust-signal** för användare och AI-agenter
  (vi kommunicerar det aktivt — `/sida/om`, footer, agent-readiness #68).
- Att stänga repot räddar inte koden — den är redan offentlig sedan ~10 år
  (förks/klonas redan om någon ville). Snabba kopior kan göras från en singel
  clone.
- Vi får PR-bidrag och bug reports från community (sällan men händer).
- Affärsmodellen är inte koden — det är **datat, traffiken, brand, drift,
  pipeline-tuningen och tillit hos polisen/medierna**.
- Vår moat är **operationell exekvering + datakällor + relationer**, inte
  algoritm-IP. Att vinna SEO i Sverige kräver år av tuning och daglig
  drift, inte bara att kunna köra koden.

### Argument för att stänga (eller flytta känsliga delar)

- Smart platsextraktion är en konkret moat — den **är** algoritm-IP, och
  ny aktör som klonar sparar konkret tid.
- AI-prompts (Haiku/Sonnet system-prompts) är finjusterade och avslöjar
  vår strategi för titel-rewrite + nyhetsmatchning.
- Cache-tuning + Hetzner-deploy-skript visar exakt hur vi drar prestanda
  ur en CX33 — konkurrenter kan replikera infrastrukturen.

### Mellanväg

- Behåll repot öppet (trust-signal stannar) men **flytta känsliga delar
  till ett privat repo** som dras in som dependency (composer-paket eller
  laddas via env). Kandidater:
    - `app/Services/PlaceExtractor*` (platsutdragning)
    - AI-prompts (system-prompts för titel-rewrite, nyhetsmatchning)
    - eventuellt cache-warmup-strategin
- Behåll det "tråkiga" (Laravel-CRUD, kontrollers, blade-templates) öppet.

## Förslag

### Fas 1 — konkurrent-spaning (1–2h)

Lista och beskriv de svenska konkurrenter / liknande sajter som finns
idag. För varje:

- URL, ägare (om känt), drift sedan när
- Datakällor (polisen, MSB, trafikverket, eget?)
- USP — vad gör de bättre/annorlunda än oss?
- SEO-fotavtryck — sök "brott {stad}" / "polishändelser {stad}" i Google,
  ranking-jämförelse
- GA4/GSC-jämförelse: ranking-överlapp via `mcp-gsc compare_search_periods`
  och manuella SERP-tester
- Tekniskt — open source? framework? AI-funktioner?
- Sparas i `docs/konkurrent-analys.md` (eller `tmp-konkurrent/` om mer
  research-dump)

Kandidater (snabbinventering 2026-05-13 via Google + WebFetch — verifiera
djupare i fas 1):

**Direkta konkurrenter (samma vertikal — polishändelser)**

Snabbgranskade (egna WebFetches 2026-05-13):

- **polisinfo.se** — Polisens öppna data, filter på brottstyp / plats /
  polisområde, dygnet-runt-uppdatering. Inget tydligt kart-UI på framsidan,
  fokus på lista + filter. Okänd ägare.
- **poliskoll.app** — Webb (trots .app-TLD), 53+ städer, brottskategorier,
  uppdaterar var 2:e min. **Ingen karta** synlig — bara listor +
  pagineringssidor. Affiliate-link "Jämför hemlarm" → SEO/affiliate-vinkel.
  Exempel-plats-URL: `/plats/stockholm`.
- **bastkommun.se / "Brottskarta Sverige"** (även `/karta`) — Interaktiv
  karta, gatunivå, polis-API var 15:e min, alla 290 kommuner. Cloudflare
  browser-check (svårt att fetcha). Mer kart-tung än de andra.
- **Blåljus Sverige** (iOS-app, App Store id 1613992848) — Tabs:
  Events / Map / Media / True Crime Podcasts. Bredare än bara karta —
  trycker på podcast/true-crime-vinkeln. Värt att kolla Android-motsvarighet.

Tipsade av användaren 2026-05-13 (ej djupgranskade — kolla i fas 1):

- **orti.se/lan/stockholm/polishandelser** — län-baserad URL-struktur som
  liknar vår; bredare sajt (orti.se ≠ ren brottskarta), polishändelser
  som en sektion.
- **snutryckning.se** — namnet (slang för "polis-ryckning") signalerar
  blåljus-fokus.
- **bulletin.nu/brottskartan** — _Bulletin_ (nyhetssite, högerprofilerad)
  har egen brottskarta. Intressant: etablerat varumärke driver kartan,
  inte ett ensam-projekt.
- **kollabrotten.se** — okänt djup, sannolikt direkt konkurrent.
- **brottskartan.app** — `.app`-TLD signalerar app-fokus (eller bara
  brand-positionering). Värt att kolla om det är webb, native app, eller
  PWA.
- **olyckskartan.se** — smalare vertikal (olyckor, inte allmän
  brottskarta). Trafikolycks-fokuserad — kan vara komplement, inte
  konkurrent.
- **trygghetskartan.io** — "trygghet"-vinkel snarare än "brott" — kan
  vara mer SEO-orienterad mot oroliga föräldrar/bostadsköpare.
- **våldskartan.se** (punycode `xn--vldskartan-15a.se`) — narrow vertikal
  (våld), troligen mediekampanj eller debattprojekt snarare än löpande
  aggregator.

**Möjlig nyhetskälla, inte konkurrent**

- **stockholmblaljus.se** — har RSS-feed, fungerar mer som
  blåljus-nyhetskanal för Stockholm. Tipsad av användaren som potentiell
  RSS-källa till #64 (per-plats nyhetsaggregering) snarare än konkurrent.
  Kolla feed-format + lic./TOS innan vi använder.

**Lokaltidningar med blåljus-sektion** (indirekta — drar sökord men inte
hela vertikalen)

- **lt.se/blaljus** (Länstidningen Södertälje) — exempel på lokaltidning
  med dedikerad blåljus-sektion. Antas finnas motsvarande på de flesta
  lokaltidningar (Mitt i, NA, Aftonbladet/Expressen-flikar etc).

**Officiell källa**

- **polisen.se/aktuellt** — länkarna dör efter ~en vecka (det är vår
  ursprungliga existensberättigande och fortfarande relevant).

**Att kolla djupare**

- Statistik-/myndighetssajter (BRÅ, MSB) — inte konkurrenter men kan
  inspirera datalager.
- Eventuella Discord-/Telegram-kanaler som aggregerar blåljus i realtid
  (vanligt bland entusiaster).
- Reddit r/sweden / Flashback "blåljus"-trådar — inte tekniska konkurrenter
  men styr en del trafik.

## Fynd från fas 1 (2026-05-13)

Råmaterial: `tmp-konkurrent-analys/01-batch1.md` (5 sajter),
`02-batch2.md` (7 sajter), `03-ip-research.md`, `04-seo.md`.

### Konkurrent-rangordning

**Direkta hot (kart-baserat eller bred brottsfeed):**

1. **polisinfo.se** — farligast. Samma ägare som valutan.org/tagtiden.se
   (portfolio-SEO-team). Bevaka-funktion (multi-plats), per-län-statistik
   på framsidan, blog-SEO på rättsfrågor, 1585 indexerade sidor.
2. **poliskoll.app** — native mobile-app + push + statistik-jämförelse mot
   riksgenomsnitt ("264 % över rikssnittet"). Ingen karta.
3. **kollabrotten.se** — färsk lansering 2026-04-15, fokus statistik /
   per-capita / trender (en vinkel vi inte har).
4. **olyckskartan.se** — Tryggsam i Sverige AB (kommersiell), kombinerar
   Polisen + MSB + medier. Äger "olyckor"-keywords trots att vi har datat.
5. **snutryckning.se** — bred, 100+ kategorier, ren karta + lista.
6. **brottskartan.app** — push-notiser + PWA-fokus.
7. **bulletin.nu/brottskartan** — bakom betalvägg, brand-styrka men
   begränsad SEO-räckvidd.
8. **trygghetskartan.io** — Stockholm-only, freemium "Pro" (enda
   monetiseringsexempel av betaltyp).
9. **bastkommun.se**, **orti.se** — breddsajter där brott är en sektion.
10. **våldskartan.se** — nisch, "gängvåld"-narrativ.

**SERP avslöjade ytterligare 7 i fas 1 — utforska om tid finns:**
polisradar.se, poliskartan.se, brottskartan.n.nu, brottsvåg.se,
alltimalmo.se, polisnytt.se, blåljus.se.

**Avskrivna:**

- **stockholmblaljus.se** — redaktionell blåljusblogg, RSS finns men bara
  ~2–3 items/månad + oklar TOS. Lägg på "framtida källor" för #64;
  prioritera DN/SR/SVT-lokala först.

### Vår styrka & glapp (GSC 28d)

**Vi äger:** Stockholm-keywords, brand ("brottsplatskartan"), "brand karta"
pos 1.1 (64 % CTR), "brottskarta" #1–3, "senaste blåljusen X".

**Inga konkurrenter har:** AI-titel-rewrite (#10), nyhetsaggregering (#63/#64),
egen kartbildspipeline. Dessa moats består.

**Konkreta SEO-glapp:**

- Malmö + Göteborg → polisinfo.se rankar pos 1, vi pos 6–8.
- **"polisen händelser"** — 32k impressions, pos 6.8, 1.27 % CTR.
  Största enskilda potential. Överlappar #52.
- **`/lan/Skåne%20län`** — URL-encoded mellanslag. Alla konkurrenter kör
  slug (`/skane-lan`). Klart sämst i klassen.
- "Olyckor"-keywords äger olyckskartan.se trots att vi har datat — vi
  kommunicerar bara inte ordet.

### IP-research-fynd

- **GitHub:** 7 stars, 1 inaktiv fork, 11 mänskliga views / 14d, 2341 clones
  (439 unika) = bot-scraping, **inte** konkurrent-research.
- **LICENSE-fil saknas** — men composer.json deklarerar MIT. **Mixed signal
  som måste fixas oavsett #73-utfall.** GitHub-API svarar `license: null`.
- **Platsutdragnings-premissen var overfittad.** Ingen dedikerad
  `PlaceExtractor`-service finns — logiken är inbäddad i `CrimeEvent.php`
  (1644 LOC) + `Helper.php`-lookups.
- **Den faktiska IP:n är AI-prompts** (~620 LOC: 433 blade-templates + 186
  wrappers). Trivialt att flytta till privat composer-paket (~2h refactor).

## Fas 2 — inspirations-todos (att bryta ut)

Konkreta kandidater från fas 1, sorterat efter effekt/effort:

| Prio | Förslag                                                               | Kommentar                                                                        |
| ---- | --------------------------------------------------------------------- | -------------------------------------------------------------------------------- |
| Hög  | **Slug-URLer för län** (`/lan/skane-lan` ersätter `/lan/Skåne%20län`) | Mikrojobb, hög SEO-effekt. Sannolikt största single-quick-win från analysen.     |
| Hög  | **`/olyckor` / trafikolycks-landningssida**                           | Plock från olyckskartan.se — vi har datat, äger inte ordet.                      |
| Hög  | **`/handelser-<stad>-idag` / titel-h1-optimering Malmö+Göteborg**     | Direkt SEO-glapp i GSC, polisinfo.se vinner pos 1.                               |
| Hög  | **LICENSE-fixen** (separat utbruten todo, akut oavsett #73)           | Committa LICENSE-fil eller dropp MIT från composer.json. 5 min.                  |
| Med  | **Bevaka-funktion** (multi-plats följa)                               | Polisinfo.se. Engagement-feature, kräver inloggning eller cookie-state.          |
| Med  | **Per-län-statistik på framsidan**                                    | Polisinfo.se + kollabrotten.se. Använder #38 (BRÅ) + #37 (SCB) som vi redan har. |
| Med  | **Per-capita / kommun-jämförelse**                                    | Kollabrotten.se-vinkel. Kombinera #38 + #37.                                     |
| Låg  | **Push-notiser / PWA**                                                | Brottskartan.app, poliskoll.app. Större jobb, oklar ROI.                         |
| Låg  | **Native mobile-app**                                                 | Poliskoll-appen. Stor satsning, antagligen nej.                                  |

Bryt ut de fyra Hög-raderna till egna todos (#74–#77) när #73 stängs.

## Fas 3 — IP-beslut: ÖPPET UNDER AGPL v3 (2026-05-13)

**Beslut taget 2026-05-13:** Repot förblir 100 % öppet, men licensen byts
från MIT → AGPL v3 för att täppa SaaS-luckan mot kommersiella aktörer som
hostar modifierade kopior. Inga delar flyttas till privat dependency.

**Motivering:**

- Hotbilden är teoretisk (1 inaktiv fork, 11 mänskliga views/14d, inga
  klon-spår hos konkurrenter). Ingen panik-stängning motiverad.
- Den konkreta vinsten av öppet repo är **agent-readiness** — Claude/Gemini/
  ChatGPT kan läsa koden när användare frågar. Linjerar med #68, llms.txt,
  markdown-responses. Att flytta AI-prompts privat skulle aktivt skada den
  vinsten.
- Pär:s konsultprofil + status-quo-cost-of-action är sekundära men
  positiva.
- AI-prompts som "IP" är överskattat — promptningstekniken kan replikeras
  oavsett om koden är synlig, det som är värt något är modellvalet +
  domänkunskapen + datat.

**Återstående akut åtgärd:** LICENSE-fil saknas men composer.json deklarerar
MIT. Bryts ut till egen mikrotodo — committa LICENSE-fil med MIT-text.
~5 min. Blocking inget annat men bör fixas snart för att inte ge mixed
signal.

## Fas 4 — kommunikation

Inget att kommunicera — `/sida/om` och `/sida/agent-readiness` stämmer
redan. README/docs behöver inte ändras.

## Risker

- **Mellanvägens underhållsbörda är låg** för 620 LOC, men kräver
  composer-token i deploy.yml + lokal `auth.json`. Liten engångsfriktion.
- **LICENSE-fixen kan bita oss bakåt** — om vi sätter "proprietary" nu
  efter 10 års implicit "synlig kod", är kopior från innan oavsett juridiskt
  oskyddade. Det är inte ett argument för att lämna det odefinierat — bara
  ett konstaterande att skyddet är framåtblickande.
- **Bias för action** — risk att fas 2-todos görs bara för att en
  konkurrent har dem, istället för att fortsätta vår egen vision. Mätbara
  SEO-glapp (slug, malmö/gbg, olyckor) är säkrare prio än feature-paritet
  (push, app).

## Confidence

**hög** för fas 1+2-fynden (data är konkret), **medel** för fas 3
(beslut beror på licens-position som bara du kan ta). Fas 4 är trivial när
fas 3 är klar.
