**Status:** klar 2026-04-26
**Senast uppdaterad:** 2026-04-26

# Todo #8 – Google Analytics MCP-server i Claude Code

## Utfört

- `analytics-mcp` 0.2.0 installerad via `pipx install analytics-mcp` lokalt på ny dator (2026-04-26).
- GCP-projekt `brottsplatskarta-1476012766238` skapad, `analyticsadmin.googleapis.com` + `analyticsdata.googleapis.com` aktiverade.
- OAuth Desktop-klient skapad i GCP, det egna Googlekontot tillagt som test user på consent-skärmen.
- ADC-token genererad med scopes `analytics.readonly` + `cloud-platform`, quota project satt.
- Registrerad i Claude Code: `claude mcp add analytics-mcp --scope user --transport stdio --env GOOGLE_APPLICATION_CREDENTIALS=… --env GOOGLE_PROJECT_ID=… -- analytics-mcp`.
- Verifierat via `get_account_summaries` (20 properties syns, brottsplatskartan = `305258979`) och två faktiska `run_report`-anrop (top landningssidor från Google organisk + mobile/desktop-trend för `/handelser`).
- Dokumentation i [docs/analytics.md](../../docs/analytics.md) + pekare i AGENTS.md. Hela run-booken nedan behålls för framtida ny-dator-setup.

Stegen nedan är guiden för att sätta upp på ytterligare en dator.

## Sammanfattning

Konfigurera Googles officiella (experimentella) Google Analytics MCP-server
lokalt i Claude Code, så att Claude kan ställa frågor direkt mot GA4-data
för Brottsplatskartan. Detta ger bättre underlag för datadrivna beslut
kring cache pre-warm (populära län/städer), SEO-prioritering (vilka
landningssidor som driver trafik), eventuell publik stats-sida m.m.

Paketet heter `analytics-mcp` (PyPI) och ligger i
[googleanalytics/google-analytics-mcp](https://github.com/googleanalytics/google-analytics-mcp).
Det är Python 3.10+ och körs bäst via `pipx run analytics-mcp`.
Officiell dokumentation beskriver installation för Gemini CLI, men samma
server fungerar med Claude Code eftersom stdio-transporten är
MCP-standard.

## Verktyg som servern exponerar

- `get_account_summaries` – lista konton och properties
- `get_property_details`
- `list_google_ads_links`
- `run_report` – kör GA4 Data API-rapport (dimensions + metrics)
- `get_custom_dimensions_and_metrics`
- `run_realtime_report`

## Förutsättningar

1. Python 3.10+ och `pipx` installerat (macOS: `brew install pipx && pipx ensurepath`).
2. Google Cloud-projekt med följande API:er aktiverade:
    - Google Analytics Admin API (`analyticsadmin.googleapis.com`)
    - Google Analytics Data API (`analyticsdata.googleapis.com`)
3. ADC-credentials (Application Default Credentials) med scope
   `https://www.googleapis.com/auth/analytics.readonly`.
4. Googlekontot/servicekontot måste ha läsbehörighet till Brottsplatskartans
   GA4-property.

## Exakta install-steg (verifierade mot README 2026-04)

### 1. Installera pipx (om saknas)

```bash
brew install pipx
pipx ensurepath
```

### 2. Aktivera API:er i GCP

Gå in på Google Cloud Console för ditt projekt och aktivera:

- https://console.cloud.google.com/apis/library/analyticsadmin.googleapis.com
- https://console.cloud.google.com/apis/library/analyticsdata.googleapis.com

### 3. Sätt upp ADC med OAuth-klient (rekommenderat för personligt bruk)

Skapa en OAuth desktop-klient i GCP-konsolen, ladda ner JSON till t.ex.
`~/.config/gcloud/brottsplatskartan-oauth-client.json`, kör sedan:

```bash
gcloud auth application-default login \
  --scopes=https://www.googleapis.com/auth/analytics.readonly,https://www.googleapis.com/auth/cloud-platform \
  --client-id-file=$HOME/.config/gcloud/brottsplatskartan-oauth-client.json
```

Notera sökvägen som skrivs ut, typiskt:
`~/.config/gcloud/application_default_credentials.json`

Alternativ: service-account impersonation via
`--impersonate-service-account=SA_EMAIL`. Undvik att ladda ner service
account-nyckel till disk om det går att undvika.

### 4. Testa att servern startar

```bash
GOOGLE_APPLICATION_CREDENTIALS=$HOME/.config/gcloud/application_default_credentials.json \
  GOOGLE_PROJECT_ID=<ditt-gcp-projekt-id> \
  pipx run analytics-mcp
```

Servern ska starta och vänta på stdin (avbryt med Ctrl+C).

### 5. Registrera servern i Claude Code

Claude Code använder `claude mcp add` (inte `claude_desktop_config.json` –
det är för Claude Desktop). Kör i terminalen:

```bash
claude mcp add analytics-mcp \
  --scope user \
  --transport stdio \
  --env GOOGLE_APPLICATION_CREDENTIALS=$HOME/.config/gcloud/application_default_credentials.json \
  --env GOOGLE_PROJECT_ID=<ditt-gcp-projekt-id> \
  -- pipx run analytics-mcp
```

Scope-val:

- `--scope user` – tillgängligt i alla projekt (lämpligt här eftersom
  credentials är personliga).
- `--scope local` (default) – bara detta projekt, för dig.
- `--scope project` – checkas in i repo via `.mcp.json`. **Använd INTE**
  för denna server eftersom credentials-sökvägen är personlig.

Verifiera:

```bash
claude mcp list
```

### 6. Starta om Claude Code och testa

Ställ en fråga som `what can analytics-mcp do?` eller `show me my GA4
properties`.

## Config-platser

- **Claude Code** lagrar MCP-config via `claude mcp add` i internt
  config-fil per scope. User scope ligger under `~/.claude.json`
  (verifierat: denna fil finns redan lokalt, 57 KB).
- **Inte** `~/Library/Application Support/Claude/claude_desktop_config.json`
  – den filen hör till Claude Desktop-appen, inte Claude Code-CLI.
- Project-scope skrivs till `.mcp.json` i repo-roten (skall inte göras här).

Existerande MCP-setup: `/Users/bonny/.claude/mcp-needs-auth-cache.json`
visar att det redan finns något MCP-flöde uppsatt; konflikt osannolik.

## Säkerhets-checklist (credentials)

- [ ] Lagra ADC-credentials under `~/.config/gcloud/` (600-rättigheter,
      default).
- [ ] Lägg INTE credentials-JSON i repo, `~/Projects/…/brottsplatskartan/`
      eller annan mapp som Claude kan läsa via `Read`/`Bash`.
- [ ] Använd `--scope user`, aldrig `project` – annars checkas
      env-variabeln med absolut sökväg in i `.mcp.json`.
- [ ] Scope på OAuth-token: endast `analytics.readonly` (+ `cloud-platform`
      som gcloud kräver). Ingen `analytics.edit`.
- [ ] Verifiera att OAuth-klienten är typ "Desktop app", inte "Web".
- [ ] Dokumentera vilket GCP-projekt och vilken GA-property som används,
      men aldrig client-ID/secret eller token-filens innehåll.
- [ ] Rotera ADC-token med `gcloud auth application-default revoke`
      om datorn blir borttappad/stulen.
- [ ] Tänk på att Claude kan läsa ut GA-data i konversationen – behandla
      transcript som innehållande affärsdata.

## Exempel på queries Claude skulle kunna köra

- "Vilka 20 län-sidor (`/lan/*`) hade flest sessions senaste 30 dagarna?"
  → underlag för cache pre-warm-listan i scheduler.
- "Top 50 landningssidor från organisk Google senaste 90 dagarna, med
  bounce rate och average engagement time." → SEO-prioritering.
- "Vilken `page_title` har högst CTR från Google Discover?"
- "Trender per veckodag/timme för kart-sidan (`/karta`)?" → när cache ska
  värmas.
- "Andel mobiltrafik senaste 6 månaderna, per län-sida."
- "Jämför trafik pre/post 2025-02-12 (server-flytt till Hetzner)."
- "Realtime: hur många samtidiga besökare just nu och vilka sidor?"
- "Vilka custom dimensions/metrics finns definierade på vår property?"

Claude kan sedan direkt omvandla svar till konkreta ändringar (t.ex. lista
i `config/cache-warmup.php` eller uppdaterad `sitemap.xml`-prio).

## Risker

1. **API-kostnad/kvota.** GA4 Data API är gratis men har kvota
   (core tokens per property per day). Claude kan råka köra många stora
   rapporter – övervaka.
2. **Credentials i fel scope.** Om `--scope project` används läcker
   sökvägen in i repo. Mitigering: `--scope user`.
3. **Data privacy.** GA4 kan innehålla PII-liknande data (IP:n anonymiseras,
   men page_path kan innehålla sökfraser). All data som Claude hämtar
   passerar Anthropic. OK för Brottsplatskartan (inga inloggade
   användare/klient-data), men skulle inte vara OK för projekt med
   känslig kunddata.
4. **Experimentell status.** Repo har "Experimental" i titeln – API kan
   ändras utan baklängeskompatibilitet.
5. **OAuth-token-rotation.** ADC-token refreshas automatiskt men kan gå
   ut vid långvarig inaktivitet; re-login behövs.
6. **pipx run** hämtar senaste version varje gång om cache saknas – nät
   krävs vid första körning. `pipx install analytics-mcp` + `analytics-mcp`
   direkt i command är snabbare efter första gången.

## Fördelar

- Snabb ad-hoc-analys utan att öppna GA-UI:t.
- Claude kan korsreferera GA-data mot repo-kod (t.ex. generera
  cache-warmup-lista direkt).
- Kontextuella frågor ("jämför periodens trender") utan handgjorda
  dashboard-byggen.
- Realtidsdata tillgängligt via `run_realtime_report`.

## Alternativ

### A. CSV-export (manuellt)

Exportera GA4-rapport till CSV, lägg i `tmp/` och låt Claude läsa med
`Read`-verktyget. Fördel: ingen MCP-setup, inga credentials till Claude.
Nackdel: statiskt, manuellt, inga realtidsfrågor.

### B. Search Console MCP

Flera community-MCP:er finns, t.ex.:

- `AminForou/mcp-gsc` (Python, 714 stars)
- `ahonn/mcp-server-gsc` (TypeScript, 208 stars)
- `Shin-sibainu/google-search-console-mcp-server` (nämner Claude Code-stöd)

GSC-data (impressions, CTR, position, query) är **mer direkt SEO-relevant**
än GA4 för Brottsplatskartan eftersom:

- Visar vilka sökfraser som leder in till vilka sidor.
- Visar indexeringsstatus, Core Web Vitals, mobilvänlighet.
- Ingen PII-risk.

**Rekommendation:** kör både. GA4-MCP för beteende på sajten (pre-warm,
populära platser), GSC-MCP för hur Google ser sajten (SEO-prioritering,
indexeringsproblem).

### C. BigQuery-export

Om GA4 kopplas till BigQuery kan en generisk BigQuery-MCP ge råare data
och SQL-frågor. Mer kraftfullt men mer setup. Inte motiverat för
Brottsplatskartans volym/komplexitet.

## Öppna frågor

- [ ] Vilket GCP-projekt skall användas? Finns ett befintligt för
      Brottsplatskartan eller skall nytt skapas?
- [ ] GA4-property-ID: vilken/vilka properties skall Claude ha tillgång
      till?
- [ ] Är det aktuellt med service account impersonation (för att undvika
      att tokens ligger i user-ADC)? Förmodligen overkill för en solo-dev.
- [ ] Kan GA4 BigQuery-export aktiveras långsiktigt om vi vill göra
      tyngre analys?
- [ ] Skall Search Console MCP installeras parallellt? (Svar: ja, enligt
      rekommendation ovan – kräver egen todo.)

## Status / nästa steg

**Status:** Research klar, ej installerat.

**Nästa steg (när Pär är redo):**

1. Bekräfta GCP-projekt och GA4-property-ID.
2. `brew install pipx && pipx ensurepath`.
3. Aktivera Admin API + Data API i GCP-konsolen.
4. Skapa OAuth desktop-klient, ladda ner JSON.
5. Kör `gcloud auth application-default login` med scopes.
6. Kör `claude mcp add analytics-mcp --scope user --transport stdio
--env GOOGLE_APPLICATION_CREDENTIALS=… --env GOOGLE_PROJECT_ID=… --
pipx run analytics-mcp`.
7. Starta om Claude Code, testa med `what can analytics-mcp do?`.
8. Kör en första konkret nytta: "top 50 populäraste sidorna senaste 30
   dagarna" → underlag för cache pre-warm-justering.
9. Öppna separat todo för Search Console MCP (rekommenderat: `mcp-gsc`
   eller `ahonn/mcp-server-gsc`).

**Loggförslag (CLAUDE.local.md / nvALT-log):**
"Research gjord för GA4 MCP-server. Installation väntar på beslut om
GCP-projekt och OAuth-klient. Se `todos/08-ga-mcp.md`."
