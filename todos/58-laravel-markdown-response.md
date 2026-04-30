**Status:** aktiv (idé — research saknas)
**Senast uppdaterad:** 2026-04-30
**Källa:** Inbox Brottsplatskartan (2026-04-30)

# Todo #58 — Servera sajten som markdown till AI-agenter

## Sammanfattning

> Använd https://freek.dev/3016-a-laravel-package-to-serve-markdown-to-ai-agents
> för att skapa AI-vänlig sajt genom att servera allt som markdown också.

Spatie släppte `spatie/laravel-markdown-response` 2025/2026: middleware
som autodetekterar AI-agenter (`Accept: text/markdown`, `.md`-suffix,
eller User-Agent-match på `GPTBot`/`ClaudeBot` etc.) och konverterar
HTML-svaret till markdown on the fly. Cachar konverteringen.

## Bakgrund

Vi har redan **delar av AI-vänlighet** sedan #12 (klar 2026-04-22):

- `llms.txt` på root med övergripande sitemap-pekare till AI-agenter
- Markdown-endpoint per event (`/handelse/{id}.md` eller liknande —
  bekräfta i `routes/web.php`)

`laravel-markdown-response` skulle utöka detta:

- **Alla** sidor blir tillgängliga som markdown automatiskt — inte
  bara events. Ortsidor, län-sidor, statistik-sidor, etc.
- **Auto-detection** — ingen extra route, AI-agenter får markdown
  utan att vi underhåller separata vyer.
- **Cache** ingår — konverteringen körs en gång per sida och TTL.

## Förslag

1. **Granska överlapp med #12.** Om `/handelse/{id}.md` redan finns
   och funkar — paketet kompletterar (ortsidor, statistik etc.) men
   ersätter inte. Kontrollera att paketets HTML-konvertering ger
   minst lika god markdown som befintliga `.md`-routes på events.
2. **Installera + konfigurera middleware** på relevant route group.
   Initialt scope: ortsidor (`PlatsController`, `CityController`),
   län-sidor (`LanController`), statistik (`/statistik`).
3. **Verifiera med User-Agent-test:** curl med `User-Agent: ClaudeBot/1.0`
   och `Accept: text/markdown` — bekräfta att markdown returneras.
4. **Cache-strategi:** paketets default räcker initialt. Synka TTL
   med vår Spatie Response Cache så att konverterad markdown inte
   blir dyrare än HTML-cachehit.
5. **Mät:** ökat AI-bot-trafik (User-Agent breakdown i nginx-logg
   eller via response cache-keys). 30d efter deploy.

## Risker

- **Dubbla cache-lager** — paketet cachar konvertering, vi cachar
  hela responses. Risk för stale markdown om HTML cache-bumpas men
  markdown-cache inte. Verifiera cache-invalidation-flödet.
- **Konvertering täcker inte allt** — interaktiva element (Leaflet-
  karta) blir oanvändbara i markdown. Kan vara OK (AI-agent läser
  kontext, inte UI), men bekräfta.
- **Paketet är nytt** (släppt 2026) — risk för oupptäckta buggar.
  Kör först på en låg-trafik route (t.ex. `/sida/om`) som test.

## Confidence

**Medel-hög.** Tekniken är solid (Spatie/Freek), men real value beror
på hur mycket AI-bot-trafik vi faktiskt ser, och om #12:s befintliga
markdown-endpoints redan täcker det viktigaste.

## Beroenden

- Bygger på #12 (klar 2026-04-22) — överlapp ska granskas innan
  installation.

## Nästa steg

1. Inventera nuvarande markdown-endpoints (kolla `routes/web.php`
   efter `.md`-suffix-routes från #12).
2. Läs paketets README + ändringslogg —
   https://github.com/spatie/laravel-markdown-response (om det är
   där det landade) eller via composer search.
3. Pilot på `/sida/om` + 1 ortsida. Curl-test med ClaudeBot UA.
4. Bredd-deploy om pilot är OK.

## Källor

- https://freek.dev/3016-a-laravel-package-to-serve-markdown-to-ai-agents
- https://freek.dev/3022-how-to-make-your-laravel-app-ai-agent-friendly
