# 12 – LLM/AI-optimering av Brottsplatskartan

Undersökning av best practice 2026 för att göra sajten LLM-vänlig
(llms.txt, markdown-varianter, robots.txt för AI-botar, schema.org).

Research gjord 2026-04-21.

---

## Sammanfattning och rekommendation

Kort version: **de flesta "LLM-optimeringstrenderna" är i april 2026
fortfarande mer hype än praktisk nytta**, särskilt för en
annonsfinansierad svensk sajt. Men det finns några billiga åtgärder
som är värda att göra ändå.

### Go / no-go per delmoment

| Åtgärd | Rek. | Motivering |
|---|---|---|
| `llms.txt` i rot | **Go (quick win)** | Billigt, skadar inget, signalerar struktur. Men räkna inte med att botar faktiskt läser den. |
| `llms-full.txt` | **No-go** | Orimligt för en sajt med 1M+ händelser; standarden är designad för dokumentation, inte nyhetsarkiv. |
| Markdown-variant per URL (`Accept: text/markdown` + fallback `.md`) | **Villkorligt go** | Bara om vi faktiskt vill bli citerade av Claude Code / ChatGPT Search / Perplexity. Trade-off mot annonsmodellen. |
| Uppdatera `robots.txt` med fler AI-botar | **Go** | Redan halvvägs. Komplettera + separera träningsbotar från sökbotar. |
| Tillåta AI-sökbotar (OAI-SearchBot, PerplexityBot, ChatGPT-User) | **Go** | De skickar faktisk trafik till sajten. Nuvarande wildcard-allow räcker men gör det explicit. |
| Blockera träningsbotar (GPTBot, ClaudeBot, CCBot, Google-Extended) | **Behåll nuvarande blockering** | Annonsmodellen vinner inget på att mata träningsdata. |
| `<link rel="alternate" type="text/markdown">` | **Go om markdown-variant införs** | Triviellt tillägg. |
| JSON-LD ItemList på översiktssidor | **Go (del av todo #11)** | Redan planerat. |
| Schema.org NewsArticle på enskild händelse | **Redan gjort** | Finns i `CrimeEvent.php`. Verifiera mot Schema.org + Google Rich Results. |

### Tl;dr rekommendation
1. Sätt upp en minimal `llms.txt` (en dag).
2. Komplettera `robots.txt` (en timme).
3. Vänta med markdown-variant tills minst en AI-leverantör publikt
   bekräftar att de prioriterar sajter som serverar den – eller om
   vi ser faktisk AI-trafik i access-loggar.

---

## Nuläge

### `public/robots.txt` idag

```
User-agent: *
Disallow: /pixel?

User-agent: ClaudeBot
Disallow: /

User-agent: GPTBot
Disallow: /

User-agent: Google-Extended
Disallow: /
```

Redan bra ansats – träningsbotar blockerade. Men flera saknas:
CCBot, PerplexityBot, Meta-ExternalAgent, Bytespider, Applebot-Extended.
Och vi skiljer inte på träningsbotar (ingen värde för oss) vs
sökbotar (kan skicka trafik).

### Schema.org

`CrimeEvent.php` innehåller redan JSON-LD. Todo #11 (SEO-audit)
planerar NewsArticle, Place, BreadcrumbList. Inget att göra dubbelt här.

### Markdown-varianter

Finns inte idag. Alla sidor serveras som Blade-renderad HTML.

### `llms.txt`

Finns inte.

---

## Best practice 2026 – per delområde

### 1. `llms.txt`-standarden

**Status i april 2026: låg faktisk adoption.**

- Föreslagen av Jeremy Howard (fast.ai / Answer.AI) september 2024.
  Spec på [llmstxt.org](https://llmstxt.org/).
- **Ingen stor LLM-leverantör läser filen operativt**:
  - Google (John Mueller + Gary Illyes, publika uttalanden 2025):
    "no AI system currently uses llms.txt".
  - Audit på 1000 AEM-domäner (aug–okt 2025, [Longato](https://www.longato.ch/llms-recommendation-2025-august/)):
    noll hits från GPTBot, ClaudeBot, PerplexityBot, Google-Extended
    på `/llms.txt`.
  - [PPC.land, mars 2026](https://ppc.land/llms-txt-adoption-stalls-as-major-ai-platforms-ignore-proposed-standard/):
    "no major LLM provider currently supports llms.txt".
- Ironiskt nog **publicerar** Anthropic, OpenAI, Perplexity och
  Cloudflare egna `llms.txt`-filer på sina docs-sajter – men det
  betyder inte att deras botar *läser* andras.

**Filens format (enligt [llmstxt.org](https://llmstxt.org/)):**

```markdown
# Projektnamn (H1, enda obligatoriska)

> Kort blockquote-beskrivning av sajten.

Valfri brödtext (ingen H2 här).

## H2-sektion med länkar
- [Sidnamn](https://…): valfri beskrivning
- [Annan sida](https://…)

## Optional
- [Sekundära resurser som kan hoppas över]
```

**`llms-full.txt`** är *inte* en del av Howards formella spec –
det är en konvention (Mintlify, Fern, GitBook m.fl.) där hela
dokumentationen dumpas i en markdown-fil. Passar sajter med
100–1000 docs-sidor. Passar **inte** oss (miljontals händelser).

**Placering:** `/llms.txt` i webroot.

**Verdict:** Skapa en statisk `llms.txt` som sajt-karta. Kostar
ingenting och skadar inte. Men räkna inte med några leads.

Källor:
- [llmstxt.org](https://llmstxt.org/) – officiell spec
- [Longato 2025 audit](https://www.longato.ch/llms-recommendation-2025-august/)
- [PPC.land mars 2026](https://ppc.land/llms-txt-adoption-stalls-as-major-ai-platforms-ignore-proposed-standard/)
- [Am I Cited – overhyped or essential?](https://www.amicited.com/blog/llms-txt-truth-overhyped-or-essential/)

### 2. Markdown-versioner per URL

**Här finns det faktisk adoption 2026.**

- **Anthropic (Boris Cherny, nov 2025):** Claude Code WebFetch
  skickar automatiskt `Accept: text/markdown, */*`.
- **Vercel ([blogpost feb 2026](https://vercel.com/blog/making-agent-friendly-pages-with-content-negotiation)):**
  implementerar content negotiation – samma URL serverar HTML
  till browsers och markdown till agents. Resultat enligt deras
  mätning: 500KB HTML → 3KB markdown (99,4% payload-reduktion =
  kraftigt reducerade tokenkostnader för agenten).
- **OpenCode, Claude Code:** skickar `Accept: text/markdown` först.

**Tre konventioner (kan kombineras):**

1. **`Accept`-header content negotiation** – samma URL, olika
   representation. Mest "webby". Vercels rekommendation.
2. **`.md`-suffix på URL** – `/blog/x` + `/blog/x.md`. Enkelt,
   cachebart, explicit. Stripe, Vercel, Anthropic docs stödjer
   detta parallellt.
3. **`<link rel="alternate" type="text/markdown" href="…">`
   i `<head>`** – fallback-discovery för agenter som inte
   skickar Accept-header.

**Content-Type vid markdown-svar:** `text/markdown; charset=utf-8`.

Källor:
- [Vercel: Markdown Access](https://vercel.com/docs/agent-resources/markdown-access)
- [Vercel blogg feb 2026](https://vercel.com/blog/making-agent-friendly-pages-with-content-negotiation)
- [Roots.io: Accept-header plugins](https://roots.io/some-seo-plugins-claim-markdown-for-ai-but-ignore-the-accept-header/)

### 3. `robots.txt` för AI-botar 2026

Bot-landskapet har mognat. Det finns nu tydlig distinktion:

| Kategori | Bots | Vår hållning |
|---|---|---|
| **Träningscrawlers** (bara skrapar, ger inget tillbaka) | GPTBot, ClaudeBot, CCBot, Google-Extended, Meta-ExternalAgent, Applebot-Extended, Bytespider, Amazonbot | **Blockera** |
| **AI-sökbotar** (live retrieval, kan skicka trafik) | OAI-SearchBot, ChatGPT-User, PerplexityBot, Claude-SearchBot, Claude-User, Google-CloudVertexBot | **Tillåt** |
| **Agent-browsers** (användare kör dem aktivt) | Claude-User (on-demand), ChatGPT-User | **Tillåt** |

Cloudflare Q1 2026 rapport ([TechnologyChecker](https://technologychecker.io/blog/robots-txt-ai-crawlers-blocking-report)):
~12–14% av sajter nämner respektive stora bot i robots.txt.
Strategin "blockera träning, tillåt retrieval" täcker ca 89% av
scraping-trafiken utan att skada ekosystemet.

Anthropic har [sedan 2025](https://almcorp.com/blog/anthropic-claude-bots-robots-txt-strategy/)
tre separata bots: `ClaudeBot` (träning), `Claude-SearchBot`
(index för Claude.ai), `Claude-User` (on-demand user action).

### JSON-LD / Schema.org 2026

- LLMs extraherar data **signifikant säkrare** från JSON-LD än
  från prosa (Data.world-studien: GPT-4 16% → 54% accuracy med
  strukturerad data).
- Viktigast: **content parity**. Schema-data som inte finns
  synligt på sidan flaggas som spam av Google och ignoreras av
  rimliga AI-pipelines.
- För oss: `NewsArticle` på enskild händelse (redan), `Place`
  på plats-/län-sidor (todo #11), `ItemList` på översikter,
  `BreadcrumbList` (todo #11).

---

## Strategiska frågor för en annonsfinansierad sajt

Detta är den **verkligt viktiga frågan** och förtjänar mer tid
än själva implementationen.

### Trade-off: AdSense-intäkter vs AI-synlighet

**Nuläget:**
- Sajten lever på Google AdSense.
- Trafikkällor: Google-sök, direkttrafik, sociala länkar,
  previousPartners (numera mest historiskt).

**Scenario A – blockera träning, tillåt AI-sök (nuvarande + komplettering):**
- Perplexity/ChatGPT Search kan länka till oss → **ger trafik** →
  AdSense-intäkter bibehålls.
- Sajtens innehåll hamnar *inte* i träningsdata utan citation.
- Risk: låg. Detta är mainstream-positionen för svenska nyhetssajter 2026.

**Scenario B – tillåt allt, servera markdown:**
- Potentiell brand-nämning i AI-svar.
- **Men Brottsplatskartan har sällan "författarröst" som citeras** –
  det är polisrapporter med lätt bearbetning. AI-assistenter som
  svarar "enligt brottsplatskartan.se…" är ovanligare än för en
  skribentdriven sajt.
- Direkt svar i AI-UI = **user doesn't click = ingen AdSense-visning**.
- För en hyperlokal "vad hände på min gata"-query är dock klickbehovet
  kvar (man vill se kartan, relaterade händelser).

**Scenario C – blockera allt inklusive AI-sök:**
- Skyddar annonsmodellen kortsiktigt.
- Riskerar att försvinna från framtida "AI-first"-sök om AdSense-intäkter
  minskar ändå (vilket hela branschen förväntar sig 2026–2028).

**Rekommendation:** Scenario A. Det är den defensiva men rationella
positionen. Ompröva om/när:
- AdSense-intäkter faller >30% YoY.
- Konkurrerande svenska sajter börjar dyka upp i Perplexity-svar
  och vi inte gör det.
- Google Search börjar defactot ersätta klassisk organic med AI Overviews
  där vi måste vara närvarande.

### Svenskspråkigt innehåll

- AI-botar har **inget problem** med svenska 2026. Claude, GPT, Gemini
  hanterar svenska som förstaspråk.
- Inget behov av att översätta innehåll för bot-konsumtion.
- `<html lang="sv">` räcker som språksignal (redan på plats – verifiera).

### Juridiska aspekter (sv kontext)

- Polisens RSS är public data – inget upphovsrättsproblem i att
  vi aggregerar, men vi har lagt ner redaktionellt arbete i
  parsning/geokodning/kategorisering. Det är *det arbetet* som
  skyddas och som vi inte vill ge gratis till träningspipelines.
- GDPR: enskilda händelser kan innehålla identifierande info
  (ortspecifika). Ingen anledning att feeda detta till träningsbotar.

---

## Teknisk implementation (Laravel 12)

### A. `llms.txt` – minimal (rekommenderad)

Statisk fil i `public/llms.txt`. Genereras av ett artisan-kommando
som körs nattligen via scheduler, eller helt enkelt hand-skrivs.

```markdown
# Brottsplatskartan

> Svensk webbplats som visualiserar polishändelser från Polisens
> officiella RSS-flöden på en interaktiv karta. Data från 2014
> och framåt. Täcker hela Sverige uppdelat på 21 län.

Brottsplatskartan aggregerar, geokodar och kategoriserar
händelser publicerade av svensk polis. Innehåller ca 1 miljon
händelser fördelat på brottstyper (inbrott, stöld, rån,
misshandel, trafikolyckor m.fl.).

## Viktiga sektioner
- [Startsida med senaste händelser](https://brottsplatskartan.se/): realtidsflöde
- [Interaktiv karta](https://brottsplatskartan.se/karta/): alla händelser geografiskt
- [Län-översikt](https://brottsplatskartan.se/lan/): 21 svenska län
- [Om Brottsplatskartan](https://brottsplatskartan.se/sida/om)
- [API-dokumentation](https://brottsplatskartan.se/sida/api)
- [Ordlista](https://brottsplatskartan.se/ordlista/): polisiär terminologi på svenska
- [Inbrott – temasida](https://brottsplatskartan.se/inbrott)
- [Brand – temasida](https://brottsplatskartan.se/brand)

## Optional
- [Blogg](https://brottsplatskartan.se/blogg)
- [VMA – viktigt meddelande till allmänheten](https://brottsplatskartan.se/vma)
- [Polisstationer](https://brottsplatskartan.se/polisstationer)
```

Inga route-ändringar behövs – `public/llms.txt` serveras direkt av nginx.

### B. Uppdaterad `robots.txt`

```
# Wildcard: tillåt allt utom tracking-pixeln
User-agent: *
Disallow: /pixel?
Disallow: /debug/
Disallow: /debug-response-cache

# Träningscrawlers – blockera (sajten lever på trafik, inte data-mining)
User-agent: GPTBot
Disallow: /

User-agent: ClaudeBot
Disallow: /

User-agent: CCBot
Disallow: /

User-agent: Google-Extended
Disallow: /

User-agent: Meta-ExternalAgent
Disallow: /

User-agent: Applebot-Extended
Disallow: /

User-agent: Bytespider
Disallow: /

User-agent: Amazonbot
Disallow: /

# AI-sökbotar – tillåt (de skickar faktisk trafik)
User-agent: OAI-SearchBot
Allow: /

User-agent: ChatGPT-User
Allow: /

User-agent: PerplexityBot
Allow: /

User-agent: Claude-SearchBot
Allow: /

User-agent: Claude-User
Allow: /

Sitemap: https://brottsplatskartan.se/sitemap.xml
```

### C. Markdown-variant per URL (om vi går vidare – scenario B)

**Rekommendation: `.md`-suffix + `Accept`-header, båda via middleware.**

1. **Middleware `ContentNegotiateMarkdown`:**
   ```php
   // app/Http/Middleware/ContentNegotiateMarkdown.php
   public function handle(Request $request, Closure $next) {
       $wantsMd = str_ends_with($request->path(), '.md')
           || str_contains($request->header('Accept', ''), 'text/markdown');
       $request->attributes->set('format', $wantsMd ? 'md' : 'html');
       return $next($request);
   }
   ```

2. **Route-strategi:** ny route-grupp som dubblerar de viktigaste
   URL:erna (single-event, lan, plats, startsida). Bara de
   publika kärnsidorna – inte admin/debug.

3. **View-renderer:** skapa `resources/views/md/`-parallell till
   befintliga Blade-filer, eller en `MarkdownRenderer`-service
   som tar `CrimeEvent`-modellen och producerar ren markdown
   utan HTML-chrome (ingen navigation, ingen footer, ingen adsense).

4. **Content-Type:** `response()->make($md, 200, ['Content-Type' => 'text/markdown; charset=utf-8'])`.

5. **`<link rel="alternate">`:** lägg till i `layouts/web.blade.php`:
   ```html
   <link rel="alternate" type="text/markdown" href="{{ url()->current() }}.md">
   ```

6. **Cache:** Spatie responsecache key-as så att HTML och MD
   cachas separat. Markdown-varianten kan ha längre TTL
   (24h+) eftersom den är mindre känslig för små layout-ändringar.

7. **Adsense:** markdown-versionen har **ingen AdSense** =
   ingen intäkt per request. Detta är scenario B:s hela poäng att
   analysera.

**Estimerad effort:** 2–4 dagar för en komplett implementation med
tester + cache-strategi + MarkdownRenderer för 4 sid-typer.

### D. JSON-LD ItemList på översiktssidor

Hanteras i todo #11 (SEO-audit). Ingen separat åtgärd här.

---

## Risker

- **`llms.txt`:** minimal risk. Filen kan tolkas fel av någon
  framtida bot, men osannolikt.
- **Markdown-varianter:**
  - SEO-risk: Google kan indexera `.md`-URL:en som duplicate content.
    Måste sätta `Link: <html-url>; rel="canonical"`-header eller
    `<link rel="canonical">` motsvarighet i markdown.
  - Cache-explosion: fördubbling av cacheade URL:er i Redis.
  - AdSense-policy: de tillåter bara ads i HTML, inte markdown,
    så det är inget brott – men det är heller ingen intäkt.
- **robots.txt-ändringar:** om vi råkar blockera Googlebot eller
  Bingbot kollapsar trafik. Test på staging + gradvis rollout.

## Fördelar

- Explicit positionering mot AI-ekosystemet (visar tech-ambition).
- Eventuell citation i Perplexity/ChatGPT → brand-awareness.
- Minskad träningsdata-läckage av vårt redaktionella arbete.
- `llms.txt` är mer försäkring än investering: om standarden
  tar fart 2027 finns vi redan där.

## Prioritering

| Prio | Åtgärd | Effort |
|---|---|---|
| 1 | Uppdatera `robots.txt` (komplettera med CCBot, Meta, Applebot-Extended, Bytespider + explicit allow för sökbotar) | 30 min |
| 2 | Skapa statisk `public/llms.txt` | 1 tim |
| 3 | Verifiera JSON-LD i `CrimeEvent.php` mot Google Rich Results Test | 1 tim (överlapp med todo #11) |
| 4 | Sätt upp access-log-övervakning: spåra `User-Agent: *Bot*` för att se vilka AI-crawlers som *faktiskt* besöker sajten | 2 tim |
| 5 | **Evaluate decision point** – efter 3 mån access-log-data, besluta om scenario B (markdown-variant) är värt 2–4 dagars arbete | – |
| 6 | Om 5 → ja: implementera markdown content negotiation | 2–4 dagar |

## Kostnadsuppskattning

- **Quick wins (prio 1–3):** ca 3 timmar totalt. Direkt värde.
- **Access-log-övervakning (prio 4):** 2 timmar + pågående observation.
- **Markdown-variant (prio 6):** 2–4 dagar utveckling + löpande
  cache-overhead i Redis (marginellt).

## Öppna frågor

1. Hur mycket AI-crawler-trafik får vi egentligen idag? Inga siffror.
   Behöver access-log-analys innan vi vet om detta är värt något alls.
2. Ska `llms.txt` auto-genereras från DB (t.ex. topp-10 mest lästa
   län-sidor) eller räcker statisk?
3. Finns AdSense-policy mot att servera samma URL med/utan reklam
   via content negotiation? (Sannolikt inte – olika URLs via
   `.md`-suffix är säkrare ur policy-synpunkt.)
4. Ska previousPartners API (som redan har `format=html`) byggas ut
   med `format=md`? Billigt att göra, ingen nackdel.
5. Blockerar vi Meta-ExternalAgent även om vi inte är i EU/AI-Act-
   diskussionen? Ja – de ger inget tillbaka.

## Status / nästa steg

- [ ] Besluta om prio 1–3 (förväntat: ja, det är lågt hängande).
- [ ] Implementera prio 1: `robots.txt`-uppdatering.
- [ ] Implementera prio 2: `public/llms.txt`.
- [ ] Sätt upp access-log-filter för AI user agents (prio 4).
- [ ] Vänta 3 mån, analysera, återkom till prio 6.

---

## Källor (verifierade 2026-04-21)

- [llmstxt.org – officiell spec](https://llmstxt.org/)
- [PPC.land – "llms.txt adoption stalls" (mars 2026)](https://ppc.land/llms-txt-adoption-stalls-as-major-ai-platforms-ignore-proposed-standard/)
- [Longato – "Why AI Crawlers Ignore It" 2025 audit](https://www.longato.ch/llms-recommendation-2025-august/)
- [Am I Cited – overhyped or essential](https://www.amicited.com/blog/llms-txt-truth-overhyped-or-essential/)
- [Vercel – Markdown Access docs](https://vercel.com/docs/agent-resources/markdown-access)
- [Vercel blog – content negotiation (feb 2026)](https://vercel.com/blog/making-agent-friendly-pages-with-content-negotiation)
- [Roots.io – SEO plugins ignore Accept header](https://roots.io/some-seo-plugins-claim-markdown-for-ai-but-ignore-the-accept-header/)
- [No Hacks – AI user agents 2026](https://nohacks.co/blog/ai-user-agents-landscape-2026)
- [TechnologyChecker – Cloudflare Q1 2026 robots.txt rapport](https://technologychecker.io/blog/robots-txt-ai-crawlers-blocking-report)
- [ALM Corp – Anthropic 3-bot-strategi](https://almcorp.com/blog/anthropic-claude-bots-robots-txt-strategy/)
- [Playwire – publisher's guide to blocking AI](https://www.playwire.com/blog/how-to-block-ai-bots-with-robotstxt-the-complete-publishers-guide)
- [ALM Corp – structured data for LLMs](https://almcorp.com/blog/structured-data-for-llms-technical-guide/)
