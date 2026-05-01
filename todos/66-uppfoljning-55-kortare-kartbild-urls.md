**Status:** uppföljning — passiv mätperiod till 2026-05-31
**Senast uppdaterad:** 2026-05-01
**Källa:** uppföljning av [#55](done/55-kortare-kartbild-urls.md) som rullades 2026-05-01

# Todo #66 — Uppföljning av #55: mät CWV-impact och PHP-FPM-CPU efter `/k/v1/`-rollout

## Sammanfattning

#55 rullades fullt ut i prod 2026-05-01 (kortare kartbild-URL:er via
`/k/v1/`-routen + 301 till tileservern + immutable cache). Före-data
visade ~80 % av HTML-bytes på listsidor var långa kartbild-URL:er.

Mät om 30 dagar (2026-05-31) om vinsten är reell på riktiga användare
och om någon negativ effekt syns på origin-load.

## Att mäta 2026-05-31

### CWV (Core Web Vitals via CrUX / GSC)

- LCP, INP, CLS för listsidor (`/{stad}`, `/{stad}/handelser/...`).
  Jämför mot baseline 2026-04-30 (innan #55).
- Hypotes: marginell LCP-förbättring eftersom thumbs är `loading="lazy"`,
  men TTFB/HTML-parse kan visa nedgång på listsidor med många events.

### PHP-FPM-CPU under bot-burst

- Kolla `docker compose stats app` på Hetzner under en period med
  ovanligt hög bot-trafik (kolla nginx access-logg, t.ex.
  `Googlebot|bingbot|GPTBot`).
- Hypotes: marginal ökning (~1–3 %) pga cold-cache `/k/v1/`-hits.
  Om vi ser >10 % press eller köbildning → överväg cache framför
  (se "Framtida förbättringar" i #55).

### Spatie Response Cache hit-rate

- `ssh deploy@brottsplatskartan.se 'docker compose exec redis redis-cli -a "$REDIS_PASSWORD" KEYS "laravelresponsecache-*k*v1*" | wc -l'`
- Räkna keys för `/k/v1/`-routen. Om <100 = något är fel (ingen cache).
  Om många tusen = funkar.

### 404-rate på `/k/v1/*`

- `ssh deploy@brottsplatskartan.se 'docker compose logs --tail 10000 app | grep "GET /k/v1/" | grep -c " 404 "'`
- Om >0.1 % = någon blade-caller bygger ogiltig URL eller event är
  borttaget men HTML cachat.

### Sociala-media share-test

- Kasta in en event-URL i FB OG-debugger
  (`https://developers.facebook.com/tools/debug/`) och Twitter card
  validator. Verifiera att preview-bilden visas (= FB följer 301:an
  som vi förväntar). Speciellt viktigt: kolla om någon shared event
  innan #55-deploy fortfarande visar gammal preview (FB cachar
  aggressivt).

## Beslut beroende på utfall

| Utfall                                   | Åtgärd                                                                                              |
| ---------------------------------------- | --------------------------------------------------------------------------------------------------- |
| CWV oförändrat eller bättre, CPU OK      | Inget — bara stäng denna todo                                                                       |
| CWV bättre, CPU pressad                  | Skapa todo: aktivera Cloudflare framför origin (gratis tier) — global edge-cache löser cold-hit-CPU |
| CWV sämre på listsidor (TTFB-regression) | Profilera: är det Spatie cache-miss på `/k/v1/`? Kolla cache-key-konstruktion                       |
| 404-rate hög på `/k/v1/*`                | Kolla logg vilka URL-mönster failar; sannolikt cachat HTML pekar på borttaget event                 |
| Social-share visar trasig preview        | Sannolikt FB-cache av gammal long-URL — manuellt re-scrape via FB debugger för aktuella event       |

## Confidence

**Hög på att mätningen är värd att göra**, låg-medel på att vi behöver
agera. Sannolikast utfall: allt OK, vi stänger todon. Men passiv
mätperiod kostar oss inget och fångar regressioner som annars hade
smugit förbi.
