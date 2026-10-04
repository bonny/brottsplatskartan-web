**Status:** öppen — kräver konfiguration i GA4:s gränssnitt, går inte att
göra i kod eller via API:t.
**Senast uppdaterad:** 2026-10-04
**Källa:** SEO-granskning 2026-08-02 (fynd 1)

# Todo #101 — Filtrera bort botttrafik i GA4

## Problemet

Under juni–juli 2026 stod en headless-browserfarm för **79 % av all
GA4-trafik**. Den kör gtag, så den syns som vanliga sessioner.

| Signal                                 | Värde                     |
| -------------------------------------- | ------------------------- |
| Direct-sessioner från Singapore (90 d) | 658 336 av 744 401        |
| Direct via Chrome/Windows              | 701 594                   |
| Distinkta landningssidor               | 459 031                   |
| Sessioner per `/plats/`-URL            | ~38, i alfabetisk ordning |
| Avvisningsfrekvens                     | ~90 %, 1,0 sidor/session  |

Månadsförlopp Singapore: mars 10 403 → april 14 572 → maj 8 655 →
**juni 268 957 → juli 365 162** → stopp 2026-07-30 (3 sessioner).

Inget i git förklarar stoppet — den slutade av sig själv och kan komma
tillbaka.

**Pågående i mindre skala:** Kina, ~350 sessioner/dygn desktop med 98,8 %
avvisning och 1,0 sidor/session. Samma signatur, mycket lägre volym.

## Varför det spelar roll

Botten var **648 395 desktop mot 415 mobil**. Det avgör vilka mätningar
som överlever:

- ✅ **Mobilsegmenterade mätningar är rena.** Sverige hade 115 181
  mobilsessioner mot Singapores 415. #90:s grind (sidor/session från `/`
  på mobil) är alltså giltig.
- ❌ **All desktop-analys juni–juli är värdelös** — desktop var 97 % bot
  (Singapore 648 395 mot Sverige 20 808).
- ❌ **Osegmenterade site-trender är kontaminerade**, t.ex. den
  "site-trend +5,7 % dwell" som #64:s DiD-analys mätte mot, och #60:s
  CTR/dwell-grind.
- ⚠️ **#99:s Redis-uppföljning är confounderad.** En crawler som drar
  459 031 distinkta URL:er genom responscachen är en trolig medorsak till
  att den svällde till 1,93 GB. Crawlern slutade 07-30, ZSTD deployades
  07-31 — förbättringen som mäts 08-07 är delvis crawlerns frånvaro.

GSC-data påverkas **inte** — Google Search Console mäter söktrafik, inte
sessioner.

## Åtgärd

Konfigureras i GA4:s gränssnitt. `analytics-mcp` är read-only, så det går
inte att göra härifrån.

**Viktigt: GA4 kan inte filtrera på land.** Data filters stödjer bara två
typer — _Internal Traffic_ (definieras på **IP-adress**) och _Developer
Traffic_ (`debug_mode`). Det finns alltså ingen permanent inställning som
tar bort "Singapore desktop". Det är värt att veta innan man letar.

Tre realistiska vägar, i den ordning de bör övervägas:

### 1. Segmentera bort i analysen (gäller nu, ingen konfiguration)

Vid varje analys av perioden juni–juli 2026: exkludera `country` =
Singapore/Kina med `deviceCategory` = desktop. Det är vad
`analytics-mcp`-anropen i den här granskningen gjorde, och det räcker för
att få rena siffror ur historiken.

Kom ihåg att **mobilsegmenterade mätningar redan är rena** — botten var
99,9 % desktop. Ofta räcker det att segmentera på mobil.

### 2. Internal-traffic-filter på IP (om crawlern kommer tillbaka)

Caddy loggar `client_ip` för varje request. Fånga intervallen medan
trafiken pågår:

```bash
ssh deploy@brottsplatskartan.se \
  'cd /opt/brottsplatskartan && docker compose logs --since 1h caddy' \
  | grep -o '"client_ip":"[^"]*"' | sort | uniq -c | sort -rn | head -40
```

Lägg sedan de största /24-näten i **Admin → Data streams → välj strömmen
→ Configure tag settings → Show all → Define internal traffic**, och
aktivera **Admin → Data filters → Internal Traffic**. Kör i _Testing_ en
vecka innan _Active_.

### 3. Blockera vid Caddy (bara vid återkomst och hög last)

Sista utvägen. Träffar även legitim trafik från samma moln-IP:n och
kräver underhåll. Motiverat först om crawlern återvänder i juli-volym och
påverkar svarstider — vilket den gjorde: se lastanalysen i
`docs/superpowers/specs/2026-08-02-plats-indexeringstroskel-design.md`.

**Obs:** GA4-datafilter är aldrig retroaktiva. Historisk data juni–juli
förblir kontaminerad oavsett vad som konfigureras — väg 1 är den enda som
hjälper för den perioden.

## Uppföljning

Kolla månadsvis om Singapore-volymen återkommer. Enkel indikator:
desktop-sessioner utanför Sverige med avvisning > 90 % och
sidor/session ≈ 1,0.

**2026-09-16: volymen är tillbaka.** GA4 realtid visade 2 431 av 2 468
aktiva användare (senaste 30 min) från Singapore, mot 34 från Sverige
(`![[Screenshot_20260916-131226.png]]` i Obsidian). Från Inbox
Brottsplatskartan (2026-10-04): "Det här är inte hållbart. Extremt mkt
trafik från Singapore hela tiden".

## Serverlast 2026-10-04

Access-loggen för en timme (48 228 requests, load 2,5–3 på 4 kärnor; app
65 % CPU, MariaDB 31 %, tileserver 23 %):

| Källa                                     | Req/h   | Andel |
| ----------------------------------------- | ------- | ----- |
| Headless Chrome `X11; Linux … Chrome/154` | 13 518  | 28 %  |
| Baidu (`116.179.0.0/16`, inkl. -render)   | ~5 900  | 12 %  |
| Google, Bing, Apple, Amazon               | ~10 000 | ~20 % |
| Ahrefs, DataForSeo, MJ12, Semrush         | ~3 350  | 7 %   |

**Headless Chrome-farmen** kommer från 527 IP:n (median 29 req/IP/h, max
58), alltså en roterande proxypool. 89 % av requesterna är
`/k/v1/…jpg`: den hämtar gamla händelser (median-id ~307 000) och laddar
alla kartbilder utan cache. Varje bild kostar ett PHP-anrop för 301:an
plus en tileserver-rendering eftersom gamla bilder sällan är cachade.
Går inte att blocka på IP och respekterar knappast robots.txt. Den syns
nästan inte i GA4 (~230 Linux-sessioner/vecka totalt), så det är
**inte** samma bot som Singapore-trafiken.

**GA4-"Kina desktop"** (vecka 40: 10 823 sessioner) är med största
sannolikhet Baiduspider-render, som kör JS och därmed gtag.

**Singapore** toppade vecka 38 (168 923 sessioner) och är nere i 6 896
vecka 40.

### Åtgärder 2026-10-04

1. `public/robots.txt`: Disallow för Baiduspider, Baiduspider-render,
   DataForSeoBot, MJ12bot.
2. `deploy/Caddyfile`: loggade hela requesten för headless-UA:n en
   förmiddag. 1 977 requests från 174 IP:n (77 % USA, 1 % Singapore,
   ingen Kina — ett landsblock hade alltså inte hjälpt). Alla såg ut som
   riktig Chrome 154 (`Sec-Ch-Ua`, `Sec-Fetch-*`, HTTP/2, cookies) men
   **alla** hade `Accept-Language: en-US,en;q=0.9` utan `sv`.
   Diagnostiken ersattes av ett block: Caddy svarar 403 när UA:n är
   exakt `X11; Linux x86_64 … Chrome/154.0.0.0` **och**
   `Accept-Language` saknar `sv`. Testat lokalt: farm → 403, Linux med
   `sv-SE` eller `en-US,…,sv` → 200, Windows/Googlebot → 200.
   **Blocket slutar gälla när farmen byter Chrome-version** — kolla vid
   uppföljningen.
3. Senare, om det behövs: svara `/k/v1`-301:an utan en PHP-worker.
