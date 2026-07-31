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

## Fallgropar — läs innan du kör loggkommandon

Sparar tid vid felsökning. Lärt den hårda vägen.

- **`docker compose logs --since 168h app` timeoutar.** Access-loggen är
  hundratals MB per vecka och stdout-uppspelningen tar många minuter. Använd
  korta fönster (`--since 60m`, `--since 6h`) eller `--tail N`. Behöver du en
  hel vecka: kör aggregeringen **serversidigt i bakgrunden** med `nohup … &`
  och läs resultatfilen efteråt — inte genom SSH-pipen.
- **Caddy har ingen access-logg.** Bara `warn`/`error` (se tabellen ovan).
  Försök inte räkna 5xx eller trafik där — det finns inga `status`-fält att
  aggregera. 5xx räknas i **app**-containerns nginx-logg.
- **Caddy-loggen är däremot rätt ställe för nertid.** Se receptet nedan.
- **Loggarnas tidszon är UTC**, appens och commit-tidernas är
  Europe/Stockholm (+2 på sommaren). Lägg till två timmar innan du jämför
  loggrader med commits — annars ser sambandet ut att saknas.
- **`docker compose logs -t`** behövs för tidsstämplar på containrar som inte
  själva stämplar sina rader (Caddy stämplar internt som unix-`ts`).

## Recept: hitta nertidsfönster (när sajten "gått ner")

Caddy terminerar all trafik. Startar den om ligger sajten nere
(connection refused) tills den är uppe igen — så dess start/stopp-rader är
facit för nertid:

```bash
cd /opt/brottsplatskartan
docker compose logs --since 168h --no-log-prefix -t caddy 2>&1 \
  | grep -E "byeee|serving initial configuration" \
  | awk '{print $1, ($0 ~ /byeee/ ? "STOPP" : "START")}' \
  | sed 's/T/ /;s/\..*Z//'
```

Varje STOPP→START-par är ett avbrott (typiskt 2–13 s). Tidsstämplarna är UTC.

Skilj på orsakerna:

- **Par som matchar commit-tider (+2 h)** → deployen själv, se
  `deploy/deploy.sh`. Fram till 2026-07-31 kördes
  `docker compose restart caddy` vid _varje_ push; numera bara när
  `deploy/Caddyfile` eller `/opt/caddy-sites.d` ändrats. Ser du ett par vid
  en vanlig kod-deploy efter det datumet är det alltså inte deployen —
  leta vidare bland orsakerna nedan.
- **Alla containrar startade samtidigt, `RestartCount=0`** → docker-daemonen
  startade om (t.ex. paketuppgradering). Verifiera med
  `sudo journalctl -u docker.service --since "8 days ago" | grep -i "Stopping\|Starting"`.
- **`RestartCount>0` eller `OOMKilled=true`** → containern kraschade. Kolla:

```bash
for c in $(docker ps -a --format "{{.Names}}"); do
  printf "%-40s restarts=%s started=%s oomkilled=%s\n" "$c" \
    "$(docker inspect -f '{{.RestartCount}}' $c)" \
    "$(docker inspect -f '{{.State.StartedAt}}' $c)" \
    "$(docker inspect -f '{{.State.OOMKilled}}' $c)"
done
```

Kom ihåg att Redis-fel (`LOADING Redis is loading the dataset in memory`,
`array_map(): Argument #2 … false given` från `PhpRedisConnection.php`) i
laravel.log oftast är **följdfel** av en omstart, inte egna buggar — jämför
tidsstämpeln med omstartsfönstret innan du felsöker dem.
