# Loggar — var de finns och hur man läser dem

Snabbreferens för att hitta access-/felloggar på prod (Hetzner) och göra
trafik-/bot-analys. Alla kommandon körs från `/opt/brottsplatskartan` på servern
(`ssh deploy@brottsplatskartan.se`).

## Var loggarna ligger

| Källa                 | Container     | Innehåll                                                             |
| --------------------- | ------------- | -------------------------------------------------------------------- |
| **App access-logg**   | `app`         | nginx combined-logg för huvudsajten — **alla HTTP-requests**         |
| **Caddy**             | `caddy`       | Bara `warn`/`error` (ingen full access-logg) — upstream-fel, TLS m.m |
| **Tileserver/kartor** | `nginx-tiles` | Statiska kartbilder (`kartbilder.brottsplatskartan.se`)              |
| **Scheduler**         | `scheduler`   | `schedule:work`-jobb (fetch, texttv, cache-warmup)                   |
| **Laravel app-logg**  | i `app`       | `storage/logs/laravel.log` (PHP-fel, stack traces)                   |

Access-loggar går till **container-stdout**, så `docker compose logs app` är
rätt ingång (inte en fil på disk). Laravel-felloggen ligger däremot på disk i
containern: `docker compose exec app tail -f storage/logs/laravel.log`.

## App access-loggformat (viktigt för IP-analys)

Sajten ligger bakom Caddy, så `remote_ip` i nginx är alltid Caddy
(`172.18.0.6`). **Riktig klient-IP loggas sist på raden** (från
`X-Forwarded-For`). Exempelrad:

```
172.18.0.6 - - [25/Jun/2026:19:49:02 +0000] "GET /lan/skane-lan/... HTTP/1.1" 200 44717 "-" "Mozilla/5.0 (compatible; Googlebot/2.1; ...)" "66.249.68.131"
```

Vid split på `"` (`awk -F"`):

- `$2` = request (metod + URI)
- `$6` = **user-agent**
- `$8` = **riktig klient-IP**

## Recept: trafik-/bot-analys senaste timmen

```bash
ssh deploy@brottsplatskartan.se
cd /opt/brottsplatskartan

# Spara senaste 60 min access-rader till temp
docker compose logs app --since 60m --no-log-prefix 2>/dev/null \
  | grep -E '" (200|301|302|404|429|403) ' > /tmp/bpk_access.log

# Totalt antal requests
wc -l < /tmp/bpk_access.log

# Topp 20 klient-IP
awk -F'"' '{print $8}' /tmp/bpk_access.log | sort | uniq -c | sort -rn | head -20

# Topp 15 user-agents (avslöjar crawlers/scrapers)
awk -F'"' '{print $6}' /tmp/bpk_access.log | sort | uniq -c | sort -rn | head -15

# Vilka sidor en specifik IP hämtar
grep '"<IP>"$' /tmp/bpk_access.log | awk -F'"' '{print $2}' | head -40
```

Justera `--since` (`5m`, `60m`, `24h`) efter behov.

## GA4 realtid (kompletterande, för JS-besökare)

Bottar kör sällan JS, så de flesta crawlers **syns inte i GA4** — använd
access-loggen ovan för dem. GA4-realtid fångar däremot headless-scrapers som
exekverar JS (visar sig ofta som onaturligt många "aktiva användare" från en
datacenter-region, t.ex. Singapore/USA, spridda över hundratals distinkta
sidor). Se [analytics.md](analytics.md) för MCP-queries; använd
`run_realtime_report` med dimensionerna `country`/`city` och
`unifiedScreenName`.
