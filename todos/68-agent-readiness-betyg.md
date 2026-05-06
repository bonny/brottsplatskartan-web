**Status:** quick wins implementerade lokalt 2026-05-06 — väntar på deploy + re-scan
**Senast uppdaterad:** 2026-05-06

# Todo #68 — Höj agent-readiness-betyg (isitagentready.com)

**Källa:** Inbox Brottsplatskartan (2026-05-06)

## Sammanfattning

Kolla igenom vad vi kan och bör göra för att få högre betyg här.
https://isitagentready.com/brottsplatskartan.se

Cloudflare-tjänst som mäter hur "agent-redo" en sajt är (robots.txt,
sitemap, Markdown-negotiation, MCP, Agent Skills, WebMCP, OAuth, m.fl.).
Skala 0–5. Brottsplatskartan ligger på **Level 0 "Not Ready"** — nästa
nivå är 1 "Basic Web Presence" som kräver sitemap + Link-headers.

## Bakgrund

Rapport hämtad 2026-05-06 via `POST /api/scan`.

### Pass (3)

- **`robots.txt`** — finns och har giltigt format
- **Markdown for Agents** — stöds (vi har `spatie/laravel-markdown-response`, todo #58)
- **AI bot-rules i robots.txt** — vi blockerar gptbot, claudebot, google-extended,
  ccbot, bytespider, perplexitybot, cohere-ai, applebot-extended,
  meta-externalagent, diffbot

### Neutral (informationellt eller ej tillämpligt)

- **Web Bot Auth** — informationellt (gäller bot-operatörer, inte oss)
- **Commerce** — x402, MPP, UCP, ACP, AP2 — vi är inte e-handel

### Fail (10)

| Check                    | Status         | Notering                                                                                                                         |
| ------------------------ | -------------- | -------------------------------------------------------------------------------------------------------------------------------- |
| sitemap                  | falskt negativ | `/sitemap.xml` returnerar 200 och refereras i robots.txt — scannern hittar den inte (cookie-sätt? cache-header? bot-blockering?) |
| Link headers             | saknas         | RFC 8288 — peka på api-katalog/service-doc                                                                                       |
| Content Signals          | saknas         | Cloudflare-standard för att signalera AI-träningspolicy i robots.txt                                                             |
| API Catalog              | saknas         | `/.well-known/api-catalog`                                                                                                       |
| OAuth discovery          | saknas         | inte tillämpligt — vi har ingen auth                                                                                             |
| OAuth Protected Resource | saknas         | inte tillämpligt                                                                                                                 |
| MCP Server Card          | saknas         | för en MCP-server (vi har ingen ännu)                                                                                            |
| A2A Agent Card           | saknas         | bara om vi blir agent själva                                                                                                     |
| Agent Skills index       | saknas         | publik skill-katalog för agent-konsumtion                                                                                        |
| WebMCP                   | saknas         | inline MCP-tools i HTML — kräver implementation                                                                                  |

## Implementerat 2026-05-06 (lokalt, ej deployat)

- **Sitemap utan cookies:** ny middleware `StripCookiesForAgentDiscovery`
  strippar `Set-Cookie` från `/sitemap.xml`, `/sitemap-*.xml`,
  `/.well-known/*` och `/llms.txt`. Verifierat lokalt — inga
  `laravel_session` eller `XSRF-TOKEN` på sitemap-svar längre.
- **Link-headers:** ny middleware `AgentDiscoveryLinkHeaders` lägger
  RFC 8288 `Link`-headers på startsidan: `llms.txt`, `api-catalog`,
  `service-doc` (mot GitHub-URL för `docs/API.md`).
- **Content Signals:** lagt till `Content-Signal: search=yes, ai-input=yes, ai-train=no`
  i `public/robots.txt` (Cloudflare-spec, matchar vår existerande policy:
  retrieval ok, träning förbjuden).
- **API Catalog (RFC 9727):** ny route `GET /.well-known/api-catalog`
  returnerar Linkset-JSON (`application/linkset+json`) med våra fem
  publika API-endpoints, varje med `service-desc` mot `docs/API.md`.

PHPStan: `composer analyse` grön. Verifierat lokalt mot
`http://brottsplatskartan.test:8350`.

**Nästa steg:** deploy + re-scan på isitagentready.com. Förväntat: går
från Level 0 → Level 2–3 (sitemap pass, linkHeaders pass, contentSignals
pass, apiCatalog pass).

## Förslag

Sortera efter värde/kostnad. Easy wins först.

1. **Felsök sitemap-falsknegativen** (~30 min)
    - `curl` från en bot-UA mot `https://brottsplatskartan.se/sitemap.xml` — verifiera 200, content-type, ingen redirect.
    - Misstänkt: `Set-Cookie` på sitemap (Laravel session-cookie sätts på alla requests) kan förvirra parsern. Värt att lägga till `->withoutMiddleware([\Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class, \Illuminate\Session\Middleware\StartSession::class])` på sitemap-routen — den behöver ingen session.
    - Alternativt: rapportera bug till isitagentready.com.

2. **Link-headers på startsidan** (~30 min)
    - RFC 8288 — lägg till middleware/header som skickar:
        ```
        Link: </llms.txt>; rel="alternate"; type="text/plain"
        Link: </docs/api>; rel="service-doc"
        ```
    - Vi har redan `llms.txt` (todo #12) och `docs/API.md`. `service-doc` kan peka på en publik route för API-dokumentation om vi inte redan exponerar `/docs/api`.

3. **Content Signals i robots.txt** (~30 min)
    - Cloudflare-spec: https://blog.cloudflare.com/content-signals/
    - Signalerar AI-träningspolicy mer maskin-läsbart än bara `Disallow`. Bör matcha vår existerande policy (träning förbjuden, retrieval ok).
    - Exempel-direktiv: `Content-Signal: ai-train=no, search=yes` (kontrollera exakt syntax mot specen).

4. **API Catalog `/.well-known/api-catalog`** (~1 h)
    - Vi har redan ett publikt API (`/api/events`, `/api/event/{id}`, `/api/eventsMap`, `/api/eventsNearby`, `/api/areas`) — dokumenterat i `docs/API.md`.
    - Skapa OpenAPI-spec eller följ api-catalog-specens minimala JSON-format. Returnera från en Laravel-route med korrekt `Content-Type`.

5. **Agent Skills index** (~2 h, kräver mer tankearbete)
    - Publicera publika skills för agent-konsumtion (sök händelser efter ort/datum/typ, hämta enskild händelse, etc.).
    - Skill-format: https://agentskills.io/
    - Värdebedömning: hur mycket trafik från agenter får vi via detta vs. existerande JSON-API?

6. **MCP Server Card + WebMCP** (~stort projekt)
    - Skulle innebära att bygga en Brottsplatskartan-MCP-server (eller WebMCP-integration i frontend). Inte triviallt.
    - Bryt ut till egen todo om vi väljer att gå vidare. Värdet beror på om Claude/ChatGPT/agent-användare faktiskt skulle använda den.

7. **Skippa**: OAuth-relaterat (vi har ingen auth), A2A Agent Card (vi är inte agent), Commerce (inte e-handel).

## Risker

- **Falskt-negativ-buggen kan vara svår att fixa** om scannern inte är transparent
  med vad den letar efter. Värsta fallet: vi får aldrig sitemap-passet och därmed
  aldrig Level 1, oavsett hur mycket annat vi gör. → Värdet av betyget begränsas.
- **Specs är emerging** — flera av kategorierna (WebMCP, Agent Skills, A2A) är
  Cloudflare-/Anthropic-/OpenAI-specifika och kan ändras eller dö. Bygg inte
  djupt mot oklara protokoll.
- **Resurs-prioritet**: dessa förbättringar tävlar med riktiga SEO/UX-todos.
  Värdet är "framtidssäkring för agent-trafik" — svårt att mäta innan agenter
  driver märkbar trafik.

## Confidence

medel — fix 1–4 är konkreta och rimliga (1–4 timmar totalt) och tar oss till
Level 2–3. Fix 5–6 är större och behöver egen vinst-bedömning. Värdet av
själva betyget är mest signal-/PR-värde tills agent-trafik blir mätbar i GA4.
